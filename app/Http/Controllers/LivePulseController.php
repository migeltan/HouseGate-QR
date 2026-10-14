<?php

namespace App\Http\Controllers;

use App\Services\AwayCards;
use App\Services\PassesLive;
use Illuminate\Http\Request;

class LivePulseController extends Controller
{
    /**
     * One cheap poll for every live region on a page. The client sends the signature it currently shows
     * (?away=...). A region whose signature still matches costs a few bytes; a changed one comes back with
     * fresh HTML. Regions are opt-in: nothing is computed for a region the page didn't ask about.
     * Each region is scoped to the signed-in user exactly like the page itself (guards: their desk only).
     */
    public function pulse(Request $request)
    {
        $out = [];

        if ($request->has('away')) {
            $away = AwayCards::forCurrentUser();
            $sig = AwayCards::signature($away);

            $out['away'] = $sig === (string) $request->query('away')
                ? ['changed' => false]
                : [
                    'changed' => true,
                    'sig' => $sig,
                    'html' => view('passes.away-list', ['awayData' => $away])->render(),
                    'count' => $away['count'],
                    'overdue' => $away['overdue'],
                ];
        }

        if ($request->has('logs')) {
            $sig = app(LogController::class)->liveSignature($request);

            $out['logs'] = $sig === (string) $request->query('logs')
                ? ['changed' => false]
                : ['changed' => true, 'sig' => $sig];
        }

        if ($request->has('passes')) {
            $snap = PassesLive::snapshot();

            $out['passes'] = $snap['sig'] === (string) $request->query('passes')
                ? ['changed' => false]
                : ['changed' => true, 'sig' => $snap['sig'], 'counts' => $snap['counts']];
        }

        return response()->json($out)->header('Cache-Control', 'no-store');
    }
}