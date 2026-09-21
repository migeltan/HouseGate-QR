<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('congressmen', function (Blueprint $table) {
            $table->id();
            // Stable ID from the congress.gov.ph directory (e.g. "K106") — the upsert key.
            $table->string('member_id')->unique();
            $table->string('name');
            $table->string('rep_type');                    // District / Party-list Representative
            $table->string('rep_detail')->nullable();      // e.g. "Manila, 6th District" or "TINGOG"
            // Building the congressman's office room is in. North Gate never appears here —
            // it is the multi-building pass, not a physical office location.
            $table->foreignId('building_id')->constrained();
            $table->unsignedTinyInteger('floor')->nullable();
            $table->string('room')->nullable();            // e.g. "SWA-316"; some are non-standard ("DDC")
            // Members who drop out of the roster CSV are deactivated, not deleted,
            // so past registrations that reference them stay intact.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['building_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('congressmen');
    }
};