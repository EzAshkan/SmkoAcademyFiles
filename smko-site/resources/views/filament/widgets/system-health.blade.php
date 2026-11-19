<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">System status</x-slot>
        <x-slot name="description">
            @if ($lastRun)
                Checked {{ $lastRun->diffForHumans() }} · runs every 5 minutes · admins get a Telegram message when something breaks or recovers
            @else
                Not checked yet
            @endif
        </x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="runNow" wire:loading.attr="disabled">
                Check now
            </x-filament::button>
        </x-slot>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($labels as $name => $label)
                @php $c = $checks[$name] ?? null; @endphp
                <div class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <span @class([
                        'mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                        'bg-gray-300' => ! $c,
                        'bg-emerald-500' => $c?->ok,
                        'bg-red-500 animate-pulse' => $c && ! $c->ok,
                    ])></span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $c?->message }}">{{ $c?->message ?? 'Not checked yet' }}</p>
                        @if ($c && ! $c->ok)
                            <p class="text-xs text-red-600">since {{ $c->changed_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
