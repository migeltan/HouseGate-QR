<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('visitor_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->string('contact_no')->nullable()->after('gender');
            $table->string('office_to_visit')->nullable()->after('purpose');
            $table->string('vehicle')->nullable()->after('office_to_visit');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('visitor_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->string('contact_no')->nullable()->after('gender');
            $table->string('office_to_visit')->nullable()->after('purpose');
            $table->string('vehicle')->nullable()->after('office_to_visit');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'gender', 'contact_no', 'office_to_visit', 'vehicle']);
        });
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'gender', 'contact_no', 'office_to_visit', 'vehicle']);
        });
    }
};