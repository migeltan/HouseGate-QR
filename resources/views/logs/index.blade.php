@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')

{{-- Hero — reuses the exact segmented-border treatment from scanner/index.blade.php --}}
<div class="hero-govt">
    <div class="sunburst-red" aria-hidden="true"></div>

    <div class="hero-frame">
        <div class="hero-frame-rattan" aria-hidden="true"></div>

        <div class="hero-inner-panel flex items-center gap-5">
            <div class="hero-logo-badge hidden sm:flex">
                <img src="{{ asset('images/lsb-icon.png') }}" alt="LSB emblem">
            </div>
            <div>
                <p class="eyebrow">Perimeter Security Group &middot; Audit Trail</p>
                <h1>Centralized Visitor Audit Trail</h1>
                <p class="lead">Every scan event, authorized or denied, recorded with timestamp, pass, and reason.</p>
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert-govt-success" role="status">
        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert-govt-error" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ session('error') }}
    </div>
@endif

{{-- Sub-tabs: Scan Audit Trail / Pass Registration Records --}}
<div class="records-subtabs">
    <button type="button" class="records-subtab-btn is-active" data-tab="scans" onclick="showRecordsTab('scans')">
        Scan Audit Trail
    </button>
    <button type="button" class="records-subtab-btn" data-tab="registrations" onclick="showRecordsTab('registrations')">
        Pass Registration Records
    </button>
</div>

<form method="GET" id="logsFilterForm" class="space-y-6">

    {{-- ============================= SCANS PANE ============================= --}}
    <div id="scansPane" class="records-pane">

        {{-- Filter by Building (red) / Filter by Result (blue) — vivid cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card-govt card-vivid p-5 shadow-sm" style="--ribbon-color: var(--brand-red)">
                <label class="card-header-title mb-2">
                    <i class="fa-solid fa-building-circle-check"></i> Filter by Building
                </label>
                <select name="building" class="scanner-select w-full rounded-2xl px-4 py-3 font-bold" onchange="this.form.submit()">
                    <option value="ALL">All buildings</option>
                    @foreach ($buildings as $b)
                        <option value="{{ $b->id }}" {{ (string) request('building') === (string) $b->id ? 'selected' : '' }}>
                            {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="card-govt card-vivid p-5 shadow-sm" style="--ribbon-color: var(--brand-blue)">
                <label class="card-header-title mb-2">
                    <i class="fa-solid fa-shield-halved"></i> Filter by Result
                </label>
                <select name="result" class="scanner-select w-full rounded-2xl px-4 py-3 font-bold" onchange="this.form.submit()">
                    <option value="ALL">All results</option>
                    <option value="AUTHORIZED" {{ request('result') === 'AUTHORIZED' ? 'selected' : '' }}>Authorized</option>
                    <option value="UNAUTHORIZED" {{ request('result') === 'UNAUTHORIZED' ? 'selected' : '' }}>Unauthorized</option>
                    <option value="INVALID" {{ request('result') === 'INVALID' ? 'selected' : '' }}>Invalid</option>
                </select>
            </div>
        </div>

        {{-- Audit Logs — gold vivid card wrapping the search bar + table --}}
        <div class="card-govt card-vivid p-5 shadow-sm mt-6" style="--ribbon-color: var(--brand-gold)">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <label class="card-header-title">
                    <i class="fa-solid fa-magnifying-glass"></i> Audit Logs
                </label>

                <div class="search-field-govt search-field-govt--vivid">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="logSearchInput" name="search" value="{{ request('search') }}"
                           placeholder="Search by name, pass #, building..." autocomplete="off">
                </div>
            </div>

            {{-- White inset panel — same pattern as meta-grid-card / status-banner-card on vivid backgrounds --}}
            <div class="table-panel-govt">
                <div class="table-scroll-govt custom-scrollbar">
                    <table class="table-govt w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="py-3 px-4">Timestamp</th>
                                <th class="py-3 px-4">Visitor</th>
                                <th class="py-3 px-4">Pass #</th>
                                <th class="py-3 px-4">Authorized bldg</th>
                                <th class="py-3 px-4">Scanned at</th>
                                <th class="py-3 px-4">Result</th>
                                <th class="py-3 px-4">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($logs as $l)
                                <tr class="row-govt {{ $loop->even ? 'row-alt-govt' : '' }}">
                                    <td class="py-3 px-4 font-mono text-slate-500">{{ $l->created_at->format('Y-m-d h:i:s A') }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $l->visitor_name_snapshot }}</td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-800">{{ $l->pass_number_snapshot }}</td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">{{ $l->authorized_building_snapshot }}</td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">{{ $l->scannedBuilding->name ?? '' }}</td>
                                    <td class="py-3 px-4">
                                        <span class="badge-status {{ $l->result === 'AUTHORIZED' ? 'badge-authorized' : 'badge-denied' }}">
                                            {{ ucfirst(strtolower($l->result)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 truncate max-w-xs" title="{{ $l->reason }}">{{ $l->reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-8 text-center text-slate-400">No scan events recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom toolbar: results + pagination grouped left, Export + Purge grouped right --}}
                <div class="pager-footer-govt">
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="pager-count-govt">
                            @if ($logs->total() > 0)
                                Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} results
                            @else
                                No results
                            @endif
                        </p>

                        @if ($logs->hasPages())
                            <nav class="pager-govt" aria-label="Log pagination">
                                <a href="{{ $logs->previousPageUrl() ?? '#' }}"
                                   class="pager-btn-govt pager-arrow-govt {{ $logs->onFirstPage() ? 'is-disabled' : '' }}"
                                   aria-label="Previous page">&lsaquo;</a>

                                @foreach ($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                                    <a href="{{ $url }}" class="pager-btn-govt {{ $page == $logs->currentPage() ? 'is-active' : '' }}">
                                        {{ $page }}
                                    </a>
                                @endforeach

                                <a href="{{ $logs->nextPageUrl() ?? '#' }}"
                                   class="pager-btn-govt pager-arrow-govt {{ !$logs->hasMorePages() ? 'is-disabled' : '' }}"
                                   aria-label="Next page">&rsaquo;</a>
                            </nav>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('logs.export', request()->query()) }}"
                           class="btn-govt-success font-bold px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </a>
                        <button type="button" onclick="openDeleteModal()"
                                class="btn-govt-cta font-bold px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                            <i class="fa-solid fa-trash-can"></i> Purge Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- =========================== END SCANS PANE ============================ --}}

    {{-- ======================= PASS REGISTRATION RECORDS PANE ======================= --}}
    <div id="registrationsPane" class="records-pane hidden">

        <div class="card-govt card-vivid p-5 shadow-sm" style="--ribbon-color: var(--brand-blue)">
            <label class="card-header-title mb-2">
                <i class="fa-solid fa-clipboard-check"></i> Filter by Status
            </label>
            <select name="reg_status" class="scanner-select w-full rounded-2xl px-4 py-3 font-bold" onchange="this.form.submit()">
                <option value="ALL">All registrations</option>
                <option value="open" {{ request('reg_status') === 'open' ? 'selected' : '' }}>Currently assigned</option>
                <option value="closed" {{ request('reg_status') === 'closed' ? 'selected' : '' }}>Returned / expired</option>
            </select>
        </div>

        {{-- Pass Registration Records — append-only history, no purge button by design --}}
        <div class="card-govt card-vivid p-5 shadow-sm mt-6" style="--ribbon-color: var(--brand-gold)">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <label class="card-header-title">
                    <i class="fa-solid fa-magnifying-glass"></i> Pass Registration Records
                </label>

                <div class="search-field-govt search-field-govt--vivid">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="regSearchInput" name="reg_search" value="{{ request('reg_search') }}"
                           placeholder="Search by visitor name or ID #..." autocomplete="off">
                </div>
            </div>

            <div class="table-panel-govt">
                <div class="table-scroll-govt custom-scrollbar">
                    <table class="table-govt w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="py-3 px-4">Photos</th>
                                <th class="py-3 px-4">Registered at</th>
                                <th class="py-3 px-4">Visitor</th>
                                <th class="py-3 px-4">ID type / ref</th>
                                <th class="py-3 px-4">Pass class</th>
                                <th class="py-3 px-4">Expected return</th>
                                <th class="py-3 px-4">Registered by</th>
                                <th class="py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($registrations as $r)
                                <tr class="row-govt {{ $loop->even ? 'row-alt-govt' : '' }}">
                                    <td class="py-3 px-4">
                                        <div class="flex gap-1.5">
                                            @if ($r->photo_path)
                                                <img src="{{ asset('storage/' . $r->photo_path) }}" alt="Visitor photo" class="reg-thumb">
                                            @endif
                                            @if ($r->id_photo_path)
                                                <img src="{{ asset('storage/' . $r->id_photo_path) }}" alt="ID photo" class="reg-thumb">
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-500">{{ $r->registered_at->format('Y-m-d h:i:s A') }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $r->visitor_name }}</td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">{{ $r->id_type }} &middot; {{ $r->id_ref }}</td>
                                    <td class="py-3 px-4">
                                        @if ($r->pass_class === 'long_term')
                                            <span class="badge-neutral">Long-term</span>
                                        @else
                                            <span class="badge-neutral">Day</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">{{ $r->expected_return_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="py-3 px-4 text-slate-700 font-medium">{{ $r->registered_by ?? '—' }}</td>
                                    <td class="py-3 px-4">
                                        @if (! $r->unassigned_at)
                                            <span class="badge-status badge-authorized">Currently assigned</span>
                                        @else
                                            <span class="badge-status badge-denied">
                                                {{ $r->unassign_reason === 'auto_expired' ? 'Auto-expired' : ($r->unassign_reason === 'renewed' ? 'Renewed' : 'Returned') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="py-8 text-center text-slate-400">No pass registrations recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom toolbar: results + pagination grouped left, Export grouped right — no Purge button here on purpose --}}
                <div class="pager-footer-govt">
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="pager-count-govt">
                            @if ($registrations->total() > 0)
                                Showing {{ $registrations->firstItem() }} to {{ $registrations->lastItem() }} of {{ $registrations->total() }} results
                            @else
                                No results
                            @endif
                        </p>

                        @if ($registrations->hasPages())
                            <nav class="pager-govt" aria-label="Registration pagination">
                                <a href="{{ $registrations->previousPageUrl() ?? '#' }}"
                                   class="pager-btn-govt pager-arrow-govt {{ $registrations->onFirstPage() ? 'is-disabled' : '' }}"
                                   aria-label="Previous page">&lsaquo;</a>

                                @foreach ($registrations->getUrlRange(1, $registrations->lastPage()) as $page => $url)
                                    <a href="{{ $url }}" class="pager-btn-govt {{ $page == $registrations->currentPage() ? 'is-active' : '' }}">
                                        {{ $page }}
                                    </a>
                                @endforeach

                                <a href="{{ $registrations->nextPageUrl() ?? '#' }}"
                                   class="pager-btn-govt pager-arrow-govt {{ !$registrations->hasMorePages() ? 'is-disabled' : '' }}"
                                   aria-label="Next page">&rsaquo;</a>
                            </nav>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('logs.registrations.export', request()->query()) }}"
                           class="btn-govt-success font-bold px-4 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </a>
                        {{-- No purge/delete button here — pass_registrations is append-only by design. --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- ===================== END PASS REGISTRATION RECORDS PANE ===================== --}}

</form>

{{-- Purge Modal (scans only) --}}
<div id="purgeModalOverlay" class="modal-overlay-govt hidden" onclick="if(event.target === this) closeDeleteModal()">
    <div class="modal-govt-panel modal-purge-panel">
        <div class="modal-header-govt">
            <h3><i class="fa-solid fa-trash-can"></i> Purge Logs</h3>
            <button type="button" class="modal-close-govt" onclick="closeDeleteModal()" aria-label="Close">&times;</button>
        </div>

        <div class="modal-body-govt space-y-5">
            {{-- Option A --}}
            <form method="POST" action="{{ route('logs.purge.range') }}">
                @csrf
                @method('DELETE')
                <p class="modal-option-title-govt">Option A — Clear a date range</p>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <label class="modal-field-govt">
                        <span>Start date</span>
                        <input type="date" name="start_date" required>
                    </label>
                    <label class="modal-field-govt">
                        <span>End date</span>
                        <input type="date" name="end_date" required>
                    </label>
                </div>
                <button type="submit" class="btn-govt-gold w-full mt-3 py-2.5 rounded-xl font-bold"
                        onclick="return confirm('Delete all logs in this date range? This cannot be undone.');">
                    Delete Logs In Range
                </button>
            </form>

            <hr class="modal-divider-govt">

            {{-- Option B --}}
            <form method="POST" action="{{ route('logs.purge.all') }}">
                @csrf
                @method('DELETE')
                <p class="modal-option-title-govt modal-option-title-govt--danger">Option B — Purge all logs</p>
                <p class="modal-option-hint-govt">This permanently deletes every audit log. Type <strong>PURGE</strong> to confirm.</p>
                <input type="text" name="confirm" required placeholder="Type PURGE to confirm" class="modal-confirm-input-govt mt-2">
                <button type="submit" class="btn-govt-cta w-full mt-3 py-2.5 rounded-xl font-bold"
                        onclick="return confirm('This deletes ALL audit logs permanently. Continue?');">
                    Delete All Logs
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Debounced live search — auto-submits ~300ms after the user stops typing.
    // Watches BOTH search inputs; whichever pane is active is the one the
    // user is realistically typing into.
    (function () {
        ['logSearchInput', 'regSearchInput'].forEach(function (id) {
            const input = document.getElementById(id);
            if (!input) return;
            let t;
            input.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    input.form.submit();
                }, 300);
            });
        });
    })();

    function openDeleteModal() {
        document.getElementById('purgeModalOverlay').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('purgeModalOverlay').classList.add('hidden');
    }

    // ---- Sub-tab toggle ----
    function showRecordsTab(tab) {
        document.querySelectorAll('.records-subtab-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.tab === tab);
        });
        document.getElementById('scansPane').classList.toggle('hidden', tab !== 'scans');
        document.getElementById('registrationsPane').classList.toggle('hidden', tab !== 'registrations');
    }

    // Restore the tab the user was on if they came back via a reg_* or
    // scan filter submit (GET reload loses in-memory JS state otherwise).
    (function () {
        const params = new URLSearchParams(window.location.search);
        const hasRegParam = params.has('reg_search') || params.has('reg_status') || params.has('reg_page');
        if (hasRegParam) {
            showRecordsTab('registrations');
        }
    })();
</script>
@endsection