@extends('layouts.app')
@section('title', 'Select Your Building')

@section('content')
<div class="flex items-center justify-center min-h-[70vh]">
    <div class="modal-govt-panel shadow-2xl max-w-sm w-full p-6 space-y-4">
        <h3 class="font-bold text-slate-800 text-lg">Which building are you stationed at?</h3>
        <p class="text-xs text-slate-500">This locks your Scanner, Passes, and Logs access for this session — you won't be able to scan or issue passes for any other building.</p>

        <form method="POST" action="{{ route('building.select.store') }}" class="space-y-3 text-xs">
            @csrf
            <select name="building_id" required class="w-full px-3 py-2 bg-slate-50">
                <option value="">Select building</option>
                @foreach ($buildings as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="w-full px-4 py-2 rounded-lg btn-govt-cta">Confirm & Continue</button>
        </form>
    </div>
</div>
@endsection