<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManufacturingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- API Routes untuk 6 Menu Manufaktur & PPC ---
Route::post('/sales-orders', [ManufacturingController::class, 'storeSalesOrder']);
Route::post('/mrp/run/{salesOrderId}', [ManufacturingController::class, 'runMRP']);
Route::post('/shop-floor/scan', [ManufacturingController::class, 'scanShopFloor']);
Route::get('/dashboard/analytics', [ManufacturingController::class, 'dashboardAnalytics']);