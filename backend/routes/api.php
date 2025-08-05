<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Api\CrawlerSearchController;
use App\Http\Controllers\Api\FoundItemController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ]);
    });

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->where('provider', 'google|facebook|apple');
        
        Route::get('/{provider}/callback', [SocialAuthController::class, 'callback'])
            ->where('provider', 'google|facebook|apple');
        
        Route::post('/logout', [SocialAuthController::class, 'logout'])
            ->middleware('auth:sanctum');
        
        Route::post('/logout-all', [SocialAuthController::class, 'logoutAll'])
            ->middleware('auth:sanctum');
        
        Route::post('/refresh', [SocialAuthController::class, 'refresh'])
            ->middleware('auth:sanctum');
        
        Route::get('/me', [SocialAuthController::class, 'me'])
            ->middleware('auth:sanctum');
    });

    // Public categories (no auth required)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);

        // User management
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
            Route::post('/avatar', [UserController::class, 'uploadAvatar']);
            Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
            Route::get('/settings', [UserController::class, 'getSettings']);
            Route::put('/settings', [UserController::class, 'updateSettings']);
            Route::get('/notifications', [UserController::class, 'getNotifications']);
            Route::put('/notifications/{id}/read', [UserController::class, 'markNotificationAsRead']);
            Route::post('/notifications/read-all', [UserController::class, 'markAllNotificationsAsRead']);
            Route::delete('/account', [UserController::class, 'deleteAccount']);
        });

        // Crawler searches
        Route::apiResource('searches', CrawlerSearchController::class);
        Route::post('/searches/{search}/toggle', [CrawlerSearchController::class, 'toggle']);
        Route::post('/searches/{search}/run', [CrawlerSearchController::class, 'run']);
        Route::get('/searches/{search}/statistics', [CrawlerSearchController::class, 'statistics']);

        // Found items
        Route::prefix('items')->group(function () {
            Route::get('/', [FoundItemController::class, 'index']);
            Route::get('/{item}', [FoundItemController::class, 'show']);
            Route::post('/{item}/favorite', [FoundItemController::class, 'toggleFavorite']);
            Route::post('/{item}/hide', [FoundItemController::class, 'hide']);
            Route::post('/{item}/report', [FoundItemController::class, 'report']);
            Route::get('/{item}/similar', [FoundItemController::class, 'similar']);
        });

        // Favorites
        Route::prefix('favorites')->group(function () {
            Route::get('/', [FoundItemController::class, 'favorites']);
            Route::delete('/{item}', [FoundItemController::class, 'removeFavorite']);
        });

        // Export
        Route::prefix('export')->group(function () {
            Route::post('/searches', [CrawlerSearchController::class, 'export']);
            Route::post('/items', [FoundItemController::class, 'export']);
        });

    });

    // Admin routes
    Route::middleware(['auth:sanctum', 'role:admin', 'throttle:admin'])->prefix('admin')->group(function () {
        
        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'adminIndex']);
            Route::get('/{user}', [UserController::class, 'adminShow']);
            Route::put('/{user}', [UserController::class, 'adminUpdate']);
            Route::post('/{user}/ban', [UserController::class, 'ban']);
            Route::post('/{user}/unban', [UserController::class, 'unban']);
            Route::delete('/{user}', [UserController::class, 'adminDelete']);
        });

        // System stats
        Route::prefix('system')->group(function () {
            Route::get('/stats', [DashboardController::class, 'systemStats']);
            Route::get('/health', [DashboardController::class, 'systemHealth']);
            Route::get('/logs', [DashboardController::class, 'systemLogs']);
        });

        // Category management
        Route::apiResource('categories', CategoryController::class)->except(['index']);

    });

});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_versions' => ['v1'],
    ], 404);
});