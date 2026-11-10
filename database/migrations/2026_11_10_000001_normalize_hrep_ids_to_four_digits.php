<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HREP IDs are 4 digits in real life. Keep the last 4 digits of each legacy ID
     * (HREP-2020-0012 -> 0012). Aborts, changing nothing, if two users would collide.
     */
    public function up(): void
    {
        $users = DB::table('users')->select('id', 'name', 'hrep_id')->get();

        $seen = [];        // 4-digit value => user id that owns it
        $plan = [];        // user id => new 4-digit value
        $collisions = [];

        // IDs already in the 4-digit form claim their value first.
        foreach ($users as $u) {
            $id = trim((string) $u->hrep_id);
            if (preg_match('/^\d{4}$/', $id)) {
                // NEW: two users already holding the same 4-digit ID used to overwrite each other silently.
                if (isset($seen[$id])) {
                    $collisions[] = "{$u->name} ('{$u->hrep_id}') duplicates user #{$seen[$id]}";
                    continue;
                }
                $seen[$id] = $u->id;
            }
        }

        foreach ($users as $u) {
            $id = trim((string) $u->hrep_id);

            // Already 4 digits, or no trailing 4 digits to parse: leave untouched.
            if (preg_match('/^\d{4}$/', $id) || ! preg_match('/(\d{4})$/', $id, $m)) {
                continue;
            }

            $new = $m[1];

            if (isset($seen[$new])) {
                $collisions[] = "{$u->name} ('{$u->hrep_id}') would become {$new}, already used by user #{$seen[$new]}";
                continue;
            }

            $seen[$new] = $u->id;
            $plan[$u->id] = $new;
        }

        if ($collisions) {
            throw new RuntimeException("HREP ID cleanup stopped, nothing was changed. Resolve these duplicates first:\n".implode("\n", $collisions));
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $id => $new) {
                DB::table('users')->where('id', $id)->update(['hrep_id' => $new]);
            }
        });
    }

    public function down(): void
    {
        // The original long IDs cannot be reconstructed; restore from a backup if needed.
    }
};