<?php

namespace Ultra\ErrorManager\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Facades\TestingConditions;
use Ultra\ErrorManager\Models\ErrorLog;
use Ultra\ErrorManager\Facades\UltraError;
use Illuminate\Routing\Controller;


class ErrorDashboardController extends Controller
{
    /**
     * Display the error dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get filters from request
        $type = $request->input('type');
        $code = $request->input('code');
        $status = $request->input('status', 'unresolved');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Build query based on filters
        $query = ErrorLog::query()->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        if ($code) {
            $query->where('error_code', $code);
        }

        if ($status === 'resolved') {
            $query->where('resolved', true);
        } elseif ($status === 'unresolved') {
            $query->where('resolved', false);
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate . ' 23:59:59');
        }

        // Paginate results
        $errors = $query->paginate(15)->appends($request->except('page'));

        // Get stats for the dashboard
        $stats = [
            'total' => ErrorLog::count(),
            'unresolved' => ErrorLog::unresolved()->count(),
            'critical' => ErrorLog::critical()->count(),
            'critical_unresolved' => ErrorLog::critical()->unresolved()->count(),
            'today' => ErrorLog::whereDate('created_at', today())->count(),
            'this_week' => ErrorLog::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
        ];

        // Get top error codes
        $topErrorCodes = ErrorLog::getTopErrorCodes(5);

        // Get error types for filter dropdown
        $errorTypes = Config::get('error-manager.error_types', []);

        // Get error codes for filter dropdown
        $errorCodes = ErrorLog::select('error_code')
            ->distinct()
            ->orderBy('error_code')
            ->pluck('error_code')
            ->toArray();

        return view('error-manager::dashboard.index', compact(
            'errors',
            'stats',
            'topErrorCodes',
            'errorTypes',
            'errorCodes',
            'type',
            'code',
            'status',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Display details for a specific error.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $error = ErrorLog::findOrFail($id);

        // Get similar errors
        $similarErrors = $error->getSimilarErrors(5);

        // Get error frequency over time
        $errorFrequency = ErrorLog::getErrorFrequency($error->error_code, 'daily', 14);

        return view('error-manager::dashboard.show', compact('error', 'similarErrors', 'errorFrequency'));
    }

    /**
     * Mark an error as resolved.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resolve(Request $request, $id)
    {
        $error = ErrorLog::findOrFail($id);

        $notes = $request->input('notes');
        $resolvedBy = $request->user() ? $request->user()->name : 'System';

        $error->markAsResolved($resolvedBy, $notes);

        return redirect()->route('error-manager.dashboard.show', $id)
            ->with('success', 'Error marked as resolved.');
    }

    /**
     * Mark an error as unresolved.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unresolve($id)
    {
        $error = ErrorLog::findOrFail($id);
        $error->markAsUnresolved();

        return redirect()->route('error-manager.dashboard.show', $id)
            ->with('success', 'Error marked as unresolved.');
    }

    /**
     * Delete an error log.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $error = ErrorLog::findOrFail($id);
        $error->delete();

        return redirect()->route('error-manager.dashboard.index')
            ->with('success', 'Error log deleted successfully.');
    }

    /**
     * Display error statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function statistics(Request $request)
    {
        $period = $request->input('period', 'daily');
        $days = (int) $request->input('days', 30);

        // Ensure days is within reasonable limits
        $days = min(max($days, 7), 90);

        // Get top error codes
        $topErrorCodes = ErrorLog::getTopErrorCodes(10);

        // Get data for each top error code
        $errorData = [];
        foreach ($topErrorCodes as $errorInfo) {
            $code = $errorInfo['error_code'];
            $errorData[$code] = ErrorLog::getErrorFrequency($code, $period, $days);
        }

        // Get error distribution by type
        $errorsByType = ErrorLog::select('type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        return view('error-manager::dashboard.statistics', compact(
            'topErrorCodes',
            'errorData',
            'errorsByType',
            'period',
            'days'
        ));
    }

    /**
     * Bulk resolve multiple errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkResolve(Request $request)
    {
        $ids = $request->input('error_ids', []);
        $notes = $request->input('bulk_notes');
        $resolvedBy = $request->user() ? $request->user()->name : 'System';

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No errors selected.');
        }

        $errors = ErrorLog::whereIn('id', $ids)->get();

        foreach ($errors as $error) {
            $error->markAsResolved($resolvedBy, $notes);
        }

        return redirect()->back()->with('success', count($errors) . ' errors marked as resolved.');
    }

    /**
     * Purge resolved errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function purgeResolved(Request $request)
    {
        $olderThan = $request->input('older_than', 30);

        $date = now()->subDays($olderThan);
        $count = ErrorLog::resolved()->where('created_at', '<', $date)->count();

        // Delete the records
        ErrorLog::resolved()->where('created_at', '<', $date)->delete();

        return redirect()->route('error-manager.dashboard.index')
            ->with('success', "{$count} resolved errors older than {$olderThan} days have been purged.");
    }

    /**
     * Display error simulation interface.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function simulations(Request $request)
    {
        Log::channel('upload')->info('Simulations method called');

        // Get all available error codes from config
        $errorCodes = array_keys(Config::get('error-manager.errors', []));

        // // // Get all currently active simulations
        $activeSimulations = TestingConditions::getActiveConditions();

        Log::channel('upload')->info('Active simulations', $activeSimulations);

        // // // Group error codes by their type for better UI organization
        $errorsByType = [];
        foreach ($errorCodes as $code) {
            $config = UltraError::getErrorConfig($code);
            $type = $config['type'] ?? 'unknown';
            $errorsByType[$type][] = [
                'code' => $code,
                'config' => $config,
                'active' => isset($activeSimulations[$code]) && $activeSimulations[$code] === true
            ];
        }

        // dd($errorsByType);

        return view('error-manager::dashboard.simulations', compact('errorsByType', 'activeSimulations'));
    }
    /**
     * Activate error simulation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activateSimulation(Request $request)
    {
        $errorCode = $request->input('error_code');

        if (!$errorCode) {
            return redirect()->back()->with('error', 'No error code specified.');
        }

        // Verify the error code exists
        $errorConfig = UltraError::getErrorConfig($errorCode);
        if (!$errorConfig) {
            return redirect()->back()->with('error', "Error code '{$errorCode}' does not exist.");
        }

        // Activate the simulation
        TestingConditions::setCondition($errorCode, true);

        return redirect()->back()->with('success', "Simulation for '{$errorCode}' has been activated.");
    }

    /**
     * Deactivate error simulation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivateSimulation(Request $request)
    {
        $errorCode = $request->input('error_code');

        if ($errorCode === 'all') {
            // Reset all simulations
            TestingConditions::resetAllConditions();
            return redirect()->back()->with('success', 'All error simulations have been deactivated.');
        }

        if (!$errorCode) {
            return redirect()->back()->with('error', 'No error code specified.');
        }

        // Deactivate the simulation
        TestingConditions::setCondition($errorCode, false);

        return redirect()->back()->with('success', "Simulation for '{$errorCode}' has been deactivated.");
    }
}
