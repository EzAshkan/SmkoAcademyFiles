@extends('layouts.app', ['title' => $folder->name])

@section('header')
    @include('library._header', ['crumbs' => $crumbs])
@endsection

@section('content')
<div x-data="fileManager({ assets: @js($assetData), openId: @js($openId), folderUrl: @js(route('library.folder', $folder)) })"
     @keydown.window.escape="current && close()"
     @keydown.window.arrow-right="current && step(1)"
     @keydown.window.arrow-left="current && step(-1)"
     class="rounded-xl bg-white p-4 shadow-sm sm:p-6">

    {{-- Toolbar --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
            Back to Parent
        </a>
        @if ($children->count() + $assets->count() > 8)
            <label class="relative w-full sm:w-64">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                <input x-model="query" type="search" placeholder="Search in this folder…"
                       class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
            </label>
        @endif
    </div>

    {{-- Sub-folders --}}
    @if ($children->isNotEmpty())
        <h3 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
            <svg class="h-4 w-4 text-violet-400" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
            Folders
        </h3>
        <div class="mb-8">
            @include('library._folders', ['folders' => $children, 'filterable' => true, 'newCounts' => $newCounts])
        </div>
    @endif

    {{-- Files --}}
    @if ($assets->isNotEmpty())
        <h3 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
            <svg class="h-4 w-4 text-indigo-500" viewBox="0 0 24 24" fill="currentColor"><path d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625Z"/></svg>
            Files
        </h3>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="w-14 px-4 py-3">Type</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="hidden px-4 py-3 md:table-cell">Size</th>
                        <th class="hidden px-4 py-3 sm:table-cell">Added</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($assets as $asset)
                        <tr x-show="matches(@js($asset->name))"
                            @click="show(assets.find(a => a.id === {{ $asset->id }}))"
                            class="cursor-pointer transition-colors hover:bg-indigo-50/50">
                            <td class="px-4 py-3"><x-file-icon :type="$asset->type" :ext="$asset->file_ext" /></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('library.asset', $asset) }}" @click.prevent class="font-medium text-gray-900">{{ $asset->name }}</a>
                                @isset($newIds[$asset->id])
                                    <span x-show="assets.find(a => a.id === {{ $asset->id }})?.is_new"
                                          class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">New</span>
                                @endisset
                                @unless ($asset->hasFile() || $asset->type === 'TEXT')
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">unavailable</span>
                                @endunless
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-gray-500 md:table-cell">{{ $asset->sizeForHumans() ?? '—' }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-gray-500 sm:table-cell">{{ $asset->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($children->isEmpty() && $assets->isEmpty())
        <div class="py-16 text-center text-gray-400">This folder is empty.</div>
    @endif
    <p x-cloak x-show="query && !visible.length && !$el.parentElement.querySelector('a[x-show]:not([style*=none])')"
       class="py-10 text-center text-sm text-gray-400">Nothing matches “<span x-text="query"></span>”.</p>

    @include('library._preview')
</div>
@endsection
