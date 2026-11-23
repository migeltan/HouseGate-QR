<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WhatsNewController extends Controller
{
    /**
     * Closing the popup hides it for the rest of this sign-in (session flag).
     * Only when "Don't show this again" is ticked is the update recorded on the user for good.
     * The id is read from config, never from the request, so the client can't mark anything else as seen.
     */
    public function dismiss(Request $request)
    {
        $request->session()->put('whatsnew_closed', true);

        if ($request->boolean('forever')) {
            $request->user()->forceFill(['last_seen_update' => config('whatsnew.id')])->save();
        }

        return response()->noContent();
    }
}