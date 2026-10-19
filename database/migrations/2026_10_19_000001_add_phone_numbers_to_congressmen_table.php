<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congressmen', function (Blueprint $table) {
            $table->string('trunkline', 20)->nullable()->after('photo_path');
            $table->string('local', 10)->nullable()->after('trunkline');
            $table->string('direct', 20)->nullable()->after('local');
        });
    }

    public function down(): void
    {
        Schema::table('congressmen', function (Blueprint $table) {
            $table->dropColumn(['trunkline', 'local', 'direct']);
        });
    }
};