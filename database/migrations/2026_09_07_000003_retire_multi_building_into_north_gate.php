<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $multi = DB::table('buildings')->where('code', 'MULTI')->first();
        $northGate = DB::table('buildings')->where('code', 'NG')->first();

        if (! $multi || ! $northGate) {
            return; // nothing to migrate
        }

        $existingMax = (int) DB::table('visitor_passes')
            ->where('building_id', $northGate->id)
            ->selectRaw('MAX(CAST(pass_number AS UNSIGNED)) as max_num')
            ->value('max_num');

        $multiPasses = DB::table('visitor_passes')->where('building_id', $multi->id)->get();

        foreach ($multiPasses as $i => $pass) {
            $newNumber = str_pad((string) ($existingMax + $i + 1), 4, '0', STR_PAD_LEFT);
            DB::table('visitor_passes')->where('id', $pass->id)->update([
                'building_id' => $northGate->id,
                'pass_number' => $newNumber,
                // qr_token intentionally untouched
            ]);
        }

        DB::table('buildings')->where('id', $multi->id)->delete();
    }

    public function down(): void
    {
        // Data migration — not reversible without a backup of prior pass_number/building_id values.
    }
};