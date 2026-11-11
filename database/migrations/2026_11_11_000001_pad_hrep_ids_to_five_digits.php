<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HREP IDs are 5 digits. Existing 4-digit IDs get a leading zero (0012 -> 00012).
     * Aborts, changing nothing, if a padded ID would collide with an ID already in use.
     */
    public function up(): void
    {
        $users = DB::table('users')->select('id', 'name', 'hrep_id')->get();

        $taken = [];   // every ID currently in use => user id
        foreach ($users as $u) {
            $taken[trim((string) $u->hrep_id)] = $u->id;
        }

        $plan = [];
        $collisions = [];
        foreach ($users as $u) {
            $id = trim((string) $u->hrep_id);
            if (! preg_match('/^\d{4}$/', $id)) {
                continue;   // already 5 digits, empty, or a legacy format: leave untouched
            }

            $new = '0' . $id;
            if (isset($taken[$new])) {
                $collisions[] = "{$u->name} ('{$id}') would become {$new}, already used by user #{$taken[$new]}";
                continue;
            }

            $taken[$new] = $u->id;
            $plan[$u->id] = $new;
        }

        if ($collisions) {
            throw new RuntimeException("HREP ID padding stopped, nothing was changed. Resolve these first:\n" . implode("\n", $collisions));
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $id => $new) {
                DB::table('users')->where('id', $id)->update(['hrep_id' => $new]);
            }
        });
    }

    public function down(): void
    {
        // Not reversible: a padded ID cannot be told apart from one that was always 5 digits.
    }
};