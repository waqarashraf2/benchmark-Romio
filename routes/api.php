<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Manager\UserManagementController;
use App\Http\Controllers\Api\Manager\ManagerOrderController;
use App\Http\Controllers\Api\Manager\ManagerDashboardController;
use App\Http\Controllers\Api\Drawer\DrawerDashboardController;
use App\Http\Controllers\Api\Drawer\DrawerOrderController;
use App\Http\Controllers\Api\Checker\CheckerDashboardController;
use App\Http\Controllers\Api\Checker\CheckerOrderController;
use App\Http\Controllers\Api\Checker\CheckerChecklistController;
use App\Http\Controllers\Api\Checker\CheckerIssueController;
use App\Http\Controllers\Api\Qa\QaDashboardController;
use App\Http\Controllers\Api\Qa\QaOrderController;
use App\Http\Controllers\Api\Qa\QaStatsController;
use App\Http\Controllers\Api\Qa\QaExportController;

/* AUTH */
Route::post('/login', [AuthController::class, 'login']);

/* PROTECTED ROUTES */
Route::middleware('auth:sanctum')->group(function () {

    /* Logged-in user info */
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | MANAGER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::prefix('manager')->middleware('role:manager')->group(function () {
        Route::get('/dashboard', [ManagerDashboardController::class,'index']);
        
        // Orders
        Route::get('/orders', [ManagerOrderController::class,'index']);
        Route::post('/orders/{order}/assign-drawer', [ManagerOrderController::class,'assignDrawer']);
        Route::post('/orders/{order}/reject', [ManagerOrderController::class,'reject']);

        // Users
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::put('/users/{user}/role', [UserManagementController::class, 'changeRole']);
        Route::put('/users/{user}/toggle', [UserManagementController::class, 'toggle']);
        Route::put('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | DRAWER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::prefix('drawer')->middleware('role:drawer')->group(function () {
        Route::get('/dashboard', [DrawerDashboardController::class,'index']);
        Route::post('/orders/{order}/start', [DrawerOrderController::class,'start']);
        Route::post('/orders/{order}/complete', [DrawerOrderController::class,'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | CHECKER ROUTES
    |--------------------------------------------------------------------------
    */
    Route::prefix('checker')->middleware('role:checker')->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [CheckerDashboardController::class, 'index']);
        
        // Orders list with filters
        Route::get('/orders', [CheckerOrderController::class, 'index']);
        Route::get('/orders/high-priority', [CheckerOrderController::class, 'highPriority']);
        Route::get('/orders/overdue', [CheckerOrderController::class, 'overdue']);
        Route::get('/orders/search', [CheckerOrderController::class, 'search']);
        
        // Single order operations
        Route::get('/orders/{order}/details', [CheckerOrderController::class, 'details']);
        Route::post('/orders/{order}/start-review', [CheckerOrderController::class, 'startReview']);
        Route::post('/orders/{order}/review', [CheckerOrderController::class, 'submitReview']);
        Route::post('/orders/{order}/approve', [CheckerOrderController::class, 'approve']);
        Route::post('/orders/{order}/reject', [CheckerOrderController::class, 'reject']);
        
        // Checklist routes
        Route::get('/orders/{order}/checklist', [CheckerChecklistController::class, 'index']);
        Route::post('/checklist/{checklistItem}/toggle', [CheckerChecklistController::class, 'toggle']);
        Route::post('/orders/{order}/checklist/complete', [CheckerChecklistController::class, 'complete']);
        
        // Order issues
        Route::get('/orders/{order}/issues', [CheckerIssueController::class, 'index']);
        Route::post('/orders/{order}/issues', [CheckerIssueController::class, 'store']);
        Route::get('/orders/{order}/issues/stats', [CheckerIssueController::class, 'stats']);
        
        // Review issues
        Route::get('/reviews/{review}/issues', [CheckerIssueController::class, 'getByReview']);
        
        // Single issue operations
        Route::put('/issues/{issue}', [CheckerIssueController::class, 'update']);
        Route::delete('/issues/{issue}', [CheckerIssueController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | QA ROUTES - COMPLETE SET (REMOVED THE DUPLICATE)
    |--------------------------------------------------------------------------
    */
    Route::prefix('qa')->middleware('role:qa')->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [QaDashboardController::class, 'index']);
        
        // Orders list with filters
        Route::get('/orders', [QaOrderController::class, 'index']);
        Route::get('/orders/pending', [QaOrderController::class, 'pending']);
        Route::get('/orders/completed', [QaOrderController::class, 'completed']);
        Route::get('/orders/rejected', [QaOrderController::class, 'rejected']);
        Route::get('/orders/high-priority', [QaOrderController::class, 'highPriority']);
        Route::get('/orders/overdue', [QaOrderController::class, 'overdue']);
        Route::get('/orders/search', [QaOrderController::class, 'search']);
        
        // Single order operations
        Route::get('/orders/{order}/details', [QaOrderController::class, 'details']);
        Route::post('/orders/{order}/start-review', [QaOrderController::class, 'startReview']);
        Route::post('/orders/{order}/approve', [QaOrderController::class, 'approve']);
        Route::post('/orders/{order}/reject', [QaOrderController::class, 'reject']);
        
        // Stats
        Route::get('/stats', [QaStatsController::class, 'index']);
        Route::get('/daily-progress', [QaStatsController::class, 'dailyProgress']);
        
        // Export
        Route::get('/orders/export', [QaExportController::class, 'export']);
    });

});