<?php

use Illuminate\Support\Facades\Route;
use Src\Setting\Http\Controllers\SettingController;

Route::prefix('admin')->name('admin.')->middleware('permission:manage-settings')->controller(SettingController::class)->group(function (): void {
    Route::get('/settings', 'edit')->name('settings.edit');
    Route::put('/settings', 'update')->name('settings.update');
    Route::put('/settings/storage', 'updateStorage')->name('settings.storage.update');
});
