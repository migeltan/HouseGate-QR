<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Audit log screen + live poll: newest scans first, per building or overall.
            $table->index(['scanned_building_id', 'created_at'], 'scan_logs_building_created_idx');
            $table->index('created_at', 'scan_logs_created_idx');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->index('registered_at', 'pass_reg_registered_idx');
            $table->index('updated_at', 'pass_reg_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex('scan_logs_building_created_idx');
            $table->dropIndex('scan_logs_created_idx');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropIndex('pass_reg_registered_idx');
            $table->dropIndex('pass_reg_updated_idx');
        });
    }
};