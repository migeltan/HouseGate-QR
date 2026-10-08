@extends('layouts.app')
@section('title', 'Directory')

@section('content')

<style hidden>
    .dir-label { display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #64748b; }
    .dir-input { width: 100%; height: 2.75rem; border-radius: 8.1px; border: 1px solid #94a3b8; background: #f8fafc;
        padding: 0 .75rem; font-size: .875rem; outline: none; transition: border-color .15s, box-shadow .15s; }
    .dir-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
    .dir-input.has-error { border-color: #f87171; }
    .dir-error { margin-top: .25rem; font-size: .75rem; color: #dc2626; }
    #dirResults { position: relative; transition: opacity .15s; }
    #dirResults.is-loading { opacity: .5; pointer-events: none; }
    #dirResults.is-loading::before { content: ''; position: absolute; top: 0; left: 0; z-index: 20; height: 2px;
        width: 40%; background: #235aa6; animation: dir-bar 1s ease-in-out infinite; }
    @keyframes dir-bar { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
</style>

{{-- ============================= INSTRUCTIONS ============================= --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-address-book gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">20th Congress</span>
                <span class="gov-card-title">Congressmen Directory</span>
            </div>
        </div>
    </div>
    <div class="gov-card-body">
        <p class="gov-instr-lead">Members of the 20th Congress. Find which building, floor and room a member's office is in. The Registry's congressman list reads from this directory.</p>
        <ol class="gov-instr-steps">
            <li><span class="gov-instr-num">1</span><span>Search by name, district, party-list or room</span></li>
            <li><span class="gov-instr-num">2</span><span>Narrow the list by building</span></li>
            <li><span class="gov-instr-num">3</span><span>Click a row to enlarge the photo</span></li>
            @if ($isAdmin)
                <li><span class="gov-instr-num">4</span><span>Add, edit or deactivate members</span></li>
            @endif
        </ol>
    </div>
</div>

{{-- ============================= FILTERS + RESULTS ============================= --}}
<div class="mt-6 overflow-hidden rounded-xl border border-slate-300 bg-white">

    <form id="dirFilters" data-url="{{ route('congressmen.index') }}" onsubmit="return false;"
          class="flex flex-col gap-3 border-b border-slate-300 px-10 py-6 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
            <input type="text" name="q" value="{{ request('q') }}" autocomplete="off"
                   placeholder="Search by name, district, party-list, room..."
                   class="h-11 w-full rounded-[8.1px] border border-slate-400 bg-slate-50 pl-9 pr-3 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </div>

        <div class="gov-location-wrap w-full shrink-0 sm:w-56">
            <select name="building" class="gov-location-badge h-11 w-full">
                <option value="">Building</option>
                @foreach ($buildings as $b)
                    <option value="{{ $b->id }}" {{ (string) request('building') === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
        </div>

        @if ($isAdmin)
            <div class="gov-location-wrap w-full shrink-0 sm:w-44">
                <select name="status" class="gov-location-badge h-11 w-full">
                    <option value="">Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <i class="fa-solid fa-chevron-down gov-location-chevron" aria-hidden="true"></i>
            </div>
            <button type="button" id="addMemberBtn" class="gov-btn-camera h-11 shrink-0 whitespace-nowrap"><i class="fa-solid fa-user-plus"></i> Add Member</button>
        @endif
    </form>

    <div class="px-10 py-5">
        <p id="dirNotice" class="mb-2 h-5 text-sm font-semibold"></p>
        <div id="dirResults">
            @include('congressmen.table')
        </div>
    </div>
</div>
{{-- Member details (photo expand) --}}
<div id="memberModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4" role="dialog" aria-modal="true" aria-labelledby="mmName">
    <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="gov-card-header flex items-start justify-between gap-3" style="padding: 1.25rem 1.5rem;">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-address-card gov-card-header-icon"></i>
                <div>
                    <span class="gov-eyebrow">20th Congress</span>
                    <h3 class="gov-card-title" id="mmName"></h3>
                </div>
            </div>
            <button type="button" data-close class="mt-1 text-slate-400 hover:text-slate-700" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="flex flex-col gap-5 p-6 sm:flex-row">
            <div class="w-full shrink-0 sm:w-48">
                <img id="mmPhoto" alt="" class="hidden aspect-[4/5] w-full rounded-xl border border-slate-200 object-cover object-top">
                <div id="mmNoPhoto" class="grid aspect-[4/5] w-full place-items-center rounded-xl border border-slate-200 bg-slate-100 text-slate-300"><i class="fa-solid fa-user text-5xl"></i></div>
            </div>
            <dl class="flex-1 space-y-3 text-sm">
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Representing</dt><dd class="font-semibold text-slate-800" id="mmType"></dd></div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">District / Party-list</dt><dd class="font-semibold text-slate-800" id="mmDetail"></dd></div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Building</dt>
                    <dd class="mt-0.5"><span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700"><span class="h-2 w-2 rounded-full" id="mmDot"></span><span id="mmBuilding"></span></span></dd>
                </div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Floor / Room</dt><dd class="font-mono font-semibold text-slate-800" id="mmLocation"></dd></div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Member ID</dt><dd class="font-mono text-slate-600" id="mmId"></dd></div>
            </dl>
        </div>
        @if ($isAdmin)
            <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-6 py-3">
                <button type="button" id="mmToggle"></button>
                <button type="button" id="mmEdit" class="gov-btn-camera"><i class="fa-solid fa-pen"></i> Edit</button>
            </div>
        @endif
    </div>
</div>

@if ($isAdmin)
{{-- Add / Edit member --}}
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 backdrop-blur-sm px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="editTitle">
    <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="gov-card-header flex items-start justify-between gap-3" style="padding: 1.25rem 1.5rem;">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-user-pen gov-card-header-icon"></i>
                <div>
                    <span class="gov-eyebrow">Admin Action</span>
                    <h3 class="gov-card-title" id="editTitle">Add Member</h3>
                </div>
            </div>
            <button type="button" data-close class="mt-1 text-slate-400 hover:text-slate-700" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="editForm" data-create-url="{{ route('congressmen.store') }}" enctype="multipart/form-data" novalidate class="px-6 py-5">
            @csrf
            <div class="flex flex-col gap-5 sm:flex-row">
                <div class="w-full shrink-0 sm:w-40">
                    <span class="dir-label">Photo</span>
                    <img id="efPreview" alt="" class="hidden aspect-[4/5] w-full rounded-xl border border-slate-200 object-cover object-top">
                    <div id="efNoPreview" class="grid aspect-[4/5] w-full place-items-center rounded-xl border border-slate-200 bg-slate-100 text-slate-300"><i class="fa-solid fa-user text-4xl"></i></div>
                    <label class="gov-btn-glass-outline mt-2 w-full cursor-pointer justify-center">
                        <i class="fa-solid fa-camera"></i> Choose photo
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden">
                    </label>
                    <p class="dir-error hidden" data-error="photo"></p>
                </div>

                <div class="grid flex-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="dir-label" for="ef-name">Full name</label>
                        <input id="ef-name" name="name" type="text" class="dir-input" placeholder="Surname, Given Names">
                        <p class="dir-error hidden" data-error="name"></p>
                    </div>
                    <div>
                        <label class="dir-label" for="ef-type">Representing</label>
                        <select id="ef-type" name="rep_type" class="dir-input">
                            <option value="District Representative">District</option>
                            <option value="Party-list Representative">Party-list</option>
                        </select>
                        <p class="dir-error hidden" data-error="rep_type"></p>
                    </div>
                    <div>
                        <label class="dir-label" for="ef-detail">District / Party-list</label>
                        <input id="ef-detail" name="rep_detail" type="text" class="dir-input" placeholder="Manila, 6th District or TINGOG">
                        <p class="dir-error hidden" data-error="rep_detail"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="dir-label" for="ef-building">Building</label>
                        <select id="ef-building" name="building_id" class="dir-input">
                            @foreach ($buildings as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <p class="dir-error hidden" data-error="building_id"></p>
                    </div>
                    <div>
                        <label class="dir-label" for="ef-floor">Floor</label>
                        <input id="ef-floor" name="floor" type="number" min="0" max="30" class="dir-input">
                        <p class="dir-error hidden" data-error="floor"></p>
                    </div>
                    <div>
                        <label class="dir-label" for="ef-room">Room</label>
                        <input id="ef-room" name="room" type="text" class="dir-input" placeholder="e.g. SWA-316">
                        <p class="dir-error hidden" data-error="room"></p>
                    </div>
                </div>
            </div>
            <p class="dir-error hidden mt-3" data-form-error></p>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" data-close class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="gov-btn-camera"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Deactivate confirmation --}}
<div id="dirConfirm" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/70 px-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-2xl">
        <p id="dirConfirmMsg" class="mb-2 text-sm text-slate-700"></p>
        <p id="dirConfirmError" class="dir-error mb-3"></p>
        <div class="mt-3 flex justify-center gap-3">
            <button type="button" data-cancel class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="button" id="dirConfirmBtn" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Deactivate</button>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    const filters = document.getElementById('dirFilters');
    const results = document.getElementById('dirResults');
    let ctrl = null, debounce, current = null;

    function buildUrl() {
        const p = new URLSearchParams();
        new FormData(filters).forEach((v, k) => { if (v) p.set(k, v); });
        const qs = p.toString();
        return filters.dataset.url + (qs ? '?' + qs : '');
    }

    async function load(url) {
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        current = url || buildUrl();
        results.classList.add('is-loading');
        try {
            const res = await fetch(current, {
                signal: ctrl.signal, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            results.innerHTML = await res.text();
            history.replaceState(null, '', current);
        } catch (e) {
            if (e.name === 'AbortError') return;
            results.innerHTML = '<div class="py-14 text-center"><i class="fa-solid fa-triangle-exclamation text-2xl text-slate-300"></i>'
                + '<p class="mt-2 text-sm font-semibold text-slate-600">Couldn\'t load the directory.</p>'
                + '<button type="button" data-retry class="gov-btn-glass-outline mt-3"><i class="fa-solid fa-rotate-right"></i> Try again</button></div>';
        }
        results.classList.remove('is-loading');
    }

    filters.querySelector('[name=q]').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(() => load(), 300); });
    filters.querySelectorAll('select').forEach(s => s.addEventListener('change', () => load()));

    results.addEventListener('click', e => {
        const page = e.target.closest('a.js-dir-page');
        if (page) { e.preventDefault(); if (page.getAttribute('href') !== '#') load(page.href); return; }
        if (e.target.closest('[data-retry]')) { load(); return; }
        const row = e.target.closest('tr[data-member]');
        if (row) openMember(JSON.parse(row.dataset.member));
    });

    // ---------- Member details (photo expand) ----------
    const modal = document.getElementById('memberModal');
    const byId = id => document.getElementById(id);
    let onMemberOpen = () => {};   // admin hook, assigned below

    function openMember(m) {
        onMemberOpen(m);
        byId('mmName').textContent = m.name;
        byId('mmType').textContent = m.type || '—';
        byId('mmDetail').textContent = m.detail || '—';
        byId('mmBuilding').textContent = m.building || '—';
        byId('mmDot').style.background = m.color;
        byId('mmLocation').textContent = [m.floor ? 'Floor ' + m.floor : null, m.room].filter(Boolean).join(' · ') || '—';
        byId('mmId').textContent = m.member_id;

        const img = byId('mmPhoto'), none = byId('mmNoPhoto');
        if (m.photo) {
            img.src = m.photo; img.alt = m.name;
            img.classList.remove('hidden'); none.classList.add('hidden');
        } else {
            img.removeAttribute('src');
            img.classList.add('hidden'); none.classList.remove('hidden');
        }
        modal.classList.remove('hidden');
    }
    function closeMember() { modal.classList.add('hidden'); }

    modal.addEventListener('click', e => { if (e.target === modal || e.target.closest('[data-close]')) closeMember(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMember(); });

    // ---------- Admin: add / edit / deactivate / reactivate ----------
    const editModal = byId('editModal');
    if (editModal) {
        const form = byId('editForm');
        const formError = form.querySelector('[data-form-error]');
        const saveBtn = form.querySelector('button[type=submit]');
        const fileIn = form.querySelector('[name=photo]');
        const preview = byId('efPreview'), noPreview = byId('efNoPreview');
        const confirmBox = byId('dirConfirm'), confirmBtn = byId('dirConfirmBtn'), confirmErr = byId('dirConfirmError');
        const notice = byId('dirNotice');
        const TOKEN = form.querySelector('[name=_token]').value;
        const HEADERS = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const field = n => form.querySelector('[name="' + n + '"]');
        let editing = null, active = null;

        function flash(msg, ok = true) {
            notice.textContent = msg;
            notice.className = 'mb-2 h-5 text-sm font-semibold ' + (ok ? 'text-emerald-700' : 'text-red-600');
            setTimeout(() => { if (notice.textContent === msg) notice.textContent = ''; }, 4000);
        }
        function clearErrors() {
            form.querySelectorAll('[data-error]').forEach(p => { p.textContent = ''; p.classList.add('hidden'); });
            form.querySelectorAll('.dir-input').forEach(i => i.classList.remove('has-error'));
            formError.classList.add('hidden');
        }
        function showErrors(errors) {
            Object.entries(errors).forEach(([key, msgs]) => {
                const p = form.querySelector('[data-error="' + key + '"]');
                const input = field(key);
                if (p) { p.textContent = msgs[0]; p.classList.remove('hidden'); }
                if (input) input.classList.add('has-error');
            });
        }
        function showPreview(url) {
            if (url) { preview.src = url; preview.classList.remove('hidden'); noPreview.classList.add('hidden'); }
            else { preview.removeAttribute('src'); preview.classList.add('hidden'); noPreview.classList.remove('hidden'); }
        }

        // Row dialog: the toggle button depends on the member's status
        onMemberOpen = m => {
            active = m;
            const t = byId('mmToggle');
            t.className = 'rounded-lg border bg-white px-4 py-2 text-sm font-semibold transition-colors '
                + (m.active ? 'border-red-300 text-red-600 hover:bg-red-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50');
            t.innerHTML = m.active ? '<i class="fa-solid fa-user-slash"></i> Deactivate' : '<i class="fa-solid fa-user-check"></i> Reactivate';
        };

        // Add / edit modal
        function openEdit(m) {
            editing = m;
            form.reset(); clearErrors(); showPreview(m ? m.photo : null);
            byId('editTitle').textContent = m ? 'Edit Member' : 'Add Member';
            if (m) {
                field('name').value = m.name;
                field('rep_type').value = m.type;
                field('rep_detail').value = m.detail || '';
                field('building_id').value = m.building_id;
                field('floor').value = m.floor ?? '';
                field('room').value = m.room || '';
            }
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (m ? 'Save changes' : 'Add member');
            editModal.classList.remove('hidden');
            field('name').focus();
        }
        function closeEdit() { editModal.classList.add('hidden'); }

        byId('addMemberBtn').addEventListener('click', () => openEdit(null));
        byId('mmEdit').addEventListener('click', () => { closeMember(); openEdit(active); });
        editModal.addEventListener('click', e => { if (e.target === editModal || e.target.closest('[data-close]')) closeEdit(); });
        fileIn.addEventListener('change', () => { if (fileIn.files[0]) showPreview(URL.createObjectURL(fileIn.files[0])); });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            clearErrors();
            const fd = new FormData(form);
            if (editing) fd.append('_method', 'PUT');
            const label = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
            try {
                const res = await fetch(editing ? editing.urls.update : form.dataset.createUrl, {
                    method: 'POST', credentials: 'same-origin', headers: HEADERS, body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    if (data.errors) showErrors(data.errors);
                    else { formError.textContent = data.message || 'Please check the form.'; formError.classList.remove('hidden'); }
                } else if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                } else {
                    closeEdit();
                    flash(data.message || 'Saved.');
                    load(current);
                }
            } catch (err) {
                formError.textContent = 'Something went wrong. Please try again.';
                formError.classList.remove('hidden');
            }
            saveBtn.disabled = false;
            saveBtn.innerHTML = label;
        });

        // Deactivate (confirmed) / reactivate
        async function act(m, action) {
            try {
                const res = await fetch(m.urls[action], { method: 'POST', credentials: 'same-origin', headers: { ...HEADERS, 'X-CSRF-TOKEN': TOKEN } });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, message: data.message || (res.ok ? 'Done.' : 'Something went wrong. Please try again.') };
            } catch (err) {
                return { ok: false, message: 'Something went wrong. Please try again.' };
            }
        }

        byId('mmToggle').addEventListener('click', async () => {
            if (active.active) {
                confirmErr.textContent = '';
                byId('dirConfirmMsg').textContent = 'Deactivate ' + active.name + "? They will no longer appear in the Registry's congressman list.";
                confirmBox.classList.remove('hidden');
                return;
            }
            const r = await act(active, 'reactivate');
            flash(r.message, r.ok);
            if (r.ok) { closeMember(); load(current); }
        });

        confirmBox.addEventListener('click', e => { if (e.target === confirmBox || e.target.closest('[data-cancel]')) confirmBox.classList.add('hidden'); });
        confirmBtn.addEventListener('click', async () => {
            const label = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Working…';
            const r = await act(active, 'deactivate');
            if (r.ok) { confirmBox.classList.add('hidden'); closeMember(); flash(r.message); load(current); }
            else { confirmErr.textContent = r.message; }
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = label;
        });

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            confirmBox.classList.add('hidden');
            closeEdit();
        });
    }
})();
</script>
@endsection