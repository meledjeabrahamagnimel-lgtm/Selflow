<?php

use App\Http\Controllers\Api\DashboardApiController;
use Illuminate\Support\Facades\Route;

// On protège ces routes avec le token du Hub
Route::middleware(['hub.token'])->group(function () {
    Route::get('/companies', [DashboardApiController::class, 'getCompanies']);
    Route::get('/entreprises', [DashboardApiController::class, 'getCompanies']);
    Route::get('/dashboard/kpis', [DashboardApiController::class, 'getKpis']);
    Route::get('/dashboard/exercices', [DashboardApiController::class, 'getExercices']);
});
