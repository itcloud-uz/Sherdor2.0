<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FaceRecognitionController;
use App\Http\Controllers\Admin\FinanceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/face-recognition/log', [FaceRecognitionController::class, 'log'])->name('api.face.log');
Route::post('/v1/hardware/event', [\App\Http\Controllers\Api\HardwareEventController::class, 'logEvent'])->name('api.hardware.event');

// Omnichannel Lead Generation
Route::post('/leads', [\App\Http\Controllers\Api\LeadController::class, 'store'])->name('api.leads.store');

// Telegram Bot API Endpoints
Route::middleware([\App\Http\Middleware\BotApiMiddleware::class])->prefix('bot/v1')->group(function () {
    Route::post('/verify-user', [\App\Http\Controllers\Api\BotApiController::class, 'verifyUser']);
    Route::post('/transactions', [\App\Http\Controllers\Api\BotApiController::class, 'createTransaction']);
    Route::get('/transactions/pending', [\App\Http\Controllers\Api\BotApiController::class, 'getPendingTransactions']);
    Route::post('/transactions/approve', [\App\Http\Controllers\Api\BotApiController::class, 'approveTransaction']);
});

// Telephony AMI API Endpoints
Route::middleware(['auth:sanctum'])->prefix('telephony')->group(function () {
    Route::post('/call', [\App\Http\Controllers\Api\TelephonyController::class, 'triggerCall']);
    Route::get('/logs', [\App\Http\Controllers\Admin\TelephonyController::class, 'getLogs']);
    Route::get('/audios', [\App\Http\Controllers\Admin\TelephonyController::class, 'getAudios']);
    Route::get('/ai-config', [\App\Http\Controllers\Admin\TelephonyController::class, 'getAiConfig']);
    Route::post('/ai-config', [\App\Http\Controllers\Admin\TelephonyController::class, 'saveAiConfig']);
});
