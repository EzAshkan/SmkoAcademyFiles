@php($gb = fn ($b) => \App\Filament\Widgets\TrafficOverview::gb($b))
@php($colors = ['AUDIO' => 'bg-teal-400', 'VIDEO' => 'bg-violet-500', 'DOCUMENT' => 'bg-red-400', 'PHOTO' => 'bg-amber-400', 'TEXT' => 'bg-sky-400'])
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-circle-stack" icon-color="warning">
        <x-slot name="heading">Bucket usage</x-slot>
        <x-slot name="description">Files of this site in ParsPack · updated {{ $updated->diffForHumans() }}</x-slot>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Stored</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $gb($total) }}</p>
                <p class="text-xs text-gray-400">{{ number_format($objects) }} files in the bucket</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Library entries</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($entries) }}</p>
                <p class="text-xs text-gray-400">{{ number_format($shared) }} share a stored file (copies / reused)</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-xs text-emerald-700 dark:text-emerald-300">Saved by copies & duplicate detection</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">{{ $gb($saved) }}</p>
            </div>
        </div>

        @if ($total)
            <div class="mt-5">
                <div class="flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    @foreach ($byType as $type => $bytes)
                        <div class="{{ $colors[$type] ?? 'bg-gray-400' }}" style="width: {{ $bytes / $total * 100 }}%" title="{{ ucfirst(strtolower($type)) }}: {{ $gb($bytes) }}"></div>
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    @foreach ($byType as $type => $bytes)
                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm {{ $colors[$type] ?? 'bg-gray-400' }}"></span>{{ ucfirst(strtolower($type)) }} {{ $gb($bytes) }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Added per month</h4>
        <div class="mt-2 grid grid-cols-6 items-end gap-2">
            @foreach ($months as $m)
                <div class="flex flex-col items-center gap-1" title="{{ $m['files'] }} files · {{ $gb($m['bytes']) }}">
                    <div class="flex h-20 w-full items-end"><div class="w-full rounded-t bg-amber-400/80" style="height: {{ max(2, $m['bytes'] / $monthMax * 100) }}%"></div></div>
                    <span class="text-[11px] text-gray-500">{{ $m['label'] }}</span>
                    <span class="text-[10px] text-gray-400">{{ $gb($m['bytes']) }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
