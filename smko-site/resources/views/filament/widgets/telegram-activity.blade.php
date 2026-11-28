<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-paper-airplane" icon-color="info">
        <x-slot name="heading">Telegram messages</x-slot>
        <x-slot name="description">Last 30 days · counts are messages handed to the bot (teachers who blocked the bot won't receive them)</x-slot>

        @if ($failedRecent)
            <div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                {{ $failedRecent }} send {{ \Illuminate\Support\Str::plural('attempt', $failedRecent) }} failed in the last 24 hours — is the bot server reachable?
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-4">
            @foreach ($kinds as $kind => [$label, $badge])
                @php($t = $totals[$kind] ?? null)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($t->queued ?? 0) }}</p>
                    <p class="text-xs text-gray-400">{{ (int) ($t->batches ?? 0) }} {{ \Illuminate\Support\Str::plural('send', (int) ($t->batches ?? 0)) }}@if ($t && $t->failed) · <span class="text-red-500">{{ $t->failed }} failed</span>@endif</p>
                </div>
            @endforeach
        </div>

        @if ($recent->isNotEmpty())
            <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Recent</h4>
            <ul class="mt-2 divide-y divide-gray-100 text-sm dark:divide-white/5">
                @foreach ($recent as $r)
                    <li class="flex items-center gap-3 py-2">
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $kinds[$r->kind][1] ?? 'bg-gray-100 text-gray-600' }}">{{ $kinds[$r->kind][0] ?? $r->kind }}</span>
                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" title="{{ $r->error }}">{{ $r->label ?: '—' }}</span>
                        <span class="shrink-0 text-xs {{ $r->failed ? 'text-red-500' : 'text-gray-500' }}">{{ $r->failed ? 'failed' : $r->queued.' / '.$r->recipients.' sent' }}</span>
                        <span class="shrink-0 text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($r->created_at)->diffForHumans(short: true) }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-4 text-sm text-gray-400">No Telegram messages sent yet.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
