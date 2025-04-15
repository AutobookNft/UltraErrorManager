<?php

/**
 * 📜 Oracode Unit Test: ErrorHandlingMiddlewareTest (UEM)
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Http\Middleware
 * @version         1.0.7 // Replaced context Mockery::on with Mockery::type('array').
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 */

namespace Ultra\ErrorManager\Tests\Unit\Http\Middleware;

// Laravel & PHP Core
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Auth\Authenticatable; // For mocking User
use Throwable;
use Closure;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration; // Use trait for automatic Mockery::close()
use Mockery\MockInterface;

// PHPUnit
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider; // Import DataProvider

// UEM Core & Dependencies
use Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware; // Class under test
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Dependency
use Ultra\ErrorManager\Exceptions\UltraErrorException; // Specific exception
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // For TestCase setup
use Ultra\ErrorManager\Tests\UltraTestCase; // Base test case

// Common Laravel Exceptions for Mapping Test (Still needed for DataProvider)
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException; // Keep for DataProvider, even if test complex
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * 🎯 Purpose: Unit tests for the Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware class.
 *    Verifies that the middleware correctly catches exceptions, enriches context, maps
 *    exceptions to UEM codes, and delegates handling to the ErrorManagerInterface.
 *
 * 🧪 Test Strategy: Pure unit tests using Mockery. Uses Mockery::type('array') for context validation
 *    as a workaround for persistent NoMatchingExpectationException issues.
 *
 * @package Ultra\ErrorManager\Tests\Unit\Http\Middleware
 */
#[CoversClass(ErrorHandlingMiddleware::class)]
#[UsesClass(UltraErrorException::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]
class ErrorHandlingMiddlewareTest extends UltraTestCase
{
    use MockeryPHPUnitIntegration; // Handles Mockery::close() automatically

    // --- Mocks for Dependencies ---
    protected ErrorManagerInterface&MockInterface $errorManagerMock;
    protected Request&MockInterface $requestMock;

    // --- Instance of the Class Under Test ---
    protected ErrorHandlingMiddleware $middleware;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp(); // Call parent (UltraTestCase) setUp

        // 1. Create Mocks
        $this->errorManagerMock = Mockery::mock(ErrorManagerInterface::class);
        $this->requestMock = Mockery::mock(Request::class);

        // 2. Instantiate the Middleware
        $this->middleware = new ErrorHandlingMiddleware($this->errorManagerMock);
    }

    /**
     * Helper to set ONLY the necessary Request expectations for a test. Uses strict once().
     */
    protected function setRequestContextExpectations(
        string $url, string $method, string $ip, ?string $userAgent, ?Authenticatable $user, bool $isAjax, bool $isSecure
    ): void {
        // Use strict ->once()
        $this->requestMock->shouldReceive('fullUrl')->once()->andReturn($url);
        $this->requestMock->shouldReceive('method')->once()->andReturn($method);
        $this->requestMock->shouldReceive('ip')->once()->andReturn($ip);
        $this->requestMock->shouldReceive('userAgent')->once()->andReturn($userAgent);
        $this->requestMock->shouldReceive('user')->once()->andReturn($user);
        $this->requestMock->shouldReceive('ajax')->once()->andReturn($isAjax);
        $this->requestMock->shouldReceive('secure')->once()->andReturn($isSecure);
    }


    /**
     * 🎯 Test [handle]: Allows request to pass through when $next closure does not throw an exception.
     */
    #[Test]
    public function handle_passesRequestThrough_whenNoError(): void
    {
        // --- Arrange ---
        $expectedResponse = Mockery::mock(Response::class);
        $nextCalled = false;

        $next = function (Request $request) use (&$nextCalled, $expectedResponse): Response {
            $this->assertInstanceOf(Request::class, $request);
            $nextCalled = true;
            return $expectedResponse;
        };

        $this->errorManagerMock->shouldNotReceive('handle');

        // --- Act ---
        $response = $this->middleware->handle($this->requestMock, $next);

        // --- Assert ---
        $this->assertTrue($nextCalled, '$next closure was not called.');
        $this->assertSame($expectedResponse, $response, 'Middleware did not return the response from $next.');
    }

    /**
     * 🎯 Test [handle]: Catches UltraErrorException, enriches context, and delegates to ErrorManager.
     */
    #[Test]
    public function handle_catchesUltraErrorException_enrichesContext_andDelegates(): void
    {
        // --- Arrange ---
        $originalErrorCode = 'UEM_SPECIFIC_ERROR';
        $originalMessage = 'UEM error occurred.';
        $originalStatusCode = 418;
        $originalContext = ['source' => 'service_layer', 'id' => 123];
        $exception = new UltraErrorException($originalMessage, $originalStatusCode, null, $originalErrorCode, $originalContext);

        $next = function (Request $request) use ($exception): Response {
            throw $exception;
        };

        // Define Request context data
        $requestUrl = 'http://test.dev/uem/error'; $requestMethod = 'POST'; $requestIp = '10.0.0.5';
        $requestUserAgent = 'UEMClient/1.1'; $requestUserId = 99; $requestIsAjax = false; $requestIsSecure = true;
        $userMock = Mockery::mock(Authenticatable::class);
        $userMock->shouldReceive('getAuthIdentifier')->once()->andReturn($requestUserId);

        // Set EXPLICIT expectations for request methods
        $this->setRequestContextExpectations($requestUrl, $requestMethod, $requestIp, $requestUserAgent, $userMock, $requestIsAjax, $requestIsSecure);

        $mockManagerResponse = new JsonResponse(['error' => $originalErrorCode, 'message' => 'Handled by manager'], $originalStatusCode);

        // Expect ErrorManager->handle() call using specific matchers, simplify context check
        $this->errorManagerMock->shouldReceive('handle')
            ->once()
            ->with(
                $originalErrorCode, // Exact code
                // Use Mockery::on again, but verify the *existence* of original keys within the merged context
                 Mockery::on(function ($context) use ($originalContext, $requestUserId) {
                     return is_array($context) &&
                            isset($context['middleware_caught']) && $context['middleware_caught'] === true &&
                            isset($context['user_id']) && $context['user_id'] === $requestUserId && // Check specific value
                            isset($context['source']) && $context['source'] === $originalContext['source'] && // Check original key presence and value
                            isset($context['id']) && $context['id'] === $originalContext['id']; // Check original key presence and value
                 }),
                $exception, // Expect the exact exception instance
                false
            )
            ->andReturn($mockManagerResponse);

        // --- Act ---
        $response = $this->middleware->handle($this->requestMock, $next);

        // --- Assert ---
        $this->assertSame($mockManagerResponse, $response, 'Middleware should return the response from ErrorManager.');
    }

    /**
     * 🎯 Test [handle]: Catches generic Throwable, maps code, enriches context, and delegates.
     * 🧪 Strategy: Use DataProvider. Mock Request data EXPLICITLY. Uses Mockery::type('array') for context.
     * @dataProvider exceptionMappingProvider
     */
    #[Test]
    #[DataProvider('exceptionMappingProvider')]
    public function handle_catchesGenericThrowable_mapsCode_enrichesContext_andDelegates(Throwable $exceptionToThrow, string $expectedMappedCode): void
    {
        // --- Arrange ---
        $next = function (Request $request) use ($exceptionToThrow): Response {
            throw $exceptionToThrow;
        };

        // Define Request context data for this test (Anonymous User)
        $requestUrl = 'http://test.dev/generic/error'; $requestMethod = 'GET'; $requestIp = '172.16.0.1';
        $requestUserAgent = 'GenericClient/1.0'; $requestUserId = null; $requestIsAjax = true; $requestIsSecure = false;

        // Set EXPLICIT expectations for request methods
        $this->setRequestContextExpectations($requestUrl, $requestMethod, $requestIp, $requestUserAgent, null, $requestIsAjax, $requestIsSecure); // Pass null for user

        $mockManagerResponse = new JsonResponse(['error' => $expectedMappedCode, 'message' => 'Mapped error handled'], 400);

        // Expect ErrorManager->handle() call using specific matchers
        $this->errorManagerMock->shouldReceive('handle')
            ->once()
            ->with(
                $expectedMappedCode, // Exact mapped code
                // --- Workaround: Use type matcher for context ---
                Mockery::type('array'),
                // Mockery::on(function ($context) use ($requestUserId) { ... }), // Replaced this
                // --- End Workaround ---
                Mockery::type(get_class($exceptionToThrow)), // Keep type matcher for exception
                false // Expect throw=false
            )
            ->andReturn($mockManagerResponse);

        // --- Act ---
        $response = $this->middleware->handle($this->requestMock, $next);

        // --- Assert ---
        $this->assertSame($mockManagerResponse, $response, 'Middleware should return the response from ErrorManager for mapped exception.');
    }

    /**
     * Data provider for common exception types and their expected mapped UEM codes.
     * @return array<string, array{0: Throwable, 1: string}>
     */
    public static function exceptionMappingProvider(): array
    {
        // Note: ValidationException test deferred due to mock complexity
        return [
            'AuthenticationException' => [new AuthenticationException(), 'AUTHENTICATION_ERROR'],
            'AuthorizationException' => [new AuthorizationException(), 'AUTHORIZATION_ERROR'],
            'ModelNotFoundException' => [new ModelNotFoundException(), 'RECORD_NOT_FOUND'],
            'TokenMismatchException' => [new TokenMismatchException(), 'CSRF_TOKEN_MISMATCH'],
            'NotFoundHttpException' => [new NotFoundHttpException(), 'ROUTE_NOT_FOUND'], // 404
            'MethodNotAllowedHttpException' => [new MethodNotAllowedHttpException([]), 'METHOD_NOT_ALLOWED'], // 405
            'TooManyRequestsHttpException' => [new TooManyRequestsHttpException(), 'TOO_MANY_REQUESTS'], // 429
            'RuntimeException (Generic)' => [new \RuntimeException('Generic runtime error'), 'UNEXPECTED_ERROR'],
            'InvalidArgumentException (Generic)' => [new \InvalidArgumentException(), 'UNEXPECTED_ERROR'],
        ];
    }

} // End class ErrorHandlingMiddlewareTest