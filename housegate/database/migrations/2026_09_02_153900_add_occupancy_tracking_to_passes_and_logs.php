<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->foreignId('current_building_id')->nullable()->after('is_multi_building')
                ->constrained('buildings')->nullOnDelete();
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->string('direction')->nullable()->after('scanned_building_id');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_building_id');
        });
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};