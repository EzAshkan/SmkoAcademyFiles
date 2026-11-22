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
        // Pass files up to the size limit through this site so they save as "Track 1.5.mp3".
        $maxBytes = config('smko.proxy_download_max_mb') * 1048576;
        if (config('smko.bucket_forces_attachment') && $asset->size_bytes && $asset->size_bytes <= $maxBytes) {
            $stream = Storage::disk(config('smko.disk'))->readStream($asset->storageKey());
            abort_unless($stream, 404);
            $name = $asset->downloadName();

            return response()->stream(function () use ($stream) {
                @set_time_limit(0);
                while (! feof($stream)) {
                    echo fread($stream, 1024 * 256);
                    flush();
                }
                fclose($stream);
            }, 200, [
                'Content-Type' => Asset::MIME[$asset->file_ext] ?? 'application/octet-stream',
                'Content-Length' => $asset->size_bytes,
                'Content-Disposition' => 'attachment; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
                'Cache-Control' => 'private, no-transform',
                'X-Accel-Buffering' => 'no',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return redirect()->away($asset->temporaryUrl(config('smko.download_minutes')));
    }

    public function preview(Request $request, Asset $asset)
    {
        $this->authorizeAsset($request, $asset);
        abort_unless($asset->hasFile(), 404);

        // PDFs: pass through this site so the browser shows them instead of downloading
        // (the bucket objects carry "Content-Disposition: attachment").
        if ($asset->file_ext === 'pdf' && config('smko.bucket_forces_attachment')) {
            $stream = Storage::disk(config('smko.disk'))->readStream($asset->storageKey());
            abort_unless($stream, 404);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, array_filter([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.Str::ascii($asset->downloadName()).'"',
                'Content-Length' => $asset->size_bytes,
                'Cache-Control' => 'private, max-age=600',
                'X-Content-Type-Options' => 'nosniff',
            ]));
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
