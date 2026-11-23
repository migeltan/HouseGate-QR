<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->enum('pass_class', ['day', 'long_term'])->default('day')->after('status');
            $table->date('expected_return_date')->nullable()->after('pass_class');
            $table->string('visitor_email')->nullable()->after('expected_return_date');
            $table->string('id_photo_path')->nullable()->after('photo_path');
            $table->string('registered_by')->nullable()->after('id_photo_path');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('last_egress_at')->nullable();
            $table->date('egress_reminder_sent_on')->nullable();
            $table->date('expiry_reminder_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn([
                'pass_class', 'expected_return_date', 'visitor_email',
                'id_photo_path', 'registered_by', 'checked_in_at',
                'last_egress_at', 'egress_reminder_sent_on', 'expiry_reminder_sent_on',
            ]);
        });
    }
};