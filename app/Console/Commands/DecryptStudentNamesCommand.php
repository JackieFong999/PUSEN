<?php

namespace App\Console\Commands;

use App\Services\StudentNameEncryption;

/**
 * Rollback helper: restore plaintext student names (tblStudent +
 * tblHK_Student_Log). Only needed if encryption must be reversed
 * (e.g. key rotation gone wrong); plaintext values are skipped.
 */
class DecryptStudentNamesCommand extends StudentNamesCommand
{
    protected $signature = 'student:decrypt-names {--dry-run : Show what would change without writing}';

    protected $description = 'Restore plaintext student names (tblStudent + tblHK_Student_Log) — rollback helper';

    protected function needsTransform(string $value): bool
    {
        return StudentNameEncryption::isEncrypted($value);
    }

    protected function transform(string $value): string
    {
        return (string) StudentNameEncryption::decrypt($value);
    }

    protected function actionLabel(): string
    {
        return 'decrypt';
    }
}
