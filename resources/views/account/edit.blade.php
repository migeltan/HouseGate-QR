@extends('layouts.app')
@section('title', 'Edit Info')

@section('content')
@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $initialTab = ($isAdmin && request('tab') === 'log') ? 'log' : 'profile';
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<style hidden>
    .acct-tab { padding: .75rem 1.5rem; border-radius: .75rem .75rem 0 0; font-weight: 600; color: #94a3b8;
        border: 1px solid transparent; border-bottom: 0; transition: background-color .15s, color .15s; }
    .acct-tab:hover { background: rgba(255,255,255,.7); }
    .acct-tab.is-active { background: #fff; color: #0f172a; font-weight: 700; border-color: #cbd5e1;
        position: relative; z-index: 1; margin-bottom: -1px; }
    .acct-tab.is-active i { color: #2563eb; }
    .acct-label { display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #64748b; }
    .acct-input { width: 100%; height: 2.75rem; border-radius: 8.1px; border: 1px solid #94a3b8; background: #f8fafc;
        padding: 0 .75rem; font-size: .875rem; outline: none; transition: border-color .15s, box-shadow .15s; }
    .acct-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
    .acct-input.has-error { border-color: #f87171; }
    .acct-input:disabled { cursor: not-allowed; opacity: .7; }
    .acct-field-error { margin-top: .25rem; font-size: .75rem; color: #dc2626; }
    .acct-eye { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); padding: .35rem;
        color: #94a3b8; background: none; border: 0; cursor: pointer; }
    .acct-eye:hover { color: #475569; }
    .acct-card { border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; padding: 1.25rem 1.5rem; }
    .acct-card-title { font-size: .95rem; font-weight: 700; color: #0f172a; }
    .acct-card-sub { font-size: .8rem; color: #64748b; margin-bottom: 1rem; }
    .acct-avatar { width: 4.5rem; height: 4.5rem; border-radius: 9999px; display: grid; place-items: center;
        font-size: 1.5rem; font-weight: 700; color: #fff; background: linear-gradient(145deg, #2f6fc0, #1a4680);
        box-shadow: 0 6px 16px rgba(35,90,166,.25); }
    .acct-status { font-size: .8rem; font-weight: 600; }
    .acct-status.is-ok { color: #047857; }
    .acct-status.is-error { color: #dc2626; }
    .acct-save:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    #logResults { position: relative; transition: opacity .15s; }
    #logResults.is-loading { opacity: .5; pointer-events: none; }
    #logResults.is-loading::before { content: ''; position: absolute; top: 0; left: 0; z-index: 20; height: 2px;
        width: 40%; background: #235aa6; animation: acct-bar 1s ease-in-out infinite; }
    @keyframes acct-bar { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
</style>

{{-- ============================= INSTRUCTIONS ============================= --}}
<div class="gov-card">
    <div class="gov-card-header">
        <div class="gov-card-header-left">
            <i class="fa-solid fa-user-pen gov-card-header-icon"></i>
            <div>
                <span class="gov-eyebrow">Account</span>
                <span class="gov-card-title">Edit Info</span>
            </div>
        </div>
    </div>
    <div class="gov-card-body">
        <p class="gov-instr-lead">Keep your account details current. Your HREP ID and role can only be changed by an administrator.</p>
        <ol class="gov-instr-steps">
            <li><span class="gov-instr-num">1</span><span>Update your name and email</span></li>
            <li><span class="gov-instr-num">2</span><span>Keep your password current</span></li>
            @if ($isAdmin)
                <li><span class="gov-instr-num">3</span><span>Review the Admin Log</span></li>
            @endif
        </ol>
    </div>
</div>

{{-- ============================= TABS + BODY ============================= --}}
<div>
    <div class="flex items-end gap-2">
        <button type="button" class="acct-tab {{ $initialTab === 'profile' ? 'is-active' : '' }}" data-tab="profile">
            <i class="fa-solid fa-user mr-2"></i>My Profile
        </button>
        @if ($isAdmin)
            <button type="button" class="acct-tab {{ $initialTab === 'log' ? 'is-active' : '' }}" data-tab="log">
                <i class="fa-solid fa-list-check mr-2"></i>Admin Log
            </button>
        @endif
    </div>

    <div class="rounded-xl rounded-tl-none border border-slate-300 bg-white p-6">

        {{-- ===================== MY PROFILE ===================== --}}
        <div id="pane-profile" class="{{ $initialTab === 'profile' ? '' : 'hidden' }}">
            <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">

                {{-- Identity summary --}}
                <div class="acct-card flex flex-col items-center text-center">
                    <div class="acct-avatar" id="acctAvatar">{{ $initials }}</div>
                    <p class="mt-3 text-base font-bold text-slate-900" id="acctName">{{ $user->name }}</p>
                    <span class="mt-1 inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">{{ $user->role }}</span>
                    <dl class="mt-4 w-full space-y-3 border-t border-slate-100 pt-4 text-left text-sm">
                        <div><dt class="acct-label">HREP ID</dt><dd class="font-semibold text-slate-800">{{ $user->hrep_id ?? '—' }}</dd></div>
                        <div><dt class="acct-label">Email</dt><dd class="break-all font-semibold text-slate-800" id="acctEmail">{{ $user->email }}</dd></div>
                        <div><dt class="acct-label">Account created</dt><dd class="font-semibold text-slate-800">{{ $user->created_at?->format('M j, Y') ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="space-y-6">
                    {{-- Profile form --}}
                    <form id="profileForm" method="POST" action="{{ route('account.profile') }}" class="acct-card" novalidate>
                        @csrf @method('PUT')
                        <p class="acct-card-title">Profile</p>
                        <p class="acct-card-sub">This name appears in the sidebar and in the records you create.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="acct-label" for="f-name">Name</label>
                                <input id="f-name" name="name" type="text" value="{{ $user->name }}" data-initial="{{ $user->name }}" required class="acct-input">
                                <p class="acct-field-error hidden" data-error="name"></p>
                            </div>
                            <div>
                                <label class="acct-label" for="f-email">Email</label>
                                <input id="f-email" name="email" type="email" value="{{ $user->email }}" data-initial="{{ $user->email }}" required class="acct-input">
                                <p class="acct-field-error hidden" data-error="email"></p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-3">
                            <button type="submit" class="gov-btn-camera acct-save" disabled><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
                            <span class="acct-status" data-status></span>
                        </div>
                    </form>

                    {{-- Password form --}}
                    <form id="passwordForm" method="POST" action="{{ route('account.password') }}" class="acct-card" novalidate>
                        @csrf @method('PUT')
                        <p class="acct-card-title">Change password</p>
                        <p class="acct-card-sub">Use at least 8 characters.</p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="acct-label" for="f-current">Current password</label>
                                <div class="relative">
                                    <input id="f-current" name="current_password" type="password" autocomplete="current-password" class="acct-input pr-9">
                                    <button type="button" class="acct-eye" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                                </div>
                                <p class="acct-field-error hidden" data-error="current_password"></p>
                            </div>
                            <div>
                                <label class="acct-label" for="f-new">New password</label>
                                <div class="relative">
                                    <input id="f-new" name="password" type="password" autocomplete="new-password" class="acct-input pr-9">
                                    <button type="button" class="acct-eye" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                                </div>
                                <p class="acct-field-error hidden" data-error="password"></p>
                            </div>
                            <div>
                                <label class="acct-label" for="f-confirm">Confirm new password</label>
                                <div class="relative">
                                    <input id="f-confirm" name="password_confirmation" type="password" autocomplete="new-password" class="acct-input pr-9">
                                    <button type="button" class="acct-eye" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-3">
                            <button type="submit" class="gov-btn-glass-outline acct-save" disabled><i class="fa-solid fa-key"></i> Update Password</button>
                            <span class="acct-status" data-status></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===================== ADMIN LOG ===================== --}}
        @if ($isAdmin)
        <div id="pane-log" class="{{ $initialTab === 'log' ? '' : 'hidden' }}">
            <form id="logFilters" data-url="{{ route('account.log') }}" class="mb-4 flex flex-col gap-3 sm:flex-row" onsubmit="return false;">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                    <input type="text" name="q" placeholder="Search by person, subject, details..." autocomplete="off" class="acct-input pl-9 text-xs">
                </div>
                <select name="action" class="acct-input sm:w-60">
                    <option value="">All actions</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}">{{ ucfirst(str_replace(['.', '_'], ' ', $a)) }}</option>
                    @endforeach
                </select>
            </form>

            <div id="logResults">
                <div class="animate-pulse space-y-3 py-2">
                    <div class="h-9 rounded-lg bg-slate-100"></div>
                    <div class="h-9 rounded-lg bg-slate-100"></div>
                    <div class="h-9 rounded-lg bg-slate-100"></div>
                    <div class="h-9 rounded-lg bg-slate-100"></div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // ---------- Tabs (no reload) ----------
    const tabs = document.querySelectorAll('.acct-tab');
    const panes = { profile: document.getElementById('pane-profile'), log: document.getElementById('pane-log') };
    let logLoaded = false;

    function showTab(name) {
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.tab === name));
        Object.entries(panes).forEach(([k, el]) => el && el.classList.toggle('hidden', k !== name));
        history.replaceState(null, '', name === 'log' ? '?tab=log' : window.location.pathname);
        if (name === 'log' && !logLoaded) loadLog();
    }
    tabs.forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));

    // ---------- Admin Log (async filter + pagination) ----------
    const results = document.getElementById('logResults');
    const filters = document.getElementById('logFilters');
    let logCtrl = null;
    let debounce;

    function logUrl() {
        const p = new URLSearchParams();
        new FormData(filters).forEach((v, k) => { if (v) p.set(k, v); });
        const qs = p.toString();
        return filters.dataset.url + (qs ? '?' + qs : '');
    }

    async function loadLog(url) {
        if (!results) return;
        if (logCtrl) logCtrl.abort();
        logCtrl = new AbortController();
        results.classList.add('is-loading');
        try {
            const res = await fetch(url || logUrl(), {
                signal: logCtrl.signal, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            results.innerHTML = await res.text();
            logLoaded = true;
        } catch (e) {
            if (e.name === 'AbortError') return;
            results.innerHTML = '<div class="py-14 text-center"><i class="fa-solid fa-triangle-exclamation text-2xl text-slate-300"></i>'
                + '<p class="mt-2 text-sm font-semibold text-slate-600">Couldn\'t load the log.</p>'
                + '<button type="button" data-retry class="gov-btn-glass-outline mt-3"><i class="fa-solid fa-rotate-right"></i> Try again</button></div>';
        }
        results.classList.remove('is-loading');
    }

    if (filters) {
        filters.querySelector('[name=q]').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(() => loadLog(), 300); });
        filters.querySelector('[name=action]').addEventListener('change', () => loadLog());
        results.addEventListener('click', e => {
            const page = e.target.closest('a.js-log-page');
            if (page) { e.preventDefault(); if (page.getAttribute('href') !== '#') loadLog(page.href); return; }
            if (e.target.closest('[data-retry]')) loadLog();
        });
    }

    // ---------- Forms (async, inline errors) ----------
    function setStatus(el, msg, kind) {
        el.textContent = msg;
        el.className = 'acct-status' + (kind ? ' is-' + kind : '');
        if (kind === 'ok') setTimeout(() => { if (el.textContent === msg) el.textContent = ''; }, 4000);
    }
    function clearErrors(form) {
        form.querySelectorAll('[data-error]').forEach(p => { p.textContent = ''; p.classList.add('hidden'); });
        form.querySelectorAll('.acct-input').forEach(i => i.classList.remove('has-error'));
    }
    function showErrors(form, errors) {
        Object.entries(errors).forEach(([key, msgs]) => {
            const p = form.querySelector('[data-error="' + key + '"]');
            const input = form.querySelector('[name="' + key + '"]');
            if (p) { p.textContent = msgs[0]; p.classList.remove('hidden'); }
            if (input) input.classList.add('has-error');
        });
    }

    function wireForm(form, onSuccess, refresh) {
        const btn = form.querySelector('button[type=submit]');
        const status = form.querySelector('[data-status]');
        form.addEventListener('input', refresh);
        form.addEventListener('submit', async e => {
            e.preventDefault();
            clearErrors(form);
            setStatus(status, '');
            const label = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
            try {
                const res = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    showErrors(form, data.errors || {});
                    setStatus(status, 'Please fix the highlighted fields.', 'error');
                } else if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                } else {
                    onSuccess(data);
                    setStatus(status, data.message || 'Saved.', 'ok');
                }
            } catch (err) {
                setStatus(status, 'Something went wrong. Please try again.', 'error');
            }
            btn.innerHTML = label;
            refresh();
        });
        refresh();
    }

    // Profile: Save enabled only when something changed
    const profileForm = document.getElementById('profileForm');
    const nameIn = profileForm.querySelector('[name=name]');
    const emailIn = profileForm.querySelector('[name=email]');
    wireForm(profileForm, data => {
        nameIn.value = nameIn.dataset.initial = data.name;
        emailIn.value = emailIn.dataset.initial = data.email;
        document.getElementById('acctName').textContent = data.name;
        document.getElementById('acctEmail').textContent = data.email;
        document.getElementById('acctAvatar').textContent =
            data.name.trim().split(/\s+/).slice(0, 2).map(w => w[0].toUpperCase()).join('');
        const greet = document.querySelector('.sidebar-greeting');
        if (greet) greet.textContent = 'Good day, ' + data.name + '!';
    }, () => {
        profileForm.querySelector('button[type=submit]').disabled =
            nameIn.value === nameIn.dataset.initial && emailIn.value === emailIn.dataset.initial;
    });

    // Password: enabled only when all three fields are filled
    const pwForm = document.getElementById('passwordForm');
    wireForm(pwForm, () => pwForm.reset(), () => {
        pwForm.querySelector('button[type=submit]').disabled =
            ![...pwForm.querySelectorAll('input[type=password], input[type=text]')].every(i => i.value);
    });

    // Show / hide password
    pwForm.addEventListener('click', e => {
        const eye = e.target.closest('.acct-eye');
        if (!eye) return;
        const input = eye.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        eye.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });

    // Initial tab
    showTab('{{ $initialTab }}');
})();
</script>
@endsection