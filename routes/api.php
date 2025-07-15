<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Endpoint ini akan mengambil data historis dari Google Analytics.
| URL: /api/analytics/report/{propertyId}
|
*/

Route::get('/analytics/report/{propertyId}', [AnalyticsController::class, 'fetchPageReport']);