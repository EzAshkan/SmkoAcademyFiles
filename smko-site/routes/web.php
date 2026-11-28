<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/share/assets/{asset}', [LibraryController::class, 'share'])
    ->middleware('signed')->name('assets.share');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'showPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    // Background uploads from the admin panel's upload queue.
    Route::middleware('admin')->prefix('admin-api/uploads')->controller(\App\Http\Controllers\Admin\UploadController::class)->group(function () {
        Route::post('check', 'check')->middleware('throttle:300,1');
        Route::post('summary', 'summary');
        Route::post('{upload}/chunk', 'chunk')->middleware('throttle:2000,1');
        Route::post('{upload}/complete', 'complete');
        Route::delete('{upload}', 'cancel');
    });
    // Download a database backup (admins only; name must be one of the listed backups).
    Route::middleware('admin')->get('admin-api/backups/{name}', function (string $name) {
        $b = \App\Support\Backups::list()->firstWhere('name', $name);
        abort_unless($b, 404);
        \App\Models\AdminLog::add('downloaded', 'backup', null, 'Downloaded backup '.$name);

        return response()->download($b['path'], $name);
    })->where('name', '[A-Za-z0-9_.-]+\.zip')->name('admin.backups.download');

    // Folder list for the upload panel's "where should these go?" picker.
    Route::middleware('admin')->get('admin-api/folders', fn () => collect(\App\Support\FolderTree::options())
        ->map(fn ($path, $id) => ['id' => $id, 'path' => $path])->values());

});

// Student library. In public mode (SMKO_PUBLIC_MODE) it's open to everyone.
Route::middleware('library')->group(function () {
    Route::get('/', [LibraryController::class, 'home'])->name('library.home');
    Route::get('/dashboard', [LibraryController::class, 'legacyDashboard']);
    Route::get('/search', [LibraryController::class, 'search'])->middleware('throttle:60,1')->name('library.search');
    Route::get('/folders/{folder}', [LibraryController::class, 'folder'])->name('library.folder');
    Route::get('/assets/{asset}', [LibraryController::class, 'asset'])->name('library.asset');
    Route::get('/assets/{asset}/download', [LibraryController::class, 'download'])
        ->middleware('throttle:60,1')->name('library.download');
    Route::get('/assets/{asset}/preview', [LibraryController::class, 'preview'])->name('library.preview');
    Route::post('/assets/{asset}/seen', [LibraryController::class, 'seen'])
        ->middleware('throttle:120,1')->name('library.seen');
    // Not available in public mode (needs a student account).
    Route::post('/assets/{asset}/telegram', [LibraryController::class, 'telegram'])
        ->middleware('throttle:20,1')->name('library.telegram');
});
