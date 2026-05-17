<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->controller(ProductController::class)
    ->group(function () {

        Route::get('products', 'index');
        Route::get('products/{id}', 'show');

        Route::get('categories/{categoryId}/products', 'byCategoryId');
        Route::get('categories/slug/{slug}/products', 'byCategorySlug');

        Route::post('products', 'store');
        Route::put('products/update/{id}', 'update');
        Route::delete('products/{id}', 'destroy');
        Route::put('products/decrease-stock/{id}', 'decreaseStock');
    });
