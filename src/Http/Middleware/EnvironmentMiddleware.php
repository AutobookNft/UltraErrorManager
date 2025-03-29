<?php

namespace Ultra\ErrorManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Environment Middleware
 *
 * This middleware restricts access to routes based on the current environment.
 * It's particularly useful for protecting testing/development features in production.
 *
 * @package Ultra\ErrorManager\Http\Middleware
 */
class EnvironmentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$environments Allowed environments
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$environments)
    {
        $currentEnv = app()->environment();
        
        // If no environments are specified, default to non-production environments
        if (empty($environments)) {
            $environments = ['local', 'development', 'testing', 'staging'];
        }
        
        // If the current environment is not in the allowed list, deny access
        if (!in_array($currentEnv, $environments) && $currentEnv === 'production') {
            abort(403, 'This feature is not available in the production environment.');
        }
        
        return $next($request);
    }
}
