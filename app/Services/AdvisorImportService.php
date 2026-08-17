<?php

namespace App\Services;

/**
 * Advisor List for the Student List CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec-advisor.md
 *
 * Differences from other imports: 8-column CSV (cols 2-4 ignored), target
 * tblAdvisor_Student, and a FULL-ROW dedup model — the CSV has no key, so
 * duplicate detection compares all 5 stored fields
 * (Advisor_Id, Student_Id, Advisor_Type, Start_Date, End_Date). There is no
 * update case: existing rows are never modified, changed data inserts a new
 * row. Side effect (inside the transaction): disabled staff whose Staff_Id
 * appears as an Advisor_Id in the file are re-enabled (status 1 -> 0).
 */
class AdvisorImportService extends AbstractImportService
{
    /** Filename prefix for advisor CSVs. */
    public const FILE_PREFIX = 'sao_sen_srs_advisor_';

    public const FILE_TYPE = 'ADVISOR';

    /** Column max lengths (varchar limits). */
    public const MAX_ADVISOR_ID = 20;   // tblAdvisor_Student.Advisor_Id varchar(20)
    public const MAX_STUDENT_ID = 12;   // tblAdvisor_Student.Student_Id varchar(12)
    public const MAX_ADVISOR_TYPE = 14; // tblAdvisor_Student.Advisor_Type varchar(14)

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
     * Parse CSV content into rows of 8 fields; null when no data rows.
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
            // pad/limit to 8 so column-count validation decides
            $rows[] = array_slice(array_pad($fields, 8, ''), 0, 8);
        }
        return empty($rows) ? null : $rows;
    }

    /**
     * Validate every row; write tblImport_Failed_Log for non-insert rows.
     * Rules (first hit wins): a column count, b empty, c max length,
     * d date format, e Advisor in tblStaff, f Student in tblStudent,
     * g Advisor Type in master, h in-file duplicate, i full-row duplicate
     * in tblAdvisor_Student, j new row -> INSERT. No update case.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array,
     *               advisorIds: array} advisorIds = distinct Advisor_Ids to re-enable
     */
    protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
    {
        // master lookups (case-insensitive via lowercase keys)
        $staffMap = [];
        foreach ($conn->table('tblStaff')->select('Staff_Id')->get() as $s) {
            $staffMap[strtolower($s->Staff_Id)] = $s->Staff_Id;
        }

        $studentMap = [];
        foreach ($conn->table('tblStudent')->select('Student_Id')->get() as $s) {
            $studentMap[strtolower($s->Student_Id)] = $s->Student_Id;
        }

        $typeMap = [];
        foreach ($conn->table('tblAdvisor_Type')->select('Advisor_Type')->get() as $t) {
            $typeMap[strtolower($t->Advisor_Type)] = $t->Advisor_Type;
        }

        // existing rows keyed by lowercase full-row (all 5 stored fields)
        $existingMap = [];
        foreach ($conn->table('tblAdvisor_Student')
                     ->select('Advisor_Id', 'Student_Id', 'Advisor_Type', 'Start_Date', 'End_Date')
                     ->get() as $r) {
            $existingMap[$this->rowKey(
                $r->Advisor_Id, $r->Student_Id, $r->Advisor_Type, $r->Start_Date, $r->End_Date
            )] = true;
        }

        $fileDate  = $this->fileDateFromName($filename);
        $seenKeys  = [];
        $advisorIds = []; // distinct advisor ids among valid rows (for staff re-enable)
        $counters = ['failures' => 0, 'duplicated' => 0];
        $inserts  = [];

        foreach ($rows as $row) {
            $status  = null;
            $remarks = null;
            $entry   = null; // normalized insert candidate

            // a. column count (cols 2-4 are present but ignored)
            if (count($row) !== 8) {
                $status  = 'Failure';
                $remarks = 'Incorrect number of columns';
            } else {
                [$advRaw, , , , $stuRaw, $typRaw, $startRaw, $endRaw] = $row;
                $advRaw   = trim((string) $advRaw);
                $stuRaw   = trim((string) $stuRaw);
                $typRaw   = trim((string) $typRaw);
                $startRaw = trim((string) $startRaw);
                $endRaw   = trim((string) $endRaw);

                // b. all 5 used fields present
                if ($advRaw === '' || $stuRaw === '' || $typRaw === '' || $startRaw === '' || $endRaw === '') {
                    $status  = 'Failure';
                    $remarks = 'one or more field is empty';
                }
                // c. max length — count characters, not bytes
                elseif (mb_strlen($advRaw) > self::MAX_ADVISOR_ID
                     || mb_strlen($stuRaw) > self::MAX_STUDENT_ID
                     || mb_strlen($typRaw) > self::MAX_ADVISOR_TYPE) {
                    $status  = 'Failure';
                    $remarks = 'field exceeds max length';
                }
                // d. dates must be valid (YYYY-MM-DD or dd-MMM-yy) — normalized to YYYY-MM-DD
                $startNorm = $this->normalizeDate($startRaw);
                $endNorm   = $this->normalizeDate($endRaw);
                if ($startNorm === null || $endNorm === null) {
                    $status  = 'Failure';
                    $remarks = 'Date must be a valid date (YYYY-MM-DD or dd-MMM-yy)';
                }
                // e. Advisor Id must exist in tblStaff (disabled staff still pass — re-enabled later)
                else {
                    $advNorm = $staffMap[strtolower($advRaw)] ?? null;
                    if (! $advNorm) {
                        $status  = 'Failure';
                        $remarks = 'Advisor Id not exist in tblStaff master table.';
                    }
                    // f. Student Id must exist in tblStudent
                    else {
                        $stuNorm = $studentMap[strtolower($stuRaw)] ?? null;
                        if (! $stuNorm) {
                            $status  = 'Failure';
                            $remarks = 'Student Id not exist in tblStudent master table.';
                        }
                        // g. Advisor Type must exist (normalize to master casing)
                        else {
                            $typNorm = $typeMap[strtolower($typRaw)] ?? null;
                            if (! $typNorm) {
                                $status  = 'Failure';
                                $remarks = 'Advisor Type not exist in tblAdvisor_Type master table.';
                            } else {
                                $key = $this->rowKey($advNorm, $stuNorm, $typNorm, $startNorm, $endNorm);

                                // h. identical row already in this file
                                if (isset($seenKeys[$key])) {
                                    $status  = 'Failure';
                                    $remarks = 'Duplicated record in the same CSV file';
                                } else {
                                    $seenKeys[$key] = true;
                                    $advisorIds[$advNorm] = $advNorm;

                                    // i. identical row already in tblAdvisor_Student
                                    if (isset($existingMap[$key])) {
                                        $status  = 'Duplicated';
                                        $remarks = 'Same data already exists, no update occurred.';
                                    } else {
                                        // j. new row
                                        $inserts[] = [
                                            'advisor' => $advNorm,
                                            'student' => $stuNorm,
                                            'type'    => $typNorm,
                                            'start'   => $startNorm,
                                            'end'     => $endNorm,
                                        ];
                                    }
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
            'updates'    => [], // no update case for this import
            'advisorIds' => array_values($advisorIds),
        ];
    }

    /**
     * Insert new rows + re-enable disabled staff (inside the caller's
     * transaction). No update case: existing rows are never modified.
     *
     * @return array{0: int, 1: int} [inserted, updated=0]
     */
    protected function applyChanges($conn, array $plan, string $user, string $ip): array
    {
        $inserted = 0;
        foreach ($plan['inserts'] as $r) {
            $conn->table('tblAdvisor_Student')->insert([
                'Advisor_Id'   => $r['advisor'],
                'Student_Id'   => $r['student'],
                'Advisor_Type' => $r['type'],
                'Start_Date'   => $r['start'],
                'End_Date'     => $r['end'],
                'updated_by'   => $user,
                'updated_ip'   => $ip,
                // created_at / updated_at use DB defaults
            ]);
            $inserted++;
        }

        // re-enable disabled staff for every distinct Advisor_Id in the file
        foreach ($plan['advisorIds'] ?? [] as $advisorId) {
            $conn->table('tblStaff')
                ->where('Staff_Id', $advisorId)
                ->where('status', 1)
                ->update([
                    'status'     => 0,
                    'updated_by' => $user,
                    'updated_ip' => $ip,
                ]);
        }

        return [$inserted, 0];
    }

    /** Lowercased full-row key used for in-file and DB duplicate checks. */
    private function rowKey($adv, $stu, $typ, $start, $end): string
    {
        return strtolower(implode('|', [$adv, $stu, $typ, $start, $end]));
    }

    /**
     * Normalize a date to YYYY-MM-DD; null when invalid.
     * Accepts YYYY-MM-DD or dd-MMM-yy (e.g. 12-Mar-15 / 5-Sep-16 — day may be
     * 1 or 2 digits, month case-insensitive). Two-digit years are interpreted
     * as 2000-2099 (matches the system's 2015-2099 data range: 15 -> 2015,
     * 99 -> 2099).
     */
    private function normalizeDate(string $value): ?string
    {
        $v = trim($value);

        // format 1: YYYY-MM-DD (strict — must round-trip exactly)
        $dt = \DateTime::createFromFormat('!Y-m-d', $v);
        if ($dt !== false && $dt->format('Y-m-d') === $v) {
            return $dt->format('Y-m-d');
        }

        // format 2: dd-MMM-yy (day 1-2 digits, month case-insensitive)
        if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{2})$/', $v, $m)) {
            $day      = (int) $m[1];
            $month    = ucfirst(strtolower($m[2]));
            $year     = 2000 + (int) $m[3]; // two-digit year -> 2000s
            $monthDt  = \DateTime::createFromFormat('!M', $month);
            $monthNum = $monthDt ? (int) $monthDt->format('n') : 0;
            if ($monthNum >= 1 && checkdate($monthNum, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
            }
        }

        return null;
    }
}
