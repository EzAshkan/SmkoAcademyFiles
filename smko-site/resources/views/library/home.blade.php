@extends('layouts.app', ['title' => 'File Manager'])

@section('header')
    @include('library._header', ['crumbs' => []])
@endsection

@section('content')
<div class="space-y-6">
    @if ($recent->isNotEmpty())
        {{-- Recently added --}}
        <section class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                    </span>
                    Recently added
                </h3>
                @if (count($newIds))
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ count($newIds) }} new for you</span>
                @endif
            </div>
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recent as $asset)
                    <li>
                        <a href="{{ route('library.asset', $asset) }}"
                           class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2.5 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gray-50"><x-file-icon :type="$asset->type" :ext="$asset->file_ext" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-medium text-gray-900">{{ $asset->name }}</span>
                                    @isset($newIds[$asset->id])
                                        <span class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-px text-[10px] font-bold uppercase text-emerald-700">New</span>
                                    @endisset
                                </span>
                                <span class="block truncate text-xs text-gray-400">{{ \App\Support\FolderTree::path($asset->folder_id, ' › ') }} · {{ $asset->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
        @if ($folders->isEmpty())
            <div class="py-16 text-center text-gray-500">
                <svg class="mx-auto mb-3 h-12 w-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
                You don't have access to any folders yet.<br>Please contact SMKO Academy.
            </div>
        @else
            @include('library._folders', ['folders' => $folders, 'showParent' => true, 'newCounts' => $newCounts, 'expiries' => $expiries])
        @endif
    </section>
</div>
@endsection
