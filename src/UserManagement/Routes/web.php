<?php

use Illuminate\Support\Facades\Route;
use Src\UserManagement\Http\Controllers\PermissionController;
use Src\UserManagement\Http\Controllers\RoleController;
use Src\UserManagement\Http\Controllers\UserController;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('roles', RoleController::class)->except(['show'])
        ->middlewareFor(['index'], 'permission:view-roles')
        ->middlewareFor(['create', 'store'], 'permission:create-roles')
        ->middlewareFor(['edit', 'update'], 'permission:edit-roles')
        ->middlewareFor(['destroy'], 'permission:delete-roles');
    Route::resource('permissions', PermissionController::class)->only(['index', 'create', 'store', 'destroy'])
        ->middlewareFor(['index'], 'permission:view-permissions')
        ->middlewareFor(['create', 'store'], 'permission:create-permissions')
        ->middlewareFor(['destroy'], 'permission:delete-permissions');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middlewareFor(['index'], 'permission:view-users')
        ->middlewareFor(['create', 'store'], 'permission:create-users')
        ->middlewareFor(['edit', 'update'], 'permission:edit-users')
        ->middlewareFor(['destroy'], 'permission:delete-users');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggleActive')
        ->middleware('permission:edit-users');
});
