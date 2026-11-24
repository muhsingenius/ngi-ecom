<?php

use App\Http\Controllers\PrivateFileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');

// Admin area: guests -> login, signed-in users without the role -> 403.
Route::middleware(['auth', 'role:admin', 'verified'])
    ->prefix('admin')
    ->group(function () {
        Route::view('/', 'admin.dashboard')->name('admin.dashboard');
    });

// Shop (print/production) area.
Route::middleware(['auth', 'role:shop', 'verified'])
    ->prefix('shop')
    ->group(function () {
        Route::view('/', 'shop.jobs')->name('shop.jobs');
    });

// Protected download of private files via signed, expiring URLs (see App\Support\PrivateFile).
Route::get('/files/download', PrivateFileController::class)
    ->middleware('signed')
    ->name('files.download');
