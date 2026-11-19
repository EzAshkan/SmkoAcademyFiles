{{-- Preview popup (same layout as the old site: preview on the left, details + buttons on the right). --}}
<template x-teleport="body">
    <div x-cloak x-show="current" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 sm:items-center sm:p-6"
         @click.self="close()">
        <div x-ref="dialog" tabindex="-1" role="dialog" aria-modal="true" x-show="current"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0 sm:scale-95"
             class="flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl outline-none sm:h-[85vh] sm:rounded-2xl">
            <template x-if="current">
                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Title bar --}}
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
                        <div class="flex min-w-0 items-center gap-2 text-sm font-semibold text-gray-800">
                            <svg class="h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                            <span>Preview</span>
                            <span class="hidden text-xs font-normal text-gray-400 sm:inline" x-text="`${position.index + 1} of ${position.total}`"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="step(-1)" :disabled="!position.hasPrev" title="Previous (←)"
                                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                            </button>
                            <button @click="step(1)" :disabled="!position.hasNext" title="Next (→)"
                                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </button>
                            <button @click="close()" title="Close (Esc)" class="ml-1 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto sm:flex-row sm:overflow-hidden">
                        {{-- Preview area --}}
                        <div class="flex min-h-[16rem] flex-1 items-center justify-center bg-gray-100 p-4 sm:min-h-0 sm:overflow-y-auto sm:p-8">
                            {{-- Audio --}}
                            <template x-if="current.kind === 'audio' && current.available">
                                <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-lg">
                                    <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-indigo-50 text-indigo-500">
                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M19.952 1.651a.75.75 0 0 1 .298.599V16.303a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.403-4.909l2.311-.66a1.5 1.5 0 0 0 1.088-1.442V6.994l-9 2.572v9.737a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.402-4.909l2.31-.66a1.5 1.5 0 0 0 1.088-1.442V5.25a.75.75 0 0 1 .544-.721l10.5-3a.75.75 0 0 1 .658.122Z"/></svg>
                                    </div>
                                    <p class="mb-4 font-semibold text-gray-800" x-text="current.name"></p>
                                    <audio x-ref="media" controls preload="metadata" class="w-full" :src="current.preview_url"></audio>
                                </div>
                            </template>
                            {{-- Video --}}
                            <template x-if="current.kind === 'video' && current.available">
                                <video x-ref="media" controls preload="metadata" playsinline class="max-h-full w-full rounded-xl bg-black shadow-lg" :src="current.preview_url"></video>
                            </template>
                            {{-- Photo --}}
                            <template x-if="current.kind === 'image' && current.available">
                                <img :src="current.preview_url" :alt="current.name" class="max-h-full max-w-full rounded-lg object-contain shadow-lg">
                            </template>
                            {{-- PDF: shown right in the page (the old site couldn't) --}}
                            <template x-if="current.kind === 'pdf' && current.available">
                                <iframe :src="current.preview_url" class="h-[60vh] w-full rounded-lg border-0 bg-white shadow-lg sm:h-full" title="PDF preview"></iframe>
                            </template>
                            {{-- Text post --}}
                            <template x-if="current.kind === 'text'">
                                <div class="h-full w-full rounded-xl bg-white p-5 shadow-sm sm:overflow-y-auto">
                                    <div class="post-text text-[15px] leading-relaxed text-gray-800" dir="auto" x-html="current.text_html"></div>
                                </div>
                            </template>
                            {{-- Anything else --}}
                            <template x-if="current.kind !== 'text' && (!current.available || current.kind === 'none')">
                                <div class="text-center text-gray-400">
                                    <svg class="mx-auto mb-3 h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                    <p class="text-sm" x-text="current.available ? 'No preview for this file type — use Download.' : 'This file isn\'t available right now.'"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Details --}}
                        <aside class="flex w-full shrink-0 flex-col gap-5 border-t border-gray-100 p-5 sm:w-80 sm:overflow-y-auto sm:border-l sm:border-t-0">
                            <div>
                                <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">File name</h4>
                                <p class="break-words font-semibold text-gray-900" dir="auto" x-text="current.name"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Size</h4>
                                    <p class="font-mono text-sm text-gray-700" x-text="current.size || 'N/A'"></p>
                                </div>
                                <div>
                                    <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Added</h4>
                                    <p class="font-mono text-sm text-gray-700" x-text="current.date"></p>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Description</h4>
                                <div class="post-text min-h-[5rem] rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm text-gray-600 shadow-inner" dir="auto"
                                     x-text="current.description || 'No description added.'"></div>
                            </div>
                            <div class="grid gap-2 border-t border-gray-100 pt-4" x-show="current.available">
                                <a :href="current.download_url"
                                   class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M12 12.75l-3.375-3.375M12 12.75l3.375-3.375M12 12.75V3"/></svg>
                                    Download
                                </a>
                                <form method="POST" :action="current.telegram_url" target="_blank">
                                    @csrf
                                    <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#0088CC] px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-[#0077b3] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21.94 4.3a1.1 1.1 0 0 0-1.13-.18L2.9 11.1c-.5.2-.83.7-.8 1.24.03.54.4 1 .93 1.13l4.32 1.1 1.67 5.06c.15.45.55.77 1.02.8h.08c.44 0 .85-.25 1.05-.65l1.96-3.86 4.2 3.1c.2.15.45.23.7.23.13 0 .27-.02.4-.07.37-.13.65-.45.73-.83L22.3 5.4c.09-.42-.05-.84-.36-1.1Zm-11.6 10.4-.6 3.07-.92-2.8 8.16-6.23-6.64 5.96Z"/></svg>
                                        Get on Telegram
                                    </button>
                                </form>
                            </div>
                        </aside>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
