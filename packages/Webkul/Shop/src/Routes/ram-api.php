<?php

use Illuminate\Support\Facades\Route;
use Webkul\Shop\Http\Controllers\API\RamCartController;
use Webkul\Shop\Http\Controllers\API\RamProductsController;
use Webkul\Shop\Http\Controllers\API\RamWishlistController;

/**
 * RAM Integration APIs #192, #159
 *
 * Server-to-server endpoints for Muro Loco integration.
 * Uses 'api' middleware (no session, no CSRF).
 * Authenticated via service token (ram.service.token middleware).
 */
Route::prefix('api/ram')->middleware(['ram.service.token'])->group(function () {
    Route::get('products/popular', [RamProductsController::class, 'popular'])
        ->name('shop.api.ram.products.popular');

    Route::post('cart/add', [RamCartController::class, 'add'])
        ->name('shop.api.ram.cart.add');

    Route::get('cart/count', [RamCartController::class, 'count'])
        ->name('shop.api.ram.cart.count');

    Route::post('wishlist/toggle', [RamWishlistController::class, 'toggle'])
        ->name('shop.api.ram.wishlist.toggle');

    Route::get('wishlist', [RamWishlistController::class, 'index'])
        ->name('shop.api.ram.wishlist.index');
});
