<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->string('result', 20)->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as string; narrowing back to the old enum would
        // fail once UNASSIGNED / BLOCKED rows exist.
    }
};