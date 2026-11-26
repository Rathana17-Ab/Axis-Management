<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Axis Management — Project hub')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="h-screen w-screen overflow-hidden flex flex-col selection:bg-sky-500/30 selection:text-sky-200">

    @yield('content')

    {{-- Micro-interaction engines: runButtonLifecycle and triggerIconLifecycle --}}
    <script src="{{ asset('js/axis-ui-engine.js') }}?v={{ filemtime(public_path('js/axis-ui-engine.js')) }}"></script>
    <script src="{{ asset('js/axis-drawer.js') }}?v={{ filemtime(public_path('js/axis-drawer.js')) }}"></script>
    @stack('scripts')
</body>
</html>
