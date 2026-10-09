@extends('layouts.guest')
@section('title', 'Sign In')

@section('content')
<div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-xl border border-slate-200 grid grid-cols-1 md:grid-cols-2 overflow-hidden">

        {{-- Left: branding --}}
        <div class="p-10 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 mb-6">
                    @if (file_exists(public_path('images/hrep-seal.png')))
                        <img src="{{ asset('images/hrep-seal.png') }}" alt="House of Representatives Seal" class="h-20 w-20 object-contain">
                    @else
                        <div class="h-20 w-20 rounded-full border-2 border-dashed border-slate-300 flex items-center justify-center text-[9px] font-body font-bold tracking-wide text-slate-400">
                            SEAL
                        </div>
                    @endif

                    @if (file_exists(public_path('images/inspire-logo.png')))
                        <img src="{{ asset('images/inspire-logo.png') }}" alt="INSPIRE Program" class="h-20 object-contain">
                    @else
                        <div class="h-20 px-3 flex items-center border-2 border-dashed border-slate-300 rounded text-[9px] font-body font-bold tracking-wide text-slate-400">
                            INSPIRE LOGO
                        </div>
                    @endif
                </div>

                <p class="font-heading text-lg font-semibold mb-1" style="color:#245BA6;">Legislative Security Bureau</p>
                <h1 class="font-body font-black text-2xl text-slate-900 leading-snug">HouseGate QR:<br>Visitor Access System</h1>
            </div>

            <p class="font-heading italic text-sm text-slate-500 mt-10 leading-relaxed">
                "We take pride in belonging to the Office of the Sergeant-at-Arms."
            </p>
        </div>

        {{-- Right: sign-in form --}}
        <div class="p-10 border-t md:border-t-0 md:border-l border-slate-200">
            <h2 class="font-body font-bold text-xl text-slate-900 mb-6">Sign in to your account</h2>

            @if ($errors->any())
                {{-- .alert-govt-error lives in theme-govt.css, which this layout never loads, so it showed as plain text --}}
                <div class="login-alert" role="alert">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="font-body space-y-4 text-sm">
                @csrf

                <div>
                    <label class="block mb-1 font-bold text-slate-700">HREP ID</label>
                    <input type="text" name="hrep_id" required {{ $errors->has('hrep_id') ? '' : 'autofocus' }}
                        class="login-input w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800 {{ $errors->has('hrep_id') ? 'has-error' : '' }}"
                        placeholder="LSB-0001" value="{{ old('hrep_id') }}">
                </div>

                <div>
                    <label class="block mb-1 font-bold text-slate-700">Password</label>
                    {{-- after a failed sign-in the HREP ID is kept, so put the cursor here --}}
                    <input type="password" name="password" required {{ $errors->has('hrep_id') ? 'autofocus' : '' }}
                        class="login-input w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800 {{ $errors->has('hrep_id') || $errors->has('password') ? 'has-error' : '' }}">
                </div>
                <div>
                    <label class="block mb-1 font-bold text-slate-700">Workstation</label>
                    <select name="role" required
                        class="login-input w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800 {{ $errors->has('role') ? 'has-error' : '' }}">
                        <option value="" disabled {{ old('role') ? '' : 'selected' }}>Administrator / Personnel</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrator</option>
                        <option value="guard" {{ old('role') === 'guard' ? 'selected' : '' }}>Building Personnel</option>
                    </select>
                </div>

                {{-- Always rendered (dimmed + disabled unless Building Personnel is chosen), so picking a role never resizes the card --}}
                <div id="building-field" class="login-building {{ old('role') === 'guard' ? '' : 'is-off' }}">
                    <label class="block mb-1 font-bold text-slate-700">Assigned Building <span class="login-hint">· Building Personnel only</span></label>
                    <select name="building_id" {{ old('role') === 'guard' ? '' : 'disabled' }}
                        class="login-input w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800 {{ $errors->has('building_id') ? 'has-error' : '' }}">
                        <option value="">Select building…</option>
                        @foreach ($buildings as $building)
                            @continue($building->code === 'NG')
                            <option value="{{ $building->id }}" {{ (string) old('building_id') === (string) $building->id ? 'selected' : '' }}>
                                {{ $building->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="rounded border-slate-300">
                    <label for="remember" class="text-slate-600">Remember me</label>
                </div>

                <button type="submit" class="w-full px-4 py-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold tracking-wide">
                    SIGN IN
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Building is only editable for Building Personnel. The field never leaves the layout, so nothing jumps.
(function () {
    const role = document.querySelector('select[name=role]');
    const field = document.getElementById('building-field');
    const building = field.querySelector('select');
    const sync = () => {
        const on = role.value === 'guard';
        field.classList.toggle('is-off', !on);
        building.disabled = !on;   // a disabled select is not submitted, which is fine for administrators
    };
    role.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);   // back/forward cache can restore the role without firing 'change'
    sync();
})();
</script>
@endsection