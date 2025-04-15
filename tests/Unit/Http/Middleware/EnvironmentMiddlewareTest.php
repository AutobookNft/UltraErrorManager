<?php

namespace Ultra\ErrorManager\Tests\Unit\Http\Middleware;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Ultra\ErrorManager\Http\Middleware\EnvironmentMiddleware; // Class under test
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\ErrorManager\Tests\UltraTestCase;
use Closure;

/**
 * 📜 Oracode Unit Test: EnvironmentMiddlewareTest
 *
 * Tests the EnvironmentMiddleware, which restricts access based on the application environment.
 * Verifies that the middleware correctly allows or denies requests ($next closure execution)
 * based on the current environment and the environments specified as parameters.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Http\Middleware
 * @version         0.1.3 // Removed invalid UsesClass annotation.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the EnvironmentMiddleware.
 * 🧱 Mocks Application and Request contracts. Uses real Closure for $next.
 * 📡 Verifies interaction with the Application mock and whether the $next closure is called or an HttpException is thrown.
 * 🧪 Focuses on the conditional logic based on environment strings.
 */
#[CoversClass(EnvironmentMiddleware::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Needed for TestCase setup
// Removed UsesClass for Application interface
class EnvironmentMiddlewareTest extends UltraTestCase
{
    protected Application&MockInterface $appMock;
    protected Request&MockInterface $requestMock;
    protected EnvironmentMiddleware $middleware;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->appMock = Mockery::mock(Application::class);
        $this->requestMock = Mockery::mock(Request::class);
        $this->middleware = new EnvironmentMiddleware($this->appMock);
    }

    /**
     * 🧹 Clean up after each test.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 🎯 Test [handle]: Allows request when environment is explicitly listed.
     * @dataProvider allowedEnvironmentsProvider
     */
    #[Test]
    #[DataProvider('allowedEnvironmentsProvider')]
    public function handle_allows_request_when_environment_is_explicitly_listed(string $currentEnv, array $allowedEnvs): void
    {
        $this->appMock->shouldReceive('environment')->once()->withNoArgs()->andReturn($currentEnv);
        $this->appMock->shouldReceive('environment')->once()->with($allowedEnvs)->andReturn(true);
        $expectedResponse = new Response('Allowed by ' . $currentEnv, 200);
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled, $expectedResponse): Response {
            $nextCalled = true; $this->assertInstanceOf(Request::class, $request); return $expectedResponse;
        };

        $response = $this->middleware->handle($this->requestMock, $next, ...$allowedEnvs);

        $this->assertTrue($nextCalled, '$next closure was not called.');
        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Data provider for allowed environment scenarios.
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function allowedEnvironmentsProvider(): array
    {
        return [
            'local allowed' => ['local', ['local', 'testing']],
            'staging allowed' => ['staging', ['local', 'staging', 'production']],
            'testing allowed' => ['testing', ['testing']],
        ];
    }

    /**
     * 🎯 Test [handle]: Aborts request when environment is NOT explicitly listed.
     * @dataProvider deniedEnvironmentsProvider
     */
    #[Test]
    #[DataProvider('deniedEnvironmentsProvider')]
    public function handle_aborts_request_when_environment_is_not_explicitly_listed(string $currentEnv, array $allowedEnvs): void
    {
        $this->appMock->shouldReceive('environment')->once()->withNoArgs()->andReturn($currentEnv);
        $this->appMock->shouldReceive('environment')->once()->with($allowedEnvs)->andReturn(false);
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true; return new Response();
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Access to this resource is restricted in the current environment.');

        try {
             $this->middleware->handle($this->requestMock, $next, ...$allowedEnvs);
        } catch (HttpException $e) {
             $this->assertEquals(403, $e->getStatusCode(), "Expected HTTP status code 403.");
             $this->assertFalse($nextCalled, '$next closure should not have been called.');
             throw $e;
        } catch (\Throwable $e) {
             $this->fail('Unexpected exception type thrown: ' . get_class($e));
        }
    }

     /**
     * Data provider for denied environment scenarios with explicit list.
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function deniedEnvironmentsProvider(): array
    {
        return [
            'production denied when only local/staging allowed' => ['production', ['local', 'staging']],
            'testing denied when only local allowed' => ['testing', ['local']],
            'local denied when only prod allowed' => ['local', ['production']],
        ];
    }

    /**
     * 🎯 Test [handle]: Allows request when no environments listed and env is non-production.
     * @dataProvider defaultAllowedEnvironmentsProvider
     */
    #[Test]
    #[DataProvider('defaultAllowedEnvironmentsProvider')]
    public function handle_allows_request_when_no_environments_listed_and_env_is_non_production(string $currentEnv): void
    {
         $defaultAllowed = ['local', 'development', 'testing', 'staging'];
         $this->appMock->shouldReceive('environment')->once()->withNoArgs()->andReturn($currentEnv);
         $this->appMock->shouldReceive('environment')->once()->with($defaultAllowed)->andReturn(true);
         $expectedResponse = new Response('Default Allowed', 200);
         $nextCalled = false;
         $next = function (Request $request) use (&$nextCalled, $expectedResponse): Response {
             $nextCalled = true; return $expectedResponse;
         };

         $response = $this->middleware->handle($this->requestMock, $next);

         $this->assertTrue($nextCalled, '$next closure was not called.');
         $this->assertSame($expectedResponse, $response);
    }

    /**
     * Data provider for default allowed non-production environments.
     * @return array<string, array{0: string}>
     */
     public static function defaultAllowedEnvironmentsProvider(): array
     {
         return [
             'local default allowed' => ['local'],
             'testing default allowed' => ['testing'],
             'staging default allowed' => ['staging'],
             'development default allowed' => ['development'],
         ];
     }


    /**
     * 🎯 Test [handle]: Aborts request when no environments listed and env is production.
     */
    #[Test]
    public function handle_aborts_request_when_no_environments_listed_and_env_is_production(): void
    {
        $currentEnv = 'production';
        $defaultAllowed = ['local', 'development', 'testing', 'staging'];
        $this->appMock->shouldReceive('environment')->once()->withNoArgs()->andReturn($currentEnv);
        $this->appMock->shouldReceive('environment')->once()->with($defaultAllowed)->andReturn(false);
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true; return new Response();
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Access to this resource is restricted in the current environment.');

        try {
            $this->middleware->handle($this->requestMock, $next);
        } catch (HttpException $e) {
             $this->assertEquals(403, $e->getStatusCode(), "Expected HTTP status code 403.");
             $this->assertFalse($nextCalled, '$next closure should not have been called.');
             throw $e;
        } catch (\Throwable $e) {
             $this->fail('Unexpected exception type thrown: ' . get_class($e));
        }
    }

} // End class EnvironmentMiddlewareTest