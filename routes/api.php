<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;


Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);

Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);