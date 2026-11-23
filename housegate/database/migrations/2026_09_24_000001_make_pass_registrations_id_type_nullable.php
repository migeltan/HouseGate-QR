<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 2026_09_23_000001 relaxed id_ref but missed id_type, so registering a
    // visitor without an ID type crashed on the pass_registrations insert.
    public function up(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('id_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('id_type')->nullable(false)->change();
        });
    }
};