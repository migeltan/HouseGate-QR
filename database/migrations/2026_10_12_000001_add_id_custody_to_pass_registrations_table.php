<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            // Desk (building) physically holding the visitor's surrendered ID.
            $table->foreignId('id_held_building_id')
                ->nullable()
                ->constrained('buildings')
                ->nullOnDelete();

            // Set when the ID is handed back to the visitor, and by whom.
            $table->timestamp('id_returned_at')->nullable();
            $table->foreignId('id_returned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropForeign(['id_held_building_id']);
            $table->dropForeign(['id_returned_by']);
            $table->dropColumn(['id_held_building_id', 'id_returned_at', 'id_returned_by']);
        });
    }
};