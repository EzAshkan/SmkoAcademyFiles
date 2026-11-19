<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Asset;
use App\Models\Folder;
use App\Models\Upload;
use App\Support\AssetFiles;
use App\Support\FolderTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Background uploads from the admin panel's upload queue:
 *  1. check    — browser sends name, size and MD5; if the same file is already in the bucket,
 *                a new file entry reuses it and nothing is uploaded.
 *  2. chunk    — otherwise the file arrives in small pieces (progress + no big-request limits).
 *  3. complete — pieces are joined, checked against the MD5 and moved into the bucket.
 */
class UploadController extends Controller
{
    public const CHUNK = 4 * 1024 * 1024; // keep under PHP's post_max_size

    public function check(Request $request)
    {
        $data = $request->validate([
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.(2 * 1024 * 1024 * 1024)],
            'md5' => ['nullable', 'regex:/^[a-f0-9]{32}$/'],
            'notify' => ['boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $title = trim($data['title'] ?? '') ?: AssetFiles::titleFromFilename($data['filename']);
        $description = trim($data['description'] ?? '') ?: null;
        $folder = Folder::findOrFail($data['folder_id']);
        $ext = AssetFiles::cleanExtension($data['filename']);

        if (! empty($data['md5']) && $existing = AssetFiles::findByContent($data['md5'], $data['size'])) {
            $asset = Asset::create([
                'folder_id' => $folder->id,
                'name' => $title,
                'description' => $description,
                'type' => AssetFiles::typeFromExtension($ext),
                'file_ext' => $existing->file_ext,
                'storage_key' => $existing->storageKey(),
                'size_bytes' => $existing->size_bytes ?: $data['size'],
                'content_md5' => $data['md5'],
                'sort_order' => (int) $folder->assets()->max('sort_order') + 1,
                'notify' => (bool) ($data['notify'] ?? false),
            ]);

            return response()->json([
                'reused' => true,
                'asset_id' => $asset->id,
                'matched' => FolderTree::path($existing->folder_id, ' › ').' › '.$existing->name,
            ]);
        }

        $upload = Upload::create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'folder_id' => $folder->id,
            'filename' => $data['filename'],
            'title' => $title,
            'description' => $description,
            'size' => $data['size'],
            'md5' => $data['md5'] ?? null,
            'notify' => (bool) ($data['notify'] ?? false),
        ]);

        return response()->json(['reused' => false, 'upload_id' => $upload->id, 'chunk_size' => self::CHUNK, 'received' => 0]);
    }

    public function chunk(Request $request, Upload $upload)
    {
        $this->own($request, $upload);
        $offset = (int) $request->query('offset', -1);
        if ($offset !== (int) $upload->received) {
            // Out of step (e.g. a retried piece): tell the browser where to continue.
            return response()->json(['received' => (int) $upload->received], 409);
        }

        $body = $request->getContent();
        if ($body === '' || strlen($body) > self::CHUNK || $upload->received + strlen($body) > $upload->size) {
            return response()->json(['message' => 'Bad piece'], 422);
        }

        Storage::disk('local')->append($upload->partPath(), $body, null);
        $upload->increment('received', strlen($body));

        return response()->json(['received' => (int) $upload->received]);
    }

    public function complete(Request $request, Upload $upload)
    {
        $this->own($request, $upload);
        $disk = Storage::disk('local');
        if ((int) $upload->received !== (int) $upload->size || $disk->size($upload->partPath()) !== (int) $upload->size) {
            return response()->json(['message' => 'The upload is incomplete.'], 422);
        }

        $md5 = md5_file($disk->path($upload->partPath()));
        if ($upload->md5 && $upload->md5 !== $md5) {
            $disk->delete($upload->partPath());
            $upload->update(['status' => 'failed']);

            return response()->json(['message' => 'The file was damaged on the way. Please try again.'], 422);
        }

        @set_time_limit(0);
        $folder = $upload->folder;
        $ext = AssetFiles::cleanExtension($upload->filename);
        $asset = Asset::create([
            'folder_id' => $folder->id,
            'name' => $upload->title ?: AssetFiles::titleFromFilename($upload->filename),
            'description' => $upload->description,
            'type' => AssetFiles::typeFromExtension($ext),
            'sort_order' => (int) $folder->assets()->max('sort_order') + 1,
            'notify' => $upload->notify,
        ]);
        // attach() also reuses an identical file if one appeared meanwhile.
        $reused = AssetFiles::attach($asset, $upload->partPath(), $upload->filename);
        $upload->update(['status' => 'done', 'asset_id' => $asset->id]);

        return response()->json(['asset_id' => $asset->id, 'reused' => $reused]);
    }

    public function cancel(Request $request, Upload $upload)
    {
        $this->own($request, $upload);
        Storage::disk('local')->delete($upload->partPath());
        $upload->delete();

        return response()->noContent();
    }

    /** Logged once per batch by the browser when a queue finishes. */
    public function summary(Request $request)
    {
        $data = $request->validate(['folder_id' => ['required', 'integer'], 'uploaded' => ['integer'], 'reused' => ['integer']]);
        AdminLog::add('uploaded', 'file', $data['folder_id'],
            'Uploaded '.($data['uploaded'] ?? 0).' file(s), reused '.($data['reused'] ?? 0).' already in storage, in '.FolderTree::path($data['folder_id']));

        return response()->noContent();
    }

    protected function own(Request $request, Upload $upload): void
    {
        abort_unless($upload->user_id === $request->user()->id && $upload->status === 'uploading', 404);
    }
}
