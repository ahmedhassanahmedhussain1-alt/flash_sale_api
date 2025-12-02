<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;

// Products
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');

// Holds
Route::post('/holds', [HoldController::class, 'store'])->name('holds.store');

// Orders
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

// Payment Webhook
Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])->name('payments.webhook');
