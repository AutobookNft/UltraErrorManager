<?php

namespace Ultra\ErrorManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Facades\TestingConditions;
use Ultra\ErrorManager\Facades\UltraError;
use Illuminate\Routing\Controller;

class ErrorSimulationController extends Controller
{
    /**
     * Activate error simulation for a specific error code
     *
     * @param Request $request
     * @param string $errorCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateSimulation(Request $request, $errorCode)
    {
        // Verifica se il codice errore esiste
        $errorConfig = UltraError::getErrorConfig($errorCode);

        if (!$errorConfig) {
            return response()->json([
                'success' => false,
                'message' => "Error code '{$errorCode}' does not exist",
            ], 404);
        }

        // Attiva la simulazione
        TestingConditions::setCondition($errorCode, true);

        return response()->json([
            'success' => true,
            'message' => "Error simulation activated for '{$errorCode}'",
            'errorCode' => $errorCode,
            'config' => $errorConfig,
        ]);
    }

    /**
     * Deactivate error simulation for a specific error code
     *
     * @param Request $request
     * @param string $errorCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateSimulation(Request $request, $errorCode)
    {
        // Disattiva la simulazione
        TestingConditions::setCondition($errorCode, false);

        return response()->json([
            'success' => true,
            'message' => "Error simulation deactivated for '{$errorCode}'",
        ]);
    }

    /**
     * List all active error simulations
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listActiveSimulations(Request $request)
    {
        $activeConditions = TestingConditions::getActiveConditions();

        return response()->json([
            'success' => true,
            'activeSimulations' => array_keys($activeConditions),
            'count' => count($activeConditions),
        ]);
    }

    /**
     * Reset all error simulations
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetAllSimulations(Request $request)
    {
        TestingConditions::resetAllConditions();

        return response()->json([
            'success' => true,
            'message' => "All error simulations have been reset",
        ]);
    }

    /**
     * List all available error codes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listErrorCodes(Request $request)
    {
        $errorCodes = array_keys(Config::get('error-manager.errors', []));
        $filteredCodes = [];

        // Filtra per "type" se specificato
        $type = $request->query('type');
        if ($type) {
            foreach ($errorCodes as $code) {
                $config = UltraError::getErrorConfig($code);
                if (($config['type'] ?? null) === $type) {
                    $filteredCodes[] = $code;
                }
            }
        } else {
            $filteredCodes = $errorCodes;
        }

        return response()->json([
            'success' => true,
            'errorCodes' => $filteredCodes,
            'count' => count($filteredCodes),
        ]);
    }
}
