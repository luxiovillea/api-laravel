<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AplikasiController;
use App\Http\Controllers\Api\OpdController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

// ===================================================================
// ROUTES UNTUK MANAGEMENT APLIKASI
// ===================================================================
Route::apiResource('/aplikasi', AplikasiController::class);
Route::get('/aplikasi/key/{key}', [AplikasiController::class, 'getByKey'])->name('aplikasi.getByKey');

// ===================================================================
// ROUTES UNTUK MANAGEMENT OPD
// ===================================================================
Route::apiResource('/opd', OpdController::class);
Route::get('/opd/kode/{kode}', [OpdController::class, 'getByKode'])->name('opd.getByKode'); // Tambahan route by kode
Route::get('/opd/{id}/aplikasi', [OpdController::class, 'aplikasis'])->name('opd.aplikasis');
// == PENAMBAHAN ROUTE BARU ==
Route::get('/opd/kode/{kode}/aplikasi', [OpdController::class, 'aplikasisByKode'])->name('opd.aplikasisByKode');

// ===================================================================
// ROUTES ANALYTICS - ENDPOINT UTAMA 
// ===================================================================
// Dashboard Summary - Sekarang dinamis berdasarkan database
Route::get('/analytics/dashboard-summary', [AnalyticsController::class, 'getDashboardSummary']);

// Realtime Summary - Sekarang dinamis berdasarkan database  
Route::get('/analytics/realtime-summary', [AnalyticsController::class, 'getRealtimeSummary']);

// Detail Report per Aplikasi - Sekarang menggunakan data dari database
Route::get('/analytics/{app_key}/report', [AnalyticsController::class, 'generateReport'])->name('analytics.detail.report');

// ===================================================================
// ROUTES ANALYTICS - ENDPOINT Detailed Reports
// ===================================================================
// Sekarang mendukung parameter app_key untuk menggunakan aplikasi dari database
Route::get('/analytics-data', [AnalyticsController::class, 'fetchRealtimeData']);
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);
Route::get('/analytics/pages-report', [AnalyticsController::class, 'fetchPagesReport']);
Route::get('/analytics/geography-report', [AnalyticsController::class, 'fetchGeographyReport']);

// ===================================================================
// ROUTE OTENTIKASI DEFAULT
// ===================================================================
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});