{{-- One pass row. Shared by the Passes page (tab2-buildings) and the JSON responses of
     PassController@unassign / @revoke, so both render identical markup.
     Expects: $p (VisitorPass with building + buildings), $allBuildingsCount --}}
@php
    $cardColor = $p->is_multi_building ? 'var(--badge-multi)' : $p->building->color_hex;
    $searchText = strtolower($p->pass_number . ' ' . ($p->visitor_name ?? 'unassigned') . ($p->hold_reason ? ' hold on-hold ' . $p->hold_reason . ' ' . $p->hold_note : ''));
    $status = $p->displayStatus();   // 'hold' when the card is on hold
    $badgeMap = [
        'active'    => ['is-active', 'Active'],
        'available' => ['is-available', 'Available'],
        'expired'   => ['is-expired', 'Expired'],
        'revoked'   => ['is-revoked', 'Revoked'],
        'hold'      => ['is-hold', 'On hold'],
    ];
    [$badgeClass, $badgeLabel] = $badgeMap[$status] ?? ['is-available', 'Available'];
    $holdLabel = $p->hold_reason ? (\App\Models\VisitorPass::HOLD_REASONS[$p->hold_reason] ?? 'Other') : null;
    if ($holdLabel) { $badgeLabel = 'On hold · ' . $holdLabel; }
    $infoPayload = $p->infoPayload();
@endphp
<div class="gov-pass-card" data-pass-id="{{ $p->id }}" data-status="{{ $status }}" data-search="{{ $searchText }}">
    <div class="gov-pass-card-info">
        <span class="gov-pass-card-number">#{{ $p->pass_number }}</span>
        <div class="gov-pass-card-details">
            @if ($p->visitor_name)
                <div class="gov-pass-card-name">{{ $p->visitor_name }}</div>
                @if ($p->pass_class === 'day')
                    <div class="gov-pass-card-meta">1 Day Access</div>
                @else
                    <div class="gov-pass-card-meta">{{ $p->issued_at?->format('n/j/Y') }} - {{ $p->expected_return_date?->format('n/j/Y') }}</div>
                @endif
                @if ($p->is_multi_building)
                    <div class="gov-pass-card-buildings">
                        @if ($p->buildings->isEmpty())
                            Awaiting building assignment
                        @elseif ($p->buildings->count() >= $allBuildingsCount)
                            All Buildings
                        @else
                            {{ $p->buildings->pluck('name')->join(', ') }}
                        @endif
                    </div>
                @endif
            @else
                @if ($p->hold_reason)
                    <span class="gov-pass-card-unassigned">On hold</span>
                    <div class="gov-pass-card-meta">{{ $p->hold_note ?: 'No note' }} · {{ $p->hold_by ?? '—' }}{{ $p->hold_at ? ', ' . $p->hold_at->format('M j, g:i A') : '' }}</div>
                @else
                    <span class="gov-pass-card-unassigned">Unassigned</span>
                @endif
            @endif
        </div>
    </div>

    <div class="gov-pass-card-right">
        <span class="gov-pass-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>

        <div class="gov-pass-card-actions">
            @if ($p->visitor_name)
                <form method="POST" action="{{ route('passes.unassign', $p) }}" data-inplace
                    data-confirm data-confirm-tone="warning"
                    data-confirm-title="Unassign this pass?"
                    data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $p->visitor_name }}"
                    data-confirm-message="The card will be reset and returned to available stock."
                    data-confirm-label="Unassign">
                    @csrf
<button type="submit" class="gov-pass-row-btn is-ghost is-warn"><i class="fa-solid fa-link-slash"></i> Unassign</button>
                </form>
<button type="button" class="gov-pass-row-btn is-ghost" data-qr-url="{{ route('passes.show', $p) }}" data-pass-number="{{ $p->pass_number }}" onclick="openPassQrModal(this)"><i class="fa-solid fa-qrcode"></i> View QR</button>
                @if ($p->status !== 'revoked')
                <form method="POST" action="{{ route('passes.revoke', $p) }}" data-inplace
                    data-confirm data-confirm-tone="danger"
                    data-confirm-title="Revoke this pass?"
                    data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $p->visitor_name }}"
                    data-confirm-message="The visitor will be denied on their next scan."
                    data-confirm-label="Revoke pass">
                    @csrf
<button type="submit" class="gov-pass-row-btn is-ghost is-danger"><i class="fa-solid fa-ban"></i> Revoke</button>
                </form>
                @endif
                <button type="button" class="gov-pass-row-btn is-ghost" data-pass-number="{{ $p->pass_number }}" onclick='openPassInfoModal(@json($infoPayload))'><i class="fa-solid fa-circle-info"></i> View Info</button>
            @else
                <button type="button" class="gov-pass-row-btn is-ghost" data-qr-url="{{ route('passes.show', $p) }}" data-pass-number="{{ $p->pass_number }}" onclick="openPassQrModal(this)"><i class="fa-solid fa-qrcode"></i> View QR</button>
                @if ($p->hold_reason)
                <form method="POST" action="{{ route('passes.release-hold', $p) }}" data-inplace
                    data-confirm data-confirm-tone="warning"
                    data-confirm-title="Put this card back in stock?"
                    data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $holdLabel }}"
                    data-confirm-message="It can be issued again from the next registration."
                    data-confirm-label="Back in stock">
                    @csrf
                    <button type="submit" class="gov-pass-row-btn is-ghost is-warn"><i class="fa-solid fa-rotate-left"></i> Back in stock</button>
                </form>
                @elseif ($p->status === 'available')
                <button type="button" class="gov-pass-row-btn is-ghost is-warn" data-hold-url="{{ route('passes.hold', $p) }}" data-pass-number="{{ $p->pass_number }}" onclick="openHoldModal(this)"><i class="fa-solid fa-flag"></i> Hold</button>
                @endif
            @endif
        </div>
    </div>
</div>