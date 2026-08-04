<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessBrandingController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LoyaltyConfigurationController;
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
Route::post('/qr/{token}/claim', [QrCodeController::class, 'claim'])->middleware(['tenant.qr', 'throttle:20,1']);
Route::prefix('/customer/{slug}')->middleware(['web', 'tenant.customer'])->group(function () {
    Route::get('/business', [CustomerPortalController::class, 'business']);
    Route::get('/logo', [CustomerPortalController::class, 'logo']);
    Route::post('/register', [CustomerPortalController::class, 'register']);
    Route::post('/register/resend', [CustomerPortalController::class, 'resendRegistration']);
    Route::post('/register/verify', [CustomerPortalController::class, 'verifyRegistration']);
    Route::post('/login', [CustomerPortalController::class, 'login']);
    Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])->middleware('customer.auth');
    Route::post('/logout', [CustomerPortalController::class, 'logout'])->middleware('customer.auth');
    Route::patch('/profile', [CustomerPortalController::class, 'updateProfile'])->middleware('customer.auth');
});

Route::middleware(['web', 'auth:sanctum', 'tenant.auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::post('/notifications/read', [AuthController::class, 'readNotifications']);
    Route::get('/subscription', [SubscriptionController::class, 'status']);
    Route::post('/subscription/payments', [SubscriptionController::class, 'submitPayment']);
    Route::post('/subscription/safepay/checkout', [SubscriptionController::class, 'createSafepayCheckout']);
    Route::post('/subscription/safepay/{tracker}/processing', [SubscriptionController::class, 'markSafepayProcessing']);
    Route::get('/subscription/safepay/{tracker}', [SubscriptionController::class, 'safepayStatus']);
    Route::middleware('subscription.active')->group(function () {
        Route::get('/business/profile', [BusinessProfileController::class, 'show']);
        Route::put('/business/profile', [BusinessProfileController::class, 'update']);
    });
    Route::middleware(['subscription.active', 'profile.complete'])->group(function () {
        Route::get('/business/branding', [BusinessBrandingController::class, 'show']);
        Route::post('/business/branding', [BusinessBrandingController::class, 'update']);
        Route::delete('/business/branding', [BusinessBrandingController::class, 'reset']);
        Route::get('/business/branding/logo', [BusinessBrandingController::class, 'logo']);
        Route::get('/business/loyalty', [LoyaltyConfigurationController::class, 'show']);
        Route::put('/business/loyalty/settings', [LoyaltyConfigurationController::class, 'updateSettings']);
        Route::post('/business/loyalty/rules', [LoyaltyConfigurationController::class, 'storeRule']);
        Route::put('/business/loyalty/rules/{rule}', [LoyaltyConfigurationController::class, 'updateRule']);
        Route::delete('/business/loyalty/rules/{rule}', [LoyaltyConfigurationController::class, 'deleteRule']);
        Route::post('/business/loyalty/levels', [LoyaltyConfigurationController::class, 'storeLevel']);
        Route::put('/business/loyalty/levels/{level}', [LoyaltyConfigurationController::class, 'updateLevel']);
        Route::delete('/business/loyalty/levels/{level}', [LoyaltyConfigurationController::class, 'deleteLevel']);
        Route::post('/business/loyalty/tours', [LoyaltyConfigurationController::class, 'completeTour']);
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

Route::prefix('/admin')->middleware(['web', 'auth:sanctum', 'tenant.auth', 'super.admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/businesses', [AdminController::class, 'businesses']);
    Route::post('/businesses', [AdminController::class, 'createBusiness']);
    Route::get('/businesses/{business}', [AdminController::class, 'businessDetail']);
    Route::patch('/businesses/{business}', [AdminController::class, 'updateBusiness']);
    Route::post('/plans', [AdminController::class, 'storePlan']);
    Route::put('/plans/{plan}', [AdminController::class, 'updatePlan']);
    Route::delete('/plans/{plan}', [AdminController::class, 'deletePlan']);
    Route::post('/plans/{plan}/restore', [AdminController::class, 'restorePlan']);
    Route::post('/payments/{payment}/review', [AdminController::class, 'reviewPayment']);
    Route::get('/payments/{payment}/receipt', [AdminController::class, 'paymentReceipt']);
    Route::post('/businesses/{business}/cash-payment', [AdminController::class, 'recordCash']);
});
