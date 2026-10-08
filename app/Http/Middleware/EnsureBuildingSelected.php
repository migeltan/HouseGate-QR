<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureBuildingSelected
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // A deactivated account loses access immediately, even mid-session.
        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account has been deactivated.'], 403)
                : redirect()->route('login');
        }

        if ($user && $user->isGuard() && ! session('assigned_building_id')) {
            return redirect()->route('building.select');
        }

        return $next($request);
    }
}