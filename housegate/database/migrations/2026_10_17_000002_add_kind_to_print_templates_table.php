<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->string('kind', 10)->default('card')->after('name');
            $table->dropUnique(['name']);
            $table->unique(['kind', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->dropUnique(['kind', 'name']);
            $table->unique('name');
            $table->dropColumn('kind');
        });
    }
};