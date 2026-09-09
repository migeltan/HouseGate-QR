<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Visitor Access Control')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Google Font imports: UnifrakturMaguntia (nav header fallback), PT Serif (headings), Lato (body/UI) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&family=Source+Serif+4:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/registration-modal.css') }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/theme-govt.css') }}">
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="navbar-govt">
        {{-- Top-left yellow sunburst. Absolutely positioned against this header
             (not fixed) so it scrolls naturally with the page. --}}
        <div class="sunburst-gold" aria-hidden="true"></div>

        <div class="max-w-7xl mx-auto px-4 py-5 flex flex-wrap justify-between items-center gap-4 relative z-10">
            <div class="flex items-center gap-3">
                <div class="brand-logo">
                    <img src="{{ asset('images/hrep-seal.png') }}" alt="House of Representatives">
                </div>
                <div class="brand-title-group">
                    <span class="brand-title">House of Representatives</span>
                    <span class="brand-subtitle">Legislative Security Bureau, Perimeter Security Group</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5">
                @auth
                    <nav class="flex gap-5">
                        <a href="{{ route('scanner.index') }}" class="nav-link-govt {{ request()->routeIs('scanner.*') ? 'is-active' : '' }}">Scanner</a>
                        <a href="{{ route('passes.index') }}" class="nav-link-govt {{ request()->routeIs('passes.*') ? 'is-active' : '' }}">Passes</a>
                        <a href="{{ route('logs.index') }}" class="nav-link-govt {{ request()->routeIs('logs.*') ? 'is-active' : '' }}">Logs</a>
                    </nav>

                    <div class="flex items-center gap-3 text-xs">
                        <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                        @if (auth()->user()->isGuard() && session('assigned_building_id'))
                            <span class="badge-neutral">{{ \App\Models\Building::find(session('assigned_building_id'))?->name }}</span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-govt-ghost px-3 py-1.5 rounded-lg">Logout</button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        {{-- Bottom-left blue sunburst. Absolutely positioned against <main>
             (not fixed) so it scrolls naturally with the page content. --}}
        <div class="sunburst-blue" aria-hidden="true"></div>

        @if (session('success'))
            <div class="alert-govt-success px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @yield('content')
      </main>

    <footer class="site-footer">
        <img src="{{ asset('images/lsb-full-logo.png') }}" alt="INSPIRE Internship Program" class="footer-logo">
    </footer>

    @yield('scripts')

</body>
</html>