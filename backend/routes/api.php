<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('web');
Route::post('/integrations/orders', [IntegrationController::class, 'ingest'])->middleware('integration.signature');
Route::post('/qr/{token}/claim', [QrCodeController::class, 'claim'])->middleware('throttle:20,1');

Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
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
