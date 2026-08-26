<?php

namespace App\Console\Commands;

use App\Services\StudentNameEncryption;

/**
 * Batch-encrypt any plaintext student names left in the DB
 * (tblStudent + tblHK_Student_Log) with the shared STUDENT_NAME_KEY.
 * Idempotent: already-encrypted values are skipped.
 */
class EncryptStudentNamesCommand extends StudentNamesCommand
{
    protected $signature = 'student:encrypt-names {--dry-run : Show what would change without writing}';

    protected $description = 'Encrypt plaintext student names (tblStudent + tblHK_Student_Log) with STUDENT_NAME_KEY';

    protected function needsTransform(string $value): bool
    {
        return ! StudentNameEncryption::isEncrypted($value);
    }

    protected function transform(string $value): string
    {
        return (string) StudentNameEncryption::encrypt($value);
    }

    protected function actionLabel(): string
    {
        return 'encrypt';
    }
}
