<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LSB Visitor Access')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}?v={{ filemtime(public_path('images/favicon-32.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}?v={{ filemtime(public_path('images/apple-touch-icon.png')) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700;900&family=Source+Serif+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    {{-- Adjust to match however your app.css / app.js are actually loaded elsewhere in the project --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">

    <style>
        .font-heading { font-family: 'Source Serif Pro', Georgia, serif; }
        .font-body    { font-family: 'Source Sans Pro', system-ui, sans-serif; }
    </style>
</head>
<body class="font-body bg-slate-100 text-slate-900 antialiased">
    @yield('content')
</body>
</html>