<?php

namespace Ultra\ErrorManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ultra\ErrorManager\Facades\UltraError;
use Ultra\ErrorManager\Exceptions\UltraErrorException;

/**
 * Middleware for automatic exception handling
 *
 * This middleware captures exceptions and routes them through
 * the Ultra Error Manager system.
 *
 * @package Ultra\ErrorManager\Http\Middleware
 */
class ErrorHandlingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (UltraErrorException $e) {
            // If it's already our exception type, handle it with the code it contains
            return UltraError::handle($e->getStringCode() ?? 'UNEXPECTED_ERROR', [
                'request_path' => $request->path(),
                'request_method' => $request->method(),
            ], $e);
        } catch (\Exception $e) {
            // Map some common exception types to error codes
            $errorCode = $this->mapExceptionToErrorCode($e);

            return UltraError::handle($errorCode, [
                'request_path' => $request->path(),
                'request_method' => $request->method(),
                'exception_message' => $e->getMessage(),
            ], $e);
        }
    }

    /**
     * Map PHP/Laravel exceptions to Ultra error codes
     *
     * @param \Exception $e The exception to map
     * @return string Mapped error code
     */
    protected function mapExceptionToErrorCode(\Exception $e): string
    {
        $class = get_class($e);

        // Map common exception types to error codes
        $mapping = [
            'Illuminate\Auth\AuthenticationException' => 'AUTHENTICATION_ERROR',
            'Illuminate\Auth\Access\AuthorizationException' => 'AUTHORIZATION_ERROR',
            'Illuminate\Database\Eloquent\ModelNotFoundException' => 'RECORD_NOT_FOUND',
            'Illuminate\Validation\ValidationException' => 'VALIDATION_ERROR',
            'Illuminate\Session\TokenMismatchException' => 'CSRF_TOKEN_MISMATCH',
            'Illuminate\Database\QueryException' => 'DATABASE_ERROR',
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => 'ROUTE_NOT_FOUND',
            'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException' => 'METHOD_NOT_ALLOWED',
            'Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException' => 'TOO_MANY_REQUESTS',
        ];

        return $mapping[$class] ?? 'UNEXPECTED_ERROR';
    }
}
