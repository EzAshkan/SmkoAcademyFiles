<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Folder;
use App\Models\TelegramClaim;
use App\Models\User;
use App\Support\FolderTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SmkoTest extends TestCase
{
    use RefreshDatabase;

    protected Folder $adults;
    protected Folder $book;
    protected Folder $lesson;
    protected Folder $teens;
    protected Asset $track;

    protected function setUp(): void
    {
        parent::setUp();
        FolderTree::flush();
        \App\Support\NewFiles::flush();
        config(['smko.telegram.internal_token' => 'test-internal-token', 'smko.prefix' => 'downloads',
            // Most tests check the direct-bucket links; streaming is tested separately.
            'smko.proxy_previews' => false, 'smko.bucket_forces_attachment' => false]);
        Storage::fake('bucket');
        Storage::disk('bucket')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?signed=1");

        $this->adults = Folder::create(['name' => 'Adults', 'sort_order' => 0]);
        $this->book = Folder::create(['name' => 'E4E1', 'parent_id' => $this->adults->id]);
        $this->lesson = Folder::create(['name' => 'Lesson 01', 'parent_id' => $this->book->id]);
        $this->teens = Folder::create(['name' => 'Teens', 'sort_order' => 1]);
        $this->track = Asset::create(['folder_id' => $this->lesson->id, 'name' => 'Track 1.5', 'type' => 'AUDIO', 'file_ext' => 'mp3']);
    }

    protected function student(array $attrs = []): User
    {
        return User::create($attrs + [
            'name' => 'Student', 'phone' => '5551234', 'password' => User::DEFAULT_PASSWORD, 'must_change_password' => false,
        ]);
    }

    public function test_login_with_phone_and_starting_password_forces_password_change(): void
    {
        $this->student(['must_change_password' => true]);

        $this->post('/login', ['phone' => '5551234', 'password' => 'smko'])->assertRedirect(route('password.edit'));
        $this->get('/')->assertRedirect(route('password.edit'));

        // "smko" is not accepted as the new password.
        $this->put('/password', ['password' => 'smko', 'password_confirmation' => 'smko'])->assertSessionHasErrors('password');

        $this->put('/password', ['password' => 'my-secret', 'password_confirmation' => 'my-secret'])->assertRedirect(route('library.home'));
        $this->get('/')->assertOk();
    }

    public function test_persian_and_arabic_digits_log_in(): void
    {
        $this->student(['phone' => '09123456789', 'must_change_password' => false]);
        $this->post('/login', ['phone' => '۰۹۱۲ ۳۴۵ ۶۷۸۹', 'password' => 'smko'])->assertRedirect(route('library.home'));
        $this->assertAuthenticated();
        auth()->logout();
        $this->post('/login', ['phone' => '٠٩١٢٣٤٥٦٧٨٩', 'password' => 'smko'])->assertRedirect(route('library.home'));
    }

    public function test_wrong_password_is_rate_limited(): void
    {
        $this->student();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['phone' => '5551234', 'password' => 'nope'])->assertSessionHasErrors('phone');
        }
        // Even the right password is refused while locked out.
        $this->post('/login', ['phone' => '5551234', 'password' => 'smko'])->assertSessionHasErrors('phone');
        $this->assertStringStartsWith('Too many tries', session('errors')->first('phone'));
        $this->assertGreaterThan(0, session('lockout_seconds'));
        $this->assertGuest();
    }

    public function test_access_on_top_folder_covers_everything_inside(): void
    {
        $u = $this->student();
        $u->folders()->attach($this->adults);

        $this->actingAs($u)->get('/')->assertSee('Adults')->assertDontSee('Teens');
        $this->get(route('library.folder', $this->lesson))->assertOk()->assertSee('Track 1.5');
        $this->get(route('library.folder', $this->teens))->assertForbidden();
        $this->get(route('library.download', $this->track))
            ->assertRedirect('https://bucket.test/downloads/'.$this->track->id.'.mp3?signed=1');
    }

    public function test_access_on_sub_folder_alone_shows_just_that_branch(): void
    {
        $u = $this->student();
        $u->folders()->attach($this->book);

        $this->actingAs($u)->get('/')->assertSee('E4E1')->assertSee('Adults');   // parent path shown as context
        $this->get(route('library.folder', $this->adults))->assertForbidden();
        $this->get(route('library.folder', $this->lesson))->assertOk();
    }

    public function test_files_with_an_exact_old_site_key_use_it(): void
    {
        $newer = Asset::create(['folder_id' => $this->lesson->id, 'name' => 'Living with Stress', 'type' => 'AUDIO']);
        \App\Support\StoragePaths::apply([$newer->id => 'smko-files/assets/822/rX5Z20wSDPto.mp3', $this->track->id => 'downloads/'.$this->track->id.'.mp3']);

        $this->assertSame('smko-files/assets/822/rX5Z20wSDPto.mp3', $newer->fresh()->storageKey());
        $this->assertNull($this->track->fresh()->storage_key); // standard layout needs no stored key

        $u = $this->student();
        $u->folders()->attach($this->adults);
        $this->actingAs($u)->get(route('library.download', $newer))
            ->assertRedirect('https://bucket.test/smko-files/assets/822/rX5Z20wSDPto.mp3?signed=1');
    }

    public function test_download_names_only_requested_when_bucket_allows_it(): void
    {
        Storage::disk('bucket')->buildTemporaryUrlsUsing(
            fn ($path, $exp, $opts) => "https://bucket.test/{$path}?".http_build_query($opts)
        );
        $u = $this->student();
        $u->folders()->attach($this->adults);

        // Bucket forces attachment: small files pass through the site with a proper name…
        config(['smko.bucket_forces_attachment' => true, 'smko.proxy_download_max_mb' => 150]);
        Storage::disk('bucket')->put('downloads/'.$this->track->id.'.mp3', 'ID3-fake-audio');
        $this->track->update(['size_bytes' => 14]);
        $res = $this->actingAs($u)->get(route('library.download', $this->track));
        $res->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringContainsString('filename="Track 1.5.mp3"', $res->headers->get('Content-Disposition'));
        $this->assertSame('ID3-fake-audio', $res->streamedContent());

        // …files over the limit go straight to the bucket.
        $this->track->update(['size_bytes' => 200 * 1048576]);
        $this->get(route('library.download', $this->track))
            ->assertRedirect('https://bucket.test/downloads/'.$this->track->id.'.mp3?');
        $this->track->update(['size_bytes' => 14]);

        config(['smko.bucket_forces_attachment' => false]);
        $location = $this->get(route('library.download', $this->track))->headers->get('Location');
        $this->assertStringContainsString('ResponseContentDisposition=attachment', $location);
        $this->assertStringContainsString('Track+1.5.mp3', $location);
    }

    public function test_copy_and_move_files_and_folders(): void
    {
        $ops = \App\Support\LibraryOps::class;

        // Copy a file: new row, same bucket object.
        $ops::copyAssets([$this->track->id], $this->teens->id);
        $copy = Asset::where('folder_id', $this->teens->id)->first();
        $this->assertNotSame($this->track->id, $copy->id);
        $this->assertSame($this->track->storageKey(), $copy->storageKey());
        $this->assertTrue($ops::keyIsShared($this->track->storageKey(), $this->track->id));

        // Replacing the original must not overwrite or delete the object the copy uses.
        Storage::disk('bucket')->put($this->track->storageKey(), 'original');
        Storage::disk('local')->put('upload-tmp/new.mp3', 'replacement');
        \App\Support\AssetFiles::attach($this->track->fresh(), 'upload-tmp/new.mp3', 'new.mp3');
        $this->assertSame('original', Storage::disk('bucket')->get($copy->fresh()->storageKey()));
        $this->assertSame('replacement', Storage::disk('bucket')->get($this->track->fresh()->storageKey()));

        // Deep-copy a folder tree, then move it.
        $c = $ops::copyFolders([$this->book->id], $this->teens->id);
        $this->assertSame(['folders' => 2, 'files' => 1], $c);
        $bookCopy = Folder::where('parent_id', $this->teens->id)->where('name', 'E4E1')->first();
        $this->assertNotNull($bookCopy);
        $ops::moveFolders([$bookCopy->id], null);
        $this->assertNull($bookCopy->fresh()->parent_id);

        // A folder can't go inside itself.
        $this->expectException(\InvalidArgumentException::class);
        $ops::moveFolders([$this->adults->id], $this->lesson->id);
    }

    public function test_expired_access_no_longer_counts(): void
    {
        $u = $this->student();
        $u->folders()->attach($this->adults, ['expires_at' => now()->subMinute()]);
        $this->actingAs($u)->get(route('library.folder', $this->lesson))->assertForbidden();

        FolderTree::flush();
        $u->folders()->updateExistingPivot($this->adults->id, ['expires_at' => now()->addDay()]);
        $this->get('/')->assertSee('Adults')->assertSee('until');
        $this->get(route('library.folder', $this->lesson))->assertOk();
    }

    public function test_new_badge_until_opened_and_recently_added(): void
    {
        $u = $this->student();
        $u->folders()->attach($this->adults);
        $this->actingAs($u)->get('/')->assertSee('Recently added')->assertSee('1 new');

        $this->post(route('library.seen', $this->track))->assertNoContent();
        \App\Support\NewFiles::flush();
        $this->get('/')->assertDontSee('1 new');
        $this->assertDatabaseHas('asset_events', ['user_id' => $u->id, 'asset_id' => $this->track->id, 'type' => 'view']);
    }

    public function test_new_file_alerts_go_only_to_students_who_can_open_them(): void
    {
        \App\Support\TelegramRelay::$fake = true;
        \App\Support\TelegramRelay::$sent = [];
        $adultsStudent = $this->student(['phone' => '123456789']);
        $adultsStudent->folders()->attach($this->adults);
        $teensStudent = $this->student(['phone' => '987654321']);
        $teensStudent->folders()->attach($this->teens);
        $this->track->forceFill(['created_at' => now()->subMinutes(10), 'notify' => true])->save();

        $this->artisan('smko:notify-new-files')->assertSuccessful();

        $this->assertCount(1, \App\Support\TelegramRelay::$sent);
        $this->assertSame('123456789', \App\Support\TelegramRelay::$sent[0]['chat_id']);
        $this->assertStringContainsString('Track 1.5', \App\Support\TelegramRelay::$sent[0]['text']);
        $this->assertNotNull($this->track->fresh()->notified_at);
        $this->assertDatabaseHas('telegram_sends', ['kind' => 'new_files', 'recipients' => 1, 'failed' => false]);

        // Nothing is sent twice.
        $this->artisan('smko:notify-new-files')->assertSuccessful();
        $this->assertCount(1, \App\Support\TelegramRelay::$sent);
        \App\Support\TelegramRelay::$fake = false;
    }

    public function test_announcement_banner_reaches_its_audience_only(): void
    {
        \App\Models\Announcement::create(['title' => 'Teens class moved to Monday', 'folder_ids' => [$this->teens->id], 'show_banner' => true]);
        $u = $this->student();
        $u->folders()->attach($this->adults);
        $this->actingAs($u)->get('/')->assertDontSee('Teens class moved');

        $u->folders()->attach($this->teens);
        FolderTree::flush();
        $this->get('/')->assertSee('Teens class moved to Monday');
    }

    public function test_background_upload_in_chunks_then_identical_file_is_reused(): void
    {
        $admin = $this->student(['phone' => '999', 'is_admin' => true]);
        $content = str_repeat('SMKO-audio-bytes-', 1000); // ~17 KB
        $md5 = md5($content);

        // 1) New file → upload in two pieces → stored in the bucket.
        $r = $this->actingAs($admin)->postJson('/admin-api/uploads/check', [
            'folder_id' => $this->lesson->id, 'filename' => 'Lesson_02 Audio.mp3', 'size' => strlen($content), 'md5' => $md5, 'notify' => true,
        ])->assertOk()->assertJson(['reused' => false]);
        $id = $r->json('upload_id');
        $half = intdiv(strlen($content), 2);
        $this->call('POST', "/admin-api/uploads/{$id}/chunk?offset=0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], substr($content, 0, $half))->assertOk();
        // A repeated piece is answered with where to continue.
        $this->call('POST', "/admin-api/uploads/{$id}/chunk?offset=0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], substr($content, 0, $half))
            ->assertStatus(409)->assertJson(['received' => $half]);
        $this->call('POST', "/admin-api/uploads/{$id}/chunk?offset={$half}", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], substr($content, $half))->assertOk();
        $done = $this->postJson("/admin-api/uploads/{$id}/complete")->assertOk()->assertJson(['reused' => false]);

        $asset = Asset::find($done->json('asset_id'));
        $this->assertSame('Lesson 02 Audio', $asset->name);
        $this->assertSame('AUDIO', $asset->type);
        $this->assertSame($md5, $asset->content_md5);
        $this->assertSame($content, Storage::disk('bucket')->get($asset->storageKey()));

        // 2) Same file again, elsewhere → nothing uploaded, existing object reused.
        $again = $this->postJson('/admin-api/uploads/check', [
            'folder_id' => $this->teens->id, 'filename' => 'copy.mp3', 'size' => strlen($content), 'md5' => $md5,
            'title' => 'Unit 3 — Listening', 'description' => 'Play twice in class.',
        ])->assertOk()->assertJson(['reused' => true]);
        $reused = Asset::find($again->json('asset_id'));
        $this->assertSame('Unit 3 — Listening', $reused->name);
        $this->assertSame('Play twice in class.', $reused->description);
        $this->assertFalse($reused->notify); // Telegram alert only when ticked
        $this->assertSame($asset->storageKey(), $reused->storageKey());
        $this->assertSame($this->teens->id, $reused->folder_id);

        // Students can't use the upload API.
        $this->actingAs($this->student(['phone' => '111']))->postJson('/admin-api/uploads/check', [])->assertForbidden();
    }

    public function test_previews_and_downloads_stream_through_the_site(): void
    {
        config(['smko.proxy_previews' => true, 'smko.bucket_forces_attachment' => true]);
        Storage::disk('bucket')->put('downloads/'.$this->track->id.'.mp3', 'ID3-audio-bytes');
        $u = $this->student();
        $u->folders()->attach($this->adults);

        $preview = $this->actingAs($u)->get(route('library.preview', $this->track));
        $preview->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->assertSame('ID3-audio-bytes', $preview->streamedContent());

        $download = $this->get(route('library.download', $this->track));
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertSame('ID3-audio-bytes', $download->streamedContent());

        // Traffic through the server is counted for the dashboard.
        $this->assertSame(15, (int) \Illuminate\Support\Facades\DB::table('traffic_daily')->where('kind', 'download')->value('bytes'));
        $this->assertSame(1, (int) \Illuminate\Support\Facades\DB::table('traffic_daily')->where('kind', 'preview')->value('hits'));
    }

    public function test_backups_keep_only_the_newest(): void
    {
        $dir = storage_path('framework/testing/backups');
        @mkdir($dir, 0777, true);
        array_map('unlink', glob($dir.'/*') ?: []);
        config(['smko.backups.path' => $dir, 'smko.backups.keep' => 14, 'app.name' => 'SMKO Files']);
        foreach (range(1, 16) as $i) {
            $f = $dir."/smko-files-2026-01-{$i}.zip";
            file_put_contents($f, 'x');
            touch($f, now()->subDays(20 - $i)->timestamp);
        }
        $this->assertSame(2, \App\Support\Backups::prune());
        $this->assertCount(14, \App\Support\Backups::list());
        $this->assertSame('smko-files-2026-01-16.zip', \App\Support\Backups::latest()['name']);
        array_map('unlink', glob($dir.'/*') ?: []);
    }

    public function test_student_without_access_cannot_download(): void
    {
        $this->actingAs($this->student())->get(route('library.download', $this->track))->assertForbidden();
    }

    public function test_legacy_dashboard_links_redirect(): void
    {
        $u = $this->student();
        $this->actingAs($u)->get('/dashboard?folder_id='.$this->lesson->id)->assertRedirect(route('library.folder', $this->lesson->id));
    }

    public function test_telegram_flow_matches_what_the_bot_expects(): void
    {
        $u = $this->student();
        $u->folders()->attach($this->adults);

        $res = $this->actingAs($u)->post(route('library.telegram', $this->track));
        $claim = TelegramClaim::first();
        $res->assertRedirect('https://t.me/SAFilesbot?start='.$claim->token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/', $claim->token); // the bot's own pattern

        // Wrong / missing internal token
        $this->postJson('/internal/telegram/claim', ['token' => $claim->token])->assertStatus(401);

        $ok = $this->withToken('test-internal-token')->postJson('/internal/telegram/claim', ['token' => $claim->token]);
        $ok->assertOk()->assertJson([
            'asset_id' => $this->track->id,
            'filename' => 'Track 1.5.mp3',
            'download_url' => 'https://bucket.test/downloads/'.$this->track->id.'.mp3?signed=1',
        ])->assertJsonStructure(['hash']);

        // Version stays the same when only the title changes, and changes when the file is replaced.
        $hash = $ok->json('hash');
        $this->track->update(['name' => 'Track 1.5 (renamed)', 'description' => 'new text']);
        $this->assertSame($hash, $this->track->fresh()->versionHash());
        $this->track->update(['content_md5' => md5('fingerprint filled in later')]);
        $this->assertSame($hash, $this->track->fresh()->versionHash());
        Storage::disk('local')->put('upload-tmp/new.mp3', 'replacement audio');
        \App\Support\AssetFiles::attach($this->track->fresh(), 'upload-tmp/new.mp3', 'new.mp3');
        $this->assertNotSame($hash, $this->track->fresh()->versionHash());
        $this->assertNotSame('downloads/'.$this->track->id.'.mp3', $this->track->fresh()->storageKey());

        // Codes work once only.
        $this->withToken('test-internal-token')->postJson('/internal/telegram/claim', ['token' => $claim->token])->assertNotFound();
    }

    public function test_expired_telegram_code_is_rejected(): void
    {
        $u = $this->student();
        TelegramClaim::create(['token' => str_repeat('a', 32), 'user_id' => $u->id, 'asset_id' => $this->track->id, 'expires_at' => now()->subMinute()]);

        $this->withToken('test-internal-token')->postJson('/internal/telegram/claim', ['token' => str_repeat('a', 32)])->assertNotFound();
    }

    public function test_admin_panel_requires_admin(): void
    {
        $this->actingAs($this->student())->get('/admin')->assertForbidden();

        $admin = $this->student(['phone' => '999', 'is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->get('/admin/folders')->assertOk()->assertSee('Adults');
        $this->get('/admin/folders/'.$this->book->id.'/edit')->assertOk()->assertSee('Adults › E4E1')->assertSee('Sub-folders');
        \Livewire\Livewire::test(\App\Filament\Resources\Folders\RelationManagers\ChildrenRelationManager::class, [
            'ownerRecord' => $this->book, 'pageClass' => \App\Filament\Resources\Folders\Pages\EditFolder::class,
        ])->assertOk()->assertSee('Lesson 01');
        \Livewire\Livewire::test(\App\Filament\Resources\Folders\RelationManagers\AssetsRelationManager::class, [
            'ownerRecord' => $this->lesson, 'pageClass' => \App\Filament\Resources\Folders\Pages\EditFolder::class,
        ])->assertOk()->assertSee('Track 1.5')->assertSee('Upload files')->assertSee('smko-upload');
        $this->get('/admin/assets')->assertOk()->assertSee('Track 1.5');
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/announcements')->assertOk();
        $this->get('/admin/announcements/create')->assertOk();
        $this->get('/admin/admin-logs')->assertOk();
        foreach ([\App\Filament\Widgets\BackupStatus::class, \App\Filament\Widgets\OnlineNow::class,
            \App\Filament\Widgets\TrafficOverview::class, \App\Filament\Widgets\BucketUsage::class,
            \App\Filament\Widgets\TelegramActivity::class,
            \App\Filament\Widgets\SystemHealth::class, \App\Filament\Widgets\ActivityStats::class,
            \App\Filament\Widgets\ActivityChart::class, \App\Filament\Widgets\TopFiles::class] as $widget) {
            \Livewire\Livewire::test($widget)->assertOk();
        }
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }
}
