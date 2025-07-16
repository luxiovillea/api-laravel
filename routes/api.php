<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

// Rute yang sudah ada
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);

// --- TAMBAHKAN RUTE BARU DI SINI ---
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);