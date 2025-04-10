<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository; // Dependency
use Illuminate\Http\RedirectResponse; // Return type hint
use Illuminate\Http\Request; // Method dependency
use Illuminate\Routing\Controller; // Base controller
use Illuminate\Support\Facades\Log; // Keep for potential temporary debug logging if needed
use Illuminate\Support\Str; // Keep common helper
use Illuminate\View\View; // Return type hint
use Ultra\ErrorManager\Facades\UltraError; // Needed for static calls in Blade views for now (e.g., simulations badge) - or inject into View
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Dependency
use Ultra\ErrorManager\Models\ErrorLog; // Eloquent Model
use Ultra\ErrorManager\Services\TestingConditionsManager; // Dependency

/**
 * 🎯 ErrorDashboardController – Oracoded Web Controller for UEM Dashboard
 *
 * Handles requests for the UEM web dashboard, displaying error logs, statistics,
 * and providing interfaces for managing errors and simulations. Uses DI for core
 * services and Eloquent for database interactions.
 *
 * 🧱 Structure:
 * - Standard Laravel Controller.
 * - Requires ErrorManagerInterface, TestingConditionsManager, ConfigRepository injected.
 * - Implements actions for index (list/filter logs), show (details), statistics,
 *   simulations (view/activate/deactivate), resolve/unresolve, delete, bulk actions, purge.
 * - Uses ErrorLog model extensively for querying.
 * - Passes data to Blade views in `resources/views/dashboard`.
 *
 * 📡 Communicates:
 * - With Database via ErrorLog model.
 * - With ErrorManagerInterface to get config details.
 * - With TestingConditionsManager to manage simulation state.
 * - With ConfigRepository to get general UEM config (e.g., error types).
 * - Responds with Blade Views or RedirectResponses.
 *
 * 🧪 Testable:
 * - Dependencies are injectable/mockable.
 * - Eloquent queries can be tested with a test database or mocking.
 * - Controller actions can be tested using Laravel's HTTP testing features.
 *
 * 🛡️ GDPR Considerations:
 * - Displays potentially sensitive data from ErrorLog (IP, User Agent, Context, etc.). Access should be restricted via appropriate auth/middleware.
 * - Audit trail of resolution/deletion actions is implicit in timestamps/resolved_by, but could be enhanced if needed.
 * - Purge functionality requires careful consideration of data retention policies.
 */
final class ErrorDashboardController extends Controller // Mark as final
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

        // Optional: Apply middleware directly here if needed for all dashboard routes
        // $this->middleware(['web', 'auth', 'can:viewErrorDashboard']); // Example
    }

    /**
     * 📊 Display the main error dashboard (list/filter errors).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // --- Filter logic (remains largely the same, uses Request) ---
        $type = $request->input('type');
        $code = $request->input('code');
        $status = $request->input('status', 'unresolved'); // Default to unresolved
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // --- Query logic (remains the same, uses ErrorLog model) ---
        $query = ErrorLog::query()->orderBy('created_at', 'desc');
        if ($type) $query->ofType($type);
        if ($code) $query->withCode($code);
        if ($status === 'resolved') $query->resolved();
        elseif ($status === 'unresolved') $query->unresolved();
        if ($fromDate) $query->occurredAfter($fromDate);
        if ($toDate) $query->occurredBefore($toDate);

        $errors = $query->paginate(15)->appends($request->except('page'));

        // --- Stats logic (remains the same, uses ErrorLog model) ---
        $stats = [
            'total'      => ErrorLog::count(),
            'unresolved' => ErrorLog::unresolved()->count(),
            'critical'   => ErrorLog::critical()->count(),
            'today'      => ErrorLog::whereDate('created_at', today())->count(),
        ];

        // --- Data for Filters (use injected Config) ---
        // Get available error types from config
        $errorTypes = $this->config->get('error-manager.error_types', []);
        // Get distinct error codes present in the log table
        $errorCodes = ErrorLog::select('error_code')->distinct()->orderBy('error_code')->pluck('error_code')->toArray();

        return view('error-manager::dashboard.index', compact(
            'errors', 'stats', 'errorTypes', 'errorCodes',
            'type', 'code', 'status', 'fromDate', 'toDate'
        ));
    }

    /**
     * 👁️ Display details for a specific error log entry.
     *
     * @param string|int $id The ID of the ErrorLog record.
     * @return View
     */
    public function show(string|int $id): View
    {
        $error = ErrorLog::findOrFail($id);
        $similarErrors = $error->getSimilarErrors(5);
        $errorFrequency = ErrorLog::getErrorFrequency($error->error_code, 'daily', 14); // Uses static method on model

        return view('error-manager::dashboard.show', compact('error', 'similarErrors', 'errorFrequency'));
    }

     /**
      * ✅ Mark an error log as resolved.
      *
      * @param Request $request
      * @param string|int $id
      * @return RedirectResponse
      */
     public function resolve(Request $request, string|int $id): RedirectResponse
     {
         $error = ErrorLog::findOrFail($id);
         $notes = $request->input('notes');
         // Get user from request's authenticated user
         $resolvedBy = $request->user() ? ($request->user()->name ?? $request->user()->email ?? 'Auth User #' . $request->user()->id) : 'System';

         $error->markAsResolved($resolvedBy, $notes); // Uses model method

         return redirect()->route('error-manager.dashboard.show', $id)
             ->with('success', 'Error marked as resolved.');
     }

    /**
     * ↩️ Mark an error log as unresolved.
     *
     * @param string|int $id
     * @return RedirectResponse
     */
    public function unresolve(string|int $id): RedirectResponse
    {
        $error = ErrorLog::findOrFail($id);
        $error->markAsUnresolved(); // Uses model method

        return redirect()->route('error-manager.dashboard.show', $id)
            ->with('success', 'Error marked as unresolved.');
    }

    /**
     * 🗑️ Delete a specific error log entry.
     *
     * @param string|int $id
     * @return RedirectResponse
     */
    public function destroy(string|int $id): RedirectResponse
    {
        $error = ErrorLog::findOrFail($id);
        $error->delete(); // Uses model method

        return redirect()->route('error-manager.dashboard.index')
            ->with('success', 'Error log deleted successfully.');
    }

    /**
     * 📈 Display error statistics view.
     * (Logic uses ErrorLog model static methods - no changes needed here for DI)
     *
     * @param Request $request
     * @return View
     */
     public function statistics(Request $request): View
     {
         $period = $request->input('period', 'daily');
         $days = (int) $request->input('days', 30);
         $days = min(max($days, 7), 90); // Clamp days

         $topErrorCodes = ErrorLog::getTopErrorCodes(10);
         $errorData = [];
         foreach ($topErrorCodes as $errorInfo) {
             $code = $errorInfo['error_code'];
             $errorData[$code] = ErrorLog::getErrorFrequency($code, $period, $days);
         }
         $errorsByType = ErrorLog::select('type', DB::raw('COUNT(*) as count')) // Use DB::raw
             ->groupBy('type')
             ->orderByDesc('count')
             ->get()
             ->toArray();

         return view('error-manager::dashboard.statistics', compact(
             'topErrorCodes', 'errorData', 'errorsByType', 'period', 'days'
         ));
     }

    /**
     * ✅ Mark multiple error logs as resolved.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function bulkResolve(Request $request): RedirectResponse
    {
        $ids = $request->input('error_ids', []);
        if (empty($ids) || !is_array($ids)) { // Add validation
            return redirect()->back()->with('error', 'No errors selected or invalid input.');
        }

        $notes = $request->input('bulk_notes'); // Optional notes for all
         $resolvedBy = $request->user() ? ($request->user()->name ?? 'Bulk Action User') : 'System';

        // Update in chunks for potentially large selections
        $updatedCount = 0;
        foreach (array_chunk($ids, 100) as $chunkIds) {
             $updatedCount += ErrorLog::whereIn('id', $chunkIds)->unresolved()->update([
                 'resolved' => true,
                 'resolved_at' => now(),
                 'resolved_by' => $resolvedBy,
                 'resolution_notes' => $notes, // Apply same note to all
             ]);
        }

        if ($updatedCount > 0) {
            return redirect()->back()->with('success', "{$updatedCount} errors marked as resolved.");
        } else {
             return redirect()->back()->with('info', 'No unresolved errors were selected or updated.');
        }
    }

    /**
     * 🧹 Purge resolved error logs older than a specified period.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function purgeResolved(Request $request): RedirectResponse
    {
        $olderThan = (int) $request->input('older_than', 30); // Default 30 days
        $olderThan = max(1, $olderThan); // Ensure at least 1 day

        $date = now()->subDays($olderThan)->startOfDay();

        // Perform deletion
        $deletedCount = ErrorLog::resolved()->where('created_at', '<', $date)->delete();

        return redirect()->route('error-manager.dashboard.index')
            ->with('success', "{$deletedCount} resolved errors older than {$olderThan} days have been purged.");
    }

    /**
     * 🧪 Display error simulation management view.
     *
     * @param Request $request
     * @return View
     */
    public function simulations(Request $request): View
    {
        // Get available errors from injected config
        $errorCodesConfig = $this->config->get('error-manager.errors', []);
        $errorCodes = array_keys($errorCodesConfig);

        // Get active simulations from injected service
        $activeSimulations = $this->testingConditions->getActiveConditions();

        // Group errors by type using injected ErrorManager to get full config
        $errorsByType = [];
        foreach ($errorCodes as $code) {
            // Use injected manager here
            $config = $this->errorManager->getErrorConfig($code);
            if (!$config) continue; // Skip if config somehow missing

            $type = $config['type'] ?? 'unknown';
            // Ensure the type key exists
             if (!isset($errorsByType[$type])) {
                 $errorsByType[$type] = [];
             }
            $errorsByType[$type][] = [
                'code'   => $code,
                'config' => $config,
                'active' => isset($activeSimulations[$code]) && $activeSimulations[$code] === true,
            ];
        }
        // Sort types alphabetically for consistent display
        ksort($errorsByType);

        return view('error-manager::dashboard.simulations', compact('errorsByType', 'activeSimulations'));
    }

    /**
     * ✅ Activate a specific error simulation.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function activateSimulation(Request $request): RedirectResponse
    {
        $errorCode = $request->input('error_code');
        if (!$errorCode) {
            return redirect()->back()->with('error', 'No error code specified.');
        }

        // Verify error code exists using injected manager
        $errorConfig = $this->errorManager->getErrorConfig($errorCode);
        if (!$errorConfig) {
            return redirect()->back()->with('error', "Error code '{$errorCode}' does not exist.");
        }

        // Activate using injected service
        $this->testingConditions->setCondition($errorCode, true);

        return redirect()->back()->with('success', "Simulation for '{$errorCode}' activated.");
    }

    /**
     * ❌ Deactivate a specific error simulation or all simulations.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function deactivateSimulation(Request $request): RedirectResponse
    {
        $errorCode = $request->input('error_code');

        if ($errorCode === 'all') {
            // Reset using injected service
            $this->testingConditions->resetAllConditions();
            return redirect()->back()->with('success', 'All error simulations deactivated.');
        }

        if (!$errorCode) {
            return redirect()->back()->with('error', 'No error code specified.');
        }

        // Deactivate specific code using injected service
        $this->testingConditions->setCondition($errorCode, false);

        return redirect()->back()->with('success', "Simulation for '{$errorCode}' deactivated.");
    }
}