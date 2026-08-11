<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use phpseclib3\Net\SFTP;

/**
 * Subject CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec.md (sections 1-6).
 *
 * Flow:
 *  1. Load SFTP settings from tblConfig_SFTP
 *  2. Connect, pick latest CSV in Remote_Path (by YYYYMMDD in name, then mtime)
 *  3. INSERT tblImport_Log (File_Name, CSV_Content, created_by, updated_ip)
 *  4. Parse + validate every row -> write tblImport_Failed_Log
 *  5. Any 'Failure' -> ABORT (file stays)
 *  6. Else TRANSACTION: insert new + update changed, move file to processed/
 */
class SubjectImportService
{
    /** Filename prefix for subject CSVs. */
    public const FILE_PREFIX = 'SAO_SEN_LMS_SUBJECT_';

    /** @var object|null tblConfig_SFTP row */
    private $config;

    /** @var SFTP|null */
    private $sftp;

    public function __construct()
    {
        $this->config = DB::connection('pusen')
            ->table('tblConfig_SFTP')
            ->orderBy('Id')
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Latest subject CSV in the SFTP upload dir.
     *
     * @return array{filename: ?string, exists: bool, error: ?string}
     */
    public function latestFile(): array
    {
        if (! $this->config) {
            return ['filename' => null, 'exists' => false, 'error' => 'SFTP config not found in tblConfig_SFTP.'];
        }

        if (! $this->connect()) {
            return ['filename' => null, 'exists' => false, 'error' => 'Cannot connect to SFTP server.'];
        }

        try {
            $path = $this->importDir();
            $files = $this->sftp->nlist($path) ?: [];

            $candidates = [];
            foreach ($files as $file) {
                $name = basename((string) $file);
                if (preg_match('/^' . preg_quote(self::FILE_PREFIX, '/') . '(\d{8})\.csv$/i', $name, $m)) {
                    $candidates[] = [
                        'name'  => $name,
                        'date'  => $m[1],
                        'mtime' => (int) $this->sftp->filemtime($path . '/' . $name),
                    ];
                }
            }

            if (empty($candidates)) {
                return ['filename' => null, 'exists' => false, 'error' => null];
            }

            // newest = highest YYYYMMDD in name, then latest mtime
            usort($candidates, function ($a, $b) {
                return [$b['date'], $b['mtime']] <=> [$a['date'], $a['mtime']];
            });

            return ['filename' => $candidates[0]['name'], 'exists' => true, 'error' => null];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Last imported subject filename from tblImport_Log.
     */
    public function lastImportedFile(): ?string
    {
        return DB::connection('pusen')
            ->table('tblImport_Log')
            ->where('File_Name', 'like', self::FILE_PREFIX . '%')
            ->orderByDesc('Id')
            ->value('File_Name');
    }

    /**
     * Run the full import pipeline for one CSV file.
     *
     * @return array{status: string, file: string, message: ?string,
     *               inserted: int, updated: int, duplicated: int, failures: int}
     *         status: 'success' | 'abort' | 'error'
     */
    public function import(string $filename): array
    {
        if (! $this->config) {
            return $this->result('error', $filename, 'SFTP config not found in tblConfig_SFTP.');
        }
        if (! preg_match('/^' . preg_quote(self::FILE_PREFIX, '/') . '\d{8}\.csv$/i', $filename)) {
            return $this->result('error', $filename, 'Filename does not match subject naming convention.');
        }
        if (! $this->connect()) {
            return $this->result('error', $filename, 'Cannot connect to SFTP server.');
        }

        $conn = DB::connection('pusen');
        $user = auth()->user()->Staff_Id ?? 'system01';
        $ip   = request()->ip() ?? '';

        try {
            // --- 1. read file + write tblImport_Log (status NULL) ---
            $remoteFile = $this->importDir() . '/' . $filename;
            $csvContent = $this->sftp->get($remoteFile);
            if ($csvContent === false) {
                return $this->result('error', $filename, 'Cannot read file from SFTP server.');
            }

            $logId = $conn->table('tblImport_Log')->insertGetId([
                'File_Name'   => $filename,
                'CSV_Content' => $csvContent,
                'created_by'  => $user,
                'updated_ip'  => $ip,
                // Import_Status left NULL while processing
            ]);

            // --- 2. parse CSV ---
            $rows = $this->parseCsv($csvContent);
            if ($rows === null) {
                $conn->table('tblImport_Log')->where('Id', $logId)->update(['Import_Status' => 'Failure']);
                return $this->result('abort', $filename, 'CSV could not be parsed (no data rows).');
            }

            // --- 3. validate every row -> Failed_Log ---
            $plan = $this->validateRows($conn, $rows, $logId, $filename, $user, $ip);

            if ($plan['failures'] > 0) {
                $conn->table('tblImport_Log')->where('Id', $logId)->update(['Import_Status' => 'Failure']);
                return $this->result('abort', $filename, null, [
                    'failures'   => $plan['failures'],
                    'duplicated' => $plan['duplicated'],
                ]);
            }

            // --- 4. transaction: insert new + update changed (all or nothing) ---
            $conn->beginTransaction();
            try {
                $inserted = 0;
                foreach ($plan['inserts'] as $r) {
                    $conn->table('tblSubject')->insert([
                        'Academic_Year'    => $r['ay'],
                        'Semester'         => $r['sem'],
                        'Subject_Code'     => $r['code'],
                        'Teacher_Staff_Id' => $r['staff'],
                        'Subject_Type'     => $r['type'],
                        'updated_by'       => $user,
                        'updated_ip'       => $ip,
                    ]);
                    $inserted++;
                }

                $updated = 0;
                foreach ($plan['updates'] as $r) {
                    $conn->table('tblSubject')
                        ->where('Academic_Year', $r['ay'])
                        ->where('Semester', $r['sem'])
                        ->where('Subject_Code', $r['code'])
                        ->update([
                            'Teacher_Staff_Id' => $r['staff'],
                            'Subject_Type'     => $r['type'],
                            'updated_by'       => $user,
                            'updated_ip'       => $ip,
                        ]);
                    $updated++;
                }

                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                $conn->table('tblImport_Log')->where('Id', $logId)->update(['Import_Status' => 'Failure']);
                return $this->result('error', $filename, 'Import transaction failed: ' . $e->getMessage(), [
                    'inserted'   => 0,
                    'updated'    => 0,
                    'duplicated' => $plan['duplicated'],
                    'failures'   => 0,
                ]);
            }

            // --- 5. success: mark log, archive file ---
            $conn->table('tblImport_Log')->where('Id', $logId)->update(['Import_Status' => 'Success']);

            $archiveOk = $this->sftp->rename($remoteFile, $this->archiveDir() . '/' . $filename);

            return $this->result('success', $filename, null, [
                'inserted'   => $inserted,
                'updated'    => $updated,
                'duplicated' => $plan['duplicated'],
                'failures'   => $plan['failures'],
            ] + ['archive_moved' => $archiveOk]);
        } finally {
            $this->disconnect();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Validation                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Validate all rows; write tblImport_Failed_Log for non-insert rows.
     *
     * @return array{failures: int, duplicated: int, updates: array, inserts: array}
     */
    private function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
    {
        // master lookups (case-insensitive via lowercase keys)
        $staffMap = [];
        foreach ($conn->table('tblStaff')->select('Staff_Id', 'status')->get() as $s) {
            $staffMap[strtolower($s->Staff_Id)] = $s;
        }

        $typeMap = [];
        foreach ($conn->table('tblSubject_Type')->select('Subject_Type')->get() as $t) {
            $typeMap[strtolower($t->Subject_Type)] = $t->Subject_Type;
        }

        // existing subjects keyed by lowercase composite key
        $subjectMap = [];
        foreach ($conn->table('tblSubject')
                     ->select('Academic_Year', 'Semester', 'Subject_Code', 'Teacher_Staff_Id', 'Subject_Type')
                     ->get() as $s) {
            $subjectMap[$this->compositeKey($s->Academic_Year, $s->Semester, $s->Subject_Code)] = $s;
        }

        $fileDate = $this->fileDateFromName($filename);
        $seenKeys = [];
        $counters = ['failures' => 0, 'duplicated' => 0];
        $inserts  = [];
        $updates  = [];

        foreach ($rows as $row) {
            $status  = null;
            $remarks = null;
            $entry   = null; // normalized insert/update candidate

            // a. column count
            if (count($row) !== 7) {
                $status  = 'Failure';
                $remarks = 'Incorrect number of columns';
            } else {
                [$ayRaw, $semRaw, $codeRaw, $staffRaw, , , $typeRaw] = $row;
                $ayRaw    = trim((string) $ayRaw);
                $semRaw   = trim((string) $semRaw);
                $codeRaw  = trim((string) $codeRaw);
                $staffRaw = trim((string) $staffRaw);
                $typeRaw  = trim((string) $typeRaw);

                // b. all 5 used fields present (cols 5/6 ignored)
                if ($ayRaw === '' || $semRaw === '' || $codeRaw === '' || $staffRaw === '' || $typeRaw === '') {
                    $status  = 'Failure';
                    $remarks = 'one or more field is empty';
                }
                // c. Academic Year numeric
                elseif (! ctype_digit($ayRaw)) {
                    $status  = 'Failure';
                    $remarks = 'Academic_Year is not numeric';
                }
                // d. 2000 < AY < 2046
                elseif ((int) $ayRaw <= 2000 || (int) $ayRaw >= 2046) {
                    $status  = 'Failure';
                    $remarks = 'Academic_Year out of range';
                }
                // e. Semester numeric
                elseif (! ctype_digit($semRaw)) {
                    $status  = 'Failure';
                    $remarks = 'Semester is not numeric';
                }
                // f. Semester in {1,2,3}
                elseif (! in_array((int) $semRaw, [1, 2, 3], true)) {
                    $status  = 'Failure';
                    $remarks = 'Semester is out of range';
                } else {
                    $ay   = (int) $ayRaw;
                    $sem  = (int) $semRaw;
                    $code = $codeRaw;
                    $key  = $this->compositeKey($ay, $sem, $code);

                    // g. duplicate within this file
                    if (isset($seenKeys[$key])) {
                        $status  = 'Failure';
                        $remarks = 'Duplicated record in the same CSV file';
                    } else {
                        $seenKeys[$key] = true;

                        // h. staff must exist
                        $staffRow = $staffMap[strtolower($staffRaw)] ?? null;
                        if (! $staffRow) {
                            $status  = 'Failure';
                            $remarks = 'Staff code not exist in Staff master table.';
                        }
                        // i. staff must be enabled
                        elseif ((int) $staffRow->status === 1) {
                            $status  = 'Failure';
                            $remarks = 'Staff code is disabled.';
                        } else {
                            // j. subject type must exist (normalize to master casing)
                            $typeNorm = $typeMap[strtolower($typeRaw)] ?? null;
                            if (! $typeNorm) {
                                $status  = 'Failure';
                                $remarks = 'Subject Type is not exist in Subject Type master table';
                            } else {
                                $staffNorm = $staffRow->Staff_Id;
                                $entry = [
                                    'ay'    => $ay,
                                    'sem'   => $sem,
                                    'code'  => $code,
                                    'staff' => $staffNorm,
                                    'type'  => $typeNorm,
                                ];

                                // k/l. key already in tblSubject?
                                $existing = $subjectMap[$key] ?? null;
                                if ($existing) {
                                    $sameData = strtolower($existing->Teacher_Staff_Id ?? '') === strtolower($staffNorm)
                                        && strtolower($existing->Subject_Type ?? '') === strtolower($typeNorm);
                                    if ($sameData) {
                                        $status  = 'Duplicated';
                                        $remarks = 'Same data already exist, no updated occurred.';
                                    } else {
                                        $status  = 'Update';
                                        $remarks = 'Information: Key already exist, record will be updated.';
                                        $updates[] = $entry;
                                    }
                                } else {
                                    $inserts[] = $entry;
                                }
                            }
                        }
                    }
                }
            }

            // every row with an outcome (all except plain inserts) goes to Failed_Log
            if ($status !== null) {
                if ($status === 'Failure') {
                    $counters['failures']++;
                } elseif ($status === 'Duplicated') {
                    $counters['duplicated']++;
                }

                $conn->table('tblImport_Failed_Log')->insert([
                    'Import_Log_Id' => $logId,
                    'File_Date'     => $fileDate ?? now(),
                    'File_Name'     => $filename,
                    'FileType'      => 'SUBJECT',
                    'Row_Content'   => $this->rawRow($row),
                    'Import_Status' => $status,
                    'Remarks'       => $remarks,
                    'Import_By'     => $user,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        return [
            'failures'   => $counters['failures'],
            'duplicated' => $counters['duplicated'],
            'inserts'    => $inserts,
            'updates'    => $updates,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function connect(): bool
    {
        try {
            $this->sftp = new SFTP($this->config->Host, (int) $this->config->Port);
            if (! $this->sftp->login($this->config->Username, $this->config->Password)) {
                $this->sftp = null;
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->sftp = null;
            return false;
        }
    }

    private function disconnect(): void
    {
        if ($this->sftp) {
            try { $this->sftp->disconnect(); } catch (\Throwable $e) { /* ignore */ }
            $this->sftp = null;
        }
    }

    private function importDir(): string
    {
        return rtrim((string) ($this->config->Remote_Path ?? 'upload'), '/') ?: 'upload';
    }

    /** Archive dir = sibling of Remote_Path at chroot root. */
    private function archiveDir(): string
    {
        return dirname($this->importDir()) . '/processed';
    }

    private function compositeKey($ay, $sem, $code): string
    {
        return strtolower($ay . '|' . $sem . '|' . $code);
    }

    /** Parse CSV content into rows of 7 fields; null when no data rows. */
    private function parseCsv(string $content): ?array
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, ',', '"', '\\');
            // pad/limit to 7 so column-count validation decides
            $rows[] = array_slice(array_pad($fields, 7, ''), 0, 7);
        }
        return empty($rows) ? null : $rows;
    }

    /** Rebuild the raw CSV row text for Row_Content. */
    private function rawRow(array $fields): string
    {
        return implode(',', array_map(fn ($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields));
    }

    /** Y-m-d from filename suffix, or null. */
    private function fileDateFromName(string $filename): ?string
    {
        if (preg_match('/_(\d{8})\.csv$/i', $filename, $m)) {
            return date('Y-m-d', strtotime($m[1]));
        }
        return null;
    }

    private function result(string $status, string $file, ?string $message, array $counts = []): array
    {
        return array_merge([
            'status'   => $status,
            'file'     => $file,
            'message'  => $message,
            'inserted' => 0,
            'updated'  => 0,
            'duplicated' => 0,
            'failures' => 0,
        ], $counts);
    }
}
