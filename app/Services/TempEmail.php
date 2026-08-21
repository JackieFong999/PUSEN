<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Temporary demo email addresses for all test emails (ET-001 / ET-002 /
 * Email Management). Stored in the tblEmail_Temp table so the demo operator
 * can change the destination(s) at runtime without code or .env changes.
 * Falls back to the legacy env config when the table is empty or missing.
 */
class TempEmail
{
    /** Recipient address: first row of tblEmail_Temp.Email_To (Id asc). */
    public static function get(): string
    {
        try {
            $row = DB::connection('pusen')->table('tblEmail_Temp')->orderBy('Id')->first();
            if ($row && trim((string) $row->Email_To) !== '') {
                return trim((string) $row->Email_To);
            }
        } catch (\Throwable $e) {
            // table missing etc. -> fall through to the env fallback
        }

        $env = trim((string) config('mail.dev_override_to', ''));
        if ($env !== '') {
            return $env;
        }

        return 'hokayuen48@gmail.com';
    }

    /** BCC address: first row of tblEmail_Temp.Email_BCC (empty when unset). */
    public static function bcc(): string
    {
        try {
            $row = DB::connection('pusen')->table('tblEmail_Temp')->orderBy('Id')->first();
            if ($row && trim((string) $row->Email_BCC) !== '') {
                return trim((string) $row->Email_BCC);
            }
        } catch (\Throwable $e) {
            // table missing etc. -> fall through to the env fallback
        }

        return trim((string) config('mail.et002_bcc', ''));
    }
}
