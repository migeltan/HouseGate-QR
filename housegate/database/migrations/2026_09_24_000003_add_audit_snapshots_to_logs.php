<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // scan_logs are snapshots by design, so the contact person is copied in at scan time.
    // pass_registrations needs the destination building(s) frozen at registration: a
    // North Gate (multi-building) pass has its pass_building links wiped on unassign,
    // so without this the history would lose which buildings a visitor was cleared for.
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->string('contact_person_snapshot')->nullable()->after('authorized_building_snapshot');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->string('buildings_snapshot')->nullable()->after('contact_person');
        });

        // Backfill single-building registrations: their destination is the card's home
        // building, which never changes. Multi-building history can't be recovered.
        DB::statement("
            UPDATE pass_registrations
            SET buildings_snapshot = (
                SELECT b.name
                FROM visitor_passes vp
                JOIN buildings b ON b.id = vp.building_id
                WHERE vp.id = pass_registrations.visitor_pass_id AND vp.is_multi_building = 0
            )
            WHERE buildings_snapshot IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn('contact_person_snapshot');
        });

        Schema::table('pass_registrations', function (Blueprint $table) {
            $table->dropColumn('buildings_snapshot');
        });
    }
};