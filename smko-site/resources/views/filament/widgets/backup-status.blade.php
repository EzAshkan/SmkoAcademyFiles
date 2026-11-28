<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-archive-box" :icon-color="$overdue ? 'danger' : 'success'">
        <x-slot name="heading">Backups</x-slot>
        <x-slot name="description">Database, compressed to .zip · every {{ $every }} days at 03:00 · newest {{ $keep }} kept</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-on-square" wire:click="backupNow" wire:loading.attr="disabled" wire:target="backupNow">
                <span wire:loading.remove wire:target="backupNow">Back up now</span>
                <span wire:loading wire:target="backupNow">Backing up…</span>
            </x-filament::button>
        </x-slot>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg border p-3 dark:border-white/10 {{ $overdue ? 'border-red-300 bg-red-50 dark:bg-red-500/10' : 'border-gray-200' }}">
                <p class="text-xs text-gray-500 dark:text-gray-400">Last backup</p>
                <p class="mt-1 text-lg font-semibold {{ $overdue ? 'text-red-600' : 'text-gray-950 dark:text-white' }}">{{ $last ? $last['at']->diffForHumans() : 'None yet' }}</p>
                @if ($last)<p class="text-xs text-gray-400">{{ $last['at']->format('M j, Y H:i') }}</p>@endif
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Size of last</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $last ? number_format($last['size'] / 1048576, 2).' MB' : '—' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Kept</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $count }} / {{ $keep }}</p>
                <p class="text-xs text-gray-400">{{ number_format($totalMb, 1) }} MB in total</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Next backup</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $next->diffForHumans() }}</p>
                <p class="text-xs text-gray-400">{{ $next->format('M j, H:i') }}</p>
            </div>
        </div>

        @if ($count)
            <ul class="mt-4 divide-y divide-gray-100 text-sm dark:divide-white/5">
                @foreach ($list as $b)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0 truncate font-mono text-xs text-gray-700 dark:text-gray-300">{{ $b['name'] }}</span>
                        <span class="shrink-0 text-xs text-gray-400">{{ number_format($b['size'] / 1048576, 2) }} MB · {{ $b['at']->diffForHumans() }}</span>
                        <a href="{{ route('admin.backups.download', $b['name']) }}" class="shrink-0 text-xs font-semibold text-primary-600 hover:underline">Download</a>
                    </li>
                @endforeach
            </ul>
            @if ($count > 5)
                <button type="button" wire:click="$toggle('showAll')" class="mt-2 text-xs text-gray-500 hover:underline">{{ $showAll ? 'Show fewer' : 'Show all '.$count }}</button>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
