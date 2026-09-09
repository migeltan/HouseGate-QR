<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'buildings' => Building::orderBy('name')->get(),
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'hrep_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'in:admin,guard'],
            'building_id' => ['required_if:role,guard', 'nullable', 'exists:buildings,id'],
        ]);

        if (! Auth::attempt([
            'hrep_id' => $validated['hrep_id'],
            'password' => $validated['password'],
        ])) {
            return back()->withErrors(['hrep_id' => 'Invalid HREP ID or password.'])->onlyInput('hrep_id');
        }

        $user = Auth::user();

        if ($user->role !== $validated['role']) {
            Auth::logout();
            return back()->withErrors(['role' => 'Selected role does not match this account.'])->onlyInput('hrep_id');
        }

        $request->session()->regenerate();

        if ($user->isGuard()) {
            session(['assigned_building_id' => (int) $validated['building_id']]);
        }

        return redirect()->route('scanner.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session()->forget('assigned_building_id');

        return redirect()->route('login');
    }
}