{{-- Background upload queue for the admin panel. Loaded in <head> so it exists before Alpine starts. --}}
<script src="{{ asset('js/spark-md5.min.js') }}"></script>
<script>
window.smkoUploader = function () {
    const ACTIVE = ['queued', 'hashing', 'checking', 'uploading', 'saving'];
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || @js(csrf_token());

    return {
        items: [],
        open: false,
        minimized: false,
        notify: false, // Telegram alert is opt-in
        running: 0,
        max: 2,
        batches: {},

        init() {
            window.addEventListener('smko-upload', (e) => this.pick(e.detail));
            window.addEventListener('beforeunload', (e) => {
                if (this.active || this.drafts.length) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        get active() { return this.items.some((i) => ACTIVE.includes(i.status)); },
        get activeCount() { return this.items.filter((i) => ACTIVE.includes(i.status)).length; },
        get overall() {
            const list = this.items.filter((i) => i.status !== 'cancelled');
            const total = list.reduce((s, i) => s + i.size, 0) || 1;
            return Math.round(list.reduce((s, i) => s + i.size * (i.progress / 100), 0) / total * 100);
        },
        get summary() {
            const c = (s) => this.items.filter((i) => i.status === s).length;
            const parts = [];
            if (this.activeCount) parts.push(this.activeCount + ' in progress');
            if (c('done')) parts.push(c('done') + ' uploaded');
            if (c('reused')) parts.push(c('reused') + ' reused');
            if (c('failed')) parts.push(c('failed') + ' failed');
            if (c('draft')) parts.push(c('draft') + ' ready to upload');
            return parts.join(' · ') || 'No uploads';
        },

        // "Where should these go?" — used when uploading from a page that isn't a folder.
        choosing: false,
        folders: [],
        folderQuery: '',
        dest: null,

        get folderMatches() {
            const q = this.folderQuery.trim().toLowerCase();
            const list = q ? this.folders.filter((f) => f.path.toLowerCase().includes(q)) : this.folders;
            return list.slice(0, 50);
        },

        async chooseDestination() {
            this.choosing = true;
            this.open = true;
            this.minimized = false;
            this.dest = null;
            this.folderQuery = '';
            if (!this.folders.length) this.folders = await this.json('GET', '/admin-api/folders');
            this.$nextTick(() => this.$refs.folderSearch?.focus());
        },

        pickInto(folder) {
            this.choosing = false;
            this.pick({ folderId: folder.id, path: folder.path }); // direct click → the browser allows the file dialog
        },

        dropInto(e, folder) {
            this.choosing = false;
            this.add([...e.dataTransfer.files], { folderId: folder.id, path: folder.path });
        },

        pick(target) {
            if (!target || !target.folderId) return this.chooseDestination();
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.onchange = () => this.add([...input.files], target);
            input.click();
        },

        add(files, target) {
            for (const f of files) {
                this.items.push({
                    key: Math.random().toString(36).slice(2), file: f, name: f.name, size: f.size,
                    folderId: target.folderId, path: target.path, notify: false,
                    // Step 1: you set these, then press Upload.
                    title: f.name.replace(/\.[^.]+$/, '').replace(/[_\s]+/g, ' ').trim() || 'File',
                    description: '',
                    status: 'draft', progress: 0, message: '', uploadId: null, cancel: false,
                });
            }
            this.open = true;
            this.minimized = false;
            this.$nextTick(() => this.$root.querySelector('[data-draft-title]')?.focus());
        },

        get drafts() { return this.items.filter((i) => i.status === 'draft'); },

        /** Step 2: start uploading the drafts and tuck the panel away. */
        startDrafts() {
            for (const it of this.drafts) {
                it.title = (it.title || '').trim() || it.name;
                it.notify = this.notify;
                it.status = 'queued';
            }
            this.minimized = true;
            this.pump();
        },

        removeDraft(it) {
            this.items = this.items.filter((i) => i !== it);
            if (!this.items.length && !this.choosing) this.open = false;
        },

        pump() {
            while (this.running < this.max) {
                const item = this.items.find((i) => i.status === 'queued');
                if (!item) break;
                this.running++;
                this.process(item).finally(() => { this.running--; this.pump(); });
            }
        },

        async process(it) {
            const batch = (this.batches[it.folderId] ??= { uploaded: 0, reused: 0 });
            try {
                it.status = 'hashing';
                it.md5 = await this.md5(it.file, (p) => { it.progress = Math.round(p * 10); }, () => it.cancel);

                it.status = 'checking';
                const r = await this.json('POST', '/admin-api/uploads/check', {
                    folder_id: it.folderId, filename: it.name, size: it.size, md5: it.md5, notify: it.notify,
                    title: it.title, description: it.description,
                });
                if (r.reused) {
                    it.status = 'reused';
                    it.progress = 100;
                    it.message = 'Already stored — reused (' + r.matched + ')';
                    batch.reused++;
                    this.finished(it);
                    return;
                }

                it.uploadId = r.upload_id;
                it.status = 'uploading';
                let offset = r.received || 0;
                while (offset < it.size) {
                    if (it.cancel) throw new Error('Cancelled');
                    const start = offset;
                    const blob = it.file.slice(start, start + r.chunk_size);
                    const res = await this.sendChunk(it, start, blob, (loaded) => {
                        it.progress = 10 + Math.round(85 * (start + loaded) / it.size);
                    });
                    offset = res.received;
                }

                it.status = 'saving';
                it.progress = 96;
                const done = await this.json('POST', `/admin-api/uploads/${it.uploadId}/complete`, {});
                it.status = done.reused ? 'reused' : 'done';
                it.message = done.reused ? 'Same file was already stored — reused' : '';
                it.progress = 100;
                done.reused ? batch.reused++ : batch.uploaded++;
                this.finished(it);
            } catch (e) {
                it.status = it.cancel ? 'cancelled' : 'failed';
                it.message = it.cancel ? '' : (e.message || 'Upload failed');
                if (it.uploadId) this.json('DELETE', `/admin-api/uploads/${it.uploadId}`).catch(() => {});
                it.uploadId = null;
            }
        },

        finished(it) {
            window.Livewire?.dispatch('smko-uploaded', { folderId: it.folderId });
            const pending = this.items.some((i) => i.folderId === it.folderId && ACTIVE.includes(i.status));
            const b = this.batches[it.folderId];
            if (!pending && b) {
                this.json('POST', '/admin-api/uploads/summary', { folder_id: it.folderId, ...b }).catch(() => {});
                delete this.batches[it.folderId];
            }
        },

        cancel(it) {
            it.cancel = true;
            if (it.status === 'queued') it.status = 'cancelled';
        },

        retry(it) {
            Object.assign(it, { status: 'queued', progress: 0, message: '', cancel: false, uploadId: null });
            this.pump();
        },

        clearFinished() {
            this.items = this.items.filter((i) => ACTIVE.includes(i.status) || i.status === 'draft');
            if (!this.items.length && !this.choosing) this.open = false;
        },

        closePanel() {
            if (this.drafts.length && !confirm('Discard the files you haven\'t uploaded yet?')) return;
            this.items = this.items.filter((i) => i.status !== 'draft');
            this.choosing = false;
            this.clearFinished();
        },

        // MD5 of the file, read in 4 MB slices so big videos don't freeze the page.
        md5(file, onProgress, cancelled) {
            return new Promise((resolve, reject) => {
                const size = 4 * 1024 * 1024;
                const spark = new SparkMD5.ArrayBuffer();
                const reader = new FileReader();
                let offset = 0;
                reader.onerror = () => reject(new Error('Could not read the file'));
                reader.onload = (e) => {
                    spark.append(e.target.result);
                    offset += size;
                    onProgress(Math.min(offset / file.size, 1));
                    if (cancelled()) return reject(new Error('Cancelled'));
                    offset < file.size ? next() : resolve(spark.end());
                };
                const next = () => reader.readAsArrayBuffer(file.slice(offset, offset + size));
                next();
            });
        },

        sendChunk(it, offset, blob, onProgress, attempt = 1) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/admin-api/uploads/${it.uploadId}/chunk?offset=${offset}`);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                xhr.upload.onprogress = (e) => onProgress(e.loaded);
                xhr.onload = () => {
                    let body = {};
                    try { body = JSON.parse(xhr.responseText); } catch (e) {}
                    if (xhr.status === 200 || xhr.status === 409) return resolve(body); // 409: continue where the server is
                    retryOrFail(new Error(body.message || `Upload error (${xhr.status})`));
                };
                xhr.onerror = () => retryOrFail(new Error('Network problem'));
                const retryOrFail = (err) => {
                    if (it.cancel || attempt >= 4) return reject(err);
                    setTimeout(() => this.sendChunk(it, offset, blob, onProgress, attempt + 1).then(resolve, reject), 1500 * attempt);
                };
                xhr.send(blob);
            });
        },

        async json(method, url, body) {
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
            if (res.status === 204) return {};
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || `Error ${res.status}`);
            return data;
        },

        fmt(bytes) {
            const u = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < 3) { bytes /= 1024; i++; }
            return bytes.toFixed(i ? 1 : 0) + ' ' + u[i];
        },
    };
};
</script>
