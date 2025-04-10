<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable; // For type hinting User
use Illuminate\Http\Request;
use Ultra\ErrorManager\Exceptions\UltraErrorException;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;
use Throwable;
// Import specific exception types for mapping if needed, or rely on get_class() / instanceof
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpFoundation\Response; // Return type hint

/**
 * 🎯 ErrorHandlingMiddleware – Oracoded Global Exception Catcher for UEM
 *
 * Catches uncaught exceptions during the HTTP request lifecycle and routes them
 * through the configured UltraErrorManager instance for centralized handling.
 * Enriches the error context with relevant Request and User details before handling.
 * Maps common framework exceptions to specific UEM error codes.
 *
 * 🧱 Structure:
 * - Standard Laravel middleware implementing `handle`.
 * - Requires ErrorManagerInterface injected via constructor.
 * - Wraps the request pipeline ($next) in a try-catch block.
 * - Catches UltraErrorException specifically to use its embedded code/context.
 * - Catches generic Throwable for broader coverage.
 * - Uses `mapExceptionToErrorCode` helper to classify common exceptions.
 * - Uses `getRequestContext` helper to gather request/user details safely.
 * - Delegates actual handling to the injected ErrorManager instance.
 *
 * 📡 Communicates:
 * - With the injected ErrorManagerInterface instance (`handle` method).
 *
 * 🧪 Testable:
 * - Dependency (ErrorManagerInterface) is injectable and mockable.
 * - Exception mapping and context gathering logic are testable in isolation.
 *
 * 🛡️ GDPR Considerations:
 * - Gathers request/user details (`getRequestContext`) like IP, User Agent, User ID.
 * - Passes potentially sensitive exception details and the gathered context to UEM.
 *   UEM handlers are responsible for sanitization if needed before logging/output.
 */
final class ErrorHandlingMiddleware
{
    protected readonly ErrorManagerInterface $errorManager;

    /**
     * 🎯 Constructor: Injects the ErrorManager dependency.
     * @param ErrorManagerInterface $errorManager The UEM service instance.
     */
    public function __construct(ErrorManagerInterface $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    /**
     * 🔥 Handle an incoming request, catching exceptions and enriching context for UEM.
     *
     * @param Request $request The incoming request instance.
     * @param Closure $next The next middleware or controller action.
     * @return mixed The response from the next middleware/controller or from the ErrorManager.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            // Proceed with the request pipeline
            return $next($request);

        } catch (UltraErrorException $e) {
            // 🎯 Handle UEM's own exceptions
            // 🧱 Extract original context, merge with current request/user context
            $originalContext = $e->getContext();
            $requestContext = $this->getRequestContext($request); // Use helper
            // Merge, giving priority to specific context from the exception if keys conflict
            $context = array_merge($requestContext, $originalContext);
            $context['middleware_caught'] = true; // Add flag

             $this->logCaughtExceptionInfo('UltraErrorException', $e->getStringCode() ?? 'UNKNOWN_UEM', $e);


            // 📡 Delegate to ErrorManager
            return $this->errorManager->handle(
                $e->getStringCode() ?? 'UNEXPECTED_ERROR', // Use stored code or fallback
                $context, // Pass merged and enriched context
                $e,       // Pass the original UEM exception
                false     // Let ErrorManager decide response/throw
            );

        } catch (Throwable $e) { // Catch generic Throwable
            // 🎯 Handle generic exceptions
            // 🗺️ Map exception to a UEM error code
            $errorCode = $this->mapExceptionToErrorCode($e);

            // 🧱 Prepare context using current request/user details
            $context = $this->getRequestContext($request); // Use helper
            $context['middleware_caught'] = true;
            // Optional: Add exception message preview if safe/useful
            // if (!$e instanceof HttpException) { // Avoid redundant HTTP messages
            //     $context['exception_message_preview'] = Str::limit($e->getMessage(), 150);
            // }

             $this->logCaughtExceptionInfo(get_class($e), $errorCode, $e);

            // 📡 Delegate to ErrorManager
            return $this->errorManager->handle(
                $errorCode,
                $context, // Pass enriched context
                $e,       // Pass the original generic exception
                false     // Let ErrorManager decide response/throw
            );
        }
    }

    /**
     * 🧱 Helper to gather safe Request and User details for error context.
     * Avoids directly passing sensitive objects.
     *
     * 🛡️ Gathers potentially sensitive info (IP, User Agent, User ID).
     *
     * @param Request $request The current request instance.
     * @return array<string, mixed> Context array with request/user details.
     */
    private function getRequestContext(Request $request): array
    {
        $context = [
            // Request Details
            'request_url'    => $request->fullUrl(), // Use fullUrl for complete path
            'request_method' => $request->method(),
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent() ?? 'N/A',
            // Optional potentially useful, non-sensitive details
            'request_ajax'   => $request->ajax(),
            'request_secure' => $request->secure(),
            // 'route_name' => $request->route()?->getName(), // Can be verbose
            // 'referer' => $request->header('referer'), // Can contain PII, omit by default
        ];

        // User Details (attempt safely)
        $context['user_id'] = null; // Default
        try {
            // Use $request->user() which uses the appropriate guard
            $user = $request->user();
            if ($user instanceof Authenticatable) { // Check if we got a valid user object
                $context['user_id'] = $user->getAuthIdentifier(); // Standard way to get ID
                // Add user type/role if safe and useful for debugging?
                // $context['user_type'] = get_class($user);
                // $context['user_roles'] = $user->getRoleNames()->implode(', '); // Example if using Spatie Permissions
            }
        } catch (Throwable $authError) {
             // Log that we couldn't get user details but don't fail the request
             // Use logger() helper for simplicity within this private method, or inject ULM Logger if preferred
             logger()->warning('UEM Middleware: Could not retrieve auth user for error context.', [
                 'exception' => $authError->getMessage()
             ]);
        }

        return $context;
    }

     /**
      * 🗺️ Map common PHP/Laravel/Symfony exception types to UEM error codes.
      * Uses `instanceof` for reliable type checking including inheritance.
      *
      * @param Throwable $e The exception instance to map.
      * @return string The mapped UEM error code (defaults to 'UNEXPECTED_ERROR').
      */
     protected function mapExceptionToErrorCode(Throwable $e): string
     {
         // Prioritize more specific exceptions first
         return match (true) {
             $e instanceof ValidationException           => 'VALIDATION_ERROR',
             $e instanceof AuthenticationException       => 'AUTHENTICATION_ERROR',
             $e instanceof AuthorizationException        => 'AUTHORIZATION_ERROR',
             $e instanceof TokenMismatchException        => 'CSRF_TOKEN_MISMATCH',
             $e instanceof ModelNotFoundException        => 'RECORD_NOT_FOUND',
             $e instanceof NotFoundHttpException         => 'ROUTE_NOT_FOUND', // 404
             $e instanceof MethodNotAllowedHttpException => 'METHOD_NOT_ALLOWED', // 405
             $e instanceof TooManyRequestsHttpException  => 'TOO_MANY_REQUESTS', // 429
             $e instanceof QueryException                => 'DATABASE_ERROR', // DB Query failed
             // Add other specific application or dependency exceptions here
             // Example: $e instanceof \App\Exceptions\SpecificServiceException => 'SERVICE_X_FAILED',
             default                                     => 'UNEXPECTED_ERROR',
         };
     }

     /**
      * 🪵 Internal helper for logging caught exception details before handling.
      * Useful for debugging the middleware itself. Uses standard logger.
      *
      * @param string $exceptionClass
      * @param string $mappedErrorCode
      * @param Throwable $exception
      * @return void
      */
     private function logCaughtExceptionInfo(string $exceptionClass, string $mappedErrorCode, Throwable $exception): void
     {
         // Use standard logger() for middleware's internal diagnostics
         logger()->debug('UEM Middleware: Caught Exception', [
            'exception_class' => $exceptionClass,
            'mapped_code' => $mappedErrorCode,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
         ]);
     }
} // End of ErrorHandlingMiddleware class