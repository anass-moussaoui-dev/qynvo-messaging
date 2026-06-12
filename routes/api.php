<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/messages', [MessageController::class, 'store']);
Route::get('/messages/{itinerary}', [MessageController::class, 'index']);