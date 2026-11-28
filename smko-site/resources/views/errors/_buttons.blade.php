<div class="mt-8 flex flex-wrap items-center justify-center gap-3">
    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3 font-semibold text-white shadow-lg shadow-violet-600/25 transition hover:shadow-violet-600/40">Go to my files</a>
    @isset($refresh)
        <button type="button" onclick="location.reload()" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-white/10">Refresh the page</button>
    @else
        <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-white/10">Go back</button>
    @endisset
</div>
