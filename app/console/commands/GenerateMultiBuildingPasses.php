<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\VisitorPass;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenerateMultiBuildingPasses extends Command
{
    /**
     * php artisan passes:seed-multi
     * php artisan passes:seed-multi 10
     */
    protected $signature = 'passes:seed-multi {count=5}';

    protected $description = 'Generate sample Multiple Access (multi-building) visitor passes for testing scanner validation.';

    public function handle(): int
    {
        // All real buildings are now fair game as the pool of "authorized
        // buildings" a multi-pass can grant access to — the old dedicated
        // MULTI building was retired in favor of homing multi-passes under
        // North Gate (NG), so there's no longer a placeholder building to
        // exclude here.
        $buildings = Building::all();

        if ($buildings->count() < 2) {
            $this->error('Need at least 2 buildings in the database before generating multi-building passes.');
            return self::FAILURE;
        }

        $multiBuildingId = Building::where('code', 'NG')->value('id');

        if (! $multiBuildingId) {
            $this->error('No North Gate building found (code = NG). Create it first.');
            return self::FAILURE;
        }

        $count = (int) $this->argument('count');
        $buildingsPerPass = min(3, $buildings->count());

        // pass_number is sized to match the existing 4-digit convention
        // (e.g. "0001"), unlike qr_token which tolerates longer strings.
        // Scoped to North Gate's building_id specifically, since that's
        // what the visitor_passes_building_id_pass_number_unique
        // constraint actually enforces uniqueness against.
        $startingNumber = (int) (VisitorPass::query()
    ->where('building_id', $multiBuildingId)
    ->where('is_multi_building', true)
    ->selectRaw('MAX(CAST(pass_number AS UNSIGNED)) as max_num')
    ->first()
    ?->max_num ?? 0);

        for ($i = 1; $i <= $count; $i++) {
            $number = $startingNumber + $i;
            $passNumber = str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $qrToken = "PASS-MULTI-{$passNumber}";

            /** @var Collection $assigned */
            $assigned = $buildings->random($buildingsPerPass);
            if (! $assigned instanceof Collection) {
                $assigned = collect([$assigned]);
            }

            $pass = VisitorPass::create([
                'building_id' => $multiBuildingId,
                'pass_number' => $passNumber,
                'qr_token' => $qrToken,
                'status' => 'available',
                'is_multi_building' => true,
            ]);

            $this->info("Created pass #{$passNumber} ({$qrToken}) — available");
        }

        return self::SUCCESS;
    }
}