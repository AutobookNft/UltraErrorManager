<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application; // Dependency
use Illuminate\Http\Request; // Dependency
use Symfony\Component\HttpFoundation\Response; // Return type hint
use Symfony\Component\HttpKernel\Exception\HttpException; // For abort

/**
 * 🎯 EnvironmentMiddleware – Oracoded Environment-Based Access Control
 *
 * Restricts access to specific routes based on the current application environment.
 * Primarily used to protect development/testing features (like error simulation APIs)
 * from being accessible in production. Relies on injected Application contract.
 *
 * 🧱 Structure:
 * - Standard Laravel middleware implementing `handle`.
 * - Requires Application contract injected via constructor.
 * - Reads the current environment from the Application instance.
 * - Compares current environment against allowed environments passed as middleware parameters.
 * - Aborts with 403 Forbidden if the environment is not allowed (specifically blocks production if not explicitly allowed).
 *
 * 📡 Communicates:
 * - With the Application contract to get the environment name.
 *
 * 🧪 Testable:
 * - Dependency (Application) is injectable and mockable.
 * - Logic can be tested by simulating different environments and middleware parameters.
 *
 * 🛡️ GDPR Considerations:
 * - Indirectly contributes by preventing access to potentially sensitive debug/test routes in production.
 */
final class EnvironmentMiddleware // Mark as final
{
    /**
     * 🧱 @dependency The Application instance.
     * @var Application
     */
    protected readonly Application $app;

    /**
     * 🎯 Constructor: Injects the Application dependency.
     *
     * @param Application $app Laravel Application instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 🔐 Handle an incoming request, checking the environment.
     *
     * @param Request $request The incoming request.
     * @param Closure $next The next middleware.
     * @param string ...$environments List of allowed environment names (e.g., 'local', 'staging').
     *                                If empty, defaults to non-production environments.
     * @return Response The response from the next middleware or an abort response.
     * @throws HttpException (403 Forbidden) if environment check fails.
     */
    public function handle(Request $request, Closure $next, string ...$environments): Response
    {
        // Get environment using injected Application instance
        $currentEnv = $this->app->environment();

        // Default to allowing all non-production environments if none specified
        $allowedEnvironments = empty($environments)
            ? ['local', 'development', 'testing', 'staging'] // Sensible defaults
            : $environments;

        // Check if the current environment is allowed
        $isAllowed = $this->app->environment($allowedEnvironments); // environment() can check against an array

        // Explicitly deny production if it wasn't in the allowed list
        // Or if the environment wasn't in the default non-production list
        if (!$isAllowed) {
            // Abort with 403 Forbidden
             abort(403, 'Access to this resource is restricted in the current environment.');
        }

        // Environment allowed, proceed with the request
        return $next($request);
    }
}