<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductModifierController;
use App\Http\Controllers\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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

        Route::middleware('merchant.manage')->group(function () {
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
