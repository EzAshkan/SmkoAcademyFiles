import './bootstrap';
import Alpine from 'alpinejs';

/**
 * Folder page: file preview popup with previous/next, deep links (/assets/{id}),
 * keyboard shortcuts and a quick filter — same flow as the old site, plus extras.
 */
Alpine.data('fileManager', ({ assets, openId, folderUrl }) => ({
    assets,
    folderUrl,
    query: '',
    current: null,

    init() {
        if (openId) {
            const a = this.assets.find((x) => x.id === openId);
            if (a) this.show(a, false);
        }
        window.addEventListener('popstate', () => {
            const m = location.pathname.match(/\/assets\/(\d+)/);
            const a = m && this.assets.find((x) => x.id === +m[1]);
            a ? this.show(a, false) : this.close(false);
        });
    },

    matches(name) {
        const q = this.query.trim().toLowerCase();
        return !q || name.toLowerCase().includes(q);
    },

    get visible() {
        return this.assets.filter((a) => this.matches(a.name));
    },

    show(asset, push = true) {
        this.current = asset;
        this.markSeen(asset);
        document.body.classList.add('overflow-hidden');
        if (push) history.pushState({}, '', asset.url);
        this.$nextTick(() => this.$refs.dialog?.focus());
    },

    /** Records a view (statistics) and clears the "New" badge. */
    markSeen(asset) {
        if (asset._seen) return;
        asset._seen = true;
        asset.is_new = false;
        fetch(asset.seen_url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
            keepalive: true,
        }).catch(() => {});
    },

    close(push = true) {
        this.$refs.media?.pause?.();
        this.current = null;
        document.body.classList.remove('overflow-hidden');
        if (push) history.pushState({}, '', this.folderUrl);
    },

    step(dir) {
        if (!this.current) return;
        const list = this.visible;
        const i = list.findIndex((a) => a.id === this.current.id);
        const next = list[i + dir];
        if (next) {
            this.$refs.media?.pause?.();
            this.current = next;
            this.markSeen(next);
            history.replaceState({}, '', next.url);
        }
    },

    get position() {
        const list = this.visible;
        const i = this.current ? list.findIndex((a) => a.id === this.current.id) : -1;
        return { index: i, total: list.length, hasPrev: i > 0, hasNext: i >= 0 && i < list.length - 1 };
    },
}));

window.Alpine = Alpine;
Alpine.start();
