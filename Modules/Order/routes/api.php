<?php
use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;

Route::prefix('v1')->group(function () {
    Route::post('orders', [OrderController::class, 'store'])->middleware('auth:sanctum');
    Route::get('orders/my', [OrderController::class, 'myOrders'])->middleware('auth:sanctum');
    Route::get('orders/{id}', [OrderController::class, 'show'])->middleware('auth:sanctum');
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('auth:sanctum');
    Route::put('orders/{id}/status', [OrderController::class, 'updateStatus'])->middleware('auth:sanctum'); // admin or internal
});
