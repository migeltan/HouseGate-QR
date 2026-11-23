<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congressmen', function (Blueprint $table) {
            $table->string('remarks', 255)->nullable()->after('direct');
        });
    }

    public function down(): void
    {
        Schema::table('congressmen', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};