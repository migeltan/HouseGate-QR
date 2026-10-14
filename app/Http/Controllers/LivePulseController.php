<?php

namespace App\Http\Controllers;

use App\Services\AwayCards;
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

        return response()->json($out)->header('Cache-Control', 'no-store');
    }
}