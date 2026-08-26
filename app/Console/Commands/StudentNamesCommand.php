<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic for student:encrypt-names / student:decrypt-names.
 *
 * Operates in place on tblStudent + tblHK_Student_Log name columns,
 * idempotent, never prints the names themselves, only Staff_Id-style ids.
 */
abstract class StudentNamesCommand extends Command
{
    /** table => name columns to transform */
    protected function tables(): array
    {
        return [
            'tblStudent'        => ['Student_Name_Eng', 'Student_Name_Chn'],
            'tblHK_Student_Log' => ['Student_Name_Eng', 'Student_Name_Chn'],
        ];
    }

    /** does this stored value need the transform? */
    abstract protected function needsTransform(string $value): bool;

    /** transform one value (encrypt or decrypt) */
    abstract protected function transform(string $value): string;

    /** human label: "encrypt" / "decrypt" */
    abstract protected function actionLabel(): string;

    public function handle(): int
    {
        $conn = DB::connection('pusen');
        $dryRun = (bool) $this->option('dry-run');
        $action = $this->actionLabel();

        $pending = []; // [table, id, column, newValue]
        $totals = ['tblStudent' => 0, 'tblHK_Student_Log' => 0];
        $examples = [];

        foreach ($this->tables() as $table => $columns) {
            $rows = $conn->table($table)->select(array_merge(['Student_Id'], $columns))->get();

            foreach ($rows as $row) {
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (! $this->needsTransform($value)) {
                        continue;
                    }

                    $totals[$table]++;
                    if (count($examples[$table] ?? []) < 5) {
                        $examples[$table][] = $row->Student_Id;
                    }
                    if (! $dryRun) {
                        $pending[] = [$table, $row->Student_Id, $column, $this->transform($value)];
                    }
                }
            }
        }

        $total = $totals['tblStudent'] + $totals['tblHK_Student_Log'];

        if ($dryRun) {
            $this->info("Dry run — would {$action} {$total} value(s):");
            foreach ($this->tables() as $table => $_) {
                if ($totals[$table] > 0) {
                    $this->line(sprintf("  %-20s %d value(s), e.g. %s",
                        $table, $totals[$table], implode(', ', $examples[$table] ?? [])));
                }
            }

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info("Nothing to {$action} — all values already " . ($action === 'encrypt' ? 'encrypted.' : 'plaintext.'));

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($pending as [$table, $id, $column, $newValue]) {
            $conn->table($table)->where('Student_Id', $id)->update([$column => $newValue]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$action}: {$total} value(s) — tblStudent {$totals['tblStudent']}, tblHK_Student_Log {$totals['tblHK_Student_Log']}.");

        return self::SUCCESS;
    }
}
