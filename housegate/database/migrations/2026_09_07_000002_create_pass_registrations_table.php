<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_pass_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('id_type');
            $table->string('id_ref');
            $table->string('photo_path')->nullable();
            $table->string('id_photo_path')->nullable();
            $table->string('purpose')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('registered_by')->nullable();
            $table->enum('pass_class', ['day', 'long_term']);
            $table->date('expected_return_date')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->enum('unassign_reason', ['returned', 'auto_expired', 'renewed'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_registrations');
    }
};