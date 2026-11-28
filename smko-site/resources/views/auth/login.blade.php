<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0b1020" media="(prefers-color-scheme: dark)">
    <title>Log in · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-[#0b1020] dark:text-slate-100">
<div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">

    {{-- ───────── Left: SMKO world (computers only) ───────── --}}
    <aside class="relative hidden overflow-hidden bg-gradient-to-br from-violet-700 via-indigo-700 to-violet-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
        {{-- glow + sun --}}
        <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-amber-400/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 22px 22px;"></div>

        <a href="{{ url('/') }}" class="relative inline-flex w-fit items-center rounded-2xl bg-white/95 px-4 py-2.5 shadow-lg shadow-black/10">
            <x-logo class="h-9 w-auto text-gray-800" />
        </a>

        {{-- floating cards --}}
        <div class="relative mx-auto my-10 h-80 w-full max-w-md" aria-hidden="true">
            <div class="smko-float absolute left-4 top-6 w-56 rounded-2xl bg-white/95 p-4 shadow-2xl shadow-indigo-950/40 [--d:0s]">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-100 text-violet-500">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
                    </span>
                    <div><p class="text-sm font-semibold text-slate-800">E4E1</p><p class="text-xs text-slate-400">80 lessons</p></div>
                    <span class="ml-auto rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">3 new</span>
                </div>
            </div>
            <div class="smko-float absolute right-2 top-28 w-60 rounded-2xl bg-white/95 p-4 shadow-2xl shadow-indigo-950/40 [--d:1.2s]">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-teal-50 text-teal-500">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19.952 1.651a.75.75 0 0 1 .298.599V16.303a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.403-4.909l2.311-.66a1.5 1.5 0 0 0 1.088-1.442V6.994l-9 2.572v9.737a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.402-4.909l2.31-.66a1.5 1.5 0 0 0 1.088-1.442V5.25a.75.75 0 0 1 .544-.721l10.5-3a.75.75 0 0 1 .658.122Z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800">Track 1.5</p>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="smko-progress h-full rounded-full bg-teal-400"></div></div></div>
                </div>
            </div>
            <div class="smko-float absolute bottom-2 left-12 w-52 rounded-2xl bg-white/95 p-4 shadow-2xl shadow-indigo-950/40 [--d:2.4s]">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-500">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625Z" clip-rule="evenodd"/></svg>
                    </span>
                    <div><p class="text-sm font-semibold text-slate-800">Worksheet.pdf</p><p class="text-xs text-slate-400">Opens in the page</p></div>
                </div>
            </div>
            <span class="smko-twinkle absolute right-16 top-0 text-2xl text-amber-300 [--d:.4s]">✦</span>
            <span class="smko-twinkle absolute bottom-16 right-6 text-lg text-white/80 [--d:1.6s]">✦</span>
            <span class="smko-twinkle absolute left-0 top-40 text-sm text-amber-200 [--d:2.2s]">✦</span>
        </div>

        <div class="relative">
            <h2 class="text-3xl font-extrabold leading-tight text-white">Your course files,<br><span class="text-amber-300">anywhere.</span></h2>
            <p class="mt-3 max-w-md text-indigo-100/80">Lessons, audio, videos and worksheets from SMKO Academy, all in one place.</p>
        </div>
    </aside>

    {{-- ───────── Right: the login card ───────── --}}
    <main class="flex items-center justify-center px-5 py-10 sm:px-8">
        <div class="w-full max-w-md"
             x-data="{
                phone: @js(old('phone', '')),
                show: false,
                loading: false,
                forgot: false,
                wait: {{ (int) session('lockout_seconds', 0) }},
                digits(v) {
                    // Persian ۰-۹ and Arabic ٠-٩ → 0-9; no spaces
                    const fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
                    return v.replace(/[۰-۹٠-٩]/g, d => (fa.indexOf(d) > -1 ? fa.indexOf(d) : ar.indexOf(d))).replace(/\s+/g, '');
                },
                init() {
                    if (this.wait > 0) {
                        const t = setInterval(() => { if (--this.wait <= 0) { this.wait = 0; clearInterval(t); } }, 1000);
                    }
                },
                submit(e) {
                    if (this.loading || this.wait > 0) { e.preventDefault(); return; }
                    this.loading = true;
                },
             }">

            {{-- logo on phones --}}
            <a href="{{ url('/') }}" class="mb-8 inline-flex rounded-2xl bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200 lg:hidden dark:ring-white/10">
                <x-logo class="h-8 w-auto text-gray-800" />
            </a>

            <div class="rounded-3xl bg-white p-7 shadow-xl shadow-slate-200/60 ring-1 ring-slate-200/70 sm:p-9 dark:bg-slate-900 dark:shadow-black/40 dark:ring-white/10">
                <h1 class="text-2xl font-extrabold tracking-tight">Welcome back <span class="inline-block origin-[70%_70%] motion-safe:animate-[smko-wave_2.4s_ease-in-out_1]">👋</span></h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Log in with your username</p>

                {{-- friendly errors --}}
                <template x-if="wait > 0">
                    <div class="mt-6 flex items-center gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30" role="alert">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                        <span>Too many tries, please wait <strong class="tabular-nums" x-text="wait"></strong> <span x-text="wait === 1 ? 'second' : 'seconds'"></span>.</span>
                    </div>
                </template>
                @error('phone')
                    @unless (session('lockout_seconds'))
                        <div class="mt-6 flex items-center gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-200 dark:ring-red-500/30" role="alert">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @endunless
                @enderror

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5" @submit="submit($event)">
                    @csrf

                    {{-- username --}}
                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-semibold">Username</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                            <input id="phone" name="phone" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                                   dir="ltr" placeholder="Your username"
                                   x-model="phone" @input="phone = digits($event.target.value)"
                                   class="w-full rounded-xl border-0 bg-slate-50 py-3 pl-11 pr-4 text-[15px] tracking-wide ring-1 ring-slate-200 outline-none transition placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-violet-500 dark:bg-white/5 dark:ring-white/10 dark:focus:bg-white/10">
                        </div>
                    </div>

                    {{-- password --}}
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold">Password</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                            <input id="password" name="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                                   class="w-full rounded-xl border-0 bg-slate-50 py-3 pl-11 pr-12 text-[15px] ring-1 ring-slate-200 outline-none transition focus:bg-white focus:ring-2 focus:ring-violet-500 dark:bg-white/5 dark:ring-white/10 dark:focus:bg-white/10">
                            <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'"
                                    class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-white/10 dark:hover:text-slate-200">
                                <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <label class="flex cursor-pointer items-center gap-2 text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500 dark:border-white/20 dark:bg-white/5">
                            Remember me
                        </label>
                        <button type="button" @click="forgot = !forgot" class="font-semibold text-violet-600 hover:text-violet-500 dark:text-violet-400">Forgot your password?</button>
                    </div>

                    <div x-show="forgot" x-cloak x-transition.opacity
                         class="flex items-start gap-3 rounded-xl bg-violet-50 px-4 py-3 text-sm text-violet-900 ring-1 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-100 dark:ring-violet-400/30">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#229ED9]" viewBox="0 0 24 24" fill="currentColor"><path d="M21.94 4.3a1.1 1.1 0 0 0-1.13-.18L2.9 11.1c-.5.2-.83.7-.8 1.24.03.54.4 1 .93 1.13l4.32 1.1 1.67 5.06c.15.45.55.77 1.02.8h.08c.44 0 .85-.25 1.05-.65l1.96-3.86 4.2 3.1c.2.15.45.23.7.23.13 0 .27-.02.4-.07.37-.13.65-.45.73-.83L22.3 5.4c.09-.42-.05-.84-.36-1.1Z"/></svg>
                        <span>Contact SMKO Academy on Telegram:
                            <a href="https://t.me/techsupportsa" target="_blank" rel="noopener" class="font-semibold underline decoration-2 underline-offset-2">@techsupportsa</a></span>
                    </div>

                    <button type="submit" :disabled="loading || wait > 0"
                            class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-3.5 text-base font-bold text-white shadow-lg shadow-violet-600/25 transition hover:shadow-violet-600/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 dark:focus-visible:ring-offset-slate-900">
                        <span class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition duration-700 group-hover:translate-x-full"></span>
                        <svg x-show="loading" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        <span x-text="loading ? 'Logging in…' : (wait > 0 ? 'Please wait…' : 'Log in')">Log in</span>
                        <svg x-show="!loading && !wait" class="h-5 w-5 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">© SMKO Academy</p>
        </div>
    </main>
</div>
</body>
</html>
