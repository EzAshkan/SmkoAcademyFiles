<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-signal" icon-color="success">
        <x-slot name="heading">Online now</x-slot>
        <x-slot name="description">Active in the last 5 minutes · updates every 30 seconds</x-slot>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $public ? 'Visitors' : 'Teachers online' }}</p>
                <p class="mt-1 flex items-center gap-2 text-2xl font-semibold text-gray-950 dark:text-white">
                    <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span>
                    {{ $public ? $visitors : $teachers->count() }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Admins online</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $admins->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $public ? 'Admin logins today' : 'Teachers logged in today' }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $today }}</p>
            </div>
        </div>

        @php($people = $teachers->concat($admins))
        @if ($people->isNotEmpty())
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($people as $u)
                    <li class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $u->name }}</span>
                        @if ($u->is_admin)<span class="rounded bg-primary-50 px-1 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">admin</span>@endif
                        <span class="text-gray-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($u->seen)->diffForHumans(short: true) }}</span>
                    </li>
                @endforeach
            </ul>
        @elseif (! $public)
            <p class="mt-4 text-sm text-gray-400">Nobody online right now.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
