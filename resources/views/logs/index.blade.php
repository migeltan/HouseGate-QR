@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')

<style>
    @import url("https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap");
    .font-source-sans {
        font-family: 'Source Sans Pro', sans-serif;
    }
    .font-times {
        font-family: 'Times New Roman', Times, serif;
    }
    .no-scrollbar {
        scrollbar-width: none;
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
</style>

@include('logs._header')

{{-- ============================= ALERTS ============================= --}}
@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
        <i class="fa-solid fa-circle-check text-emerald-500"></i> {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
        <i class="fa-solid fa-triangle-exclamation text-red-500"></i> {{ session('error') }}
    </div>
@endif

{{-- ============================= FOLDER-STYLE TABS + CARD ============================= --}}
<div class="relative">

    {{-- --------------------------------------------------------------------- --}}
    {{-- --------------------------------------------------------------------- --}}
    {{-- Tabs: active tab pops up as a folder "tab" attached to the card below --}}
    {{-- --------------------------------------------------------------------- --}}
    {{-- --------------------------------------------------------------------- --}}
    <div class="flex items-end gap-1 pl-6 overflow-x-auto no-scrollbar [container-type:inline-size]">


        {{-- ++++++++++++++++ --}}
        {{-- Scan Audit Trail --}}
        {{-- ++++++++++++++++ --}}
        <button type="button" id="tabBtn-scans" data-tab="scans" onclick="showRecordsTab('scans')"
                class="tab-btn w-[clamp(160px,42cqw,300.5px)] h-[clamp(60px,14cqw,90.5px)] shrink-0 relative z-10 -mb-px flex items-center gap-3 rounded-t-xl border border-slate-300 bg-white px-4 pt-3 pb-4 transition-colors">

            <div class="w-auto h-auto shrink-0 flex">
                <span class="tab-icon grid h-auto w-auto shrink-0 place-items-center bg-white pr-[10.5px]">
                    <img src="{{ asset('images/icons/Folder.svg') }}" alt="Table Logs" class="h-[clamp(24px,6cqw,48px)] w-[clamp(24px,6cqw,48px)] shrink-0">
                </span>
                <span class="text-left min-w-0">
                    <span class="tab-eyebrow block truncate font-times text-[clamp(11px,2.2cqw,17px)] font-semibold text-blue-700">Table Logs</span>
                    <span class="tab-title block whitespace-nowrap font-source-sans text-[clamp(14px,3.2cqw,25px)] font-bold text-slate-1000">Scan Audit Trail</span>
                </span>
            </div>

        </button>

        {{-- ++++++++++++++++++++ --}}
        {{-- Registration Records --}}
        {{-- ++++++++++++++++++++ --}}
        <button type="button" id="tabBtn-registrations" data-tab="registrations" onclick="showRecordsTab('registrations')"
                class="tab-btn w-[clamp(160px,42cqw,320.5px)] h-[clamp(60px,14cqw,90.5px)] shrink-0 self-end flex items-center gap-3 rounded-t-lg px-3 pb-3 transition-colors">

            <div class="w-auto h-auto shrink-0 flex">
                <span class="tab-icon hidden h-auto w-auto shrink-0 place-items-center bg-white pr-[10.5px]">
                    <img src="{{ asset('images/icons/Folder.svg') }}" alt="Table Logs" class="h-[clamp(24px,6cqw,48px)] w-[clamp(24px,6cqw,48px)] shrink-0">
                </span>
                <span class="text-left min-w-0">
                    <span class="tab-eyebrow hidden truncate font-times text-[clamp(11px,2.2cqw,17px)] font-semibold text-blue-700">Table Logs</span>
                    <span class="tab-title block whitespace-nowrap leading-tight font-source-sans text-[clamp(14px,3.2cqw,25px)] font-semibold text-slate-400">Registration Records</span>
                </span>
            </div>

        </button>
    </div>


        {{-- +++++++++++++ --}}
        {{--  Folder Body --}}
        {{-- ++++++++++++++++++++ --}}
        <form method="GET" id="logsFilterForm" class="relative overflow-hidden rounded-xl rounded-tr-xl border border-slate-300 bg-white shadow-[200px] ">
            @include('logs._scans-table')
            @include('logs._registrations-table')
        </form>
</div>
{{-- ========================= END FOLDER-STYLE TABS + CARD ========================= --}}

{{-- ============================= PURGE MODAL (scans only) ============================= --}}
{{-- Entire modal is admin-only markup, not just the trigger button,
     so the forms/inputs never even exist in a guard's DOM. --}}
@if (auth()->user()->isAdmin())
<div id="purgeModalOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
     onclick="if(event.target === this) closeDeleteModal()">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
            <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i class="fa-solid fa-trash-can text-red-500"></i> Purge Logs
            </h3>
        </div>

        <div class="space-y-5 px-6 py-5">
            {{-- Option A --}}
            <form method="POST" action="{{ route('logs.purge.range') }}">
                @csrf
                @method('DELETE')
                <p class="text-sm font-semibold text-slate-700">Option A — Clear a date range</p>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label class="block text-xs font-medium text-slate-500">
                        <span class="mb-1 block">Start date</span>
                        <input type="date" name="start_date" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </label>
                    <label class="block text-xs font-medium text-slate-500">
                        <span class="mb-1 block">End date</span>
                        <input type="date" name="end_date" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                    </label>
                </div>
                <button type="submit"
                        onclick="return confirm('Delete all logs in this date range? This cannot be undone.');"
                        class="mt-3 w-full rounded-lg bg-amber-500 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-600 transition-colors">
                    Delete Logs In Range
                </button>
            </form>

            <hr class="border-slate-200">

            {{-- Option B --}}
            <form method="POST" action="{{ route('logs.purge.all') }}">
                @csrf
                @method('DELETE')
                <p class="text-sm font-semibold text-red-600">Option B — Purge all logs</p>
                <p class="mt-1 text-xs text-slate-500">
                    This permanently deletes every audit log. Type <strong>PURGE</strong> to confirm.
                </p>
                <input type="text" name="confirm" required placeholder="Type PURGE to confirm"
                       class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none">
                <button type="submit"
                        onclick="return confirm('This deletes ALL audit logs permanently. Continue?');"
                        class="mt-3 w-full rounded-lg bg-red-600 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">
                    Delete All Logs
                </button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============================= UNIVERSAL ROW DETAILS MODAL ============================= --}}
@include('logs._row-modal')

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
        const overlay = document.getElementById('purgeModalOverlay');
        if (overlay) overlay.classList.remove('hidden');
    }

    function closeDeleteModal() {
        const overlay = document.getElementById('purgeModalOverlay');
        if (overlay) overlay.classList.add('hidden');
    }

    // ---- Folder-tab toggle ----
    // Active tab: raised "folder tab" look (icon + eyebrow + bold title, white bg,
    // border on 3 sides, sits flush against the card via -mb-px).
    // Inactive tab: flat plain-text label, no icon/eyebrow, muted color.
    function setTabState(btn, active) {
        const icon = btn.querySelector('.tab-icon');
        const eyebrow = btn.querySelector('.tab-eyebrow');
        const title = btn.querySelector('.tab-title');

        btn.classList.toggle('relative', active);
        btn.classList.toggle('z-10', active);
        btn.classList.toggle('-mb-px', active);
        btn.classList.toggle('border', active);
        btn.classList.toggle('border-b-0', active);
        btn.classList.toggle('border-slate-300', active);
        btn.classList.toggle('bg-white', active);
        btn.classList.toggle('rounded-t-xl', active);
        btn.classList.toggle('pt-3', active);
        btn.classList.toggle('pb-4', active);
        btn.classList.toggle('px-4', active);

        btn.classList.toggle('self-end', !active);
        btn.classList.toggle('rounded-t-lg', !active);
        btn.classList.toggle('pb-3', !active);
        btn.classList.toggle('px-3', !active);

        icon.classList.toggle('hidden', !active);
        icon.classList.toggle('grid', active);
        eyebrow.classList.toggle('hidden', !active);
        title.classList.toggle('text-slate-900', active);
        title.classList.toggle('font-bold', active);
        title.classList.toggle('text-slate-400', !active);
        title.classList.toggle('font-semibold', !active);
    }

    function showRecordsTab(tab) {
        setTabState(document.getElementById('tabBtn-scans'), tab === 'scans');
        setTabState(document.getElementById('tabBtn-registrations'), tab === 'registrations');
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
