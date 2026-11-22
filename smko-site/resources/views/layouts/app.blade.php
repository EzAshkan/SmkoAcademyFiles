<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}SMKO Files</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-gray-900 antialiased">
    @auth
        <nav class="border-b border-gray-100 bg-white" x-data="{ menu: false }">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex h-full items-center gap-8">
                    <a href="{{ route('library.home') }}" class="shrink-0"><x-logo class="h-8 w-auto text-gray-800" /></a>
                    <a href="{{ route('library.home') }}"
                       class="hidden h-full items-center border-b-2 px-1 pt-1 text-sm font-medium sm:inline-flex {{ request()->routeIs('library.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                        Dashboard
                    </a>
                </div>

                <div class="relative" @click.outside="menu = false">
                    <button @click="menu = !menu" class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-700">
                        <span class="max-w-[10rem] truncate">{{ auth()->user()->name }}</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-cloak x-show="menu" x-transition.origin.top.right
                         class="absolute right-0 z-40 mt-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                        @if (auth()->user()->is_admin && ! auth()->user()->must_change_password)
                            <a href="/admin" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin panel</a>
                        @endif
                        <a href="{{ route('password.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Change password</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @else
        @if (\App\Support\Mode::isPublic())
            <nav class="border-b border-gray-100 bg-white">
                <div class="mx-auto flex h-16 max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                    <a href="{{ route('library.home') }}" class="shrink-0"><x-logo class="h-8 w-auto text-gray-800" /></a>
                </div>
            </nav>
        @endif
    @endauth

    @hasSection('header')
        <header class="bg-white shadow-sm">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
                @yield('header')
            </div>
        </header>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        @include('library._banners')
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
