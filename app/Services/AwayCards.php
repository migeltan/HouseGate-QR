<?php

namespace App\Services;

use App\Models\PassRegistration;

/**
 * Cards that left their home desk during a transfer, and the surrendered IDs still waiting
 * behind them. Guards see their own desk; admins see everything.
 */
class AwayCards
{
    /** A card (and its ID) still away after this many hours is flagged as overdue. */
    public const OVERDUE_HOURS = 12;

    public static function forCurrentUser(): array
    {
        $user = request()->user();
        $desk = $user && $user->isGuard() ? (int) session('assigned_building_id') : null;

        $registrations = PassRegistration::with(['visitorPass.building', 'visitorPass.holder', 'idHeldAt'])
            ->where('unassign_reason', 'transferred')
            ->whereNull('id_returned_at')
            ->whereNotNull('id_held_building_id')
            ->orderBy('unassigned_at')
            ->get();

        $follow = [];
        $holding = [];

        foreach ($registrations as $r) {
            $pass = $r->visitorPass;
            if (! $pass) {
                continue;
            }

            $isAway = $pass->holder_building_id !== null
                && (int) $pass->holder_building_id !== (int) $pass->building_id;
            $hours = $r->unassigned_at ? abs($r->unassigned_at->diffInHours(now())) : 0;

            $item = [
                'registration_id' => $r->id,
                'pass_id' => $pass->id,
                'pass_number' => $pass->pass_number,
                'home' => $pass->is_multi_building ? 'North Gate Access' : optional($pass->building)->name,
                'holder' => optional($pass->holder)->name,
                'visitor' => $r->visitor_name,
                'id_label' => trim(($r->id_type ?? '') . ($r->id_ref ? ' · ' . $r->id_ref : '')) ?: 'No ID details',
                'id_held' => optional($r->idHeldAt)->name,
                'since' => $r->unassigned_at,
                'state' => $isAway ? 'away' : 'back',
                'overdue' => $hours >= self::OVERDUE_HOURS,
            ];

            // Items whose ID sits at this desk (admins: everything).
            if ($desk === null || (int) $r->id_held_building_id === $desk) {
                $follow[] = $item;
            }
            // Cards this desk is physically holding for someone else.
            if ($desk !== null && $isAway && (int) $pass->holder_building_id === $desk) {
                $holding[] = $item;
            }
        }

        $all = array_merge($follow, $holding);

        return [
            'follow' => $follow,
            'holding' => $holding,
            'count' => count($all),
            'overdue' => count(array_filter($all, fn ($i) => $i['overdue'])),
        ];
    }
}