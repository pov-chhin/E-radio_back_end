<?php

use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MediaContentController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TagController;
use App\Http\Middleware\RecordUserCountry;
use Illuminate\Support\Facades\Route;
use Src\Setting\Http\Controllers\YoutubeCookieSyncController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:20,1,auth-google');

// The cookie-sync browser extension keeps the server's YouTube cookies fresh. Off unless YOUTUBE_COOKIE_SYNC_TOKEN
// is set; it answers only a request that carries that token.
Route::post('/youtube-cookies/sync', YoutubeCookieSyncController::class)->middleware('throttle:30,1,youtube-cookie-sync');

// Forgotten password: emailed code → reset token → new password. The third
// throttle argument names each limit's own bucket — without it every
// `throttle:N,M` route shares one counter per IP, and a person's earlier steps
// would use up their later ones.
Route::post('/password/forgot', [PasswordResetController::class, 'sendCode'])->middleware('throttle:6,1,password-forgot');
Route::post('/password/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:10,1,password-verify');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1,password-reset');

Route::middleware(['auth:sanctum', RecordUserCountry::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks/toggle', [BookmarkController::class, 'toggle']);
});

Route::get('/stations', [StationController::class, 'index']);
Route::get('/home', HomeController::class);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/tags', [TagController::class, 'index']);
Route::get('/artists', [ArtistController::class, 'index']);
Route::get('/programs', [ProgramController::class, 'index']);
Route::get('/programs/{program}', [ProgramController::class, 'show']);
Route::get('/media-contents', [MediaContentController::class, 'index']);
Route::get('/media-contents/{mediaContent}', [MediaContentController::class, 'show']);
Route::post('/media-contents/{mediaContent}/play', [MediaContentController::class, 'recordPlay'])->middleware('throttle:60,1');
Route::get('/comments', [CommentController::class, 'index']);
