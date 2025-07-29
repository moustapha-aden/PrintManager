<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\DepartmentController;

// Authentification
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register'])->name('api.register');

// Route protégée pour récupérer l'utilisateur connecté
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Ressources principales
    Route::apiResource('users', UserController::class);
    Route::apiResource('printers', PrinterController::class);
    Route::apiResource('interventions', InterventionController::class);
    Route::get('/interventions/statistics', [InterventionController::class, 'getInterventionStatistics']);
    Route::get('/interventions/by-period', [InterventionController::class, 'getInterventionsByPeriod']);
    // Routes spécifiques pour les interventions
    Route::get('companies/{companyId}/interventions', [AnalyticsController::class, 'getInterventionsByCompany']);
    Route::get('printers/{printerId}/interventions', [AnalyticsController::class, 'getInterventionsByPrinter']);
    Route::get('/printers/search', [AnalyticsController::class, 'search']);

    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('analyse', AnalyticsController::class); // si tu veux la garder
    Route::put('/printers/{printer}/move', [PrinterController::class, 'move'])->name('printers.move');
    Route::get('/printer-movements', [PrinterController::class, 'getPrinterMovements'])->name('printer_movements.index');
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/recent-activities', [DashboardController::class, 'getRecentActivities']);
    Route::get('/dashboard/technician-stats', [DashboardController::class, 'getTechnicianStats']);

    Route::put('/users/{user}/change-password', [UserController::class, 'changePassword']);
    // Analytics spécifiques
    Route::prefix('analytics')->group(function () {
        Route::get('overview', [AnalyticsController::class, 'getOverviewStats']);
        Route::get('companies', [AnalyticsController::class, 'getCompanyStats']);
        Route::get('frequent-errors', [AnalyticsController::class, 'getFrequentErrors']);
        Route::get('printers-attention', [AnalyticsController::class, 'getPrintersNeedingAttention']);
    });

    // Rapports
    Route::prefix('reports')->group(function () {
        Route::get('/', [AnalyticsController::class, 'listReports']);
        Route::post('generate/{reportType}', [AnalyticsController::class, 'generateReport']);
    });

});
