<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            // Desk (building) physically holding the card. NULL = vault (held by no desk).
            $table->foreignId('holder_building_id')
                ->nullable()
                ->after('building_id')
                ->constrained('buildings')
                ->nullOnDelete();
        });

        // Keep today's behaviour: every existing card starts at its own home desk.
        DB::table('visitor_passes')->update(['holder_building_id' => DB::raw('building_id')]);
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropForeign(['holder_building_id']);
            $table->dropColumn('holder_building_id');
        });
    }
};