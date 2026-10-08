<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    // ------------------------------------------------------------------
    // Accounts (admin only; routes are inside the `admin` middleware group)
    // ------------------------------------------------------------------

    /** HTML fragment for the Accounts tab. */
    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('hrep_id', 'like', $s));
            })
            ->when(in_array($request->query('role'), ['admin', 'guard'], true), fn ($q) => $q->where('role', $request->query('role')))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('account.users', compact('users'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'hrep_id' => ['required', 'string', 'max:50', 'unique:users,hrep_id'],
            'role' => ['required', Rule::in(['admin', 'guard'])],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create($data);   // 'hashed' cast
        AdminLog::record('account.created', $user->name, "HREP ID {$user->hrep_id} · role {$user->role}");

        return response()->json(['message' => 'Account created.']);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'hrep_id' => ['required', 'string', 'max:50', Rule::unique('users', 'hrep_id')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'guard'])],
        ]);

        // The acting admin is always an active admin, so blocking a self-role-change
        // also means the last remaining admin can never be demoted.
        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return $this->refuse("You can't change your own role.");
        }

        $user->fill($data);
        $changes = collect($user->getDirty())
            ->map(fn ($new, $field) => $field . ': ' . $user->getOriginal($field) . ' → ' . $new)
            ->implode('; ');
        $user->save();

        if ($changes !== '') {
            AdminLog::record('account.updated', $user->name, $changes);
        }

        return response()->json(['message' => $changes !== '' ? 'Account updated.' : 'No changes to save.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate(['password' => ['required', Password::min(8)]]);

        $user->update(['password' => $request->input('password')]);   // 'hashed' cast

        if (! $user->is($request->user())) {
            $this->endSessions($user);
        }

        AdminLog::record('account.password_reset', $user->name);   // never log the password itself

        return response()->json(['message' => 'Password reset.']);
    }

    public function deactivate(Request $request, User $user)
    {
        // Same reasoning as above: this also protects the last remaining admin.
        if ($user->is($request->user())) {
            return $this->refuse("You can't deactivate your own account.");
        }

        $user->is_active = false;
        $user->save();
        $this->endSessions($user);
        AdminLog::record('account.deactivated', $user->name, "HREP ID {$user->hrep_id}");

        return response()->json(['message' => 'Account deactivated.']);
    }

    public function reactivate(User $user)
    {
        $user->is_active = true;
        $user->save();
        AdminLog::record('account.reactivated', $user->name, "HREP ID {$user->hrep_id}");

        return response()->json(['message' => 'Account reactivated.']);
    }

    private function refuse(string $message)
    {
        return response()->json(['message' => $message], 422);
    }

    /** Signs the user out everywhere (database session driver only). */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}