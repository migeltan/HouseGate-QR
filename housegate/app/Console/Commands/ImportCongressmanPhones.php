<?php

namespace App\Console\Commands;

use App\Models\Congressman;
use Illuminate\Console\Command;

class ImportCongressmanPhones extends Command
{
    protected $signature = 'congressmen:import-phones {--overwrite : Replace numbers that are already filled in}';
    protected $description = 'Fill trunkline / local / direct on existing congressmen from database/data/congressmen.csv.';

    public function handle(): int
    {
        $path = database_path('data/congressmen.csv');

        if (! is_readable($path)) {
            $this->error("Roster CSV not found: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');

        foreach (['member_id', 'trunkline', 'local', 'direct'] as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);
                $this->error("CSV has no '{$col}' column. Replace database/data/congressmen.csv with the merged file first.");
                return self::FAILURE;
            }
        }

        $overwrite = (bool) $this->option('overwrite');
        $updated = 0;
        $unchanged = 0;
        $missing = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($row === [null] || count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $member = Congressman::where('member_id', $data['member_id'])->first();

            if (! $member) {
                $missing[] = $data['member_id'];
                continue;
            }

            $changes = [];
            foreach (['trunkline', 'local', 'direct'] as $field) {
                $incoming = trim((string) $data[$field]);

                if ($incoming === '') {
                    continue; // never blank out a number
                }
                if ($overwrite || blank($member->{$field})) {
                    if ($member->{$field} !== $incoming) {
                        $changes[$field] = $incoming;
                    }
                }
            }

            if ($changes) {
                $member->update($changes);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        fclose($handle);

        $this->info("{$updated} updated, {$unchanged} unchanged.");
        if ($missing) {
            $this->warn(count($missing) . ' CSV members not in the database: ' . implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}