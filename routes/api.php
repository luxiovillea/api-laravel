<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Berikut adalah route untuk API Google Analytics.
|
*/
// Endpoint utama dan direkomendasikan
Route::get('/analytics/dashboard-summary', [AnalyticsController::class, 'getDashboardSummary']);
Route::get('/analytics/realtime-summary', [AnalyticsController::class, 'getRealtimeSummary']); // Route baru untuk fungsi realtime summary
Route::get('/analytics/{app_key}/report', [AnalyticsController::class, 'generateReport'])->name('analytics.detail.report');
// Endpoint legacy (dipertahankan untuk kompatibilitas)
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);
// Route otentikasi default
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});