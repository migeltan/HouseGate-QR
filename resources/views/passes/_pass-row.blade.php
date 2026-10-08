{{-- One pass row. Shared by the Passes page (tab2-buildings) and the JSON responses of
     PassController@unassign / @revoke, so both render identical markup.
     Expects: $p (VisitorPass with building + buildings), $allBuildingsCount --}}
@php
    $cardColor = $p->is_multi_building ? 'var(--badge-multi)' : $p->building->color_hex;
    $searchText = strtolower($p->pass_number . ' ' . ($p->visitor_name ?? 'unassigned'));
    $badgeMap = [
        'active'    => ['is-active', 'Active'],
        'available' => ['is-available', 'Available'],
        'expired'   => ['is-expired', 'Expired'],
        'revoked'   => ['is-revoked', 'Revoked'],
    ];
    [$badgeClass, $badgeLabel] = $badgeMap[$p->status] ?? ['is-available', 'Available'];
    $infoPayload = [
        'pass_number' => $p->pass_number,
        'status' => $p->status,
        'building' => $p->building->name,
        'qr_url' => route('passes.show', $p),
        'visitor_name' => $p->visitor_name,
        'gender' => $p->gender,
        'contact_no' => $p->contact_no,
        'visitor_email' => $p->visitor_email,
        'id_type' => $p->id_type,
        'id_ref' => $p->id_ref,
        'office_to_visit' => $p->office_to_visit,
        'contact_person' => $p->contact_person,
        'purpose' => $p->purpose,
        'vehicle' => $p->vehicle,
        'registered_by' => $p->registered_by,
        'pass_class' => $p->pass_class,
        'issued_at' => $p->issued_at?->format('M j, Y g:i A'),
        'expected_return_date' => $p->expected_return_date?->format('M j, Y'),
        'photo_url' => $p->photo_path ? asset('storage/' . $p->photo_path) : null,
        'id_photo_url' => $p->id_photo_path ? asset('storage/' . $p->id_photo_path) : null,
    ];
@endphp
<div class="gov-pass-card" data-pass-id="{{ $p->id }}" data-status="{{ $p->status }}" data-search="{{ $searchText }}">
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
                <span class="gov-pass-card-unassigned">Unassigned</span>
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
            @endif
        </div>
    </div>
</div>