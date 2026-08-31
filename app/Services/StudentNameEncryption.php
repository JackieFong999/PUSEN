<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

/**
 * At-rest encryption for student names (tblStudent + tblHK_Student_Log).
 *
 * Uses AES-256-CBC with a DEDICATED key (env STUDENT_NAME_KEY), NOT APP_KEY:
 * dev (:8080), demo (:8081) and the partner server are separate Laravel
 * installations sharing/reading the same MySQL database, so they must all be
 * able to decrypt each other's values. The same base64 key value must be
 * present in every .env.
 *
 * Ciphertext is prefixed with "se1:" so isEncrypted() is a cheap prefix
 * check and decrypt() can pass plaintext through untouched (which also makes
 * mixed plaintext/ciphertext states safe during migration).
 *
 * KEY LOSS = NAMES UNREADABLE. Keep a copy outside the DB (backups folder).
 */
class StudentNameEncryption
{
    /** marker prefix distinguishing our ciphertext from plaintext */
    private const MARKER = 'se1:';

    private static ?Encrypter $cipher = null;

    private static function cipher(): Encrypter
    {
        if (self::$cipher === null) {
            // Read via config() so this survives `php artisan config:cache` -
            // after caching, env() outside config files returns null and the
            // key would silently go empty (names show as raw "se1:" text).
            $key = (string) config('services.student_name_key', '');
            // strip the conventional "base64:" prefix (same convention as APP_KEY)
            if (str_starts_with($key, 'base64:')) {
                $key = substr($key, 7);
            }
            $raw = base64_decode($key, true) ?: '';
            if (strlen($raw) !== 32) {
                throw new \RuntimeException(
                    'STUDENT_NAME_KEY is missing or invalid — expected a base64-encoded 32-byte (AES-256) key. ' .
                    'Generate one with: php -r "echo \'base64:\'.base64_encode(random_bytes(32));"'
                );
            }
            self::$cipher = new Encrypter($raw, 'AES-256-CBC');
        }

        return self::$cipher;
    }

    /** Encrypt a name for storage. null/'' pass through unchanged. */
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return self::MARKER . self::cipher()->encryptString($value);
    }

    /**
     * Decrypt a stored name. Plaintext (legacy rows) passes through
     * unchanged; undecryptable values are returned raw with a warning
     * (fail visible, never silently lose the value).
     */
    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || ! str_starts_with($value, self::MARKER)) {
            return $value;
        }

        try {
            return self::cipher()->decryptString(substr($value, strlen(self::MARKER)));
        } catch (\Throwable $e) {
            Log::warning('StudentNameEncryption: decrypt failed: ' . $e->getMessage());
            return $value;
        }
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && $value !== '' && str_starts_with($value, self::MARKER);
    }
}
