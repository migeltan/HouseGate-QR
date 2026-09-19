@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')

<style>
    @import url("https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap");
    .font-source-sans {
        font-family: 'Source Sans Pro', sans-serif;
    }
    .no-scrollbar {
        scrollbar-width: none;
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
</style>

@include('logs.header')

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

{{-- ============================= FLAT TABS + CARD ============================= --}}
<div class="relative">

    {{-- --------------------------------------------------------------------- --}}
    {{-- Tabs: flat, uniform-size tabs sitting flush on top of the card below --}}
    {{-- --------------------------------------------------------------------- --}}
    <div class="flex items-end gap-2 overflow-x-auto no-scrollbar [container-type:inline-size]">

        {{-- ++++++++++++++++ --}}
        {{-- Scan Audit Trail --}}
        {{-- ++++++++++++++++ --}}
        <button type="button" id="tabBtn-scans" data-tab="scans" onclick="showRecordsTab('scans')"
                class="gov-btn-cameratab-btn group w-[clamp(180px,42cqw,300.5px)] h-[clamp(52px,10cqw,64px)] shrink-0 relative z-10 -mb-px flex items-center justify-center gap-2 rounded-t-xl border border-b-0 border-slate-300 bg-white px-5 transition-colors">
            <i class="fa-solid fa-clipboard-list text-blue-600 text-sm"></i>
            <span class="tab-title block whitespace-nowrap font-source-sans text-[clamp(14px,3.2cqw,20px)] font-bold text-slate-900">Scan Audit Trail</span>
        </button>

        <button type="button" id="tabBtn-registrations" data-tab="registrations" onclick="showRecordsTab('registrations')"
                class="tab-btn group w-[clamp(180px,42cqw,320.5px)] h-[clamp(52px,10cqw,64px)] shrink-0 flex items-center justify-center gap-2 rounded-t-xl border border-b-0 border-transparent px-5 transition-colors hover:bg-white/70">
            <i class="fa-solid fa-id-card text-slate-400 text-sm"></i>
            <span class="tab-title block whitespace-nowrap font-source-sans text-[clamp(14px,3.2cqw,20px)] font-semibold text-slate-400">Registration Records</span>
        </button>

    </div>

    {{-- +++++++++++++ --}}
    {{--  Folder Body --}}
    {{-- ++++++++++++++++++++ --}}
    <div class="relative overflow-hidden rounded-xl rounded-tl-none border border-slate-300 bg-white shadow-[200px]">
        @include('logs.scans-table')
        @include('logs.registrations-table')
    </div>
</div>
{{-- ========================= END FLAT TABS + CARD ========================= --}}

{{-- ============================= PURGE MODAL (scans only) ============================= --}}
{{-- Entire modal is admin-only markup, not just the trigger button,
     so the forms/inputs never even exist in a guard's DOM. --}}
@if (auth()->user()->isAdmin())
<div id="purgeModalOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
     onclick="if(event.target === this) closeDeleteModal()">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="gov-card-header flex items-start justify-between gap-3" style="padding: 1.25rem 1.5rem;">
    <div class="flex items-start gap-3">
        <i class="fa-solid fa-trash-can gov-card-header-icon"></i>
        <div>
            <span class="gov-eyebrow">Admin Action</span>
            <h3 class="gov-card-title">Purge Audit Logs</h3>
        </div>
    </div>
    <button type="button" onclick="closeDeleteModal()" class="text-slate-400 hover:text-slate-700 mt-1">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>

<div id="confirmPurgeOverlay" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/70 px-4">
    <div class="w-full max-w-sm rounded-xl bg-white shadow-2xl p-6 text-center">
        <p id="confirmPurgeMessage" class="text-sm text-slate-700 mb-5"></p>
        <div class="flex justify-center gap-3">
            <button type="button" onclick="document.getElementById('confirmPurgeOverlay').classList.add('hidden')"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </button>
            <button type="button" id="confirmPurgeSubmitBtn"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">
                Confirm
            </button>
        </div>
    </div>
</div>

<div class="px-6 pt-2">
    {{-- Option A: neutral, scoped action --}}
    <form method="POST" action="{{ route('logs.purge.range') }}" style="padding: 1.25rem 0;">
        @csrf
        @method('DELETE')
        <p class="text-sm font-semibold text-slate-800 mb-1">Clear a date range</p>
        <p class="text-xs text-slate-500 mb-3">Deletes only the scan logs recorded between these two dates.</p>
        <div class="grid grid-cols-2 gap-3">
            <label class="block text-xs font-medium text-slate-500">
                <span class="mb-1 block">Start date</span>
                <input type="date" name="start_date" required
                       style="width:100%; border-radius:0.5rem; border:1px solid #cbd5e1; padding:0.5rem 0.75rem; font-size:0.875rem; color:#1e293b; outline:none;">
            </label>
            <label class="block text-xs font-medium text-slate-500">
                <span class="mb-1 block">End date</span>
                <input type="date" name="end_date" required
                       style="width:100%; border-radius:0.5rem; border:1px solid #cbd5e1; padding:0.5rem 0.75rem; font-size:0.875rem; color:#1e293b; outline:none;">
            </label>
        </div>
        <button type="submit"
                onclick="event.preventDefault(); confirmPurge(this.form, 'Delete all logs in this date range? This cannot be undone.');"
                style="margin-top:0.75rem; width:100%; border-radius:0.5rem; border:1px solid #cbd5e1; background:#fff; padding:0.625rem 0; font-size:0.875rem; font-weight:600; color:#334155;">
            Delete Logs In Range
        </button>
    </form>

    <div style="margin: 0; border-top: 1px solid #f1f5f9;"></div>

    {{-- Option B: the only destructive-red action in the modal --}}
    <form method="POST" action="{{ route('logs.purge.all') }}" style="padding: 1.25rem 0;">
        @csrf
        @method('DELETE')
        <p class="text-sm font-semibold text-red-700 mb-1">Purge all logs</p>
        <p class="text-xs text-slate-500 mb-3">Permanently deletes every scan log in the system. Type <strong>PURGE</strong> below to confirm.</p>
        <input type="text" name="confirm" required placeholder="Type PURGE to confirm"
               style="width:100%; border-radius:0.5rem; border:1px solid #fecaca; padding:0.5rem 0.75rem; font-size:0.875rem; outline:none;">
        <button type="submit"
                onclick="event.preventDefault(); confirmPurge(this.form, 'This deletes ALL audit logs permanently. Continue?');"
                style="margin-top:0.75rem; width:100%; border-radius:0.5rem; background:#dc2626; padding:0.625rem 0; font-size:0.875rem; font-weight:700; color:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            Delete All Logs
        </button>
    </form>
</div>

        <div class="flex justify-end border-t border-slate-200 px-6 py-3">
            <button type="button" onclick="closeDeleteModal()"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>
@endif

{{-- ============================= UNIVERSAL ROW DETAILS MODAL ============================= --}}
@include('logs.row-modal')

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

    function confirmPurge(form, message) {
    document.getElementById('confirmPurgeMessage').textContent = message;
    document.getElementById('confirmPurgeOverlay').classList.remove('hidden');
    document.getElementById('confirmPurgeSubmitBtn').onclick = function () {
        form.submit();
    };
    }

    // ---- Flat-tab toggle ----
    // Active tab: white bg, border on top/left/right (flush against the card
    // via -mb-px), bold dark text.
    // Inactive tab: no border, muted gray text.
    function setTabState(btn, active) {
        const title = btn.querySelector('.tab-title');
        const icon = btn.querySelector('i');

        btn.classList.toggle('relative', active);
        btn.classList.toggle('z-10', active);
        btn.classList.toggle('-mb-px', active);
        btn.classList.toggle('bg-white', active);
        btn.classList.toggle('border-slate-300', active);
        btn.classList.toggle('border-transparent', !active);

        title.classList.toggle('text-slate-900', active);
        title.classList.toggle('font-bold', active);
        title.classList.toggle('text-slate-400', !active);
        title.classList.toggle('font-semibold', !active);

        if (icon) {
            icon.classList.toggle('text-blue-600', active);
            icon.classList.toggle('text-slate-400', !active);
        }
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
