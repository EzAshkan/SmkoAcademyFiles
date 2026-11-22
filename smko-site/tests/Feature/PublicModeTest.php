<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Folder;
use App\Models\User;
use App\Support\FolderTree;
use App\Support\NewFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** SMKO_PUBLIC_MODE=true: open library, admin panel still behind a login. */
class PublicModeTest extends TestCase
{
    use RefreshDatabase;

    protected Folder $kids;
    protected Folder $book;
    protected Asset $track;

    protected function setUp(): void
    {
        parent::setUp();
        config(['smko.public_mode' => true, 'smko.prefix' => 'downloads']);
        FolderTree::flush();
        NewFiles::flush();
        Storage::fake('bucket');
        Storage::disk('bucket')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?signed=1");

        $this->kids = Folder::create(['name' => 'منداڵان']);
        $this->book = Folder::create(['name' => 'All Together 1', 'parent_id' => $this->kids->id]);
        $this->track = Asset::create(['folder_id' => $this->book->id, 'name' => 'Page 7 Part 1', 'type' => 'AUDIO', 'file_ext' => 'mp3',
            'storage_key' => 'smko-media/assets/15/abc.mp3', 'size_bytes' => 600 * 1048576]);
    }

    public function test_anyone_can_browse_and_download_without_logging_in(): void
    {
        $this->get('/')->assertOk()->assertSee('منداڵان')->assertDontSee('Log in');
        $this->get(route('library.folder', $this->book))->assertOk()->assertSee('Page 7 Part 1');
        $this->get(route('library.asset', $this->track))->assertOk();
        $this->get(route('library.download', $this->track))
            ->assertRedirect('https://bucket.test/smko-media/assets/15/abc.mp3?signed=1');

        // Views are counted anonymously, once per browser session.
        $this->post(route('library.seen', $this->track))->assertNoContent();
        $this->post(route('library.seen', $this->track))->assertNoContent();
        $this->assertSame(1, \App\Models\AssetEvent::whereNull('user_id')->where('type', 'view')->count());
    }

    public function test_no_telegram_in_public_mode(): void
    {
        $this->get(route('library.folder', $this->book))->assertDontSee('/telegram');
        $this->post(route('library.telegram', $this->track))->assertNotFound();
    }

    public function test_admin_panel_still_needs_a_login_and_hides_student_features(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $admin = User::create(['name' => 'Admin', 'phone' => '0912', 'password' => 'secret-pass', 'is_admin' => true, 'must_change_password' => false]);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->get('/admin/folders')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->assertFalse(\App\Filament\Resources\Folders\RelationManagers\UsersRelationManager::canViewForRecord($this->book, \App\Filament\Resources\Folders\Pages\EditFolder::class));

        // Uploads never trigger Telegram alerts here.
        $this->postJson('/admin-api/uploads/check', [
            'folder_id' => $this->book->id, 'filename' => 'new.mp3', 'size' => 10, 'md5' => md5('x'), 'notify' => true,
        ])->assertOk();
        $this->assertFalse((bool) \App\Models\Upload::first()->notify);
    }

    public function test_normal_mode_still_requires_login(): void
    {
        config(['smko.public_mode' => false]);
        $this->get('/')->assertRedirect('/login');
        $this->get(route('library.download', $this->track))->assertRedirect('/login');
    }

    public function test_import_media_export(): void
    {
        $file = storage_path('framework/testing/media-export.json');
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, json_encode([
            'exported_at' => '2026-11-21',
            'folders' => [
                '5' => ['id' => 5, 'parent_id' => null, 'name' => 'منداڵان', 'order' => 0],
                '8' => ['id' => 8, 'parent_id' => 5, 'name' => 'All Together 1', 'order' => 0],
            ],
            'assets' => [
                ['folder_id' => 8, 'order' => 0, 'name' => 'Page 7', 'type' => 'AUDIO', 'size' => '0.49MB', 'created_at' => 'Apr 12, 2026',
                    'modified' => '2026-04-13 14:40:03', 'description' => '', 'text' => '', 'storage_key' => 'smko-media/assets/15/p2HL.mp3', 'in_bucket' => true, 'size_bytes' => 513802],
                ['folder_id' => 8, 'order' => 1, 'name' => 'Broken', 'type' => 'VIDEO', 'size' => null, 'created_at' => '', 'modified' => '',
                    'description' => '', 'text' => '', 'storage_key' => 'smko-media/0'],
            ],
        ]));

        $this->artisan('smko:import-media', ['file' => $file, '--force' => true])->assertSuccessful();
        @unlink($file);

        $this->assertSame(2, Folder::count());
        $this->assertSame(1, Asset::count());
        $a = Asset::first();
        $this->assertSame('smko-media/assets/15/p2HL.mp3', $a->storageKey());
        $this->assertSame('All Together 1', $a->folder->name);
        $this->assertSame(513802, $a->size_bytes);
    }
}
