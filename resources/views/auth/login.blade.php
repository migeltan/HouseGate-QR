@extends('layouts.app')
@section('title', 'Sign In')

@section('content')
<div class="flex items-center justify-center min-h-[80vh] px-4">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-xl border border-slate-200 grid grid-cols-1 md:grid-cols-2 overflow-hidden">

        {{-- Left: branding --}}
        <div class="p-10 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <img src="{{ asset('images/hrep-seal.png') }}" alt="House of Representatives Seal" class="h-16 w-16 object-contain">
                    <img src="{{ asset('images/lsb-full-logo.png') }}" alt="INSPIRE Program" class="h-14 object-contain">
                </div>
                <p class="text-blue-800 font-serif text-lg mb-1">Legislative Security Bureau</p>
                <h1 class="text-2xl font-bold text-slate-900">QR Visitor Access System</h1>
            </div>
            <p class="text-sm italic text-slate-500 mt-10">
                "We take pride in belonging to the Office of the Sergeant-at-Arms."
            </p>
        </div>

        {{-- Right: sign-in form --}}
        <div class="p-10 border-t md:border-t-0 md:border-l border-slate-200">
            <h2 class="text-xl font-bold text-slate-900 mb-6">Sign in to your account</h2>

            @if ($errors->any())
                <div class="alert-govt-error mb-4" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4 text-sm">
                @csrf

                <div>
                    <label class="block mb-1 font-medium text-slate-700">HREP ID</label>
                    <input type="text" name="hrep_id" required autofocus
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800"
                        placeholder="LSB-0001" value="{{ old('hrep_id') }}">
                </div>

                <div>
                    <label class="block mb-1 font-medium text-slate-700">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800">
                </div>

                <div>
                    <label class="block mb-1 font-medium text-slate-700">Workstation</label>
                    <select name="role" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800"
                        onchange="document.getElementById('building-field').classList.toggle('hidden', this.value !== 'guard')">
                        <option value="" disabled {{ old('role') ? '' : 'selected' }}>Administrator / Personnel</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrator</option>
                        <option value="guard" {{ old('role') === 'guard' ? 'selected' : '' }}>Building Personnel</option>
                    </select>
                </div>

                <div id="building-field" class="{{ old('role') === 'guard' ? '' : 'hidden' }}">
                    <label class="block mb-1 font-medium text-slate-700">Assigned Building</label>
                    <select name="building_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-800">
                        <option value="">Select building…</option>
                        @foreach ($buildings as $building)
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

                <button type="submit" class="w-full px-4 py-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold tracking-wide">
                    SIGN IN
                </button>
            </form>
        </div>
    </div>
</div>
@endsection