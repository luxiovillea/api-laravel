<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);
Route::get('/analytics/{app_key}/report', [AnalyticsController::class, 'generateReport']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {return $request->user();});
