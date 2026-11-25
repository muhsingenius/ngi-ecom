<?php

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductPartsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DesignAssetController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\PrivateFileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');

// Public catalogue.
Route::get('/products', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/products/{product:slug}', [CatalogueController::class, 'show'])->name('catalogue.show');

// Designer. Guests own their work through a session token (see EnsureDesignerToken).
Route::middleware('designer')->group(function () {
    Route::get('/products/{product:slug}/design', [DesignController::class, 'create'])->name('designs.create');
    Route::get('/designs/{design}', [DesignController::class, 'show'])->name('designs.show');
    Route::get('/designs/{design}/edit', [DesignController::class, 'edit'])->name('designs.edit');
    Route::post('/designs', [DesignController::class, 'store'])->middleware('throttle:designs')->name('designs.store');
    Route::put('/designs/{design}', [DesignController::class, 'update'])->middleware('throttle:designs')->name('designs.update');

    Route::get('/design-assets/urls', [DesignAssetController::class, 'urls'])->name('design-assets.urls');
    Route::post('/design-assets', [DesignAssetController::class, 'store'])->middleware('throttle:uploads')->name('design-assets.store');
});

// Admin area: guests -> login, signed-in users without the role -> 403.
Route::middleware(['auth', 'role:admin', 'verified'])
    ->prefix('admin')
    ->group(function () {
        Route::view('/', 'admin.dashboard')->name('admin.dashboard');

        Route::get('settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('admin.settings.update');

        Route::resource('products', AdminProductController::class)->except('show')->names('admin.products');

        Route::controller(ProductPartsController::class)->group(function () {
            Route::post('products/{product}/colours', 'storeColour')->name('admin.colours.store');
            Route::put('colours/{colour}', 'updateColour')->name('admin.colours.update');
            Route::delete('colours/{colour}', 'destroyColour')->name('admin.colours.destroy');

            Route::post('products/{product}/sizes', 'storeSize')->name('admin.sizes.store');
            Route::put('products/{product}/sizes', 'updateSize')->name('admin.sizes.update');
            Route::delete('products/{product}/sizes', 'destroySize')->name('admin.sizes.destroy');
            Route::put('products/{product}/variants', 'updateVariants')->name('admin.variants.update');

            Route::post('products/{product}/areas', 'storeArea')->name('admin.areas.store');
            Route::put('areas/{area}', 'updateArea')->name('admin.areas.update');
            Route::delete('areas/{area}', 'destroyArea')->name('admin.areas.destroy');

            Route::post('colours/{colour}/mockups', 'storeMockup')->name('admin.mockups.store');
            Route::delete('mockups/{mockup}', 'destroyMockup')->name('admin.mockups.destroy');
        });
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
