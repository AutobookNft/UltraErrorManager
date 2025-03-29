<?php

use Illuminate\Support\Facades\Route;
use Ultra\ErrorManager\Http\Controllers\ErrorDashboardController;
use Ultra\ErrorManager\Http\Controllers\ErrorSimulationController;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Facades\TestingConditions;
use Ultra\ErrorManager\Models\ErrorLog;
use Ultra\ErrorManager\Facades\UltraError;

// Route::get('error-manager/dashboard/simulations', function() {
//     Log::channel('upload')->info('Simulations method called');

//         // Get all available error codes from config
//         $errorCodes = array_keys(Config::get('error-manager.errors', []));


//         // // // Get all currently active simulations
//         $activeSimulations = TestingConditions::getActiveConditions();

//         // // // Group error codes by their type for better UI organization
//         $errorsByType = [];
//         foreach ($errorCodes as $code) {
//             $config = UltraError::getErrorConfig($code);
//             $type = $config['type'] ?? 'unknown';
//             $errorsByType[$type][] = [
//                 'code' => $code,
//                 'config' => $config,
//                 'active' => isset($activeSimulations[$code]) && $activeSimulations[$code] === true
//             ];
//         }
//         // ritorniamo un valore di prova


//         return view('error-manager::dashboard.simulations', compact('errorsByType', 'activeSimulations'));

// })->name('error-manager.dashboard.simulations');


// Error Manager Dashboard Routes
Route::middleware(['web'])->prefix('error-manager')->name('error-manager.')->group(function () {


    Route::get('/dashboard', [ErrorDashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/statistics', [ErrorDashboardController::class, 'statistics'])->name('dashboard.statistics');


    Route::get('/dashboard/simulations', [ErrorDashboardController::class, 'simulations'])->name('dashboard.simulations');
    Route::post('/dashboard/simulations/activate', [ErrorDashboardController::class, 'activateSimulation'])->name('dashboard.simulations.activate');
    Route::post('/dashboard/simulations/deactivate', [ErrorDashboardController::class, 'deactivateSimulation'])->name('dashboard.simulations.deactivate');

    Route::post('/dashboard/bulk-resolve', [ErrorDashboardController::class, 'bulkResolve'])->name('dashboard.bulk-resolve');
    Route::post('/dashboard/purge-resolved', [ErrorDashboardController::class, 'purgeResolved'])->name('dashboard.purge-resolved');

    Route::get('/dashboard/{id}', [ErrorDashboardController::class, 'show'])->name('dashboard.show');
    Route::post('/dashboard/{id}/resolve', [ErrorDashboardController::class, 'resolve'])->name('dashboard.resolve');
    Route::get('/dashboard/{id}/unresolve', [ErrorDashboardController::class, 'unresolve'])->name('dashboard.unresolve');
    Route::delete('/dashboard/{id}', [ErrorDashboardController::class, 'destroy'])->name('dashboard.destroy');


});

// Error Simulation API (for testing)
Route::middleware(['api'])->prefix('api/errors')->name('error-manager.api.')->group(function () {
    // These routes are protected and accessible only in non-production environments
    Route::middleware(['environment'])->group(function () {
        // Activate error simulation
        Route::post('/simulate/{errorCode}', [ErrorSimulationController::class, 'activateSimulation'])->name('simulate');

        // Deactivate error simulation
        Route::delete('/simulate/{errorCode}', [ErrorSimulationController::class, 'deactivateSimulation'])->name('deactivate');

        // List active simulations
        Route::get('/simulations', [ErrorSimulationController::class, 'listActiveSimulations'])->name('simulations');

        // Reset all simulations
        Route::post('/simulations/reset', [ErrorSimulationController::class, 'resetAllSimulations'])->name('reset');
    });

    // List available error codes (useful for frontend)
    Route::get('/codes', [ErrorSimulationController::class, 'listErrorCodes'])->name('codes');
});


// Rotta per generare dati di test (accessibile solo in ambiente non-production)
if (app()->environment() !== 'production') {
    Route::get('/error-manager/generate-test-data', function() {
        $seeder = new \Ultra\ErrorManager\Database\Seeders\ErrorLogSeeder();
        $seeder->run();
        return redirect()->route('error-manager.dashboard.index')
            ->with('success', 'Generati 225 log di errore di prova!');
    })->name('error-manager.generate-test-data');
}
