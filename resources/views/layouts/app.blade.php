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
        <div class="gov-header-pattern" aria-hidden="true"></div>

        <div class="max-w-7xl mx-auto px-4 py-8 flex flex-wrap justify-between items-center gap-4 gov-header-inner">
            <div class="flex items-center gap-3">
                <div class="gov-brand-logo">
                    <img src="{{ asset('images/hrep-seal.png') }}" alt="House of Representatives">
                </div>
                <div class="flex flex-col">
                    <span class="gov-brand-title">House of Representatives</span>
                    <span class="gov-brand-subtitle-1">Legislative Security Bureau</span>
                    <span class="gov-brand-subtitle-2">Perimeter Security Group</span>
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
     function showToast(message, type = 'success', title = null) {
    let container = document.getElementById('govToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'govToastContainer';
        container.className = 'gov-toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `gov-toast is-${type}`;
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    const heading = title || (type === 'success' ? 'Success' : 'Something Went Wrong');
    const buttonLabel = type === 'success' ? 'Continue' : 'Try Again';
    toast.innerHTML = `
        <div class="gov-toast-icon"><i class="fa-solid ${icon}"></i></div>
        <div class="gov-toast-title">${heading}</div>
        <div class="gov-toast-message">${message}</div>
        <button type="button" class="gov-toast-action">${buttonLabel}</button>
    `;
    container.appendChild(toast);

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

    @if (session('success'))
        document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success')), 'success'));
    @endif
    @if ($errors->any())
        document.addEventListener('DOMContentLoaded', () => showToast(@json($errors->first()), 'error'));
    @endif
    </script>

    @yield('scripts')

</body>
</html>