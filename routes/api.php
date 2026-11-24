<?php

use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/geocode', GeocodeController::class);

Route::get('/locations', [LocationController::class, 'index']);
Route::post('/locations', [LocationController::class, 'store']);
Route::put('/locations/{location}', [LocationController::class, 'update']);
Route::delete('/locations/{location}', [LocationController::class, 'destroy']);
Route::get('/locations/{location}/weather', WeatherController::class);

Route::post('/image', ImageController::class)->middleware('throttle:10,1');
