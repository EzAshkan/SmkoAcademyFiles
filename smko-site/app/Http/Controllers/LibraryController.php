<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetEvent;
use App\Support\NewFiles;
use App\Models\Folder;
use App\Models\TelegramClaim;
use App\Support\FolderTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    public function home(Request $request)
    {
        $ids = FolderTree::entryPoints($request->user());
        $folders = Folder::whereIn('id', $ids)->get()->sortBy(fn ($f) => array_search($f->id, $ids))->values();

        return view('library.home', [
            'folders' => $folders,
            'recent' => NewFiles::recentFor($request->user()),
            // Folders whose access ends at a set time — shown on the tile.
            'expiries' => \Illuminate\Support\Facades\DB::table('folder_user')
                ->where('user_id', $request->user()?->id ?? 0)->whereIn('folder_id', $ids)
                ->whereNotNull('expires_at')->pluck('expires_at', 'folder_id')
                ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))->all(),
            'newIds' => NewFiles::idsFor($request->user()),
            'newCounts' => NewFiles::countsByFolder($request->user()),
        ]);
    }

    public function folder(Request $request, Folder $folder, ?int $openId = null)
    {
        $user = $request->user();
        abort_unless(FolderTree::canView($user, $folder->id), 403);

        // Breadcrumb: only the part of the path this user is allowed to open.
        $crumbs = collect(FolderTree::lineage($folder->id))
            ->filter(fn ($id) => FolderTree::canView($user, $id))
            ->map(fn ($id) => ['id' => $id, 'name' => FolderTree::nodes()[$id]['name']])
            ->values();

        $assets = $folder->assets()->get();
        $newIds = NewFiles::idsFor($user);
        $parentVisible = $folder->parent_id && FolderTree::canView($user, $folder->parent_id);

        return view('library.folder', [
            'folder' => $folder,
            'crumbs' => $crumbs,
            'children' => $folder->children()->get(),
            'assets' => $assets,
            'assetData' => $assets->map(fn (Asset $a) => $this->assetPayload($a, $newIds))->values(),
            'newIds' => $newIds,
            'newCounts' => NewFiles::countsByFolder($user),
            'openId' => $openId,
            'backUrl' => $parentVisible ? route('library.folder', $folder->parent_id) : route('library.home'),
        ]);
    }

    /** /assets/{id} opens the folder with that file's preview already showing (shareable link). */
    public function asset(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);

        return $this->folder($request, $asset->folder, $asset->id);
    }

    protected function assetPayload(Asset $a, array $newIds = []): array
    {
        $available = $a->hasFile();

        return [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'ext' => $a->file_ext,
            'size' => $a->sizeForHumans(),
            'date' => $a->created_at?->format('M j, Y'),
            'description' => $a->description,
            // Escaped first, then links made clickable.
            'text_html' => $a->text ? preg_replace(
                '~(https?://[^\s<]+)~u',
                '<a href="$1" target="_blank" rel="noopener" class="text-indigo-600 underline break-all">$1</a>',
                e($a->text)
            ) : null,
            'available' => $available,
            'is_new' => isset($newIds[$a->id]),
            'seen_url' => route('library.seen', $a),
            'kind' => $a->previewKind(),
            'url' => route('library.asset', $a),
            'preview_url' => $available ? route('library.preview', $a) : null,
            'download_url' => $available ? route('library.download', $a) : null,
            'telegram_url' => $available && ! \App\Support\Mode::isPublic() ? route('library.telegram', $a) : null,
        ];
    }

    public function download(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);
        abort_unless($asset->hasFile(), 404);

        AssetEvent::record($request->user(), $asset, 'download');

        // The bucket forces "attachment" with its own object name (290.mp3, random names…).
        // Pass files up to the size limit through this site so they save as "Track 1.5.mp3"
        // (and so visitors only need to reach this site, not ParsPack directly).
        $maxBytes = config('smko.proxy_download_max_mb') * 1048576;
        if (config('smko.bucket_forces_attachment') && (! $asset->size_bytes || $asset->size_bytes <= $maxBytes)) {
            return \App\Support\BucketStream::response($request, $asset, attachment: true);
        }

        return redirect()->away($asset->temporaryUrl(config('smko.download_minutes')));
    }

    public function preview(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);
        abort_unless($asset->hasFile(), 404);

        // Stream through this site (with seeking): players then work even for visitors who
        // can't reach ParsPack directly, and PDFs show instead of downloading.
        if (config('smko.proxy_previews')) {
            return \App\Support\BucketStream::response($request, $asset, attachment: false);
        }

        return redirect()->away($asset->temporaryUrl(config('smko.download_minutes'), asAttachment: false));
    }

    /** "Get on Telegram": one-time code, then open the bot with it. */
    public function telegram(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);
        abort_unless($asset->hasFile(), 404);

        abort_if(\App\Support\Mode::isPublic() || ! $request->user(), 404);
        AssetEvent::record($request->user(), $asset, 'telegram');

        $claim = TelegramClaim::create([
            'token' => Str::random(32),
            'user_id' => $request->user()->id,
            'asset_id' => $asset->id,
            'expires_at' => now()->addMinutes(config('smko.telegram.claim_minutes')),
        ]);

        return redirect()->away('https://t.me/'.config('smko.telegram.bot_username').'?start='.$claim->token);
    }

    /** Search files and folders by name — only what this visitor may open. */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $folders = collect();
        $assets = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $folders = Folder::where('name', 'like', $like)->orderBy('name')->limit(300)->get()
                ->filter(fn (Folder $f) => FolderTree::canView($user, $f->id))->take(24)->values();
            $assets = Asset::where('name', 'like', $like)->orderByDesc('created_at')->limit(600)->get()
                ->filter(fn (Asset $a) => FolderTree::canView($user, $a->folder_id))->take(60)->values();
        }

        return view('library.search', compact('q', 'folders', 'assets'));
    }

    /** Called when a student opens a file's preview (statistics + clears its "New" badge). */
    public function seen(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);
        // One view per file per 30 minutes (visitors without an account: per browser session).
        $sessionKey = 'seen.'.$asset->id;
        $recent = $request->user()
            ? AssetEvent::where('user_id', $request->user()->id)->where('asset_id', $asset->id)
                ->where('type', 'view')->where('created_at', '>', now()->subMinutes(30))->exists()
            : now()->timestamp - (int) $request->session()->get($sessionKey, 0) < 1800;
        $request->session()->put($sessionKey, now()->timestamp);
        if (! $recent) {
            AssetEvent::record($request->user(), $asset, 'view');
        }

        return response()->noContent();
    }

    /** Admin "temporary link" — works without logging in until it expires. */
    public function share(Asset $asset)
    {
        abort_unless($asset->hasFile(), 404);

        return redirect()->away($asset->temporaryUrl(15));
    }

    /** Old site links: /dashboard?folder_id=123 */
    public function legacyDashboard(Request $request)
    {
        return $request->filled('folder_id')
            ? redirect()->route('library.folder', (int) $request->query('folder_id'))
            : redirect()->route('library.home');
    }

    protected function authorizeAsset(Request $request, Asset $asset): void
    {
        abort_unless(FolderTree::canView($request->user(), $asset->folder_id), 403);
    }
}
