{{-- Floating upload panel (bottom-left). @persist keeps it — and running uploads — alive while navigating the admin panel. --}}
@persist('smko-uploader')
<div x-data="smkoUploader()" x-cloak x-show="open" x-transition
     class="fixed bottom-4 left-4 z-[60] flex max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-gray-900">

    {{-- Header (click to minimise / expand) --}}
    <div class="flex cursor-pointer items-center gap-3 bg-gray-50 px-4 py-3 dark:bg-white/5" @click="minimized = !minimized">
        <template x-if="active">
            <svg class="h-5 w-5 shrink-0 animate-spin text-primary-500" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        </template>
        <template x-if="!active">
            <svg class="h-5 w-5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
        </template>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="active ? `Uploading… ${overall}%` : (drafts.length ? `Upload ${drafts.length} ${drafts.length === 1 ? 'file' : 'files'}` : (choosing ? 'Upload files' : 'Uploads finished'))"></p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="drafts.length && !active ? 'Set the titles and descriptions, then press Upload' : (choosing && !active ? 'Choose the folder they go into' : summary)"></p>
        </div>
        <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-white/10" :title="minimized ? 'Expand' : 'Minimise'">
            <svg class="h-4 w-4 transition" :class="minimized && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </button>
        <button type="button" x-show="!active" @click.stop="closePanel()" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-white/10" title="Close">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    </div>
    <div class="h-1 bg-gray-100 dark:bg-white/10"><div class="h-1 bg-primary-500 transition-all" :style="`width: ${overall}%`"></div></div>

    <div x-show="!minimized" class="min-h-0 overflow-y-auto">
        {{-- Destination picker (when uploading from the Files list) --}}
        <div x-show="choosing" class="border-b border-gray-100 p-3 dark:border-white/5">
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                <input x-ref="folderSearch" x-model="folderQuery" type="search" placeholder="Search folders… e.g. E4E1 Lesson 05"
                       class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-2 text-sm text-gray-900 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
            </div>
            <p class="mt-2 text-[11px] text-gray-400">Click a folder to choose files, or drag files onto it.</p>
            <ul class="mt-1 max-h-[min(16rem,40vh)] overflow-y-auto">
                <template x-for="f in folderMatches" :key="f.id">
                    <li>
                        <button type="button" @click="pickInto(f)"
                                @dragover.prevent="$el.classList.add('ring-2')" @dragleave="$el.classList.remove('ring-2')"
                                @drop.prevent="$el.classList.remove('ring-2'); dropInto($event, f)"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm text-gray-700 ring-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-white/5">
                            <svg class="h-4 w-4 shrink-0 text-violet-400" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"/></svg>
                            <span class="truncate" x-text="f.path"></span>
                        </button>
                    </li>
                </template>
                <li x-show="folders.length && !folderMatches.length" class="px-2 py-3 text-center text-xs text-gray-400">No folder matches.</li>
                <li x-show="!folders.length" class="px-2 py-3 text-center text-xs text-gray-400">Loading folders…</li>
            </ul>
        </div>

        {{-- Step 1: details for each picked file --}}
        <div x-show="drafts.length" class="border-b border-gray-100 dark:border-white/5">
            <ul class="max-h-[min(24rem,55vh)] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                <template x-for="(it, n) in drafts" :key="it.key">
                    <li class="space-y-1.5 px-4 py-3">
                        <div class="flex items-center gap-2 text-[11px] text-gray-400">
                            <span class="min-w-0 flex-1 truncate" :title="it.name" x-text="it.name + ' · ' + fmt(it.size)"></span>
                            <button type="button" @click="removeDraft(it)" class="shrink-0 hover:text-red-600" title="Remove">Remove</button>
                        </div>
                        <input type="text" x-model="it.title" data-draft-title placeholder="Title" maxlength="255" dir="auto"
                               @keydown.enter.prevent="startDrafts()"
                               class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-900 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <textarea x-model="it.description" rows="2" placeholder="Description (optional)" maxlength="5000" dir="auto"
                                  class="w-full resize-y rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"></textarea>
                        <p class="truncate text-[11px] text-gray-400" x-text="'→ ' + it.path"></p>
                    </li>
                </template>
            </ul>
            <div class="flex items-center justify-between gap-2 bg-gray-50 px-4 py-2.5 dark:bg-white/5">
                <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" x-model="notify" class="rounded border-gray-300 text-primary-600"> Tell students on Telegram
                </label>
                <button type="button" @click="startDrafts()"
                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                        x-text="drafts.length === 1 ? 'Upload' : `Upload ${drafts.length} files`"></button>
            </div>
        </div>

        {{-- Step 2: progress --}}
        <ul class="max-h-[min(20rem,45vh)] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
            <template x-for="it in items.filter(i => i.status !== 'draft')" :key="it.key">
                <li class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800 dark:text-gray-100" :title="it.name" x-text="it.title || it.name"></p>
                        <span class="shrink-0 text-xs text-gray-400" x-text="fmt(it.size)"></span>
                        <button type="button" x-show="['queued','hashing','checking','uploading'].includes(it.status)" @click="cancel(it)" class="text-xs text-gray-400 hover:text-red-600">Cancel</button>
                        <button type="button" x-show="it.status === 'failed'" @click="retry(it)" class="text-xs font-medium text-primary-600 hover:underline">Retry</button>
                    </div>
                    <p class="truncate text-[11px] text-gray-400" x-text="'→ ' + it.path"></p>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full transition-all"
                             :class="{ 'bg-primary-500': ['hashing','checking','uploading','saving','queued'].includes(it.status), 'bg-emerald-500': it.status === 'done', 'bg-sky-500': it.status === 'reused', 'bg-red-500': it.status === 'failed', 'bg-gray-300': it.status === 'cancelled' }"
                             :style="`width: ${it.progress}%`"></div>
                    </div>
                    <p class="mt-1 text-[11px]"
                       :class="{ 'text-red-600': it.status === 'failed', 'text-sky-600': it.status === 'reused', 'text-emerald-600': it.status === 'done', 'text-gray-500': true }"
                       x-text="({ queued: 'Waiting…', hashing: 'Checking file… ' + it.progress * 10 + '%', checking: 'Looking for the same file in storage…', uploading: 'Uploading ' + it.progress + '%', saving: 'Saving to storage…', done: 'Uploaded', reused: it.message, failed: it.message, cancelled: 'Cancelled' })[it.status]"></p>
                </li>
            </template>
        </ul>
        <div x-show="items.some(i => ['done','reused','failed','cancelled'].includes(i.status))" class="flex items-center justify-end gap-2 border-t border-gray-100 px-4 py-2 dark:border-white/5">
            <button type="button" x-show="items.some(i => ['done','reused','failed','cancelled'].includes(i.status))" @click="clearFinished()" class="text-xs text-gray-500 hover:underline">Clear finished</button>
        </div>
    </div>
</div>
@endpersist
