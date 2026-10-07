<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Visitor Access Control')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Google Font imports: UnifrakturMaguntia (header title, Old English Text MT fallback),
         Source Serif 4 (subtitles / eyebrows), Source Sans 3 (nav/body/UI) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/registration-modal.css') }}?v={{ filemtime(public_path('css/registration-modal.css')) }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/theme-govt.css') }}">
    <link rel="stylesheet" href="{{ asset('css/scanner-restyle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ filemtime(public_path('css/sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/glass-status.css') }}?v={{ filemtime(public_path('css/glass-status.css')) }}">
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
        ];
    @endphp

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
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
                {{-- Placeholder: no account-edit route/UI yet --}}
                <button type="button" class="sidebar-btn" aria-disabled="true" title="Coming soon" data-label="Edit Info">
                    <i class="fa-solid fa-user-pen"></i><span class="sidebar-label">Edit Info</span>
                </button>
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

        <main class="flex-grow max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
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

    if (action) {
        toast.querySelector('.gov-toast-action').addEventListener('click', () => {
            action.onClick();
            dismiss();
        });
    }

    const dismiss = () => {
        toast.classList.add('is-leaving');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    };

    const timer = setTimeout(dismiss, 5000);

    toast.querySelector('.gov-toast-action').addEventListener('click', () => {
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
    if (ok) HTMLFormElement.prototype.submit.call(form); // .submit() skips this handler
});

    @if (session('success'))
        document.addEventListener('DOMContentLoaded', () => {
            const passNumber = @json(session('success_pass_number'));
            const action = passNumber ? {
                label: 'View Pass',
                onClick: () => {
                    const btn = document.querySelector(`[data-pass-number="${passNumber}"][onclick^="openPassInfoModal"]`);
                    if (btn) btn.click();
                }
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
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.documentElement.classList.add('sb-animate'); // no animation on page load, only after a click
            const collapsed = document.documentElement.classList.toggle('sb-collapsed');
            try { localStorage.setItem('hg.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (_) {}
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
            tip.style.left = (sidebar.getBoundingClientRect().right + 12) + 'px';
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
        const ids = ['registerModal', 'inventoryModal', 'buildingPassesModal', 'passInfoModal',
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
        const sel = '.gov-building-card-photo img, .gov-info-photo, .result-photo img';
        const watch = img => {
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
        document.querySelectorAll(sel).forEach(watch);
    })();
    </script>

    @yield('scripts')

</body>
</html>