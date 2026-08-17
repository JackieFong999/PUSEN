<?php

namespace App\Services;

/**
 * Student Registration CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec-studentreg.md
 *
 * Differences from other imports: variable-width CSV — column 1 is the
 * Student_Id, columns 2+ are one or more Subject_Codes, so ONE CSV row
 * expands into multiple (Student_Id, Subject_Code) pairs. Duplicate
 * detection is per PAIR: pairs already in tblStudent_Reg -> Duplicated
 * (informational, skipped), in-file repeated pairs -> Failure. There is no
 * update case. tblStudent_Reg carries UNIQUE KEY uq_tblStudent_Reg_Key
 * (Student_Id, Subject_Code) as a DB safeguard.
 */
class StudentRegImportService extends AbstractImportService
{
    /** Filename prefix for student registration CSVs. */
    public const FILE_PREFIX = 'sao_sen_srs_subreg_';

    public const FILE_TYPE = 'STUDENT-REG';

    /** Column max lengths (varchar limits). */
    public const MAX_STUDENT_ID = 12;   // tblStudent_Reg.Student_Id varchar(12)
    public const MAX_SUBJECT    = 20;   // tblStudent_Reg.Subject_Code varchar(20)

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
     * Parse CSV content into rows of variable width (>= 2 columns);
     * null when no data rows. UTF-8, unquoted, no header; BOM stripped
     * defensively. Fields are trimmed later in validation.
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
            // variable columns — do NOT pad; column-count validation decides
            $rows[] = str_getcsv($line, ',', '"', '\\');
        }
        return empty($rows) ? null : $rows;
    }

    /**
     * Validate every row; write tblImport_Failed_Log for non-insert rows.
     * Rules (first hit wins): a column count < 2, b Student_Id empty or all
     * codes empty, c Student in tblStudent, then per pair: d Subject in
     * tblSubject (any AY/Sem), e pair already in file, f pair in
     * tblStudent_Reg -> Duplicated, g new pair -> INSERT. No update case.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array}
     */
    protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
    {
        // master lookups (case-insensitive via lowercase keys)
        $studentMap = [];
        foreach ($conn->table('tblStudent')->select('Student_Id')->get() as $s) {
            $studentMap[strtolower($s->Student_Id)] = $s->Student_Id;
        }

        // subject codes exist in ANY Academic_Year/Semester row
        $subjectMap = [];
        foreach ($conn->table('tblSubject')->select('Subject_Code')->distinct()->get() as $s) {
            $subjectMap[strtolower($s->Subject_Code)] = $s->Subject_Code;
        }

        // existing pairs keyed by lowercase student|subject
        $existingMap = [];
        foreach ($conn->table('tblStudent_Reg')->select('Student_Id', 'Subject_Code')->get() as $r) {
            $existingMap[strtolower($r->Student_Id . '|' . $r->Subject_Code)] = true;
        }

        $fileDate  = $this->fileDateFromName($filename);
        $seenKeys  = [];
        $counters = ['failures' => 0, 'duplicated' => 0];
        $inserts  = [];

        foreach ($rows as $row) {
            $row = array_map(fn ($f) => trim((string) $f), $row);

            // a. at least 2 columns
            if (count($row) < 2) {
                $this->logFailed($conn, $logId, $fileDate, $filename, $row, 'Failure',
                    'At least 2 columns', $user);
                $counters['failures']++;
                continue;
            }

            $studentRaw = $row[0];
            $codes = array_slice($row, 1);
            $codes = array_values(array_filter($codes, fn ($c) => $c !== ''));

            // b. Student_Id empty or all Subject_Codes empty
            if ($studentRaw === '' || empty($codes)) {
                $this->logFailed($conn, $logId, $fileDate, $filename, $row, 'Failure',
                    'Student Id / Subject Code cannot be empty', $user);
                $counters['failures']++;
                continue;
            }

            // b2. max length — count characters, not bytes
            if (mb_strlen($studentRaw) > self::MAX_STUDENT_ID
                || max(array_map('mb_strlen', $codes)) > self::MAX_SUBJECT) {
                $this->logFailed($conn, $logId, $fileDate, $filename, $row, 'Failure',
                    'field exceeds max length', $user);
                $counters['failures']++;
                continue;
            }

            // c. Student_Id must exist (normalize to master casing)
            $studentNorm = $studentMap[strtolower($studentRaw)] ?? null;
            if (! $studentNorm) {
                $this->logFailed($conn, $logId, $fileDate, $filename, $row, 'Failure',
                    'Student Id not exist in tblStudent master table.', $user);
                $counters['failures']++;
                continue;
            }

            // d-g. per pair
            foreach ($codes as $codeRaw) {
                $status  = null;
                $remarks = null;

                // d. Subject_Code must exist (any AY/Sem; normalize to master casing)
                $subjectNorm = $subjectMap[strtolower($codeRaw)] ?? null;
                if (! $subjectNorm) {
                    $status  = 'Failure';
                    $remarks = 'Subject Code not exist in tblSubject master table.';
                } else {
                    $key = strtolower($studentNorm . '|' . $subjectNorm);

                    // e. pair already seen earlier in this file
                    if (isset($seenKeys[$key])) {
                        $status  = 'Failure';
                        $remarks = 'Duplicated record in the same CSV file';
                    } else {
                        $seenKeys[$key] = true;

                        // f. pair already in tblStudent_Reg
                        if (isset($existingMap[$key])) {
                            $status  = 'Duplicated';
                            $remarks = 'Same data already exists, no update occurred.';
                        } else {
                            // g. new pair
                            $inserts[] = [
                                'student' => $studentNorm,
                                'subject' => $subjectNorm,
                            ];
                        }
                    }
                }

                if ($status !== null) {
                    if ($status === 'Failure') {
                        $counters['failures']++;
                    } else {
                        $counters['duplicated']++;
                    }
                    $this->logFailed($conn, $logId, $fileDate, $filename, $row, $status, $remarks, $user);
                }
            }
        }

        return [
            'failures'   => $counters['failures'],
            'duplicated' => $counters['duplicated'],
            'inserts'    => $inserts,
            'updates'    => [], // no update case for this import
        ];
    }

    /** Insert new pairs (inside the caller's transaction). */
    protected function applyChanges($conn, array $plan, string $user, string $ip): array
    {
        $inserted = 0;
        foreach ($plan['inserts'] as $r) {
            $conn->table('tblStudent_Reg')->insert([
                'Student_Id'   => $r['student'],
                'Subject_Code' => $r['subject'],
                'updated_by'   => $user,
                'updated_ip'   => $ip,
                // Id auto-increments; created_at / updated_at use DB defaults
            ]);
            $inserted++;
        }

        return [$inserted, 0];
    }

    /** Write one tblImport_Failed_Log row (Row_Content = the raw CSV row). */
    private function logFailed($conn, int $logId, $fileDate, string $filename, array $row, string $status, string $remarks, string $user): void
    {
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
