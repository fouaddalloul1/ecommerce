<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;


Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->controller(OrderController::class)
    ->group(function () {

        Route::post('orders', 'create');
        Route::get('orders/my', 'myOrders');
        Route::get('orders/{id}', 'show');
        Route::post('orders/{id}/cancel', 'cancel');

        Route::put('orders/{id}/status', 'updateStatus');
        // ->middleware('can:manage-orders'); // admin/internal
    });
