<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('scan_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('visitor_pass_id')->nullable()->constrained()->nullOnDelete();
        $table->string('qr_token_scanned');
        $table->foreignId('scanned_building_id')->constrained('buildings')->cascadeOnDelete();
        $table->string('visitor_name_snapshot')->nullable();
        $table->string('pass_number_snapshot')->nullable();
        $table->string('authorized_building_snapshot')->nullable();
        $table->enum('result', ['AUTHORIZED', 'UNAUTHORIZED', 'INVALID', 'EXPIRED', 'REVOKED']);
        $table->string('reason');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
