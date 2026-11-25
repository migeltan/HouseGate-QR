<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            // Why an admin/guard revoked the card. Cleared again on unassign.
            $table->string('revoke_reason', 255)->nullable()->after('hold_by');
            $table->timestamp('revoked_at')->nullable()->after('revoke_reason');
            $table->string('revoked_by')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn(['revoke_reason', 'revoked_at', 'revoked_by']);
        });
    }
};