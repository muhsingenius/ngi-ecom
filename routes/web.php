<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductPartsController;
use App\Http\Controllers\Admin\ReportController;
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
use App\Http\Controllers\Shop\JobController;
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
        Route::get('/', DashboardController::class)->name('admin.dashboard');
        Route::get('reports', ReportController::class)->name('admin.reports');

        Route::controller(AdminOrderController::class)->prefix('orders')->group(function () {
            Route::get('/', 'index')->name('admin.orders.index');
            Route::get('{order}', 'show')->name('admin.orders.show');
            Route::post('{order}/dispatch', 'dispatch')->name('admin.orders.dispatch');
            Route::post('{order}/picked-up', 'pickedUp')->name('admin.orders.picked-up');
            Route::post('{order}/complete', 'complete')->name('admin.orders.complete');
            Route::post('{order}/cancel', 'cancel')->name('admin.orders.cancel');
            Route::post('{order}/mark-paid', 'markPaid')->name('admin.orders.mark-paid');
            Route::post('{order}/verify', 'verify')->name('admin.orders.verify');
            Route::post('{order}/refund', 'refund')->name('admin.orders.refund');
            Route::post('{order}/proof/replace', 'replaceProof')->name('admin.orders.proof.replace');
            Route::post('{order}/proof/resend', 'resendProof')->name('admin.orders.proof.resend');
            Route::post('{order}/proof/timing', 'proofTiming')->name('admin.orders.proof.timing');
            Route::post('{order}/lines/{line}/revise', 'reviseDesign')->name('admin.orders.revise');
            Route::post('{order}/clear-flag', 'clearFlag')->name('admin.orders.clear-flag');
            Route::get('{order}/sheet', 'sheet')->name('admin.orders.sheet');
            Route::get('{order}/files', 'files')->name('admin.orders.files');
        });

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
        Route::get('/', [JobController::class, 'index'])->name('shop.jobs');

        Route::controller(JobController::class)->prefix('jobs/{order}')->group(function () {
            Route::get('/', 'show')->name('shop.jobs.show');
            Route::post('stage', 'stage')->name('shop.jobs.stage');
            Route::get('sheet', 'sheet')->name('shop.jobs.sheet');
            Route::get('files', 'files')->name('shop.jobs.files');
            Route::get('files/{area}', 'file')->name('shop.jobs.file');
            Route::get('placement/{area}', 'placement')->name('shop.jobs.placement');
        });
    });

// Protected download of private files via signed, expiring URLs (see App\Support\PrivateFile).
Route::get('/files/download', PrivateFileController::class)
    ->middleware('signed')
    ->name('files.download');
