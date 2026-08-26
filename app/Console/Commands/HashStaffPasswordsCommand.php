<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One-off migration helper: convert any remaining plain-text passwords in
 * tblStaff to bcrypt hashes (required after the Password column was widened
 * from varchar(10) to varchar(255)).
 *
 * Safe to re-run — already-hashed rows are skipped. Passwords are NEVER
 * printed to the console.
 */
class HashStaffPasswordsCommand extends Command
{
    protected $signature = 'staff:hash-passwords {--dry-run : Show which accounts still hold plain-text passwords without writing}';

    protected $description = 'Convert plain-text tblStaff.Password values to bcrypt hashes';

    public function handle(): int
    {
        $rows = DB::connection('pusen')
            ->table('tblStaff')
            ->select('Staff_Id', 'Password')
            ->orderBy('Staff_Id')
            ->get();

        $alreadyHashed = 0;
        $empty = 0;
        $toConvert = [];

        foreach ($rows as $row) {
            $pw = $row->Password;

            if ($pw === null || $pw === '') {
                $empty++;
                continue;
            }

            if (Hash::isHashed($pw)) {
                $alreadyHashed++;
                continue;
            }

            $toConvert[] = $row->Staff_Id;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('Dry run: %d total, %d already hashed, %d empty/null, %d plain text to convert.',
                $rows->count(), $alreadyHashed, $empty, count($toConvert)));
            foreach ($toConvert as $id) {
                $this->line("  would hash: {$id}");
            }

            return self::SUCCESS;
        }

        if ($toConvert === []) {
            $this->info('No plain-text passwords found — everything is already hashed.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($toConvert));
        $bar->start();

        foreach ($toConvert as $id) {
            $plain = DB::connection('pusen')->table('tblStaff')->where('Staff_Id', $id)->value('Password');

            DB::connection('pusen')
                ->table('tblStaff')
                ->where('Staff_Id', $id)
                ->update(['Password' => Hash::make($plain)]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Hashed %d account(s). %d were already hashed, %d had no password.',
            count($toConvert), $alreadyHashed, $empty));

        return self::SUCCESS;
    }
}
