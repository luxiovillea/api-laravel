<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// == Rute untuk Google Analytics Dashboard ==

// 1. Mendapatkan laporan historis utama dengan filter
Route::get('/analytics-historical', [AnalyticsController::class, 'fetchHistoricalData']);

// 2. Mendapatkan semua opsi yang bisa dipilih untuk filter dropdown
Route::get('/analytics-filter-options', [AnalyticsController::class, 'getFilterOptions']);

// 3. Mendapatkan data perbandingan antar segmen (misal: Pengguna Baru vs Pengguna Kembali)
Route::get('/analytics-segmented-data', [AnalyticsController::class, 'getSegmentedData']);