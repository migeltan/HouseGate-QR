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
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col gov-page-bg">

    <header class="gov-header">
        {{-- Rattan pattern overlay, 75% opacity, sits above the solid blue fill and below all content --}}
        {{--<div class="gov-header-pattern" aria-hidden="true"></div>--}}

        <div class="max-w-7xl mx-auto px-4 py-8 flex flex-wrap justify-between items-center gap-4 gov-header-inner">
            <div class="flex items-center gap-3">
                <div class="gov-brand-logo">
                    <img src="{{ asset('images/hrep-seal.png') }}" alt="House of Representatives">
                </div>
                <div class="flex flex-col">
                    <span class="gov-brand-title">House of Representatives</span>
                    <span class="gov-card-subtitle-1">Legislative Security Bureau</span>
                    <span class="gov-card-subtitle-2">Perimeter Security Group</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5">
                @auth
                    <nav class="gov-nav flex gap-7">
                        <a href="{{ route('scanner.index') }}" class="gov-nav-link {{ request()->routeIs('scanner.*') ? 'is-active' : '' }}">Scanner</a>
                        <a href="{{ route('passes.index') }}" class="gov-nav-link {{ request()->routeIs('passes.*') ? 'is-active' : '' }}">Passes</a>
                        <a href="{{ route('logs.index') }}" class="gov-nav-link {{ request()->routeIs('logs.*') ? 'is-active' : '' }}">Logs</a>
                    </nav>

                    <div class="flex items-center gap-3 text-xs gov-header-meta">
                        <span class="gov-account-chip">
                            <i class="fa-solid fa-circle-user"></i>
                            {{ auth()->user()->name }} <span class="gov-account-role">({{ auth()->user()->role }})</span>
                        </span>
                        @if (auth()->user()->isGuard() && session('assigned_building_id'))
                            <span class="gov-badge-neutral">{{ \App\Models\Building::find(session('assigned_building_id'))?->name }}</span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="gov-btn-ghost px-3 py-1.5 rounded-full">Logout</button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </header>

        <main class="flex-grow max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        @yield('content')
      </main>

    <footer class="gov-footer">
        <img src="{{ asset('images/inspire-logo.png') }}" alt="House of Representatives INSPIRE">
    </footer>

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

    @yield('scripts')

</body>
</html>