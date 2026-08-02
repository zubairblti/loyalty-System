<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\SafepayWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('web');
Route::middleware('web')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/resend', [AuthController::class, 'resendRegistration']);
    Route::post('/register/verify', [AuthController::class, 'verifyRegistration']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});
Route::post('/integrations/orders', [IntegrationController::class, 'ingest'])->middleware('integration.signature');
Route::post('/webhooks/safepay', SafepayWebhookController::class);
Route::post('/qr/{token}/claim', [QrCodeController::class, 'claim'])->middleware('throttle:20,1');
Route::prefix('/customer/{slug}')->middleware('web')->group(function () {
    Route::get('/business', [CustomerPortalController::class, 'business']);
    Route::post('/register', [CustomerPortalController::class, 'register']);
    Route::post('/register/resend', [CustomerPortalController::class, 'resendRegistration']);
    Route::post('/register/verify', [CustomerPortalController::class, 'verifyRegistration']);
    Route::post('/login', [CustomerPortalController::class, 'login']);
    Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])->middleware('customer.auth');
    Route::post('/logout', [CustomerPortalController::class, 'logout'])->middleware('customer.auth');
    Route::patch('/profile', [CustomerPortalController::class, 'updateProfile'])->middleware('customer.auth');
    Route::post('/profile/phone/otp', [CustomerPortalController::class, 'requestPhoneChange'])->middleware('customer.auth');
    Route::post('/profile/phone/verify', [CustomerPortalController::class, 'verifyPhoneChange'])->middleware('customer.auth');
});

Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/subscription', [SubscriptionController::class, 'status']);
    Route::post('/subscription/payments', [SubscriptionController::class, 'submitPayment']);
    Route::post('/subscription/safepay/checkout', [SubscriptionController::class, 'createSafepayCheckout']);
    Route::get('/subscription/safepay/{tracker}', [SubscriptionController::class, 'safepayStatus']);
    Route::middleware('subscription.active')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::post('/domains', [IntegrationController::class, 'storeDomain']);
        Route::post('/integrations', [IntegrationController::class, 'createKey']);
        Route::get('/pos/terminals', [PosController::class, 'terminals']);
        Route::post('/pos/terminals', [PosController::class, 'createTerminal']);
        Route::post('/pos/sales', [PosController::class, 'sale']);
        Route::get('/qr-codes', [QrCodeController::class, 'index']);
        Route::post('/qr-codes', [QrCodeController::class, 'store']);
    });
});

Route::prefix('/admin')->middleware(['web', 'auth:sanctum', 'super.admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::patch('/businesses/{business}', [AdminController::class, 'updateBusiness']);
    Route::put('/plan', [AdminController::class, 'savePlan']);
    Route::post('/payments/{payment}/review', [AdminController::class, 'reviewPayment']);
    Route::get('/payments/{payment}/receipt', [AdminController::class, 'paymentReceipt']);
    Route::post('/businesses/{business}/cash-payment', [AdminController::class, 'recordCash']);
});
