<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            // A card that must not be issued until it is found / repaired. NULL = normal card.
            // Kept apart from `status` so the status enum (and every status check) stays untouched.
            $table->string('hold_reason', 20)->nullable()->after('holder_building_id');   // missing | damaged | other
            $table->string('hold_note', 255)->nullable()->after('hold_reason');
            $table->timestamp('hold_at')->nullable()->after('hold_note');
            $table->string('hold_by')->nullable()->after('hold_at');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn(['hold_reason', 'hold_note', 'hold_at', 'hold_by']);
        });
    }
};