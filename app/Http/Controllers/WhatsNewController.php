<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WhatsNewController extends Controller
{
    /**
     * Marks the current "What's new" update as seen for the signed-in user.
     * The id is read from config, never from the request, so the client can't mark anything else as seen.
     */
    public function dismiss(Request $request)
    {
        $request->user()->forceFill(['last_seen_update' => config('whatsnew.id')])->save();

        return response()->noContent();
    }
}