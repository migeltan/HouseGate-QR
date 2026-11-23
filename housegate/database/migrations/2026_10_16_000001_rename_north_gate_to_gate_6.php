<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('buildings')->where('code', 'NG')->update(['name' => 'Gate 6']);
    }

    public function down(): void
    {
        DB::table('buildings')->where('code', 'NG')->update(['name' => 'North Gate']);
    }
};