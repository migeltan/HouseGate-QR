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
    Schema::create('visitor_passes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('building_id')->constrained()->cascadeOnDelete();
        $table->string('pass_number', 4); // 0001 - 0005
        $table->string('qr_token')->unique();
        $table->string('visitor_name')->nullable();
        $table->string('id_ref')->nullable();
        $table->string('purpose')->nullable();
        $table->enum('status', ['available', 'active', 'expired', 'revoked'])->default('available');
        $table->timestamp('issued_at')->nullable();
        $table->timestamps();

        $table->unique(['building_id', 'pass_number']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_passes');
    }
};
