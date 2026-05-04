<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FaceRecognitionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/face-recognition/log', [FaceRecognitionController::class, 'log'])->name('api.face.log');
Route::post('/v1/hardware/event', [\App\Http\Controllers\Api\HardwareEventController::class, 'logEvent'])->name('api.hardware.event');
