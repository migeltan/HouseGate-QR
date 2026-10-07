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
                    <i class="fa-solid fa-id-badge bp-header-icon"></i>
                    <div>
                        <span class="bp-eyebrow">Visitor Passes</span>
                        <div class="bp-title-row">
                            <span id="buildingModalSwatch" class="bp-dot"></span>
                            <h3 id="buildingModalTitle" class="gov-pass-modal-title"></h3>
                        </div>
                    </div>
                </div>
                <button type="button" class="reg-close-button" aria-label="Close" onclick="closeBuildingModal()">&times;</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="filterModalPasses('all')" class="modal-filter-pill is-active" data-filter="all">All <span class="bp-count" data-count="all">0</span></button>
                    <button type="button" onclick="filterModalPasses('available')" class="modal-filter-pill" data-filter="available">Available <span class="bp-count" data-count="available">0</span></button>
                    <button type="button" onclick="filterModalPasses('active')" class="modal-filter-pill" data-filter="active">Active <span class="bp-count" data-count="active">0</span></button>
                    <button type="button" onclick="filterModalPasses('inactive')" class="modal-filter-pill" data-filter="inactive">Inactive <span class="bp-count" data-count="inactive">0</span></button>
                </div>
                <div class="flex items-center gap-3">
                    <span id="buildingModalShowing" class="bp-showing"></span>
                    <div class="gov-modal-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="buildingModalSearch" placeholder="Search by name or pass #..." autocomplete="off" oninput="applyModalFilters()">
                        <button type="button" id="buildingModalSearchClear" class="bp-search-clear hidden" aria-label="Clear search" onclick="clearModalSearch()">&times;</button>
                    </div>
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
<button type="submit" class="gov-pass-row-btn is-ghost is-warn"><i class="fa-solid fa-link-slash"></i> Unassign</button>
                                        </form>
<button type="button" class="gov-pass-row-btn is-ghost" data-qr-url="{{ route('passes.show', $p) }}" data-pass-number="{{ $p->pass_number }}" onclick="openPassQrModal(this)"><i class="fa-solid fa-qrcode"></i> View QR</button>
                                        <form method="POST" action="{{ route('passes.revoke', $p) }}"
                                            data-confirm data-confirm-tone="danger"
                                            data-confirm-title="Revoke this pass?"
                                            data-confirm-subject="Pass #{{ $p->pass_number }} · {{ $p->visitor_name }}"
                                            data-confirm-message="The visitor will be denied on their next scan."
                                            data-confirm-label="Revoke pass">
                                            @csrf
<button type="submit" class="gov-pass-row-btn is-ghost is-danger"><i class="fa-solid fa-ban"></i> Revoke</button>
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
<div id="passInfoModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="infoModalTitle">
    <div class="bp-modal-panel bp-info-panel">
        <div class="bp-modal-head">
            <div class="flex items-center gap-3 min-w-0">
                <i class="fa-solid fa-address-card bp-header-icon"></i>
                <div class="min-w-0">
                    <span class="bp-eyebrow">Pass Information</span>
                    <h3 id="infoModalTitle" class="gov-pass-modal-title"></h3>
                    <div class="bp-info-meta">
                        <span id="infoStatusBadge" class="gov-pass-badge"></span>
                        <span id="infoBuilding" class="bp-info-building"></span>
                    </div>
                </div>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closePassInfoModal()">&times;</button>
        </div>

        <div class="bp-modal-body bp-info-body">
            <div id="infoPhotos" class="bp-info-photos">
                <figure class="bp-photo-fig" onclick="toggleInfoPhotoZoom(this)" title="Click to enlarge">
                    <span class="gov-meta-label">Visitor Photo</span>
                    <img id="infoPhoto" class="gov-info-photo" alt="Visitor photo">
                </figure>
                <figure class="bp-photo-fig" onclick="toggleInfoPhotoZoom(this)" title="Click to enlarge">
                    <span class="gov-meta-label">ID Photo</span>
                    <img id="infoIdPhoto" class="gov-info-photo" alt="ID photo">
                </figure>
            </div>
            <div id="infoSections"></div>
        </div>

        <div class="bp-modal-foot">
            <button type="button" id="infoViewQr" class="gov-btn-glass-outline" onclick="openPassQrModal(this)"><i class="fa-solid fa-qrcode"></i> View QR</button>
        </div>
    </div>
</div>

{{-- Pass QR overlay (opened from "View QR"; data comes from PassController@show as JSON) --}}
<div id="passQrModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="passQrTitle">
    <div class="bp-modal-panel bp-qr-panel">
        <div class="bp-modal-head">
            <div class="flex items-center gap-3 min-w-0">
                <i class="fa-solid fa-qrcode bp-header-icon"></i>
                <div class="min-w-0">
                    <span id="passQrEyebrow" class="bp-eyebrow">Pass QR</span>
                    <h3 id="passQrTitle" class="gov-pass-modal-title"></h3>
                </div>
            </div>
            <button type="button" class="reg-close-button" aria-label="Close" onclick="closePassQrModal()">&times;</button>
        </div>

        <div class="bp-modal-body bp-qr-body">
            <div id="passQrStage" class="bp-qr-stage">
                <div id="passQrPrintArea" class="bp-qr-card">
                    <div class="bp-qr-box"><div id="passQrCanvas"></div></div>
                    <div id="passQrNumber" class="bp-qr-num"></div>
                </div>
                <div id="passQrSkeleton" class="bp-qr-skeleton sk hidden"></div>
            </div>
            <p id="passQrError" class="bp-qr-error hidden"></p>
        </div>

        <div class="bp-modal-foot">
            <button type="button" id="passQrPrintBtn" class="gov-btn-camera" onclick="printPassQr()" disabled><i class="fa-solid fa-print"></i> Print</button>
            <button type="button" class="gov-btn-glass-outline" onclick="closePassQrModal()">Close</button>
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

const INFO_STATUS = {
    active:    ['is-active', 'Active'],
    available: ['is-available', 'Available'],
    expired:   ['is-expired', 'Expired'],
    revoked:   ['is-revoked', 'Revoked'],
};

function openPassInfoModal(info) {
    document.getElementById('infoModalTitle').textContent = info.visitor_name ? `Pass #${info.pass_number} — ${info.visitor_name}` : `Pass #${info.pass_number}`;

    const [badgeClass, badgeLabel] = INFO_STATUS[info.status] || INFO_STATUS.available;
    const badge = document.getElementById('infoStatusBadge');
    badge.className = 'gov-pass-badge ' + badgeClass;
    badge.textContent = badgeLabel;
    document.getElementById('infoBuilding').textContent = info.building || '';

    // Photos: reset any zoom, hide a missing photo, go single-column if only one exists.
    const photos = document.getElementById('infoPhotos');
    photos.classList.remove('is-zoomed');
    photos.querySelectorAll('figure').forEach(f => f.classList.remove('is-zoom-target'));
    [['infoPhoto', info.photo_url], ['infoIdPhoto', info.id_photo_url]].forEach(([id, url]) => {
        const img = document.getElementById(id);
        img.src = url || '';
        img.closest('figure').style.display = url ? '' : 'none';
    });
    photos.style.display = (info.photo_url || info.id_photo_url) ? '' : 'none';
    photos.classList.toggle('is-single', !info.photo_url !== !info.id_photo_url);

    // Grouped fields. textContent, never innerHTML: names and contact persons are typed by guards.
    const sections = [
        ['Visitor', [['Gender', info.gender], ['Contact No.', info.contact_no], ['Email', info.visitor_email]]],
        ['Identification', [['ID Type', info.id_type], ['ID Number', info.id_ref]]],
        ['Visit', [['Office to Visit', info.office_to_visit], ['Contact Person', info.contact_person], ['Reason', info.purpose], ['Vehicle', info.vehicle]]],
        ['Pass', [['Registered By', info.registered_by], ['Pass Class', info.pass_class === 'long_term' ? 'Long-term' : 'Day'], ['Issued', info.issued_at], ['Expected Return', info.expected_return_date]]],
    ];

    const frag = document.createDocumentFragment();
    sections.forEach(([title, fields]) => {
        const rows = fields.filter(([, value]) => value);
        if (!rows.length) return;

        const sec = document.createElement('section');
        sec.className = 'bp-info-section';
        const h = document.createElement('h4');
        h.className = 'bp-sec-title';
        h.textContent = title;
        const grid = document.createElement('div');
        grid.className = 'gov-info-modal-grid';

        rows.forEach(([label, value]) => {
            const wrap = document.createElement('div');
            const l = document.createElement('span'); l.className = 'gov-meta-label'; l.textContent = label;
            const v = document.createElement('div'); v.className = 'gov-meta-value'; v.textContent = value;
            wrap.append(l, v);
            grid.append(wrap);
        });

        sec.append(h, grid);
        frag.append(sec);
    });
    document.getElementById('infoSections').replaceChildren(frag);

    const qrBtn = document.getElementById('infoViewQr');
    qrBtn.dataset.qrUrl = info.qr_url || '';
    qrBtn.dataset.passNumber = info.pass_number;
    qrBtn.classList.toggle('hidden', !info.qr_url);

    document.getElementById('passInfoModal').classList.remove('hidden');
    document.querySelector('#passInfoModal .bp-info-body').scrollTop = 0;
}

function toggleInfoPhotoZoom(fig) {
    const wrap = document.getElementById('infoPhotos');
    const zoom = !fig.classList.contains('is-zoom-target');
    wrap.querySelectorAll('figure').forEach(f => f.classList.remove('is-zoom-target'));
    wrap.classList.toggle('is-zoomed', zoom);
    if (zoom) fig.classList.add('is-zoom-target');
}

function closePassInfoModal() {
    document.getElementById('passInfoModal').classList.add('hidden');
}

// ---- Pass QR overlay ----
let qrSeq = 0;          // cancels stale loads if the overlay is closed/reopened mid-fetch
let qrLibPromise = null;

function loadQrLib() {
    if (window.QRCode) return Promise.resolve();
    if (!qrLibPromise) {
        qrLibPromise = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            s.onload = resolve;
            s.onerror = () => { qrLibPromise = null; reject(new Error('QR library failed to load')); };
            document.head.appendChild(s);
        });
    }
    return qrLibPromise;
}

function preloadImage(src) {
    return new Promise(resolve => {
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = src;
    });
}

function toggleQrSkeleton(on) {
    document.getElementById('passQrSkeleton').classList.toggle('hidden', !on);
}

async function openPassQrModal(btn) {
    const url = btn.dataset.qrUrl;
    const number = btn.dataset.passNumber;
    const seq = ++qrSeq;

    const card = document.getElementById('passQrPrintArea');
    const printBtn = document.getElementById('passQrPrintBtn');
    const error = document.getElementById('passQrError');

    // Reset to a clean, pending state.
    card.classList.remove('is-ready');
    document.getElementById('passQrCanvas').replaceChildren();
    document.getElementById('passQrTitle').textContent = `Pass #${number}`;
    document.getElementById('passQrEyebrow').textContent = 'Pass QR';
    document.getElementById('passQrNumber').textContent = number;
    document.getElementById('passQrStage').classList.remove('hidden');
    error.classList.add('hidden');
    printBtn.disabled = true;
    toggleQrSkeleton(false);
    document.getElementById('passQrModal').classList.remove('hidden');

    // Only show the skeleton if loading is actually slow (150 ms), to avoid flicker.
    const timer = setTimeout(() => toggleQrSkeleton(true), 150);
    try {
        const [data] = await Promise.all([
            fetch(url, { headers: { 'Accept': 'application/json' } }).then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }),
            loadQrLib(),
        ]);
        if (seq !== qrSeq) return;
        await preloadImage(data.template);
        if (seq !== qrSeq) return;

        card.style.backgroundImage = `url("${data.template}")`;
        new QRCode(document.getElementById('passQrCanvas'), {
            text: data.qr_token,
            width: 140, height: 140,
            colorDark: data.qr_color,
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H,
        });
        document.getElementById('passQrEyebrow').textContent = `Pass QR · ${data.building}`;
        card.classList.add('is-ready');
        printBtn.disabled = false;
    } catch (e) {
        if (seq === qrSeq) {
            document.getElementById('passQrStage').classList.add('hidden');
            error.textContent = "Couldn't load this QR code. Check your connection and try again.";
            error.classList.remove('hidden');
        }
    } finally {
        clearTimeout(timer);
        if (seq === qrSeq) toggleQrSkeleton(false);
    }
}

function closePassQrModal() {
    qrSeq++;
    document.getElementById('passQrModal').classList.add('hidden');
}

function printPassQr() {
    document.body.classList.add('qr-printing');
    window.addEventListener('afterprint', () => document.body.classList.remove('qr-printing'), { once: true });
    window.print();
}
// ---- End Pass QR overlay ----

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

    const searchInput = document.getElementById('buildingModalSearch');
    const query = searchInput.value.trim().toLowerCase();
    document.getElementById('buildingModalSearchClear').classList.toggle('hidden', !searchInput.value);

    const rows = visibleGroup.querySelectorAll('.gov-pass-card');
    const counts = { all: rows.length, available: 0, active: 0, inactive: 0 };
    let shown = 0;

    rows.forEach(row => {
        const status = row.dataset.status;
        if (status === 'available') counts.available++;
        else if (status === 'active') counts.active++;
        else if (status === 'expired' || status === 'revoked') counts.inactive++;

        const matchesStatus = currentModalFilter === 'all' || (currentModalFilter === 'inactive' ? ['expired', 'revoked'].includes(status) : status === currentModalFilter);
        const matchesSearch = !query || (row.dataset.search || '').includes(query);
        const show = matchesStatus && matchesSearch;
        row.style.display = show ? '' : 'none';
        if (show) shown++;
    });

    document.querySelectorAll('#buildingPassesModal [data-count]').forEach(el => {
        el.textContent = counts[el.dataset.count] ?? 0;
    });
    document.getElementById('buildingModalShowing').textContent = rows.length ? `Showing ${shown} of ${rows.length}` : '';

    const emptyState = visibleGroup.querySelector('.gov-pass-row-empty');
    if (emptyState) {
        emptyState.classList.toggle('hidden', shown > 0 || rows.length === 0);
    }
}

function clearModalSearch() {
    const input = document.getElementById('buildingModalSearch');
    input.value = '';
    applyModalFilters();
    input.focus();
}
// ---- End Tab 2 ----
</script>
