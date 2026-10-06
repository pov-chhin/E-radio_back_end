<?php

use App\Http\Controllers\MinioMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/media/minio/{path}', MinioMediaController::class)
    ->where('path', '.*')
    ->middleware('signed')
    ->name('media.minio');

Route::redirect('/', '/admin/studio');
Route::redirect('/admin/login', '/login');
