<?php

use Illuminate\Support\Facades\Route;
use Src\CmsAdmin\Http\Controllers\Auth\PasswordResetController;
use Src\CmsAdmin\Http\Controllers\Auth\RegisteredUserController;
use Src\CmsAdmin\Http\Controllers\Auth\SessionController;

Route::middleware('guest:admin,customer')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.attempt');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('customer.register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('customer.register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('customer.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('customer.password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('customer.password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('customer.password.update');
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth:admin,customer')
    ->name('logout');
