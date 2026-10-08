<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $actions = $request->user()->isAdmin()
            ? AdminLog::query()->distinct()->orderBy('action')->pluck('action')
            : collect();

        return view('account.edit', compact('actions'));
    }

    /** HTML fragment for the Admin Log tab; the page swaps it in without reloading. */
    public function log(Request $request)
    {
        $logs = AdminLog::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('actor_name', 'like', $s)
                    ->orWhere('subject', 'like', $s)
                    ->orWhere('details', 'like', $s));
            })
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('account.log', compact('logs'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);
        AdminLog::record('account.profile_updated', $user->name);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Profile updated.', 'name' => $user->name, 'email' => $user->email]);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $request->input('password')]);   // 'hashed' cast
        AdminLog::record('account.password_changed', $request->user()->name);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password changed.']);
        }

        return back()->with('success', 'Password changed.');
    }
}