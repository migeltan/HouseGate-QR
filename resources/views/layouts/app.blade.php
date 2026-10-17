<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Visitor Access Control')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Favicon: explicit links so every device finds it (versioned so browsers drop the old cached one) --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}?v={{ filemtime(public_path('images/favicon-32.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}?v={{ filemtime(public_path('images/apple-touch-icon.png')) }}">

    {{-- Self-hosted fonts (Source Sans 3 / Source Serif 4) and Font Awesome: no external CDN dependency --}}
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ filemtime(public_path('css/fonts.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/registration-modal.css') }}?v={{ filemtime(public_path('css/registration-modal.css')) }}">

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}?v={{ filemtime(public_path('vendor/fontawesome/css/all.min.css')) }}">
   <link rel="stylesheet" href="{{ asset('css/theme-govt.css') }}?v={{ filemtime(public_path('css/theme-govt.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/scanner-restyle.css') }}?v={{ filemtime(public_path('css/scanner-restyle.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ filemtime(public_path('css/sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/glass-status.css') }}?v={{ filemtime(public_path('css/glass-status.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/whats-new.css') }}?v={{ filemtime(public_path('css/whats-new.css')) }}">
    {{-- Pre-built Tailwind v3 (replaces the Play CDN). Kept LAST because the CDN used to inject its <style> at the end of <head>. --}}
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}?v={{ filemtime(public_path('css/tailwind.css')) }}">
    <script>try{if(localStorage.getItem('hg.sidebar')==='collapsed')document.documentElement.classList.add('sb-collapsed')}catch(e){}</script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased gov-page-bg">
<div class="app-shell">
@auth
    @php
        $authUser = auth()->user();
        $assignedName = $authUser->isGuard() && session('assigned_building_id')
            ? \App\Models\Building::find(session('assigned_building_id'))?->name
            : null;
        [$accessEyebrow, $accessName] = $authUser->isAdmin()
            ? ['Admin Access for', 'All Buildings']
            : ['Personnel Access at', $assignedName ?? 'Building not selected'];
        $accessLabel = "{$accessEyebrow} {$accessName}";
        $navItems = [
            ['scanner.index', 'scanner.*', 'fa-qrcode', 'Scanner'],
            ['passes.index',  'passes.*',  'fa-clipboard-list', 'Registry'],
            ['logs.index',    'logs.*',    'fa-clock-rotate-left', 'Audit Trail'],
            ['congressmen.index', 'congressmen.*', 'fa-address-book', 'Directory'],
        ];
    @endphp

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" data-label="Expand sidebar">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div class="sidebar-inner">
        <div class="sidebar-brand">
            <img src="{{ asset('images/lsb-seal.png') }}" alt="Legislative Security Bureau">
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">House of Representatives</span>
                <span class="sidebar-brand-sub1">Legislative Security Bureau</span>
                <span class="sidebar-brand-sub2">Perimeter Security Group</span>
            </div>
        </div>

        <div class="sidebar-context" data-label="{{ $accessLabel }}">
            <i class="fa-solid fa-building sidebar-context-icon"></i>
            <span class="sidebar-context-text sidebar-label">
                <span class="sidebar-context-eyebrow">{{ $accessEyebrow }}</span>
                <span class="sidebar-context-name">{{ $accessName }}</span>
            </span>
        </div>

        <ul class="sidebar-nav">
            @foreach ($navItems as [$route, $pattern, $icon, $label])
                <li>
                    <a href="{{ route($route) }}" data-label="{{ $label }}"
                       class="sidebar-link {{ request()->routeIs($pattern) ? 'is-active' : '' }}">
                        <i class="fa-solid {{ $icon }} sidebar-link-icon"></i>
                        <span class="sidebar-label">{{ $label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sidebar-footer">
            <div class="sidebar-footer-info">
                <span class="sidebar-role">{{ $authUser->role }}</span>
                <div class="sidebar-greeting">Good day, {{ $authUser->name }}!</div>
                <div class="sidebar-hint">Edit your account credentials<br>under here.</div>
            </div>
            <div class="sidebar-actions">
                <a href="{{ route('account.edit') }}" class="sidebar-btn {{ request()->routeIs('account.*') ? 'is-active' : '' }}" data-label="Edit Info">
                    <i class="fa-solid fa-user-pen"></i><span class="sidebar-label">Edit Info</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-btn is-logout" data-label="Log Out">
                        <i class="fa-solid fa-right-from-bracket"></i><span class="sidebar-label">Log Out</span>
                    </button>
                </form>
            </div>
        </div>
        </div>{{-- /sidebar-inner --}}
    </aside>
@endauth

<div class="app-content">
    @auth
        <div class="sidebar-topbar">
            <button type="button" id="sidebarOpen" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
            <img src="{{ asset('images/lsb-seal.png') }}" alt="">
            <strong>{{ $accessLabel }}</strong>
        </div>
    @endauth

<main class="flex-grow max-w-7xl w-full mx-auto p-4 pt-10 md:p-6 md:pt-12 space-y-6">
        @yield('content')
      </main>

    <footer class="gov-footer">
        <img src="{{ asset('images/inspire-logo.png') }}" alt="House of Representatives INSPIRE">
    </footer>
</div>{{-- /app-content --}}
</div>{{-- /app-shell --}}

        <script>
      function showToast(message, type = 'success', title = null, action = null) {
    let container = document.getElementById('govToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'govToastContainer';
        container.className = 'gov-toast-container';
        document.body.appendChild(container);
    }

    // Only one popup on screen at a time — a new call replaces whatever's showing
    // instead of stacking beside it (e.g. "Reading ID…" then the result).
    container.querySelectorAll('.gov-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = `gov-toast is-${type}`;
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    const heading = title || (type === 'success' ? 'Success' : 'Something Went Wrong');
    const buttonLabel = type === 'success' ? 'Continue' : 'Try Again';
    toast.innerHTML = `
        <div class="gov-toast-icon"><i class="fa-solid ${icon}"></i></div>
        <div class="gov-toast-title">${heading}</div>
        <div class="gov-toast-message">${message}</div>
        ${action ? `<button type="button" class="gov-toast-action" style="margin-bottom:0.6rem; background:transparent; color:var(--ink); border:1.5px solid #cbd5e1;">${action.label}</button>` : ''}
        <button type="button" class="gov-toast-action">${buttonLabel}</button>
    `;
    container.appendChild(toast);

    // With an extra action there are two buttons: [0] = the action (View Pass), last = Continue/Try Again.
    const actionBtns = toast.querySelectorAll('.gov-toast-action');
    const closeBtn = actionBtns[actionBtns.length - 1];

    const dismiss = () => {
        toast.classList.add('is-leaving');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    };

    const timer = setTimeout(dismiss, 5000);

    if (action) {
        actionBtns[0].addEventListener('click', () => {
            clearTimeout(timer);
            action.onClick();
            dismiss();
        });
    }

    closeBtn.addEventListener('click', () => {
        clearTimeout(timer);
        dismiss();
    });
}

function dismissAllToasts() {
    document.querySelectorAll('.gov-toast').forEach(t => t.querySelector('.gov-toast-close')?.click());
}

// Styled replacement for window.confirm(). Resolves true/false.
// All text goes in via textContent, so visitor names can't inject markup.
function confirmDialog({ title = 'Are you sure?', subject = '', message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel', tone = 'danger' } = {}) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'gov-toast-container';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        const icon = tone === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-question';
        overlay.innerHTML = `
            <div class="gov-toast gov-confirm is-${tone}">
                <div class="gov-toast-icon"><i class="fa-solid ${icon}"></i></div>
                <div class="gov-toast-title"></div>
                <div class="gov-confirm-subject"></div>
                <div class="gov-toast-message"></div>
                <div class="gov-confirm-actions">
                    <button type="button" class="gov-confirm-cancel"></button>
                    <button type="button" class="gov-confirm-ok"></button>
                </div>
            </div>`;
        const box = overlay.querySelector('.gov-confirm');
        const q = (sel) => overlay.querySelector(sel);
        q('.gov-toast-title').textContent = title;
        q('.gov-confirm-subject').textContent = subject;
        q('.gov-confirm-subject').hidden = !subject;
        q('.gov-toast-message').textContent = message;
        q('.gov-confirm-cancel').textContent = cancelLabel;
        q('.gov-confirm-ok').textContent = confirmLabel;
        overlay.setAttribute('aria-label', title);

        const close = (result) => {
            document.removeEventListener('keydown', onKey);
            box.classList.add('is-leaving');
            setTimeout(() => overlay.remove(), 180);
            resolve(result);
        };
        const onKey = (e) => { if (e.key === 'Escape') close(false); };
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) close(false); });
        q('.gov-confirm-cancel').addEventListener('click', () => close(false));
        q('.gov-confirm-ok').addEventListener('click', () => close(true));

        document.body.appendChild(overlay);
        q('.gov-confirm-cancel').focus(); // safe default for destructive actions
    });
}

// <form data-confirm data-confirm-title="…" data-confirm-message="…"> gets a styled
// confirmation before it submits. Values are HTML-escaped by Blade, never JS strings.
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
    e.preventDefault();
    const d = form.dataset;
    const ok = await confirmDialog({
        title: d.confirmTitle, subject: d.confirmSubject, message: d.confirmMessage,
        confirmLabel: d.confirmLabel, tone: d.confirmTone || 'danger',
    });
    if (!ok) return;
    if (form.hasAttribute('data-inplace') && typeof window.submitPassAction === 'function') {
        window.submitPassAction(form);                    // background request, no reload
    } else {
        HTMLFormElement.prototype.submit.call(form);      // .submit() skips this handler
    }
});

    @if (session('success'))
        document.addEventListener('DOMContentLoaded', () => {
            const passInfo = @json(session('success_pass_info'));
            const action = (passInfo && typeof openPassInfoModal === 'function') ? {
                label: 'View Pass',
                onClick: () => openPassInfoModal(passInfo),
            } : null;
            showToast(@json(session('success')), 'success', null, action);
        });
    @endif
    @if ($errors->any())
        document.addEventListener('DOMContentLoaded', () => showToast(@json($errors->first()), 'error'));
    @endif
    </script>

    <script>
    (function () {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const backdrop = document.getElementById('sidebarBackdrop');
        const setOpen = (open) => {
            sidebar.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-open', open);
        };
        // Collapsed rail: hovering only hints (CSS), a click anywhere on it opens it.
        const root = document.documentElement;
        const setCollapsed = (collapsed) => {
            root.classList.add('sb-animate'); // no animation on page load, only after a click
            root.classList.toggle('sb-collapsed', collapsed);
            try { localStorage.setItem('hg.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (_) {}
        };
        document.getElementById('sidebarToggle').addEventListener('click', () => setCollapsed(!root.classList.contains('sb-collapsed')));
        sidebar.addEventListener('click', (e) => {
            if (innerWidth < 768 || !root.classList.contains('sb-collapsed')) return;
            if (e.target.closest('a, button, input, select, textarea')) return;   // links, logout and the chevron keep their own job
            setCollapsed(false);
        });
        sidebar.addEventListener('click', (e) => {
            if (!isDesktop() || !root.classList.contains('sb-collapsed')) return;
            if (e.target.closest('a, button, input, select, textarea')) return;   // links, logout and the chevron keep their own job
            setCollapsed(false);
        });
        const startPeek = () => {
            if (peekIn || sidebar.classList.contains('is-peek') || !armed || !moved || !isDesktop() || !root.classList.contains('sb-collapsed')) return;
            clearTimeout(peekOut);
            // short delay so the mouse just passing by doesn't fling the sidebar open
            peekIn = setTimeout(() => { peekIn = null; root.classList.add('sb-animate'); sidebar.classList.add('is-peek'); hideTip(); }, 120);
        };
        sidebar.addEventListener('pointerenter', (e) => {
            clearTimeout(peekOut);
            if (e.pointerType === 'mouse') { if (e.movementX || e.movementY) moved = true; startPeek(); }
        });
        sidebar.addEventListener('pointermove', (e) => {
            if (e.pointerType === 'mouse' && (e.movementX || e.movementY)) { moved = true; startPeek(); }
        });
        sidebar.addEventListener('pointerleave', () => {
            armed = true;
            clearTimeout(peekIn); peekIn = null;
            peekOut = setTimeout(() => sidebar.classList.remove('is-peek'), 100);
        });
        document.getElementById('sidebarOpen').addEventListener('click', () => setOpen(true));
        backdrop.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

        // Collapsed-state tooltips live on <body> so the sidebar's clipping can't cut them off
        const tip = document.createElement('div');
        tip.className = 'sidebar-tip';
        document.body.appendChild(tip);
        const showTip = (e) => {
            const el = e.target.closest('[data-label]');
            if (!el || !document.documentElement.classList.contains('sb-collapsed') || innerWidth < 768) return;
            const r = el.getBoundingClientRect();
            tip.textContent = el.dataset.label;
            // the button straddles the sidebar's edge, so its label starts past the button instead of under it
            tip.style.left = (el.id === 'sidebarToggle' ? r.right + 8 : sidebar.getBoundingClientRect().right + 12) + 'px';
            tip.style.top = (r.top + r.height / 2) + 'px';
            tip.classList.add('is-visible');
        };
        const hideTip = () => tip.classList.remove('is-visible');
        sidebar.addEventListener('mouseover', showTip);
        sidebar.addEventListener('mouseout', hideTip);
        sidebar.addEventListener('focusin', showTip);
        sidebar.addEventListener('focusout', hideTip);
        document.getElementById('sidebarToggle').addEventListener('click', hideTip);
    })();
    </script>

    <script>
    // Shared modal behaviour for every overlay: lifted to <body> (fixes the 24px
    // space-y-6 offset), tagged for the open animation, page-scroll lock, Esc/backdrop close.
    (function () {
        // Order matters: later entries stack above earlier ones.
        const ids = ['registerModal', 'inventoryModal', 'awayModal', 'buildingPassesModal', 'passInfoModal', 'passQrModal', 'passHoldModal',
                     'rowDetailsModalOverlay', 'purgeModalOverlay', 'confirmPurgeOverlay'];
        const overlays = ids.map(id => document.getElementById(id)).filter(Boolean);
        if (!overlays.length) return;

        // Register modal holds a half-filled form + camera photos: X / Cancel only.
        const NO_DISMISS = ['registerModal'];
        const isOpen = el => !el.classList.contains('hidden');
        const sync = () => { document.body.style.overflow = overlays.some(isOpen) ? 'hidden' : ''; };
        const closeOf = el => {
            const btn = el.querySelector('.reg-close-button, [aria-label="Close"], [onclick*="close" i]');
            btn ? btn.click() : el.classList.add('hidden');
        };

        overlays.forEach(el => {
            document.body.appendChild(el);
            el.classList.add('hg-overlay');
            new MutationObserver(sync).observe(el, { attributes: true, attributeFilter: ['class'] });

            let downOnBackdrop = false; // ignore text-selection drags that end on the backdrop
            el.addEventListener('mousedown', e => { downOnBackdrop = e.target === el; });
            el.addEventListener('click', e => {
                if (e.target === el && downOnBackdrop && !NO_DISMISS.includes(el.id)) closeOf(el);
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const top = [...overlays].reverse().find(isOpen);
            if (top && !NO_DISMISS.includes(top.id)) closeOf(top);
        });
    })();
    </script>

    <script>
    // Skeleton for photos: shimmer sits on the <img> itself until it has loaded.
    // Covers building cards, scanner result photo, pass-info + log-row photos.
    (function () {
        const sel = '.gov-building-card-photo img, .gov-info-photo, .result-photo img, .dir-photo, #mmPhoto';
        const watched = new WeakSet();   // scan() can run again on the same <img> without stacking listeners
        const watch = img => {
            if (watched.has(img)) return;
            watched.add(img);
            const start = () => {
                if (img.getAttribute('src') && !(img.complete && img.naturalWidth)) img.classList.add('sk-img');
            };
            const done = () => {
                if (img.classList.contains('sk-img')) { img.classList.remove('sk-img'); img.classList.add('sk-fade'); }
            };
            img.addEventListener('load', done);
            img.addEventListener('error', () => img.classList.remove('sk-img'));
            new MutationObserver(start).observe(img, { attributes: true, attributeFilter: ['src'] });
            start();
        };
        const scan = (root = document) => root.querySelectorAll(sel).forEach(watch);
        scan();
        window.hgWatchPhotos = scan;   // call after JS renders more photos (the Directory list does)
    })();
    </script>

    <script>
    // Phase 9: the cached Directory page carries the signed-in user's name and a CSRF token,
    // so drop it on logout. (No-op over plain HTTP, where the Cache API doesn't exist.)
    (function () {
        const form = document.querySelector('form[action="{{ route('logout') }}"]');
        if (!form || !window.caches) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            try { await caches.delete('hg-dir-pages'); } catch (_) {}
            HTMLFormElement.prototype.submit.call(form);   // .submit() skips this handler
        });
    })();
    </script>

    {{-- "What's new": once per user per update id (config/whatsnew.php) --}}
    @auth
        @php $whatsNew = config('whatsnew'); @endphp
        @if (! empty($whatsNew['id']) && auth()->user()->last_seen_update !== $whatsNew['id'] && ! session('whatsnew_closed'))
            @include('partials.whats-new', ['whatsNew' => $whatsNew])
        @endif
    @endauth

    {{-- Connection status pill: Online / Offline / Can't reach server. Shown on every page for signed-in users. --}}
    @auth
        <div id="hgConn" class="hg-conn" data-state="online" data-url="{{ url('/up') }}" role="status" aria-live="polite">
            <span class="hg-conn-dot" aria-hidden="true"></span>
            <span class="hg-conn-label">Online</span>
        </div>
        <script>
        (function () {
            const pill = document.getElementById('hgConn');
            if (!pill) return;
            const label = pill.querySelector('.hg-conn-label');
            const PROBE_MS = 20000, TIMEOUT_MS = 5000;
            let state = 'online', probing = false;

            function paint(next) {
                if (next === state) return;
                state = next;
                pill.dataset.state = next;
                label.textContent = next === 'online' ? 'Online'
                    : next === 'offline' ? 'Offline'
                    : "Can't reach server";
            }
            // Other scripts (the upcoming live updates) can report the outcome of their own requests here.
            function report(ok) {
                if (!navigator.onLine) paint('offline');
                else paint(ok ? 'online' : 'unreachable');
            }
            async function probe() {
                if (probing) return;
                if (!navigator.onLine) { paint('offline'); return; }
                probing = true;
                const ctl = new AbortController();
                const t = setTimeout(() => ctl.abort(), TIMEOUT_MS);
                try {
                    const res = await fetch(pill.dataset.url, { method: 'HEAD', cache: 'no-store', credentials: 'omit', signal: ctl.signal });
                    report(res.ok);
                } catch (_) {
                    report(false);
                } finally {
                    clearTimeout(t);
                    probing = false;
                }
            }

            window.addEventListener('offline', () => paint('offline'));
            window.addEventListener('online', probe);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) probe(); });
            setInterval(() => { if (!document.hidden) probe(); }, PROBE_MS);
            window.hgConn = { report, probe, get state() { return state; } };
            probe();
        })();
        </script>
    @endauth

    {{-- Live updates: pages register "regions" (window.hgLive.register); one poll covers them all. --}}
    @auth
        <script>
        (function () {
            const URL_ = @json(route('live.pulse'));
            const BASE_MS = 5000, MAX_MS = 60000, IDLE_MS = 15 * 60 * 1000, TIMEOUT_MS = 8000;
            const regions = {};   // name -> { sig, apply(data), busy() }
            let delay = BASE_MS, timer = null, inflight = false, stopped = false, lastInput = Date.now();

            // Polls only while the tab is visible, the browser is online, and the user was active in the last
            // 15 minutes. The idle cutoff matters: every poll is a normal request, so without it an open tab
            // would keep the session alive forever and defeat the idle timeout.
            function active() { return !document.hidden && navigator.onLine && Date.now() - lastInput < IDLE_MS; }
            function schedule() { clearTimeout(timer); if (!stopped) timer = setTimeout(poll, delay); }
            function stop() {
                stopped = true;
                clearTimeout(timer);
                if (typeof showToast === 'function') showToast('Live updates stopped because your session changed. Reload the page to continue.', 'error', 'Live Updates Paused');
            }

            async function poll() {
                clearTimeout(timer);
                const names = Object.keys(regions);
                if (stopped || !names.length) return;
                if (!active() || inflight) { schedule(); return; }
                inflight = true;
                const qs = new URLSearchParams();
                names.forEach(n => {
                    qs.set(n, regions[n].sig);
                    // a region may send extra context (e.g. the filters it is showing)
                    if (regions[n].params) Object.entries(regions[n].params()).forEach(([k, v]) => qs.set(k, v));
                });
                const ctl = new AbortController();
                const t = setTimeout(() => ctl.abort(), TIMEOUT_MS);
                try {
                    const res = await fetch(URL_ + '?' + qs, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin', cache: 'no-store', signal: ctl.signal,
                    });
                    if (res.redirected || [401, 403, 419].includes(res.status)) { stop(); return; }
                    if (!res.ok) throw new Error('http ' + res.status);
                    const data = await res.json();
                    delay = BASE_MS;
                    if (window.hgConn) window.hgConn.report(true);   // feeds the Online / Offline pill
                    names.forEach(n => {
                        const d = data[n], r = regions[n];
                        if (!d || !d.changed) return;
                        if (r.busy && r.busy()) return;   // user is mid-action: picked up on the next poll
                        r.sig = d.sig;
                        r.apply(d);
                    });
                } catch (_) {
                    delay = Math.min(delay * 2, MAX_MS);   // back off while the server is struggling
                    if (window.hgConn) window.hgConn.report(false);
                } finally {
                    clearTimeout(t);
                    inflight = false;
                    schedule();
                }
            }

            function refresh() { delay = BASE_MS; poll(); }
            function onInput() {
                const wasIdle = Date.now() - lastInput >= IDLE_MS;
                lastInput = Date.now();
                if (wasIdle) refresh();   // back after a pause: catch up immediately
            }
            ['pointerdown', 'keydown', 'mousemove', 'touchstart', 'scroll'].forEach(ev =>
                window.addEventListener(ev, onInput, { passive: true, capture: true }));
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
            window.addEventListener('online', refresh);

            window.hgLive = {
                register(name, region) { regions[name] = region; schedule(); },
                setSig(name, sig) { if (regions[name]) regions[name].sig = sig; },
                refresh,
            };
        })();
        </script>
    @endauth

    {{-- App-wide static-asset cache (css/js/fonts/images only). See public/app-sw.js. --}}
    @auth
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register(@json(parse_url(asset('app-sw.js'), PHP_URL_PATH))).catch(() => {});
            });
        }
        </script>
    @endauth

    @yield('scripts')

</body>
</html>