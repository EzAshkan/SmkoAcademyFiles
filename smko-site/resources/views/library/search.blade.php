@extends('layouts.app', ['title' => $q ? 'Search: '.$q : 'Search'])

@section('header')
    @include('library._header', ['crumbs' => [['id' => null, 'name' => 'Search']]])
@endsection

@section('content')
<div class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
    <form action="{{ route('library.search') }}" method="GET" class="mb-6">
        <label class="relative block max-w-xl">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
            <input name="q" type="search" value="{{ $q }}" placeholder="Search for a file or folder…" minlength="2" autofocus dir="auto"
                   class="w-full rounded-lg border border-gray-300 py-2.5 pl-11 pr-3 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
        </label>
    </form>

    @if (mb_strlen($q) < 2)
        <p class="py-10 text-center text-gray-400">Type at least 2 letters.</p>
    @elseif ($folders->isEmpty() && $assets->isEmpty())
        <p class="py-10 text-center text-gray-400">Nothing found for “{{ $q }}”.</p>
    @else
        @if ($folders->isNotEmpty())
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Folders</h3>
            <div class="mb-8">@include('library._folders', ['folders' => $folders, 'showParent' => true])</div>
        @endif
        @if ($assets->isNotEmpty())
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Files</h3>
            <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
                @foreach ($assets as $asset)
                    <li>
                        <a href="{{ route('library.asset', $asset) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50/50">
                            <x-file-icon :type="$asset->type" :ext="$asset->file_ext" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-gray-900" dir="auto">{{ $asset->name }}</span>
                                <span class="block truncate text-xs text-gray-400">{{ \App\Support\FolderTree::path($asset->folder_id, ' › ') }}</span>
                            </span>
                            <span class="hidden text-xs text-gray-400 sm:block">{{ $asset->sizeForHumans() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
@endsection
