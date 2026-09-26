<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Optional staff/assistant to contact at the congressman's office, for
    // visitors sponsored by an assistant rather than the congressman.
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('office_to_visit');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('office_other');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });
    }
};