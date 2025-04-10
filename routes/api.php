<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ultra\ErrorManager\Http\Controllers\ErrorSimulationController;

/*
|--------------------------------------------------------------------------
| UEM API Routes
|--------------------------------------------------------------------------
|
| Defines API routes for the Ultra Error Manager, primarily for managing
| error simulations during testing/development and retrieving error code lists.
| Access to simulation management is restricted by the 'environment' middleware.
|
*/

// Oracode Comment: Grouping UEM API routes under 'api/errors' prefix and 'error-manager.api.' name prefix.
Route::middleware(['api']) // Standard API middleware (throttling, etc.)
    ->prefix('api/errors')
    ->name('error-manager.api.') // Add route name prefix
    ->group(function () {

        // Oracode Comment: Simulation routes protected by EnvironmentMiddleware.
        Route::middleware(['environment:local,development,testing,staging']) // Use registered alias
            ->group(function () {
                // 🎯 Activate error simulation
                Route::post('/simulate/{errorCode}', [ErrorSimulationController::class, 'activateSimulation'])
                     ->name('simulations.activate'); // Named route

                // 🎯 Deactivate error simulation
                Route::delete('/simulate/{errorCode}', [ErrorSimulationController::class, 'deactivateSimulation'])
                     ->name('simulations.deactivate'); // Named route

                // 🎯 List active simulations
                Route::get('/simulations', [ErrorSimulationController::class, 'listActiveSimulations'])
                     ->name('simulations.list'); // Named route

                // 🎯 Reset all simulations
                Route::post('/simulations/reset', [ErrorSimulationController::class, 'resetAllSimulations'])
                      ->name('simulations.reset'); // Named route
            });

        // Oracode Comment: Publicly accessible route to list available error codes.
        // 🎯 List available error codes (useful for frontend/tooling)
        Route::get('/codes', [ErrorSimulationController::class, 'listErrorCodes'])
             ->name('codes.list'); // Named route
    });