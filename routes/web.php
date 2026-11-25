<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductPartsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DesignAssetController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DesignOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\ProofController;
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

    Route::get('/designs/{design}/order', [DesignOrderController::class, 'show'])->name('designs.order');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/quote', [CartController::class, 'quote'])->middleware('throttle:120,1')->name('cart.quote');
    Route::delete('/cart/{line}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::post('/proofs/{token}/revise/{line}', [ProofController::class, 'revise'])->name('proofs.revise');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('/design-assets/urls', [DesignAssetController::class, 'urls'])->name('design-assets.urls');
    Route::post('/design-assets', [DesignAssetController::class, 'store'])->middleware('throttle:uploads')->name('design-assets.store');
});

// Customer order pages (secret link for guests, or the signed-in owner) and the Paystack webhook.
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('orders.pay');
Route::get('/proofs/{token}', [ProofController::class, 'show'])->name('proofs.show');
Route::get('/proofs/{token}/image', [ProofController::class, 'image'])->name('proofs.image');
Route::post('/proofs/{token}/approve', [ProofController::class, 'approve'])->middleware('throttle:20,1')->name('proofs.approve');
Route::post('/proofs/{token}/changes', [ProofController::class, 'changes'])->middleware('throttle:20,1')->name('proofs.changes');
Route::post('/webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('designs', [AccountController::class, 'designs'])->name('account.designs');
    Route::delete('designs/{design}', [AccountController::class, 'destroyDesign'])->name('account.designs.destroy');
});

// Admin area: guests -> login, signed-in users without the role -> 403.
Route::middleware(['auth', 'role:admin', 'verified'])
    ->prefix('admin')
    ->group(function () {
        Route::view('/', 'admin.dashboard')->name('admin.dashboard');

        Route::get('settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('admin.settings.update');

        Route::controller(DeliveryController::class)->prefix('delivery')->group(function () {
            Route::get('/', 'index')->name('admin.delivery.index');
            Route::post('zones', 'storeZone')->name('admin.delivery.zones.store');
            Route::put('zones/{zone}', 'updateZone')->name('admin.delivery.zones.update');
            Route::delete('zones/{zone}', 'destroyZone')->name('admin.delivery.zones.destroy');
            Route::post('pickups', 'storePickup')->name('admin.delivery.pickups.store');
            Route::put('pickups/{pickup}', 'updatePickup')->name('admin.delivery.pickups.update');
            Route::delete('pickups/{pickup}', 'destroyPickup')->name('admin.delivery.pickups.destroy');
        });

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
