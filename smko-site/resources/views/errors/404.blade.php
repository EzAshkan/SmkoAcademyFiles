@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404 · Lost in the library')
@section('heading', "We couldn't find that page")
@section('message', 'The file or folder may have been moved or removed, or the link is wrong.')

@section('illustration')
    {{-- A little lost purple folder floating among the stars, with a moon and a magic trail --}}
    <svg viewBox="0 0 320 256" class="h-full w-full overflow-visible">
        <defs>
            <linearGradient id="fold" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#a78bfa"/><stop offset="1" stop-color="#7c3aed"/>
            </linearGradient>
            <linearGradient id="foldBack" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#6d28d9"/>
            </linearGradient>
            <radialGradient id="glow"><stop offset="0" stop-color="#fde68a" stop-opacity=".9"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/></radialGradient>
        </defs>

        {{-- moon --}}
        <g class="smko-twinkle" style="--d:.2s">
            <circle cx="262" cy="46" r="34" fill="url(#glow)"/>
            <path d="M268 22a24 24 0 1 0 14 40 20 20 0 0 1-14-40Z" fill="#fcd34d"/>
        </g>
        {{-- stars --}}
        <g fill="#fbbf24">
            <path class="smko-twinkle" style="--d:0s" d="M40 40l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>
            <path class="smko-twinkle" style="--d:1.1s" d="M298 150l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>
            <path class="smko-twinkle" style="--d:1.8s" d="M24 168l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>
            <circle class="smko-twinkle" style="--d:.7s" cx="96" cy="24" r="2.5"/>
            <circle class="smko-twinkle" style="--d:2.3s" cx="210" cy="16" r="2"/>
            <circle class="smko-twinkle" style="--d:1.4s" cx="300" cy="104" r="2"/>
        </g>
        {{-- magic trail --}}
        <path d="M30 220 C 80 200, 100 230, 150 205" fill="none" stroke="#c4b5fd" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 10" opacity=".8"/>

        {{-- floating folder --}}
        <g class="smko-drift" style="transform-origin: 160px 140px">
            <ellipse cx="160" cy="236" rx="70" ry="8" fill="#7c3aed" opacity=".12"/>
            <path d="M88 92a12 12 0 0 1 12-12h38l14 14h68a12 12 0 0 1 12 12v8H88z" fill="url(#foldBack)"/>
            <rect x="80" y="104" width="160" height="100" rx="14" fill="url(#fold)"/>
            <rect x="80" y="104" width="160" height="22" rx="11" fill="#fff" opacity=".12"/>
            {{-- question mark --}}
            <g fill="#fff">
                <path d="M150 134c0-8 6.5-13 14-13 7.6 0 13.5 5 13.5 12.2 0 5.3-3.1 8.2-6.6 10.6-3 2-4.1 3.2-4.1 6.2v1.5h-7.7v-2c0-5 2.3-7.3 5.6-9.6 2.6-1.8 4.2-3 4.2-6 0-3-2.4-5.1-5.6-5.1-3.4 0-5.7 2.3-5.7 5.2z"/>
                <circle cx="163.2" cy="164" r="4.6"/>
            </g>
            {{-- tiny wings --}}
            <path d="M80 128c-18-10-30-4-34 6 10 0 18 4 34 6z" fill="#ede9fe" opacity=".9"/>
            <path d="M240 128c18-10 30-4 34 6-10 0-18 4-34 6z" fill="#ede9fe" opacity=".9"/>
        </g>
    </svg>
@endsection

@section('content')
    <form action="{{ url('/search') }}" method="GET" class="mt-8 w-full max-w-md">
        <label for="q" class="sr-only">Search for a file</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
            <input id="q" name="q" type="search" placeholder="Search for a file by name…" minlength="2" required dir="auto"
                   class="w-full rounded-2xl border-0 bg-white py-3.5 pl-12 pr-28 text-[15px] shadow-sm ring-1 ring-slate-200 outline-none transition focus:ring-2 focus:ring-violet-500 dark:bg-white/5 dark:ring-white/10">
            <button class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Search</button>
        </div>
    </form>

    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3 font-semibold text-white shadow-lg shadow-violet-600/25 transition hover:shadow-violet-600/40">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
            Go to my files
        </a>
        <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')"
                class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-white/10">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
            Go back
        </button>
    </div>

    <p class="mt-8 flex max-w-md items-start gap-2 rounded-xl bg-sky-50 px-4 py-3 text-left text-sm text-sky-800 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/20">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#229ED9]" viewBox="0 0 24 24" fill="currentColor"><path d="M21.94 4.3a1.1 1.1 0 0 0-1.13-.18L2.9 11.1c-.5.2-.83.7-.8 1.24.03.54.4 1 .93 1.13l4.32 1.1 1.67 5.06c.15.45.55.77 1.02.8h.08c.44 0 .85-.25 1.05-.65l1.96-3.86 4.2 3.1c.2.15.45.23.7.23.13 0 .27-.02.4-.07.37-.13.65-.45.73-.83L22.3 5.4c.09-.42-.05-.84-.36-1.1Z"/></svg>
        <span>Followed a link from Telegram? It may be old. Ask SMKO Academy for a new one.</span>
    </p>
@endsection
