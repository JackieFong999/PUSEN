<?php

namespace App\Services;

/**
 * Subject CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec.md (sections 1-6).
 *
 * Reuses the shared SFTP/logging/transaction pipeline in AbstractImportService;
 * supplies subject-specific filename, parser and validation rules.
 */
class SubjectImportService extends AbstractImportService
{
    /** Filename prefix for subject CSVs. */
    public const FILE_PREFIX = 'SAO_SEN_LMS_SUBJECT_';

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
        return 'SUBJECT';
    }

    /** Parse CSV content into rows of 7 fields; null when no data rows. */
    protected function parseCsv(string $content): ?array
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

    /**
     * Validate all rows; write tblImport_Failed_Log for non-insert rows.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array}
     */
    protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
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

    /** Insert new + update changed rows (inside the caller's transaction). */
    protected function applyChanges($conn, array $plan, string $user, string $ip): array
    {
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

        return [$inserted, $updated];
    }

    private function compositeKey($ay, $sem, $code): string
    {
        return strtolower($ay . '|' . $sem . '|' . $code);
    }
}
