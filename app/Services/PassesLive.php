<?php

namespace App\Services;

use App\Models\Building;
use App\Models\VisitorPass;

/**
 * Live-update snapshot for the Passes grid: per-building card counts plus a signature of them.
 * Scoped exactly like PassController@index (guards: their own building's single-building passes only).
 */
class PassesLive
{
    /**
     * @return array{sig: string, counts: array<int, array{available: int, active: int, inactive: int, total: int}>}
     */
    public static function snapshot(): array
    {
        $guard = (bool) request()->user()?->isGuard();
        $desk = session('assigned_building_id');

        $scope = function ($query) use ($guard, $desk) {
            $query->when($guard, fn ($q) => $q
                ->where('building_id', $desk)
                ->where('is_multi_building', false));
        };

        $rows = VisitorPass::query()
            ->selectRaw('building_id, is_multi_building, status, COUNT(*) as n')
            ->tap($scope)
            ->groupBy('building_id', 'is_multi_building', 'status')
            ->toBase()
            ->get();

        // A registration and an unassign in the same instant can leave the counts equal;
        // the newest issue time still moves, so the page refreshes.
        $issued = VisitorPass::query()->tap($scope)->max('issued_at');

        $buildings = Building::query()
            ->when($guard, fn ($q) => $q->where('id', $desk))
            ->get(['id', 'code']);

        $counts = [];
        foreach ($buildings as $b) {
            $by = $rows
                ->filter(fn ($r) => (int) $r->building_id === (int) $b->id && (bool) $r->is_multi_building === ($b->code === 'NG'))
                ->pluck('n', 'status');

            $counts[$b->id] = [
                'available' => (int) ($by['available'] ?? 0),
                'active'    => (int) ($by['active'] ?? 0),
                'inactive'  => (int) (($by['expired'] ?? 0) + ($by['revoked'] ?? 0)),
                'total'     => (int) $by->sum(),
            ];
        }

        return ['sig' => md5(json_encode($counts) . '|' . $issued), 'counts' => $counts];
    }
}