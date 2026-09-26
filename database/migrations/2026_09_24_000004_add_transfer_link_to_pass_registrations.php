<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Workflow 2: a transfer closes the old registration as 'transferred' and the
    // new registration points back at it, so the chain can be walked either way.
    public function up(): void
    {
        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->foreignId('transferred_from_registration_id')
                ->nullable()
                ->after('visitor_pass_id')
                ->constrained('pass_registrations')
                ->nullOnDelete();
        });

        // unassign_reason is an ENUM, and ->change() on enums is unreliable in MySQL,
        // so widen it with a plain ALTER.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE pass_registrations MODIFY unassign_reason "
                . "ENUM('returned','auto_expired','renewed','transferred') NULL"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('pass_registrations')
                ->where('unassign_reason', 'transferred')
                ->update(['unassign_reason' => 'returned']);

            DB::statement(
                "ALTER TABLE pass_registrations MODIFY unassign_reason "
                . "ENUM('returned','auto_expired','renewed') NULL"
            );
        }

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferred_from_registration_id');
        });
    }
};