<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: harmless if the seeder already created it. No cards are generated for Others.
        if (DB::table('buildings')->where('code', 'OTH')->exists()) {
            return;
        }

        DB::table('buildings')->insert([
            'code'           => 'OTH',
            'name'           => 'Others',
            'color_name'     => 'Gray',
            'color_hex'      => '#64748b',
            'template_image' => null,
            'qr_color_hex'   => null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('buildings')->where('code', 'OTH')->delete();
    }
};