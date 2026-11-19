{{-- Purple folder tiles, like the old site. $folders: collection; $showParent: show parent path under the name. --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 sm:gap-4">
    @foreach ($folders as $folder)
        <a href="{{ route('library.folder', $folder) }}"
           @isset($filterable) x-show="matches(@js($folder->name))" @endisset
           class="group relative flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            @if ($n = ($newCounts ?? [])[$folder->id] ?? 0)
                <span class="absolute right-2 top-2 rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm" title="{{ $n }} new {{ \Illuminate\Support\Str::plural('file', $n) }}">{{ $n }} new</span>
            @endif
            <svg class="h-11 w-11 text-violet-400 transition group-hover:text-violet-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/>
            </svg>
            <span class="line-clamp-2 text-sm font-medium text-gray-700 group-hover:text-gray-900">{{ $folder->name }}</span>
            @if ($ends = ($expiries ?? [])[$folder->id] ?? null)
                <span class="-mt-1 text-[11px] font-medium {{ $ends->lte(now()->addDays(7)) ? 'text-amber-600' : 'text-gray-400' }}">until {{ $ends->format('M j, Y') }}</span>
            @endif
            @if (! empty($showParent) && $folder->parent_id)
                <span class="-mt-1 line-clamp-1 text-[11px] text-gray-400">{{ \App\Support\FolderTree::path($folder->parent_id) }}</span>
            @endif
        </a>
    @endforeach
</div>
