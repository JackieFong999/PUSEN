<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use phpseclib3\Net\SFTP;

/**
 * Base SFTP import service — shared pipeline for all import types.
 *
 * Shared here: SFTP config from tblConfig_SFTP, connect/disconnect, latest-file
 * picking, tblImport_Log / tblImport_Failed_Log write pattern, all-or-nothing
 * transaction, archiving, result payloads.
 *
 * Subclasses supply the per-type hooks: filename matching, CSV parser,
 * validation rules, and the DB changes to apply inside the transaction.
 *
 * Spec: docs/pusen01-import-spec.md (§7 generic pattern).
 */
abstract class AbstractImportService
{
    /** @var object|null tblConfig_SFTP row */
    protected $config;

    /** @var string|null reason for the last failed connect() */
    protected $lastError;

    /** @var SFTP|null */
    protected $sftp;

    public function __construct()
    {
        $this->config = DB::connection('pusen')
            ->table('tblConfig_SFTP')
            ->orderBy('Id')
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Per-type hooks (implemented by subclasses)                         */
    /* ------------------------------------------------------------------ */

    /**
     * Regex matching a valid CSV filename. Optional first capture group
     * (\d{8}) = date in the name, used for "newest by date" sorting.
     */
    abstract protected function fileNamePattern(): string;

    /** Static filename prefix used for the "last imported" LIKE query. */
    abstract protected function filePrefix(): string;

    /** FileType stored in tblImport_Log / tblImport_Failed_Log. */
    abstract protected function fileType(): string;

    /**
     * Name to use in processed/ after a successful import.
     * Timestamped so re-importing the same dated file never collides with an
     * existing archive (phpseclib rename fails when the target exists — the
     * file would stay in upload/ and get re-imported next time).
     */
    protected function archiveName(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '_' . now()->format('Ymd_His') . '.csv';
    }

    /** Parse raw CSV content into field arrays; null when there are no data rows. */
    abstract protected function parseCsv(string $content): ?array;

    /**
     * Validate every row, write tblImport_Failed_Log, return the plan.
     *
     * @return array{failures: int, duplicated: int, inserts: array, updates: array}
     */
    abstract protected function validateRows($conn, array $rows, int $logId, string $filename, string $user, string $ip): array;

    /**
     * Apply the plan inside the transaction (all or nothing).
     *
     * @return array{0: int, 1: int} [inserted, updated]
     */
    abstract protected function applyChanges($conn, array $plan, string $user, string $ip): array;

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Latest CSV of this type in the SFTP upload dir.
     * Newest = highest YYYYMMDD in the name (when present), then latest mtime.
     *
     * @return array{filename: ?string, exists: bool, error: ?string}
     */
    public function latestFile(): array
    {
        if (! $this->config) {
            return ['filename' => null, 'exists' => false, 'error' => 'SFTP config not found in tblConfig_SFTP.'];
        }

        if (! $this->connect()) {
            return ['filename' => null, 'exists' => false, 'error' => $this->connectError()];
        }

        try {
            $path = $this->importDir();
            $files = $this->sftp->nlist($path) ?: [];

            $candidates = [];
            foreach ($files as $file) {
                $name = basename((string) $file);
                if (preg_match($this->fileNamePattern(), $name, $m)) {
                    $candidates[] = [
                        'name'  => $name,
                        'date'  => $m[1] ?? '',
                        'mtime' => (int) $this->sftp->filemtime($path . '/' . $name),
                    ];
                }
            }

            if (empty($candidates)) {
                return ['filename' => null, 'exists' => false, 'error' => null];
            }

            // newest = highest date in name, then latest mtime
            usort($candidates, function ($a, $b) {
                return [$b['date'], $b['mtime']] <=> [$a['date'], $a['mtime']];
            });

            return ['filename' => $candidates[0]['name'], 'exists' => true, 'error' => null];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Last imported filename of this type from tblImport_Log.
     */
    public function lastImportedFile(): ?string
    {
        return DB::connection('pusen')
            ->table('tblImport_Log')
            ->where('File_Name', 'like', $this->filePrefix() . '%')
            ->orderByDesc('Id')
            ->value('File_Name');
    }

    /**
     * Run the full import pipeline for one CSV file.
     *
     * @return array{status: string, file: string, message: ?string,
     *               inserted: int, updated: int, duplicated: int, failures: int,
     *               archive_name: ?string, archive_moved: bool}
     *         status: 'success' | 'abort' | 'error'
     */
    public function import(string $filename): array
    {
        if (! $this->config) {
            return $this->result('error', $filename, 'SFTP config not found in tblConfig_SFTP.');
        }
        if (! preg_match($this->fileNamePattern(), $filename)) {
            return $this->result('error', $filename, 'Filename does not match the naming convention.');
        }
        if (! $this->connect()) {
            return $this->result('error', $filename, $this->connectError());
        }

        $conn = DB::connection('pusen');
        $user = auth()->user()->Staff_Id ?? 'system01';
        $ip   = request()->ip() ?? '';
        $ft   = $this->fileType();

        try {
            // --- 1. read file + write tblImport_Log (status NULL) ---
            $remoteFile = $this->importDir() . '/' . $filename;
            $csvContent = $this->sftp->get($remoteFile);
            if ($csvContent === false) {
                return $this->result('error', $filename, 'Cannot read file from SFTP server.');
            }

            $logId = $conn->table('tblImport_Log')->insertGetId([
                'File_Name'   => $filename,
                'FileType'    => $ft,
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
                $conn->table('tblImport_Log')->where('Id', $logId)->update([
                    'Import_Status'    => 'Failure',
                    'CSV_Row_Count'    => count($rows),
                    'Duplicated_Count' => $plan['duplicated'],
                    'Error_Count'      => $plan['failures'],
                ]);
                return $this->result('abort', $filename, null, [
                    'failures'   => $plan['failures'],
                    'duplicated' => $plan['duplicated'],
                ]);
            }

            // --- 4. transaction: insert new + update changed (all or nothing) ---
            $conn->beginTransaction();
            try {
                [$inserted, $updated] = $this->applyChanges($conn, $plan, $user, $ip);
                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                $conn->table('tblImport_Log')->where('Id', $logId)->update([
                    'Import_Status'    => 'Failure',
                    'CSV_Row_Count'    => count($rows),
                    'Duplicated_Count' => $plan['duplicated'],
                ]);
                return $this->result('error', $filename, 'Import transaction failed: ' . $e->getMessage(), [
                    'inserted'   => 0,
                    'updated'    => 0,
                    'duplicated' => $plan['duplicated'],
                    'failures'   => 0,
                ]);
            }

            // --- 5. success: record counts, mark log, archive file ---
            $conn->table('tblImport_Log')->where('Id', $logId)->update([
                'Import_Status'    => 'Success',
                'CSV_Row_Count'    => count($rows),
                'Import_Count'     => $inserted,
                'Updated_Count'    => $updated,
                'Duplicated_Count' => $plan['duplicated'],
                'Error_Count'      => $plan['failures'],
            ]);

            $archiveName = $this->archiveName($filename);
            $archiveOk   = $this->sftp->rename($remoteFile, $this->archiveDir() . '/' . $archiveName);

            return $this->result('success', $filename, null, [
                'inserted'   => $inserted,
                'updated'    => $updated,
                'duplicated' => $plan['duplicated'],
                'failures'   => $plan['failures'],
            ] + ['archive_name' => $archiveName, 'archive_moved' => $archiveOk]);
        } finally {
            $this->disconnect();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Shared helpers                                                     */
    /* ------------------------------------------------------------------ */

    protected function connect(): bool
    {
        try {
            $this->sftp = new SFTP($this->config->Host, (int) $this->config->Port);
            if (! $this->sftp->login($this->config->Username, $this->config->Password)) {
                $this->lastError = 'SFTP login failed (check Username/Password in tblConfig_SFTP)';
                $this->sftp = null;
                return false;
            }
            $this->lastError = null;
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->sftp = null;
            return false;
        }
    }

    /** Human-readable reason for the last failed connect (generic fallback). */
    protected function connectError(): string
    {
        return $this->lastError ?: 'Cannot connect to SFTP server.';
    }

    protected function disconnect(): void
    {
        if ($this->sftp) {
            try { $this->sftp->disconnect(); } catch (\Throwable $e) { /* ignore */ }
            $this->sftp = null;
        }
    }

    protected function importDir(): string
    {
        return rtrim((string) ($this->config->Remote_Path ?? 'upload'), '/') ?: 'upload';
    }

    /** Archive dir = sibling of Remote_Path at chroot root. */
    protected function archiveDir(): string
    {
        return dirname($this->importDir()) . '/processed';
    }

    /** Rebuild the raw CSV row text for Row_Content. */
    protected function rawRow(array $fields): string
    {
        return implode(',', array_map(fn ($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields));
    }

    /** Y-m-d from a _YYYYMMDD filename suffix, or null. */
    protected function fileDateFromName(string $filename): ?string
    {
        if (preg_match('/_(\d{8})\.csv$/i', $filename, $m)) {
            return date('Y-m-d', strtotime($m[1]));
        }
        return null;
    }

    protected function result(string $status, string $file, ?string $message, array $counts = []): array
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
