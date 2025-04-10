<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository; // Dependency for config access
use Illuminate\Http\JsonResponse; // Return type hint
use Illuminate\Http\Request; // Method dependency
use Illuminate\Routing\Controller; // Base controller
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Dependency
use Ultra\ErrorManager\Services\TestingConditionsManager; // Dependency

/**
 * 🎯 ErrorSimulationController – Oracoded API Controller for Test Simulations
 *
 * Handles API requests related to activating, deactivating, and listing error
 * simulations for testing purposes. Interacts with TestingConditionsManager and
 * ErrorManagerInterface via Dependency Injection. Intended primarily for non-production environments.
 *
 * 🧱 Structure:
 * - Standard Laravel Controller.
 * - Requires ErrorManagerInterface, TestingConditionsManager, and ConfigRepository injected.
 * - Provides API endpoints mapped in `routes/api.php` (typically protected by EnvironmentMiddleware).
 *
 * 📡 Communicates:
 * - With TestingConditionsManager to manage simulation state.
 * - With ErrorManagerInterface to retrieve error configurations.
 * - With ConfigRepository to access the list of defined errors.
 * - Responds with JSON formatted results.
 *
 * 🧪 Testable:
 * - Dependencies are injectable and mockable.
 * - Actions return predictable JSON responses.
 * - Can be tested using Laravel's API testing features.
 *
 * 🛡️ GDPR Considerations:
 * - Primarily a development/testing tool. Endpoint access should be restricted
 *   in production via EnvironmentMiddleware. No direct PII handling expected,
 *   but lists error codes which might be considered internal system information.
 */
final class ErrorSimulationController extends Controller // Mark as final
{
    // Injected dependencies
    protected readonly ErrorManagerInterface $errorManager;
    protected readonly TestingConditionsManager $testingConditions;
    protected readonly ConfigRepository $config;

    /**
     * 🎯 Constructor: Injects required service dependencies.
     *
     * @param ErrorManagerInterface $errorManager UEM Core service instance.
     * @param TestingConditionsManager $testingConditions Service for managing test simulation state.
     * @param ConfigRepository $config Laravel's config repository instance.
     */
    public function __construct(
        ErrorManagerInterface $errorManager,
        TestingConditionsManager $testingConditions,
        ConfigRepository $config
    ) {
        $this->errorManager = $errorManager;
        $this->testingConditions = $testingConditions;
        $this->config = $config;
    }

    /**
     * ⚙️ Activate error simulation for a specific error code via API.
     *
     * @param Request $request The incoming request (unused directly here).
     * @param string $errorCode The symbolic error code to activate simulation for.
     * @return JsonResponse
     */
    public function activateSimulation(Request $request, string $errorCode): JsonResponse
    {
        // Verify error code exists using injected errorManager
        $errorConfig = $this->errorManager->getErrorConfig($errorCode);

        if (!$errorConfig) {
            return response()->json([
                'success' => false,
                'message' => "Error code '{$errorCode}' does not exist in UEM configuration.",
            ], 404); // Not Found
        }

        // Activate the simulation using injected testingConditions service
        $this->testingConditions->setCondition($errorCode, true);

        return response()->json([
            'success'   => true,
            'message'   => "Error simulation activated for '{$errorCode}'.",
            'errorCode' => $errorCode,
            // Optionally return config if needed by client
            // 'config'    => $errorConfig,
        ]);
    }

    /**
     * ⚙️ Deactivate error simulation for a specific error code via API.
     *
     * @param Request $request The incoming request (unused directly here).
     * @param string $errorCode The symbolic error code to deactivate simulation for.
     * @return JsonResponse
     */
    public function deactivateSimulation(Request $request, string $errorCode): JsonResponse
    {
        // Deactivate the simulation using injected testingConditions service
        $this->testingConditions->setCondition($errorCode, false);

        return response()->json([
            'success' => true,
            'message' => "Error simulation deactivated for '{$errorCode}'.",
        ]);
    }

    /**
     * 📡 List all currently active error simulations via API.
     *
     * @param Request $request The incoming request (unused directly here).
     * @return JsonResponse
     */
    public function listActiveSimulations(Request $request): JsonResponse
    {
        // Get active conditions using injected testingConditions service
        $activeConditions = $this->testingConditions->getActiveConditions();

        return response()->json([
            'success'           => true,
            'activeSimulations' => array_keys($activeConditions), // Return only the codes
            'count'             => count($activeConditions),
        ]);
    }

    /**
     * 🧹 Reset all active error simulations via API.
     *
     * @param Request $request The incoming request (unused directly here).
     * @return JsonResponse
     */
    public function resetAllSimulations(Request $request): JsonResponse
    {
        // Reset conditions using injected testingConditions service
        $this->testingConditions->resetAllConditions();

        return response()->json([
            'success' => true,
            'message' => "All error simulations have been reset.",
        ]);
    }

    /**
     * 📡 List all available error codes defined in the UEM configuration via API.
     * Allows filtering by error type using a query parameter (?type=critical).
     *
     * @param Request $request The incoming request, may contain 'type' query param.
     * @return JsonResponse
     */
    public function listErrorCodes(Request $request): JsonResponse
    {
        // Get all defined error codes from injected config repository
        // Use a default empty array if the config key doesn't exist
        $allErrorCodes = array_keys($this->config->get('error-manager.errors', []));
        $filteredCodes = [];

        // Filter by type if query parameter is present
        $filterType = $request->query('type');

        if ($filterType) {
            foreach ($allErrorCodes as $code) {
                // Use injected errorManager to get the config (which includes type)
                $config = $this->errorManager->getErrorConfig($code);
                if ($config && ($config['type'] ?? null) === $filterType) {
                    $filteredCodes[] = $code;
                }
            }
        } else {
            // No filter applied, return all codes
            $filteredCodes = $allErrorCodes;
        }

        return response()->json([
            'success'    => true,
            'errorCodes' => $filteredCodes,
            'count'      => count($filteredCodes),
            'filter'     => $filterType ? ['type' => $filterType] : null,
        ]);
    }
}