<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Retired. HREP IDs are 5 digits, so this former "keep the last 4 digits" cleanup would have
     * truncated real IDs. It is kept as a no-op so databases that already ran it stay consistent.
     * See 2026_11_11_000001_pad_hrep_ids_to_five_digits.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};