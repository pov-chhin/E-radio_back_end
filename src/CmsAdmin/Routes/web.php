<?php

use Illuminate\Support\Facades\Route;
use Src\CmsAdmin\Http\Controllers\ArtistController;
use Src\CmsAdmin\Http\Controllers\CategoryController;
use Src\CmsAdmin\Http\Controllers\CustomerController;
use Src\CmsAdmin\Http\Controllers\DashboardController;
use Src\CmsAdmin\Http\Controllers\MediaContentController;
use Src\CmsAdmin\Http\Controllers\MusicController;
use Src\CmsAdmin\Http\Controllers\NotificationController;
use Src\CmsAdmin\Http\Controllers\ProfileController;
use Src\CmsAdmin\Http\Controllers\ProgramController;
use Src\CmsAdmin\Http\Controllers\RadioChannelController;
use Src\CmsAdmin\Http\Controllers\TagController;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('permission:view-customers');
});

Route::prefix('admin')->name('admin.')->controller(NotificationController::class)->group(function (): void {
    Route::get('/notifications', 'index')->name('notifications.index');
    Route::post('/notifications/read-all', 'readAll')->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', 'read')->name('notifications.read');
    Route::delete('/notifications/{notification}', 'destroy')->name('notifications.destroy');
});

Route::prefix('admin')->name('admin.')->controller(RadioChannelController::class)->group(function (): void {
    Route::get('/radio-channels', 'index')->name('radio-channels.index')->middleware('permission:view-radio-channels');
    Route::get('/radio-channels/create', 'create')->name('radio-channels.create')->middleware('permission:create-radio-channels');
    Route::post('/radio-channels', 'store')->name('radio-channels.store')->middleware('permission:create-radio-channels');
    Route::post('/radio-channels/sync', 'sync')->name('radio-channels.sync')->middleware('permission:create-radio-channels');
    Route::get('/radio-channels/{radioChannel}/edit', 'edit')->name('radio-channels.edit')->middleware('permission:edit-radio-channels');
    Route::get('/radio-channels/{radioChannel}', 'show')->name('radio-channels.show')->middleware('permission:view-radio-channels');
    Route::put('/radio-channels/{radioChannel}', 'update')->name('radio-channels.update')->middleware('permission:edit-radio-channels');
    Route::patch('/radio-channels/{radioChannel}/toggle-publish', 'togglePublish')->name('radio-channels.togglePublish')->middleware('permission:edit-radio-channels');
    Route::post('/radio-channels/{radioChannel}/check-signal', 'checkSignal')->name('radio-channels.checkSignal')->middleware('permission:view-radio-channels');
    Route::delete('/radio-channels/{radioChannel}', 'destroy')->name('radio-channels.destroy')->middleware('permission:delete-radio-channels');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('categories', CategoryController::class)->except(['show'])
        ->middlewareFor(['index'], 'permission:view-categories')
        ->middlewareFor(['create', 'store'], 'permission:create-categories')
        ->middlewareFor(['edit', 'update'], 'permission:edit-categories')
        ->middlewareFor(['destroy'], 'permission:delete-categories');
    Route::resource('tags', TagController::class)->except(['show'])
        ->middlewareFor(['index'], 'permission:view-tags')
        ->middlewareFor(['create', 'store'], 'permission:create-tags')
        ->middlewareFor(['edit', 'update'], 'permission:edit-tags')
        ->middlewareFor(['destroy'], 'permission:delete-tags');
    Route::resource('artists', ArtistController::class)->except(['show'])
        ->middlewareFor(['index'], 'permission:view-artists')
        ->middlewareFor(['create', 'store'], 'permission:create-artists')
        ->middlewareFor(['edit', 'update'], 'permission:edit-artists')
        ->middlewareFor(['destroy'], 'permission:delete-artists');
    // Podcasts live at /admin/podcasts; the route names, parameter and permissions keep their "programs" names.
    Route::resource('podcasts', ProgramController::class)->except(['show'])
        ->names('programs')
        ->parameters(['podcasts' => 'program'])
        ->middlewareFor(['index'], 'permission:view-programs')
        ->middlewareFor(['create', 'store'], 'permission:create-programs')
        ->middlewareFor(['edit', 'update'], 'permission:edit-programs')
        ->middlewareFor(['destroy'], 'permission:delete-programs');
    Route::get('music', [MusicController::class, 'index'])->name('music.index')->middleware('permission:view-media-content');
    // The create form can start on a category: /admin/media-contents/create/song (the older ?category=song still works).
    Route::get('media-contents/create/{category?}', [MediaContentController::class, 'create'])->name('media-contents.create')
        ->where('category', '[a-z0-9-]+')
        ->middleware('permission:create-media-content');
    // The Article page's Edit link: /admin/media-contents/edit/news/16 — the item's category, then its id (the plain
    // /admin/media-contents/16/edit stays for every other page).
    Route::get('media-contents/edit/{category}/{media_content}', [MediaContentController::class, 'editIn'])->name('media-contents.edit-in')
        ->where(['category' => '[a-z0-9-]+', 'media_content' => '[0-9]+'])
        ->middleware('permission:edit-media-content');
    // "Retry" beside a failed audio fetch/conversion: the same job again, without pasting the link or file again.
    Route::post('media-contents/{media_content}/retry-audio', [MediaContentController::class, 'retryAudio'])->name('media-contents.retry-audio')
        ->where('media_content', '[0-9]+')
        ->middleware('permission:edit-media-content');
    Route::resource('media-contents', MediaContentController::class)->except(['show', 'create'])
        ->middlewareFor(['index'], 'permission:view-media-content')
        ->middlewareFor(['store'], 'permission:create-media-content')
        ->middlewareFor(['edit', 'update'], 'permission:edit-media-content')
        ->middlewareFor(['destroy'], 'permission:delete-media-content');
});
