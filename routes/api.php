<?php

use Illuminate\Support\Facades\Route;
use Ultra\ErrorManager\Http\Controllers\ErrorSimulationController;

Route::middleware(['api'])->prefix('api/errors')->group(function () {
    // Queste route sono protette e accessibili solo in ambiente non-production
    Route::middleware(['environment:local,development,testing,staging'])->group(function () {
        // Attiva simulazione errore
        Route::post('/simulate/{errorCode}', [ErrorSimulationController::class, 'activateSimulation']);

        // Disattiva simulazione errore
        Route::delete('/simulate/{errorCode}', [ErrorSimulationController::class, 'deactivateSimulation']);

        // Lista delle simulazioni attive
        Route::get('/simulations', [ErrorSimulationController::class, 'listActiveSimulations']);

        // Reset di tutte le simulazioni
        Route::post('/simulations/reset', [ErrorSimulationController::class, 'resetAllSimulations']);
    });

    // Lista degli errori disponibili (utile per il frontend)
    Route::get('/codes', [ErrorSimulationController::class, 'listErrorCodes']);
});
