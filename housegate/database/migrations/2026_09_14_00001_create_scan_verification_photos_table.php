<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_verification_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_pass_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_verification_photos');
    }
};