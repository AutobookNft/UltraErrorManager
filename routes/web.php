<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route; // Standard Route Facade
use Illuminate\Contracts\Foundation\Application; // For environment check in test route
use Ultra\ErrorManager\Http\Controllers\ErrorDashboardController;
// Rimosso codice di test commentato e use non necessarie

/*
|--------------------------------------------------------------------------
| UEM Web Routes
|--------------------------------------------------------------------------
|
| Defines the web routes for the Ultra Error Manager dashboard interface.
| These routes are typically protected by authentication and authorization
| middleware applied either here or within the ErrorDashboardController.
|
*/

// Oracode Comment: Grouping UEM Dashboard routes under 'error-manager' prefix and 'error-manager.' name prefix. Applying 'web' middleware group.
Route::middleware(['web']) // Apply standard web middleware (session, cookies, csrf)
     // TODO: Add Auth/Permission middleware here or in Controller constructor
     // Example: ->middleware(['auth', 'can:view-uem-dashboard'])
    ->prefix('error-manager')
    ->name('error-manager.')
    ->group(function () {

        // Dashboard Index (List/Filter)
        Route::get('/dashboard', [ErrorDashboardController::class, 'index'])
             ->name('dashboard.index');

        // Statistics View
        Route::get('/dashboard/statistics', [ErrorDashboardController::class, 'statistics'])
             ->name('dashboard.statistics');

        // Simulations View & Actions
        Route::get('/dashboard/simulations', [ErrorDashboardController::class, 'simulations'])
             ->name('dashboard.simulations');
        Route::post('/dashboard/simulations/activate', [ErrorDashboardController::class, 'activateSimulation'])
             ->name('dashboard.simulations.activate');
        Route::post('/dashboard/simulations/deactivate', [ErrorDashboardController::class, 'deactivateSimulation'])
             ->name('dashboard.simulations.deactivate');

        // Bulk Actions
        Route::post('/dashboard/bulk-resolve', [ErrorDashboardController::class, 'bulkResolve'])
             ->name('dashboard.bulk-resolve');
        Route::post('/dashboard/purge-resolved', [ErrorDashboardController::class, 'purgeResolved'])
             ->name('dashboard.purge-resolved');

        // Single Error Actions
        Route::get('/dashboard/{errorLog}', [ErrorDashboardController::class, 'show']) // Use route model binding? '{errorLog}'
             ->where('errorLog', '[0-9]+') // Ensure ID is numeric
             ->name('dashboard.show');
        Route::post('/dashboard/{errorLog}/resolve', [ErrorDashboardController::class, 'resolve']) // Keep POST
             ->where('errorLog', '[0-9]+')
             ->name('dashboard.resolve');
        // Route::get('/dashboard/{errorLog}/unresolve', [ErrorDashboardController::class, 'unresolve']) // Original GET
        Route::post('/dashboard/{errorLog}/unresolve', [ErrorDashboardController::class, 'unresolve']) // Suggest changing to POST for state change
             ->where('errorLog', '[0-9]+')
             ->name('dashboard.unresolve');
        Route::delete('/dashboard/{errorLog}', [ErrorDashboardController::class, 'destroy']) // Standard DELETE
             ->where('errorLog', '[0-9]+')
             ->name('dashboard.destroy');

    });

// Oracode Comment: Route for generating test data, accessible only in non-production environments.
// Route::get('/error-manager/generate-test-data', function (Application $app) { // Inject Application
//     if ($app->environment('production')) { // Check using injected app
//         abort(404); // Not found in production
//     }

//     try {
//         $seeder = new \Ultra\ErrorManager\Database\Seeders\ErrorLogSeeder();
//         $seeder->run();
//         $message = 'Generated test error logs successfully!'; // Adjusted message count
//         $level = 'success';
//     } catch (\Exception $e) {
//         // Log the error if seeding fails
//         logger()->error("Failed to seed UEM test data: " . $e->getMessage());
//         $message = 'Failed to generate test error logs. Check logs.';
//         $level = 'error';
//     }

//     return redirect()->route('error-manager.dashboard.index')
//         ->with($level, $message);

// })->middleware('web')->name('error-manager.generate-test-data'); // Apply web middleware if needed (for session flash)