<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->string('id_ref')->nullable()->change();
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('id_ref')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->string('id_ref')->nullable(false)->change();
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('id_ref')->nullable(false)->change();
        });
    }
};