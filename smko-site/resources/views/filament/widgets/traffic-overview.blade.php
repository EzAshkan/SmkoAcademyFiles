@php($gb = fn ($b) => \App\Filament\Widgets\TrafficOverview::gb($b))
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-arrows-up-down" icon-color="info">
        <x-slot name="heading">Traffic through this server</x-slot>
        <x-slot name="description">Downloads and previews streamed from the bucket via this server</x-slot>

        <div class="grid gap-3 sm:grid-cols-3">
            @foreach (['Today' => $today, 'Last 7 days' => $week, 'Last 30 days' => $month] as $label => $s)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $gb($s->b) }}</p>
                    <p class="text-xs text-gray-400">{{ number_format($s->h) }} plays / downloads</p>
                </div>
            @endforeach
        </div>

        {{-- 30-day bars: downloads (indigo) + previews (teal) --}}
        <div class="mt-5">
            <div class="flex h-28 items-end gap-1">
                @foreach ($days as $d)
                    @php($total = $d['download'] + $d['preview'])
                    <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ \Illuminate\Support\Carbon::parse($d['day'])->format('M j') }}: {{ $gb($total) }} (downloads {{ $gb($d['download']) }}, previews {{ $gb($d['preview']) }})">
                        <div class="w-full rounded-t bg-teal-400/80" style="height: {{ $d['preview'] / $max * 100 }}%"></div>
                        <div class="w-full bg-indigo-500/80 {{ $d['preview'] ? '' : 'rounded-t' }}" style="height: {{ $d['download'] / $max * 100 }}%"></div>
                        @if (! $total)<div class="h-0.5 w-full rounded bg-gray-200 dark:bg-white/10"></div>@endif
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                <span>{{ \Illuminate\Support\Carbon::parse($days->first()['day'])->format('M j') }}</span>
                <span class="flex gap-3"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-indigo-500"></span>Downloads</span><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-teal-400"></span>Previews</span></span>
                <span>Today</span>
            </div>
        </div>

        @if ($top->isNotEmpty())
            <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Most traffic — last 30 days</h4>
            <ul class="mt-2 divide-y divide-gray-100 text-sm dark:divide-white/5">
                @foreach ($top as $t)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0"><span class="block truncate font-medium text-gray-800 dark:text-gray-100">{{ $t['name'] }}</span><span class="block truncate text-xs text-gray-400">{{ $t['path'] }}</span></span>
                        <span class="shrink-0 text-right text-xs text-gray-500"><span class="font-semibold text-gray-800 dark:text-gray-100">{{ $gb($t['bytes']) }}</span><br>{{ $t['hits'] }} plays/downloads</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
