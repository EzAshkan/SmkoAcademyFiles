{{-- Page header: "File Manager" title + breadcrumb pill, like the old site. --}}
<h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-gray-800">
    <svg class="h-6 w-6 text-indigo-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
    File Manager
</h2>
<nav aria-label="Breadcrumb" class="max-w-full overflow-x-auto">
    <ol class="flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-500">
        <li>
            <a href="{{ route('library.home') }}" class="flex items-center gap-1 hover:text-indigo-600">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 0 0-1.414 0l-7 7a1 1 0 0 0 1.414 1.414L4 10.414V17a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-6.586l.293.293a1 1 0 0 0 1.414-1.414l-7-7Z"/></svg>
                Home
            </a>
        </li>
        @foreach ($crumbs ?? [] as $c)
            <li class="text-gray-300">›</li>
            <li>
                @if ($loop->last)
                    <span class="font-medium text-gray-700">{{ $c['name'] }}</span>
                @else
                    <a href="{{ route('library.folder', $c['id']) }}" class="hover:text-indigo-600">{{ $c['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
