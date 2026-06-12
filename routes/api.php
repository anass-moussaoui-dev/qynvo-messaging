<?php

use App\Http\Controllers\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Itinerary messaging. The acting user is identified by the X-User-Id
// header — this task's stand-in for real authentication (see README).
Route::post('/messages', [MessageController::class, 'store']);
Route::get('/messages/{itinerary}', [MessageController::class, 'index']);
