{{-- TAB 2 - BUILDINGS (legend + building grid + per-building passes modal + pass info modal)
     Script for this tab is at the bottom of this file.
     Expects: $displayBuildings, $buildings, $passes --}}
@php
    // Maps each building code to its image filename in public/images/buildings/.
    // SWA has no photo yet — falls back to RVM's until one is supplied.
    $buildingImages = [
        'NW'    => 'northwing.png',
        'SW'    => 'southwing.png',
        'RVM'   => 'rvm.png',
        'NG'    => 'northgate.png',
        'MB'    => 'main.png',
        'SWA'   => 'swa.jpg',
    ];

    $buildingColorNames = [
        'MB'  => 'Blue',
        'NG'  => 'Pink',
        'NW'  => 'Red',
        'SW'  => 'Orange',
        'RVM' => 'Green',
        'SWA' => 'Yellow',
    ];
@endphp



<div class="gov-legend">
    <span class="gov-legend-item"><span class="gov-legend-dot is-active"></span> Active Passes</span>
    <span class="gov-legend-item"><span class="gov-legend-dot is-available"></span> Available Passes</span>
    <span class="gov-legend-item"><span class="gov-legend-dot is-inactive"></span> Inactive Passes</span>
</div>

{{-- Building grid — click a building to view/manage its passes. When the
     total count is odd, the last card spans both columns and is capped to
     half-width + centered so it doesn't sit awkwardly alone on the left.
     Subtext shows live active/available counts, and cards lift on hover
     to signal clickability. --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach ($displayBuildings as $b)
        @php
            // North Gate's own pool must only ever contain multi-building passes;
            // any single-building pass homed there is stale legacy data and is
            // deliberately excluded rather than counted.
            $buildingPasses = $passes->where('building_id', $b->id)
                ->where('is_multi_building', $b->code === 'NG');
            $activeCount = $buildingPasses->where('status', 'active')->count();
            $availableCount = $buildingPasses->where('status', 'available')->count();
            $inactiveCount = $buildingPasses->whereIn('status', ['expired', 'revoked'])->count();
        @endphp
        <button type="button" onclick="openBuildingModal({{ $b->id }}, '{{ $b->name }}', '{{ $buildingColorNames[$b->code] ?? '' }}')"
            class="gov-building-card {{ $loop->last && $loop->count % 2 !== 0 ? 'md:col-span-2 md:max-w-[calc(50%-0.5rem)] md:mx-auto' : '' }}">
            <div class="gov-building-card-info">
                <h3>{{ $b->name }}</h3>
                <p class="is-active"><b>{{ $activeCount }}</b> Active Passes</p>
                <p class="is-available"><b>{{ $availableCount }}</b> Available Passes</p>
                <p class="is-inactive" title="Expired or revoked"><b>{{ $inactiveCount }}</b> Inactive Passes</p>
            </div>
            <div class="gov-building-card-photo">
                <img src="{{ asset('images/buildings/' . ($buildingImages[$b->code] ?? 'main.png')) }}"
                     alt="{{ $b->name }}">
            </div>
        </button>
    @endforeach
</div>

{{-- Per-building passes modal --}}
<div id="buildingPassesModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="modal-govt-panel rounded-2xl shadow-2xl max-w-5xl w-full max-h-[85vh] overflow-hidden flex flex-col bg-white">
        <div class="p-6 pb-4 flex-shrink-0 border-b border-slate-100">
            <div class="flex justify-between items-start gap-4">
                <div class="flex items-center gap-3">
                    <span id="buildingModalSwatch" class="gov-pass-modal-swatch"></span>
                    <div>
                        <span class="gov-eyebrow">Visitor Passes</span>
                        <h3 id="buildingModalTitle" class="gov-pass-modal-title"></h3>
                    </div>
                </div>
                <button type="button" onclick="closeBuildingModal()"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none flex-shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <div class="flex gap-2">
                    <button type="button" onclick="filterModalPasses('all')" class="modal-filter-pill is-active" data-filter="all">All</button>
                    <button type="button" onclick="filterModalPasses('available')" class="modal-filter-pill" data-filter="available">Available</button>
                    <button type="button" onclick="filterModalPasses('active')" class="modal-filter-pill" data-filter="active">Active</button>
                    <button type="button" onclick="filterModalPasses('inactive')" class="modal-filter-pill" data-filter="inactive">Inactive</button>
                </div>
                <div class="gov-modal-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="buildingModalSearch" placeholder="Search by name or pass #..." autocomplete="off" oninput="applyModalFilters()">
                </div>
            </div>
        </div>

        <div class="px-6 py-5 overflow-y-auto">
            @foreach ($displayBuildings as $b)
                <div id="buildingPassGroup-{{ $b->id }}"
                     class="hidden flex flex-col gap-2"
                     data-building-color="{{ $b->color_hex }}">
                    @forelse ($passes->where('building_id', $b->id)->where('is_multi_building', $b->code === 'NG') as $p)
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
                        <div class="gov-pass-card" data-status="{{ $p->status }}" data-search="{{ $searchText }}">
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
                                                @elseif ($p->buildings->count() >= $buildings->count())
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
                                        <form method="POST" action="{{ route('passes.unassign', $p) }}"
                                            data-confirm data-confirm-tone="warning"
                                            data-confirm-title="Unassign this pass?"
                                            data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $p->visitor_name }}"
                                            data-confirm-message="The card will be reset and returned to available stock."
                                            data-confirm-label="Unassign">
                                            @csrf
                                            <button type="submit" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-link-slash"></i> Unassign</button>
                                        </form>
                                        <a href="{{ route('passes.show', $p) }}" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-qrcode"></i> View QR</a>
                                        <form method="POST" action="{{ route('passes.revoke', $p) }}"
                                            data-confirm data-confirm-tone="danger"
                                            data-confirm-title="Revoke this pass?"
                                            data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $p->visitor_name }}"
                                            data-confirm-message="The visitor will be denied on their next scan."
                                            data-confirm-label="Revoke pass">
                                            @csrf
                                            <button type="submit" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-ban"></i> Revoke</button>
                                        </form>
                                        <button type="button" class="gov-pass-row-btn is-ghost" data-pass-number="{{ $p->pass_number }}" onclick='openPassInfoModal(@json($infoPayload))'><i class="fa-solid fa-circle-info"></i> View Info</button>
                                    @else
                                        <a href="{{ route('passes.show', $p) }}" class="gov-pass-row-btn is-ghost"><i class="fa-solid fa-qrcode"></i> View QR</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-8">No passes exist for this building yet.</p>
                    @endforelse
                    <p class="gov-pass-row-empty hidden text-sm text-slate-400 text-center py-8">No passes match your search.</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Pass info modal (opened from "View Info" inside the building passes modal) --}}
<div id="passInfoModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="gov-info-modal-panel">
        <div class="gov-info-modal-header">
            <div>
                <span class="gov-eyebrow">Pass Information</span>
                <h3 id="infoModalTitle" class="gov-pass-modal-title"></h3>
            </div>
            <button type="button" onclick="closePassInfoModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full p-2.5 transition-colors leading-none">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="gov-info-modal-body">
            <div class="gov-info-photos">
                <div>
                    <span class="gov-meta-label">Visitor Photo</span>
                    <img id="infoPhoto" class="gov-info-photo" alt="Visitor photo">
                </div>
                <div>
                    <span class="gov-meta-label">ID Photo</span>
                    <img id="infoIdPhoto" class="gov-info-photo" alt="ID photo">
                </div>
            </div>
            <div class="gov-info-modal-grid" id="infoFieldsGrid"></div>
        </div>
    </div>
</div>

<script>
// ---- Tab 2: Buildings ----
let currentModalFilter = 'all';

function openBuildingModal(buildingId, buildingName, colorName) {
    document.querySelectorAll('[id^="buildingPassGroup-"]').forEach(el => el.classList.add('hidden'));
    const group = document.getElementById('buildingPassGroup-' + buildingId);
    group.classList.remove('hidden');

    document.getElementById('buildingModalTitle').innerText = `${buildingName} - ${colorName} Pass`;

    const color = group.dataset.buildingColor || '#1e3a8a';
    document.getElementById('buildingModalSwatch').style.background = color;

    document.getElementById('buildingModalSearch').value = '';
    currentModalFilter = 'all';
    document.querySelectorAll('.modal-filter-pill').forEach(pill => {
        pill.classList.toggle('is-active', pill.dataset.filter === 'all');
    });
    applyModalFilters();

    document.getElementById('buildingPassesModal').classList.remove('hidden');
}

function closeBuildingModal() {
    document.getElementById('buildingPassesModal').classList.add('hidden');
}

function openPassInfoModal(info) {
    document.getElementById('infoModalTitle').innerText = info.visitor_name ? `Pass #${info.pass_number} — ${info.visitor_name}` : `Pass #${info.pass_number}`;

    const photo = document.getElementById('infoPhoto');
    photo.src = info.photo_url || '';
    photo.style.display = info.photo_url ? '' : 'none';

    const idPhoto = document.getElementById('infoIdPhoto');
    idPhoto.src = info.id_photo_url || '';
    idPhoto.style.display = info.id_photo_url ? '' : 'none';

    const rows = [
        ['Gender', info.gender],
        ['Contact No.', info.contact_no],
        ['Email', info.visitor_email],
        ['ID Type', info.id_type],
        ['ID Number', info.id_ref],
        ['Office to Visit', info.office_to_visit],
        ['Contact Person', info.contact_person],
        ['Reason', info.purpose],
        ['Vehicle', info.vehicle],
        ['Registered By', info.registered_by],
        ['Pass Class', info.pass_class === 'long_term' ? 'Long-term' : 'Day'],
        ['Issued', info.issued_at],
        ['Expected Return', info.expected_return_date],
    ];

    document.getElementById('infoFieldsGrid').replaceChildren(...rows
        .filter(([, value]) => value)
        .map(([label, value]) => {
            // textContent, never innerHTML: names and contact persons are typed by guards.
            const wrap = document.createElement('div');
            const l = document.createElement('span'); l.className = 'gov-meta-label'; l.textContent = label;
            const v = document.createElement('div'); v.className = 'gov-meta-value'; v.textContent = value;
            wrap.append(l, v);
            return wrap;
        }));

    document.getElementById('passInfoModal').classList.remove('hidden');
}

function closePassInfoModal() {
    document.getElementById('passInfoModal').classList.add('hidden');
}

function filterModalPasses(status) {
    currentModalFilter = status;
    document.querySelectorAll('.modal-filter-pill').forEach(pill => {
        pill.classList.toggle('is-active', pill.dataset.filter === status);
    });
    applyModalFilters();
}

function applyModalFilters() {
    const visibleGroup = document.querySelector('[id^="buildingPassGroup-"]:not(.hidden)');
    if (!visibleGroup) return;

    const query = document.getElementById('buildingModalSearch').value.trim().toLowerCase();
    let anyVisible = false;

    visibleGroup.querySelectorAll('.gov-pass-card').forEach(row => {
        const matchesStatus = currentModalFilter === 'all' || (currentModalFilter === 'inactive' ? ['expired', 'revoked'].includes(row.dataset.status) : row.dataset.status === currentModalFilter);
        const matchesSearch = !query || (row.dataset.search || '').includes(query);
        const show = matchesStatus && matchesSearch;
        row.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });

    const emptyState = visibleGroup.querySelector('.gov-pass-row-empty');
    if (emptyState) {
        emptyState.classList.toggle('hidden', anyVisible || visibleGroup.querySelectorAll('.gov-pass-card').length === 0);
    }
}
// ---- End Tab 2 ----
</script>
