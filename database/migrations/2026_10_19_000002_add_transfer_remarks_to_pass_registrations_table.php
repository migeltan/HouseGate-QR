<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('transfer_remarks', 255)->nullable()->after('others_place');
        });
    }

    public function down(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropColumn('transfer_remarks');
        });
    }
};