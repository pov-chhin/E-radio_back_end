<?php

use Illuminate\Support\Facades\Route;
use Src\Setting\Http\Controllers\LocaleController;
use Src\Setting\Http\Controllers\SettingController;

Route::prefix('admin')->name('admin.')->middleware('permission:manage-settings')->controller(SettingController::class)->group(function (): void {
    Route::get('/settings', 'edit')->name('settings.edit');
    Route::put('/settings', 'update')->name('settings.update');
    Route::put('/settings/storage', 'updateStorage')->name('settings.storage.update');
    Route::put('/settings/youtube', 'updateYoutube')->name('settings.youtube.update');
    Route::post('/settings/storage/public-read', 'makePublic')->name('settings.storage.public-read');
    Route::put('/settings/rustfs', 'updateRustfs')->name('settings.rustfs.update');
    Route::put('/settings/language', 'updateLanguage')->name('settings.language.update');
    Route::put('/settings/radio-studio', 'updateRadioStudio')->name('settings.radio-studio.update');
});

// The account menu's language switch: every signed-in admin may pick their own language.
Route::put('/admin/locale', [LocaleController::class, 'update'])->name('admin.locale.update');
