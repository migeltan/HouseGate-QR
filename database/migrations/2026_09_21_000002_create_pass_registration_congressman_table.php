<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_registration_congressman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('congressman_id')->constrained('congressmen');
            $table->timestamps();

            // Explicit name — the auto-generated one exceeds MySQL's 64-char limit.
            $table->unique(['pass_registration_id', 'congressman_id'], 'prc_registration_congressman_unique');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('office_other')->nullable()->after('office_to_visit');
        });
    }

    public function down(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropColumn('office_other');
        });

        Schema::dropIfExists('pass_registration_congressman');
    }
};