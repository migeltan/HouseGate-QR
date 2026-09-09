<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureBuildingSelected
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isGuard() && ! session('assigned_building_id')) {
            return redirect()->route('building.select');
        }

        return $next($request);
    }
}