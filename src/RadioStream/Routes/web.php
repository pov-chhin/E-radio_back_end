<?php

use Src\RadioStream\Http\Controllers\RadioController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/studio')->name('dashboard.')->middleware('permission:view-dashboard')->controller(RadioController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/live', 'live')->name('live');
    Route::get('/stations', 'stations')->name('stations');
    Route::get('/stations/reset', 'resetStation')->name('station.reset');
    Route::get('/stations/{station}/switch', 'switchStation')->name('station.switch');
    Route::get('/stations/{station}', 'stationDashboard')->name('station.show');
    Route::get('/player', 'player')->name('player');
    Route::get('/playlist', 'playlist')->name('playlist');
    Route::get('/schedule', 'schedule')->name('schedule');
    Route::get('/listeners', 'listeners')->name('listeners');
    Route::get('/settings', 'settings')->name('settings');

    Route::post('/live/toggle', 'toggleLive')->name('live.toggle');
    Route::post('/station/restart', 'restartStation')->name('station.restart');
    Route::post('/services/{service}/{action}', 'controlService')->name('services.control');
    Route::post('/requests/{requestId}', 'requestSong')->name('requests.submit');
    Route::post('/playlist/{track}/queue', 'queueTrack')->name('playlist.queue');
    Route::post('/settings', 'updateSettings')->name('settings.update');
});

Route::prefix('admin/studio')->name('dashboard.')->middleware('permission:manage-settings')->controller(RadioController::class)->group(function (): void {
    Route::put('/settings/azuracast', 'updateAzuraCastSettings')->name('settings.azuracast.update');
});
