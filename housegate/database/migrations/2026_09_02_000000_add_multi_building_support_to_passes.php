<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->boolean('is_multi_building')->default(false)->after('building_id');
        });

        // Pivot table for multi-building access. building_id on visitor_passes
        // stays as the nominal/primary building even for multi-building passes;
        // this table + is_multi_building are the real source of truth for
        // authorization once is_multi_building = true.
        Schema::create('pass_building', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['visitor_pass_id', 'building_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_building');

        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn('is_multi_building');
        });
    }
};