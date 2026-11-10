<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'buildings' => Building::where('code', '!=', 'NG')->orderBy('name')->get(),
        ]);
    }

    public function login(Request $request)
    {
        // NEW: trim first so "0012 " can't dodge the per-ID lockout (MySQL ignores trailing spaces when comparing).
        $request->merge(['hrep_id' => trim((string) $request->input('hrep_id'))]);

        $validated = $request->validate([
            'hrep_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'in:admin,guard'],
            'building_id' => ['required_if:role,guard', 'nullable', 'exists:buildings,id'],
        ], [
            // Laravel's defaults read "The building id field is required when role is guard."
            'hrep_id.required' => 'Enter your HREP ID.',
            'password.required' => 'Enter your password.',
            'role.required' => 'Select your workstation.',
            'building_id.required_if' => 'Select your assigned building.',
            'building_id.exists' => 'That building is not available. Please choose again.',
        ]);

        // Brute-force guard: 5 failed sign-ins per 5 minutes for the same HREP ID from the same IP.
        // Keyed on ID + IP (not IP alone) so guards sharing one office IP cannot lock each other out.
        // Hashed so an oversized input can never exceed the cache key length.
        $throttleKey = 'login:'.sha1(Str::lower($validated['hrep_id']).'|'.$request->ip());
        $ipKey = 'login-ip:'.sha1((string) $request->ip());   // one IP trying many IDs (IDs are only 4 digits)

        if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            $minutes = (int) ceil(max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($ipKey)) / 60);

            return back()->withErrors(['hrep_id' => "Too many failed attempts. Try again in {$minutes} minute(s)."])->onlyInput('hrep_id', 'role', 'building_id');
        }

        if (! Auth::attempt([
            'hrep_id' => $validated['hrep_id'],
            'password' => $validated['password'],
        ])) {
            RateLimiter::hit($throttleKey, 300);
            RateLimiter::hit($ipKey, 300);

            return back()->withErrors(['hrep_id' => 'Invalid HREP ID or password.'])->onlyInput('hrep_id', 'role', 'building_id');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            return back()->withErrors(['hrep_id' => 'This account has been deactivated. Please contact an administrator.'])->onlyInput('hrep_id', 'role', 'building_id');
        }

        if ($user->role !== $validated['role']) {
            RateLimiter::hit($throttleKey, 300);
            RateLimiter::hit($ipKey, 300);
            Auth::logout();
            return back()->withErrors(['role' => 'Selected role does not match this account.'])->onlyInput('hrep_id', 'role', 'building_id');
        }

        // "Remember me": only after every check above has passed, so a rejected sign-in never gets a long-lived cookie.
        // Before this the checkbox was posted but never read, so the session always expired after SESSION_LIFETIME.
        if ($request->boolean('remember')) {
            Auth::login($user, true);
        }

        RateLimiter::clear($throttleKey);

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