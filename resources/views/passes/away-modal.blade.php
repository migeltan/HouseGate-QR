{{-- Away cards: cards held at another desk after a transfer, and the IDs waiting behind them --}}
@php
    $awayFollow = $awayData['follow'];
    $awayHolding = $awayData['holding'];
@endphp
<div id="awayModal" class="reg-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="away-title">
    <section class="reg-modal" style="max-width: 40rem;">
        <header class="reg-modal-header">
            <i class="fa-solid fa-right-left header-icon"></i>
            <div>
                <p class="reg-eyebrow">Cards &amp; IDs</p>
                <h1 id="away-title">Away cards</h1>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closeAwayModal()">&times;</button>
        </header>

        <main class="reg-modal-body inv-body">
            <p class="inv-intro">When a visitor moves to another building, that building's guard keeps their old card. It must be handed back to its own desk, which then returns the visitor's ID.</p>

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
                            <form method="POST" action="{{ route('passes.receive', $item['pass_id']) }}">
                                @csrf
                                <button type="submit" class="gov-btn-camera"><i class="fa-solid fa-hand-holding"></i> Card received</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('passes.id-returned', $item['registration_id']) }}" onsubmit="return confirm('Hand this ID back to the visitor?')">
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
        </main>
    </section>
</div>