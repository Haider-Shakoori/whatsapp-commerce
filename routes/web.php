<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PaymentSettingsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductModifierController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RelayWaChannelController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/payment-result/{publicId}', PaymentReturnController::class)->name('payments.return');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('m/{merchant:slug}')->middleware('merchant.member')->name('merchant.')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/payment-settings', [PaymentSettingsController::class, 'index'])->middleware('merchant.manage')->name('payments.settings');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/channels', [RelayWaChannelController::class, 'index'])->middleware('merchant.manage')->name('channels.index');

        Route::middleware('merchant.manage')->group(function () {
            Route::put('/payment-settings', [PaymentSettingsController::class, 'update'])->name('payments.settings.update');
            Route::post('/orders/{order}/payment-method', [OrderPaymentController::class, 'createMethod'])->name('orders.payments.method');
            Route::post('/orders/{order}/stripe-test-link', [OrderPaymentController::class, 'stripeTestLink'])->name('orders.payments.link');
            Route::post('/orders/{order}/payments/{attempt}/confirm', [OrderPaymentController::class, 'confirmManual'])->name('orders.payments.confirm');
            Route::post('/orders/{order}/payments/{attempt}/reconcile', [OrderPaymentController::class, 'reconcile'])->name('orders.payments.reconcile');
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
            Route::post('/channels', [RelayWaChannelController::class, 'store'])->name('channels.store');
            Route::post('/channels/{channel}/menu', [RelayWaChannelController::class, 'setMenuEnabled'])->name('channels.menu');
            Route::post('/channels/{channel}/webhook-secret', [RelayWaChannelController::class, 'setWebhookSecret'])->name('channels.webhook-secret');
            Route::delete('/channels/{channel}', [RelayWaChannelController::class, 'destroy'])->name('channels.destroy');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import');
            Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
            Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
            Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
            Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
            Route::post('/products/{product}/modifiers', [ProductModifierController::class, 'store'])->name('products.modifiers.store');
            Route::delete('/products/{product}/modifiers/{group}', [ProductModifierController::class, 'destroy'])->name('products.modifiers.destroy');
            Route::post('/products/{product}/stock', [InventoryController::class, 'adjust'])->name('products.stock.adjust');
        });
    });
});
