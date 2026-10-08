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

    <form id="dirFilters" data-url="{{ route('congressmen.index') }}" data-roster="{{ route('congressmen.roster') }}"
          data-role="{{ $isAdmin ? 'admin' : 'guard' }}" onsubmit="return false;"
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
        <div class="flex flex-wrap items-start gap-x-4">
            <p id="dirNotice" class="mb-2 h-5 text-sm font-semibold"></p>
            <p id="dirStatus" class="ml-auto flex h-5 items-center gap-1.5 text-xs text-slate-500" aria-live="polite"></p>
        </div>
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
    const PER_PAGE = 20;
    const CACHE_KEY = 'roster:' + filters.dataset.role;   // guards and admins are served different rosters
    let current = null, roster = null, inflight = null, failure = null;   // failure: null | 'network' | 'auth'
    let page = Number(new URLSearchParams(location.search).get('page')) || 1;
    const hay = new WeakMap();                            // member -> normalised searchable fields

    // ---------- Local cache (IndexedDB) ----------
    let dbp = null;
    const idb = () => dbp || (dbp = new Promise((resolve, reject) => {
        const req = indexedDB.open('hg-directory', 1);
        req.onupgradeneeded = () => req.result.createObjectStore('kv');
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    }));
    async function cacheGet() {
        try {
            const db = await idb();
            return await new Promise((resolve, reject) => {
                const r = db.transaction('kv').objectStore('kv').get(CACHE_KEY);
                r.onsuccess = () => resolve(r.result || null);
                r.onerror = () => reject(r.error);
            });
        } catch (e) { return null; }
    }
    async function cachePut(value) {
        try {
            const db = await idb();
            await new Promise((resolve, reject) => {
                const tx = db.transaction('kv', 'readwrite');
                tx.objectStore('kv').put(value, CACHE_KEY);
                tx.oncomplete = resolve;
                tx.onerror = () => reject(tx.error);
            });
        } catch (e) { /* storage unavailable or full: the page still works from memory */ }
    }

    // ---------- Roster sync ----------
    // Lowercase + strip accents, so "pena" finds "Peña" (the old server search was accent-insensitive too).
    const norm = s => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    function prepare(r) {
        r.members.forEach(m => hay.set(m, [m.name, m.detail, m.room, m.member_id].map(norm)));
        return r;
    }

    // Asks the server whether the roster changed. Resolves 'updated' | 'current' | 'failed'.
    function sync(force = false) {
        if (inflight) return force ? inflight.then(() => sync(true)) : inflight;
        inflight = (async () => {
            try {
                const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                if (roster && !force) headers['If-None-Match'] = '"' + roster.version + '"';
                const res = await fetch(filters.dataset.roster, { credentials: 'same-origin', headers });
                if (res.status === 401 || res.status === 419 || res.redirected) { failure = 'auth'; return 'failed'; }
                if (res.status === 304 && roster) {
                    failure = null;
                    roster.checkedAt = Date.now();
                    cachePut(roster);
                    return 'current';
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                roster = prepare(await res.json());
                failure = null;
                roster.checkedAt = Date.now();
                await cachePut(roster);
                return 'updated';
            } catch (e) {
                failure = 'network';   // offline or server error: keep whatever we already have
                return 'failed';
            } finally {
                inflight = null;
                paintStatus();
            }
        })();
        paintStatus();
        return inflight;
    }

    // ---------- Freshness status ("Updated 5 min ago" / "Offline") ----------
    const statusEl = document.getElementById('dirStatus');
    function ago(ts) {
        const sec = Math.max(0, Math.round((Date.now() - ts) / 1000));
        if (sec < 45) return 'just now';
        const min = Math.round(sec / 60);
        if (min < 60) return min + ' min ago';
        const hr = Math.round(min / 60);
        if (hr < 24) return hr + ' hr ago';
        const d = Math.round(hr / 24);
        return d + (d === 1 ? ' day ago' : ' days ago');
    }
    function paintStatus() {
        if (!statusEl) return;
        if (inflight) {
            statusEl.innerHTML = '<i class="fa-solid fa-rotate fa-spin text-[10px]"></i>Checking for updates…';
            return;
        }
        const age = roster && roster.checkedAt ? ago(roster.checkedAt) : null;
        const since = age ? ' · last updated ' + age : '';
        let dot = 'bg-emerald-500', text = '';
        if (failure === 'auth') { dot = 'bg-red-500'; text = 'Session expired · sign in again to refresh'; }
        else if (!navigator.onLine) { dot = 'bg-amber-500'; text = 'Offline' + since; }
        else if (failure === 'network') { dot = 'bg-amber-500'; text = "Can't reach server" + since; }
        else if (age) { text = 'Updated ' + age; }
        statusEl.innerHTML = text ? '<span class="h-1.5 w-1.5 rounded-full ' + dot + '"></span>' + text : '';
    }

    // ---------- Rendering (same markup the server-side table.blade.php produces) ----------
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function rowHtml(m, admin) {
        const partyList = (m.type || '').includes('Party');
        const fill = u => u.replace('__ID__', m.id);
        const payload = {
            member_id: m.member_id, name: m.name, type: m.type, detail: m.detail, building: m.building,
            color: m.color, floor: m.floor, room: m.room, photo: m.photo, building_id: m.building_id, active: m.active,
            urls: admin && roster.urls ? {
                update: fill(roster.urls.update), deactivate: fill(roster.urls.deactivate), reactivate: fill(roster.urls.reactivate)
            } : null,
        };
        return `<tr data-member="${esc(JSON.stringify(payload))}" class="cursor-pointer transition-colors duration-150 hover:bg-blue-50/60 ${m.active ? '' : 'bg-slate-50/70'}">
            <td class="py-2.5 px-4">
                <div class="flex items-center gap-3">
                    ${m.photo
                        ? `<img src="${esc(m.photo)}" alt="" loading="lazy" class="h-14 w-11 shrink-0 rounded-md border border-slate-200 object-cover object-top">`
                        : `<span class="grid h-14 w-11 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-100 text-slate-300"><i class="fa-solid fa-user"></i></span>`}
                    <div>
                        <p class="font-semibold ${m.active ? 'text-slate-900' : 'text-slate-400'}">${esc(m.name)}</p>
                        ${m.detail ? `<p class="text-[11px] text-slate-500">${esc(m.detail)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="py-3 px-4 whitespace-nowrap">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ${partyList ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700'}">${partyList ? 'Party-list' : 'District'}</span>
            </td>
            <td class="py-3 px-4 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">
                    <span class="h-2 w-2 rounded-full" style="background: ${esc(m.color || '#94a3b8')}"></span>${esc(m.building ?? '—')}
                </span>
            </td>
            <td class="py-3 px-4 text-slate-700 whitespace-nowrap">${esc(m.floor ?? '—')}</td>
            <td class="py-3 px-4 font-mono font-semibold text-slate-800 whitespace-nowrap">${esc(m.room ?? '—')}</td>
            ${admin ? `<td class="py-3 px-4 whitespace-nowrap">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ${m.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'}">${m.active ? 'Active' : 'Inactive'}</span>
            </td>` : ''}
        </tr>`;
    }

    function pagerLink(n, glyph, label, disabled) {
        return `<a href="#" data-page="${n}" class="js-dir-page grid h-7 w-7 place-items-center rounded-md border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-100 ${disabled ? 'pointer-events-none opacity-40' : ''}" aria-label="${label}">${glyph}</a>`;
    }

    function tableHtml(rows, total, last, admin) {
        const empty = `<tr><td colspan="${admin ? 6 : 5}" class="py-14 text-center">
            <i class="fa-solid fa-address-book text-2xl text-slate-300"></i>
            <p class="mt-2 text-sm font-semibold text-slate-600">No members found</p>
            <p class="text-xs text-slate-400">Try adjusting your search or building filter.</p>
        </td></tr>`;
        return `<div class="overflow-hidden rounded-xl border border-slate-300">
            <div class="max-h-[440px] overflow-auto">
                <table class="w-full min-w-[820px] border-collapse text-left text-xs">
                    <thead class="uppercase tracking-wide text-slate-600 [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:bg-slate-100 [&_th]:shadow-[inset_0_-1px_0_#cbd5e1]">
                        <tr>
                            <th class="py-3 px-4 font-bold">Member</th>
                            <th class="py-3 px-4 font-bold">Type</th>
                            <th class="py-3 px-4 font-bold">Building</th>
                            <th class="py-3 px-4 font-bold">Floor</th>
                            <th class="py-3 px-4 font-bold">Room</th>
                            ${admin ? '<th class="py-3 px-4 font-bold">Status</th>' : ''}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">${rows.length ? rows.map(m => rowHtml(m, admin)).join('') : empty}</tbody>
                </table>
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between">
            <p class="text-sm text-slate-500">${total ? total + ' members · page ' + page + ' of ' + last : 'No results'}</p>
            ${last > 1 ? `<div class="flex gap-1">${pagerLink(page - 1, '&lsaquo;', 'Previous page', page === 1)}${pagerLink(page + 1, '&rsaquo;', 'Next page', page === last)}</div>` : ''}
        </div>`;
    }

    // Search / building / status filtering runs here in the browser, no request.
    function render() {
        const q = norm(filters.querySelector('[name=q]').value).trim();
        const b = filters.querySelector('[name=building]').value;
        const statusSel = filters.querySelector('[name=status]');
        const st = statusSel ? statusSel.value : '';
        const list = roster.members.filter(m =>
            (!b || String(m.building_id) === b)
            && (!st || (st === 'active') === !!m.active)
            && (!q || hay.get(m).some(f => f.includes(q))));
        const last = Math.max(1, Math.ceil(list.length / PER_PAGE));
        page = Math.min(Math.max(1, page), last);
        results.innerHTML = tableHtml(list.slice((page - 1) * PER_PAGE, page * PER_PAGE), list.length, last, roster.is_admin);
        current = urlForState();
        history.replaceState(null, '', current);
        paintStatus();
    }

    function urlForState() {
        const p = new URLSearchParams();
        new FormData(filters).forEach((v, k) => { if (v) p.set(k, v); });
        if (page > 1) p.set('page', page);
        const qs = p.toString();
        return location.pathname + (qs ? '?' + qs : '');
    }

    async function show() {
        if (!roster) await sync();
        if (!roster) {
            results.innerHTML = '<div class="py-14 text-center"><i class="fa-solid fa-triangle-exclamation text-2xl text-slate-300"></i>'
                + '<p class="mt-2 text-sm font-semibold text-slate-600">Couldn\'t load the directory.</p>'
                + '<button type="button" data-retry class="gov-btn-glass-outline mt-3"><i class="fa-solid fa-rotate-right"></i> Try again</button></div>';
            return;
        }
        render();
    }

    // Used after an admin save / deactivate / reactivate: always re-fetch, then redraw.
    async function load() { await sync(true); await show(); }

    filters.querySelector('[name=q]').addEventListener('input', () => { page = 1; show(); });
    filters.querySelectorAll('select').forEach(s => s.addEventListener('change', () => { page = 1; show(); }));

    results.addEventListener('click', e => {
        const pg = e.target.closest('a.js-dir-page');
        if (pg) {
            e.preventDefault();
            const n = Number(pg.dataset.page || new URL(pg.href, location.href).searchParams.get('page'));
            if (n && !pg.classList.contains('pointer-events-none')) { page = n; show(); }
            return;
        }
        if (e.target.closest('[data-retry]')) { load(); return; }
        const row = e.target.closest('tr[data-member]');
        if (row) openMember(JSON.parse(row.dataset.member));
    });

    // Boot: show the cached roster immediately, then check for a newer one in the background.
    (async () => {
        const cached = await cacheGet();
        if (cached && Array.isArray(cached.members)) { roster = prepare(cached); render(); }
        if (await sync() === 'updated') render();
    })();
    window.addEventListener('online', () => { paintStatus(); sync().then(r => { if (r === 'updated') render(); }); });
    window.addEventListener('offline', paintStatus);
    setInterval(paintStatus, 30000);   // keeps "5 min ago" honest
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && roster && Date.now() - (roster.checkedAt || 0) > 60000) sync().then(r => { if (r === 'updated') render(); });
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
        const TOKEN = form.querySelector('[name=_token]').value;        const HEADERS = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const failMsg = () => navigator.onLine ? 'Something went wrong. Please try again.' : "You're offline. Changes can only be saved while online.";        const HEADERS = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
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
                formError.textContent = failMsg();
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
                return { ok: false, message: failMsg() };
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