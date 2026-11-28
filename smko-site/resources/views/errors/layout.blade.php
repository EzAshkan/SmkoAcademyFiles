{{-- Shared layout for error pages. Uses no database, so it also works when something is down. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1020" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name', 'SMKO Files') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @php($vite = file_exists(public_path('build/manifest.json')))
    @if ($vite) @vite(['resources/css/app.css', 'resources/js/app.js']) @endif
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 antialiased dark:bg-[#0b1020] dark:text-slate-100">
    {{-- soft night-sky background --}}
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute -left-32 -top-32 h-[30rem] w-[30rem] rounded-full bg-violet-300/30 blur-3xl dark:bg-violet-600/20"></div>
        <div class="absolute -right-24 top-1/3 h-[26rem] w-[26rem] rounded-full bg-amber-200/40 blur-3xl dark:bg-amber-500/10"></div>
        <div class="absolute inset-0 opacity-[0.35] dark:opacity-60" style="background-image: radial-gradient(rgba(124,58,237,.18) 1px, transparent 1px); background-size: 26px 26px;"></div>
    </div>

    <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-5 py-8 sm:px-8">
        <header>
            <a href="{{ url('/') }}" class="inline-flex rounded-2xl bg-white/90 px-4 py-2.5 shadow-sm ring-1 ring-slate-200 backdrop-blur dark:ring-white/10">
                <x-logo class="h-8 w-auto text-gray-800" />
            </a>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center py-10 text-center">
            <div class="relative mb-6 h-56 w-72 sm:h-64 sm:w-80" aria-hidden="true">
                @yield('illustration')
            </div>
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.25em] text-violet-500 dark:text-violet-400">@yield('code')</p>
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">@yield('heading')</h1>
            <p class="mx-auto mt-3 max-w-lg text-base text-slate-500 dark:text-slate-400">@yield('message')</p>
            @yield('content')
        </main>

        <footer class="text-center text-xs text-slate-400">© SMKO Academy</footer>
    </div>
</body>
</html>
