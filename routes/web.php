<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
// The old Airwave studio lived here; keep the address from 404ing for bookmarks.
Route::redirect('/admin/studio', '/admin');
Route::redirect('/admin/login', '/login');
