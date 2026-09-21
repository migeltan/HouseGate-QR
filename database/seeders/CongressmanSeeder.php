<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Congressman;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads database/data/congressmen.csv into the congressmen table.
 *
 * Safe to re-run: rows are upserted by member_id, so a room change in the CSV
 * updates the existing row. Members that are no longer in the CSV are marked
 * inactive (not deleted) so old registrations keep pointing at a real row.
 *
 * Requires the buildings to exist first (DatabaseSeeder calls this after them).
 */
class CongressmanSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/congressmen.csv');

        if (! is_readable($path)) {
            throw new RuntimeException("Roster CSV not found: {$path}");
        }

        $buildingIds = Building::pluck('id', 'code');

        $handle = fopen($path, 'r');
        // Explicit separator/enclosure/escape: PHP 8.4 deprecates relying on the default escape.
        $header = fgetcsv($handle, 0, ',', '"', '');

        $seen = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;

            if ($row === [null] || count($row) !== count($header)) {
                continue; // blank / malformed line
            }

            $data = array_combine($header, $row);

            $buildingId = $buildingIds[$data['building_code']] ?? null;
            if (! $buildingId) {
                fclose($handle);
                throw new RuntimeException(
                    "Line {$line} ({$data['name']}): no building with code '{$data['building_code']}'. Run the building seeder first."
                );
            }

            Congressman::updateOrCreate(
                ['member_id' => $data['member_id']],
                [
                    'name' => $data['name'],
                    'rep_type' => $data['rep_type'],
                    'rep_detail' => $data['rep_detail'] !== '' ? $data['rep_detail'] : null,
                    'building_id' => $buildingId,
                    'floor' => $data['floor'] !== '' ? (int) $data['floor'] : null,
                    'room' => $data['room'] !== '' ? $data['room'] : null,
                    'is_active' => true,
                ]
            );

            $seen[] = $data['member_id'];
        }

        fclose($handle);

        if ($seen === []) {
            throw new RuntimeException('Roster CSV had no valid rows — refusing to deactivate everyone.');
        }

        // Anyone previously loaded but missing from this CSV is deactivated.
        Congressman::whereNotIn('member_id', $seen)->update(['is_active' => false]);

        $this->command?->info(count($seen) . ' congressmen loaded.');
    }
}