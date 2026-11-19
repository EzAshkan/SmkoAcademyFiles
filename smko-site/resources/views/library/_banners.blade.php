{{-- Announcement banners (dismissed ones stay hidden in this browser). --}}
@if (($banners ?? collect())->isNotEmpty())
    <div class="mb-5 space-y-2">
        @foreach ($banners as $b)
            @php
                $c = [
                    'info' => 'border-sky-200 bg-sky-50 text-sky-900',
                    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
                    'danger' => 'border-red-200 bg-red-50 text-red-900',
                ][$b->style] ?? 'border-sky-200 bg-sky-50 text-sky-900';
                $icon = ['info' => '📢', 'success' => '✅', 'warning' => '⚠️', 'danger' => '🚨'][$b->style] ?? '📢';
                $key = 'smko-banner-'.$b->id.'-'.$b->updated_at?->timestamp;
            @endphp
            <div x-data="{ open: (() => { try { return !localStorage.getItem(@js($key)) } catch (e) { return true } })() }"
                 x-show="open" x-transition.opacity
                 class="flex items-start gap-3 rounded-xl border px-4 py-3 shadow-sm {{ $c }}" role="status">
                <span class="text-lg leading-6">{{ $icon }}</span>
                <div class="min-w-0 flex-1" dir="auto">
                    <p class="font-semibold">{{ $b->title }}</p>
                    @if ($b->body)
                        <p class="post-text mt-0.5 text-sm opacity-90">{{ $b->body }}</p>
                    @endif
                </div>
                <button type="button" class="rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" title="Dismiss"
                        @click="open = false; try { localStorage.setItem(@js($key), '1') } catch (e) {}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>
        @endforeach
    </div>
@endif
