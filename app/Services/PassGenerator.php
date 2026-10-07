<?php

namespace App\Services;

use App\Models\Building;
use App\Models\VisitorPass;

class PassGenerator
{
    public const PER_BUILDING = 250;

    /** HOR-20TH-NW-0007-9F3A21C4 — the suffix is an HMAC, so tokens can't be guessed from a pass number. */
    public static function token(Building $building, string $number): string
    {
        $sig = strtoupper(substr(hash_hmac('sha256', "{$building->code}-{$number}", (string) config('app.key')), 0, 8));
        return "HOR-20TH-{$building->code}-{$number}-{$sig}";
    }

    /** @return array{created:int, skipped:int} */
    public function generate(Building $building, int $from, int $to): array
    {
        $existing = array_flip(
            VisitorPass::where('building_id', $building->id)->pluck('pass_number')->all()
        );
        $isMulti = $building->code === 'NG'; // North Gate only ever holds multi-building passes
        $created = $skipped = 0;

        for ($n = $from; $n <= $to; $n++) {
            $number = str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            if (isset($existing[$number])) {
                $skipped++;
                continue;
            }

            VisitorPass::create([
                'building_id'       => $building->id,
                'pass_number'       => $number,
                'qr_token'          => self::token($building, $number),
                'status'            => 'available',
                'is_multi_building' => $isMulti,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}