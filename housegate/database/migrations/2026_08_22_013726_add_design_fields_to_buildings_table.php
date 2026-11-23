<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('buildings', function (Blueprint $table) {
        $table->string('template_image')->nullable();
        $table->string('qr_color_hex', 7)->nullable(); // darker shade for scannability
    });
}

public function down(): void
{
    Schema::table('buildings', function (Blueprint $table) {
        $table->dropColumn(['template_image', 'qr_color_hex']);
    });
}
};
