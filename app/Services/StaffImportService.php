<?php

namespace App\Services;

/**
 * Staff CSV import from the SFTP server.
 *
 * Spec: docs/pusen01-import-spec-staff.md
 *
 * Differences from Subject: constant filename (no date), `||` delimiter with
 * unquoted values, tblStaff target, default password/role on insert,
 * re-enable disabled staff, timestamped archive name.
 */
class StaffImportService extends AbstractImportService
{
    /** Constant filename (no date) — verified against the real file. */
    public const FILE_NAME = 'iam_sao_sen_databank_polyu_staff.csv';

    public const FILE_TYPE = 'STAFF';

    /** Defaults for newly imported staff (decisions 2026-08-16). */
    public const DEFAULT_PASSWORD = 'Abcd1234';
    public const DEFAULT_ROLE_ID  = 'KS';

    /** Column max lengths (varchar limits). */
    public const MAX_STAFF_ID = 20;   // tblStaff.Staff_Id varchar(20)
    public const MAX_NAME     = 30;   // Staff_Name / Staff_Display_Name varchar(30)

    protected function fileNamePattern(): string
    {
        return '/^' . preg_quote(self::FILE_NAME, '/') . '$/i';
    }

    protected function filePrefix(): string
    {
        return 'iam_sao_sen_databank_polyu_staff';
    }

    protected function fileType(): string
    {
        return self::FILE_TYPE;
    }

    /**
     * Filename is constant -> timestamp the archived copy so repeated
     * imports never overwrite the previous archive.
     */
    protected function archiveName(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '_' . now()->format('Ymd_His') . '.csv';
    }

    /** Rebuild the raw row for Row_Content — keep the original || format. */
    protected function rawRow(array $fields): string
    {
        return implode('||', $fields);
    }

    /**
     * Parse `||`-delimited lines into rows of 3 fields; null when no data rows.
     * Values are unquoted in the real file; surrounding quotes are tolerated
     * (stripped) in case the source format changes. Fields are trimmed.
     */
    protected function parseCsv(string $content): ?array
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = [];
            foreach (explode('||', $line) as $field) {
                $field = trim($field);
                // tolerate surrounding double quotes per field
                if (strlen($field) >= 2 && $field[0] === '"' && substr($field, -1) === '"') {
                    $field = substr($field, 1, -1);
                }
                $fields[] = trim($field);
            }
            // pad/limit to 3 so column-count validation decides
            $rows[] = array_slice(array_pad($fields, 3, ''), 0, 3);
        }
        return empty($rows) ? null : $rows;
    }

    /**
     * Validate all rows; write tblImport_Failed_Log for non-insert rows.
     * Rules (first hit wins): a column count, b empty, c max length,
     * d duplicate in file, e identical + enabled -> Duplicated,
     * f identical + disabled -> Update (re-enable), g differs -> Update,
     * h new key -> INSERT.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array}
     */
    protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array
    {
        // existing staff keyed by lowercase Staff_Id
        $staffMap = [];
        foreach ($conn->table('tblStaff')->select('Staff_Id', 'Staff_Name', 'Staff_Display_Name', 'status')->get() as $s) {
            $staffMap[strtolower($s->Staff_Id)] = $s;
        }

        $seenIds  = [];
        $counters = ['failures' => 0, 'duplicated' => 0];
        $inserts  = [];
        $updates  = [];

        foreach ($rows as $row) {
            $status  = null;
            $remarks = null;

            // a. column count
            if (count($row) !== 3) {
                $status  = 'Failure';
                $remarks = 'Incorrect number of columns';
            } else {
                [$idRaw, $nameRaw, $displayRaw] = $row;

                // b. all 3 fields present
                if ($idRaw === '' || $nameRaw === '' || $displayRaw === '') {
                    $status  = 'Failure';
                    $remarks = 'one or more field is empty';
                }
                // c. max length (avoid whole-transaction "Data too long")
                elseif (strlen($idRaw) > self::MAX_STAFF_ID
                     || strlen($nameRaw) > self::MAX_NAME
                     || strlen($displayRaw) > self::MAX_NAME) {
                    $status  = 'Failure';
                    $remarks = 'field exceeds max length';
                }
                // d. duplicate within this file (case-insensitive)
                elseif (isset($seenIds[strtolower($idRaw)])) {
                    $status  = 'Failure';
                    $remarks = 'Duplicated record in the same CSV file';
                } else {
                    $seenIds[strtolower($idRaw)] = true;

                    $existing = $staffMap[strtolower($idRaw)] ?? null;
                    if ($existing) {
                        // e/f/g. key exists -> compare names (case-insensitive)
                        $sameData = strtolower((string) $existing->Staff_Name) === strtolower($nameRaw)
                            && strtolower((string) $existing->Staff_Display_Name) === strtolower($displayRaw);
                        $disabled = (int) $existing->status === 1;

                        if ($sameData && ! $disabled) {
                            $status  = 'Duplicated';
                            $remarks = 'Same data already exist, no updated occurred.';
                        } else {
                            $status  = 'Update';
                            $remarks = $sameData
                                ? 'Information: Key already exist, record will be re-enabled.'
                                : 'Information: Key already exist, record will be updated.';
                            // keep the DB's existing Staff_Id casing (never change the PK)
                            $updates[] = [
                                'id'      => $existing->Staff_Id,
                                'name'    => $nameRaw,
                                'display' => $displayRaw,
                            ];
                        }
                    } else {
                        // h. new key
                        $inserts[] = [
                            'id'      => $idRaw,
                            'name'    => $nameRaw,
                            'display' => $displayRaw,
                        ];
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
                    'File_Date'     => $this->fileDateFromName($filename) ?? now(),
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
     * Updates also re-enable (status=0). Password never touched on update.
     */
    protected function applyChanges($conn, array $plan, string $user, string $ip): array
    {
        $inserted = 0;
        foreach ($plan['inserts'] as $r) {
            $conn->table('tblStaff')->insert([
                'Staff_Id'           => $r['id'],
                'Staff_Name'         => $r['name'],
                'Staff_Display_Name' => $r['display'],
                'status'             => 0,                       // enable
                'Password'           => self::DEFAULT_PASSWORD,
                'Role_Id'            => self::DEFAULT_ROLE_ID,
                'Target_User_Id'     => '',
                'updated_by'         => $user,
                'updated_ip'         => $ip,
                // created_at / updated_at use DB defaults
            ]);
            $inserted++;
        }

        $updated = 0;
        foreach ($plan['updates'] as $r) {
            $conn->table('tblStaff')
                ->where('Staff_Id', $r['id'])
                ->update([
                    'Staff_Name'         => $r['name'],
                    'Staff_Display_Name' => $r['display'],
                    'status'             => 0,                   // re-enable
                    'updated_by'         => $user,
                    'updated_ip'         => $ip,
                    // Password untouched; updated_at auto-updates
                ]);
            $updated++;
        }

        return [$inserted, $updated];
    }
}
