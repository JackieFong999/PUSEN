<?php

namespace App\Services;

/**
 * Student CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec-student.md
 *
 * Differences from Subject: 9-column CSV (col 7 ignored), tblStudent target
 * keyed by Student_Id, strict Student_Id format (8 digits + one letter A-Z,
 * legacy 7-digit IDs excluded), Student_Status handled by the table default
 * ('ACTIVE' on insert, never touched on update).
 */
class StudentImportService extends AbstractImportService
{
    /** Filename prefix for student CSVs. */
    public const FILE_PREFIX = 'sao_sen_srs_student_';

    public const FILE_TYPE = 'STUDENT';

    /** Column max lengths (varchar limits on tblStudent). */
    public const MAX_STUDENT_ID   = 12;   // Student_Id varchar(12)
    public const MAX_NAME_ENG     = 30;   // Student_Name_Eng varchar(30)
    public const MAX_NAME_CHN     = 5;    // Student_Name_Chn varchar(5)
    public const MAX_FACULTY      = 10;   // Faculty varchar(10)
    public const MAX_DEPARTMENT   = 10;   // Department varchar(10)
    public const MAX_PROG_SUB     = 10;   // Prog_Sub_Code varchar(10)
    public const MAX_PROG_TITLE   = 60;   // Prog_Title varchar(60)
    public const MAX_FUND_TYPE    = 1;    // Fund_Type_Code char(1)

    /** Student_Id format: exactly 8 digits then one letter A-Z (case-insensitive). */
    public const STUDENT_ID_PATTERN = '/^\d{8}[a-z]$/i';

    protected function fileNamePattern(): string
    {
        return '/^' . preg_quote(self::FILE_PREFIX, '/') . '(\d{8})\.csv$/i';
    }

    protected function filePrefix(): string
    {
        return self::FILE_PREFIX;
    }

    protected function fileType(): string
    {
        return self::FILE_TYPE;
    }

    /**
     * Parse CSV content into rows of 9 fields; null when no data rows.
     * UTF-8, unquoted, no header; BOM stripped defensively; fields trimmed.
     */
    protected function parseCsv(string $content): ?array
    {
        // strip UTF-8 BOM defensively (source files are verified BOM-free)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, ',', '"', '\\');
            // pad/limit to 9 so column-count validation decides
            $rows[] = array_slice(array_pad($fields, 9, ''), 0, 9);
        }
        return empty($rows) ? null : $rows;
    }

    /**
     * Validate all rows; write tblImport_Failed_Log for non-insert rows.
     * Rules (first hit wins): a column count, b empty, c max length
     * (characters, not bytes), d Student_Id format, e duplicate in file,
     * f Fund Type in master, g identical -> Duplicated, h differs -> Update,
     * i new key -> INSERT.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array}
     */
    protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
    {
        // master lookup: Fund Type code (case-insensitive via lowercase keys)
        $fundTypeMap = [];
        foreach ($conn->table('tblFund_Type')->select('Fund_Type_Code')->get() as $t) {
            $fundTypeMap[strtolower($t->Fund_Type_Code)] = $t->Fund_Type_Code;
        }

        // existing students keyed by lowercase Student_Id
        $studentMap = [];
        foreach ($conn->table('tblStudent')
                     ->select('Student_Id', 'Student_Name_Eng', 'Student_Name_Chn', 'Faculty',
                              'Department', 'Prog_Sub_Code', 'Prog_Title', 'Fund_Type_Code')
                     ->get() as $s) {
            $studentMap[strtolower($s->Student_Id)] = $s;
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

            // a. column count (col 7 is present but ignored)
            if (count($row) !== 9) {
                $status  = 'Failure';
                $remarks = 'Incorrect number of columns';
            } else {
                [$idRaw, $engRaw, $chnRaw, $facRaw, $depRaw, $progRaw, , $titleRaw, $fundRaw] = $row;
                $idRaw    = trim((string) $idRaw);
                $engRaw   = trim((string) $engRaw);
                $chnRaw   = trim((string) $chnRaw);
                $facRaw   = trim((string) $facRaw);
                $depRaw   = trim((string) $depRaw);
                $progRaw  = trim((string) $progRaw);
                $titleRaw = trim((string) $titleRaw);
                $fundRaw  = trim((string) $fundRaw);

                // b. all 8 used fields present (col 7 ignored)
                if ($idRaw === '' || $engRaw === '' || $chnRaw === '' || $facRaw === ''
                    || $depRaw === '' || $progRaw === '' || $titleRaw === '' || $fundRaw === '') {
                    $status  = 'Failure';
                    $remarks = 'one or more field is empty';
                }
                // c. max length — count characters, not bytes (Chinese names)
                elseif (mb_strlen($idRaw) > self::MAX_STUDENT_ID
                     || mb_strlen($engRaw) > self::MAX_NAME_ENG
                     || mb_strlen($chnRaw) > self::MAX_NAME_CHN
                     || mb_strlen($facRaw) > self::MAX_FACULTY
                     || mb_strlen($depRaw) > self::MAX_DEPARTMENT
                     || mb_strlen($progRaw) > self::MAX_PROG_SUB
                     || mb_strlen($titleRaw) > self::MAX_PROG_TITLE
                     || mb_strlen($fundRaw) > self::MAX_FUND_TYPE) {
                    $status  = 'Failure';
                    $remarks = 'field exceeds max length';
                }
                // d. Student_Id format: 8 digits + one letter A-Z (legacy 7-digit IDs excluded)
                elseif (! preg_match(self::STUDENT_ID_PATTERN, $idRaw)) {
                    $status  = 'Failure';
                    $remarks = 'Student Id must be 8 digits + a letter (A-Z)';
                } else {
                    $idNorm = strtoupper($idRaw); // letter -> uppercase
                    $key    = strtolower($idNorm);

                    // e. duplicate within this file
                    if (isset($seenKeys[$key])) {
                        $status  = 'Failure';
                        $remarks = 'Duplicated record in the same CSV file';
                    } else {
                        $seenKeys[$key] = true;

                        // f. Fund Type must exist (normalize to master casing)
                        $fundNorm = $fundTypeMap[strtolower($fundRaw)] ?? null;
                        if (! $fundNorm) {
                            $status  = 'Failure';
                            $remarks = 'Fund Type code not exist in tblFund_Type master table.';
                        } else {
                            $entry = [
                                'id'    => $idNorm,
                                'eng'   => $engRaw,
                                'chn'   => $chnRaw,
                                'fac'   => $facRaw,
                                'dep'   => $depRaw,
                                'prog'  => $progRaw,
                                'title' => $titleRaw,
                                'fund'  => $fundNorm,
                            ];

                            // g/h. key already in tblStudent?
                            $existing = $studentMap[$key] ?? null;
                            if ($existing) {
                                $sameData = strtolower($existing->Student_Id ?? '') === strtolower($idNorm)
                                    && strtolower($existing->Student_Name_Eng ?? '') === strtolower($engRaw)
                                    && strtolower($existing->Student_Name_Chn ?? '') === strtolower($chnRaw)
                                    && strtolower($existing->Faculty ?? '') === strtolower($facRaw)
                                    && strtolower($existing->Department ?? '') === strtolower($depRaw)
                                    && strtolower($existing->Prog_Sub_Code ?? '') === strtolower($progRaw)
                                    && strtolower($existing->Prog_Title ?? '') === strtolower($titleRaw)
                                    && strtolower($existing->Fund_Type_Code ?? '') === strtolower($fundNorm);
                                if ($sameData) {
                                    $status  = 'Duplicated';
                                    $remarks = 'Same data already exist, no updated occurred.';
                                } else {
                                    $status  = 'Update';
                                    $remarks = 'Information: Key already exist, record will be updated.';
                                    // keep the DB's existing Student_Id casing (never change the PK)
                                    $updates[] = ['id' => $existing->Student_Id] + $entry;
                                }
                            } else {
                                $inserts[] = $entry;
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
                    'FileType'      => self::FILE_TYPE,
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

    /**
     * Insert new + update changed rows (inside the caller's transaction).
     * Student_Status is NOT written: insert relies on the table default
     * 'ACTIVE'; update never touches it (a withdrawn student stays withdrawn).
     */
    protected function applyChanges($conn, array $plan, string $user, string $ip): array
    {
        $inserted = 0;
        foreach ($plan['inserts'] as $r) {
            $conn->table('tblStudent')->insert([
                'Student_Id'       => $r['id'],
                'Student_Name_Eng' => $r['eng'],
                'Student_Name_Chn' => $r['chn'],
                'Faculty'          => $r['fac'],
                'Department'       => $r['dep'],
                'Prog_Sub_Code'    => $r['prog'],
                'Prog_Title'       => $r['title'],
                'Fund_Type_Code'   => $r['fund'],
                'updated_by'       => $user,
                'updated_ip'       => $ip,
                // Student_Status omitted -> table default 'ACTIVE'
                // created_at / updated_at use DB defaults
            ]);
            $inserted++;
        }

        $updated = 0;
        foreach ($plan['updates'] as $r) {
            $conn->table('tblStudent')
                ->where('Student_Id', $r['id'])
                ->update([
                    'Student_Name_Eng' => $r['eng'],
                    'Student_Name_Chn' => $r['chn'],
                    'Faculty'          => $r['fac'],
                    'Department'       => $r['dep'],
                    'Prog_Sub_Code'    => $r['prog'],
                    'Prog_Title'       => $r['title'],
                    'Fund_Type_Code'   => $r['fund'],
                    'updated_by'       => $user,
                    'updated_ip'       => $ip,
                    // Student_Status untouched; updated_at auto-updates
                ]);
            $updated++;
        }

        return [$inserted, $updated];
    }
}
