{{-- Away cards list. Re-rendered by PassController::awayResponse() after each action. --}}
@php
    $awayFollow = $awayData['follow'];
    $awayHolding = $awayData['holding'];
@endphp
<div id="awayList">
    @if (! $awayFollow && ! $awayHolding)
        <p class="away-empty"><i class="fa-solid fa-circle-check"></i> Nothing to follow up. Every card is at its own desk.</p>
    @endif

    @if ($awayFollow)
        <h2 class="away-title">{{ auth()->user()->isAdmin() ? 'All cards and IDs waiting' : 'Waiting for your desk' }}</h2>
        @foreach ($awayFollow as $item)
            <div class="away-row {{ $item['overdue'] ? 'is-overdue' : '' }}">
                <div class="away-main">
                    <b>{{ $item['visitor'] }}</b>
                    <span>{{ $item['home'] }} card #{{ $item['pass_number'] }} &middot; ID: {{ $item['id_label'] }}</span>
                    <small class="{{ $item['overdue'] ? 'is-late' : '' }}">
                        @if ($item['state'] === 'away')
                            Card is at {{ $item['holder'] }} since {{ $item['since']?->diffForHumans() }}
                        @else
                            Card is back. The ID can be handed back.
                        @endif
                        @if ($item['overdue']) &middot; Overdue @endif
                    </small>
                </div>
                @if ($item['state'] === 'away')
                    <form method="POST" action="{{ route('passes.receive', $item['pass_id']) }}" data-away>
                        @csrf
                        <button type="submit" class="gov-btn-camera"><i class="fa-solid fa-hand-holding"></i> Card received</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('passes.id-returned', $item['registration_id']) }}"
                          data-away data-away-confirm
                          data-away-visitor="{{ $item['visitor'] }}"
                          data-away-id-label="{{ $item['id_label'] }}">
                        @csrf
                        <button type="submit" class="gov-btn-camera"><i class="fa-solid fa-id-card"></i> ID handed back</button>
                    </form>
                @endif
            </div>
        @endforeach
    @endif

    @if ($awayHolding)
        <h2 class="away-title">Cards your desk is holding for other desks</h2>
        @foreach ($awayHolding as $item)
            <div class="away-row {{ $item['overdue'] ? 'is-overdue' : '' }}">
                <div class="away-main">
                    <b>{{ $item['home'] }} card #{{ $item['pass_number'] }}</b>
                    <span>Hand it back to the {{ $item['home'] }} desk &middot; visitor: {{ $item['visitor'] }}</span>
                    <small class="{{ $item['overdue'] ? 'is-late' : '' }}">
                        Held since {{ $item['since']?->diffForHumans() }}@if ($item['overdue']) &middot; Overdue @endif
                    </small>
                </div>
            </div>
        @endforeach
    @endif
</div>