<?php

namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Illuminate\Contracts\Foundation\Application; // Dependency
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider; // Import DataProvider attribute
use Ultra\ErrorManager\Handlers\ErrorSimulationHandler; // Class under test
use Ultra\ErrorManager\Services\TestingConditionsManager; // Dependency (REAL INSTANCE)
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Used by TestCase
use Ultra\ErrorManager\Tests\UltraTestCase;
use Ultra\UltraLogManager\UltraLogManager; // Dependency
use Throwable; // Base exception interface

/**
 * 📜 Oracode Unit Test: ErrorSimulationHandlerTest
 *
 * Tests the ErrorSimulationHandler, which logs whether a handled error was being
 * actively simulated in non-production environments. Verifies environment checking
 * via a mocked Application instance, interacts with a REAL TestingConditionsManager instance,
 * and verifies logging via a mocked UltraLogManager.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.2 // Corrected DataProvider usage, mock expectations, coverage annotations.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the ErrorSimulationHandler.
 * 🧱 Mocks Application, UltraLogManager. Uses REAL TestingConditionsManager.
 * 📡 Verifies calls to dependencies and log content.
 * 🧪 Focuses on the handler's logic interacting with real test conditions.
 */
#[CoversClass(ErrorSimulationHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]
// Removed Application and UltraLogManager from UsesClass
#[UsesClass(TestingConditionsManager::class)] // Keep this as we use a real instance
class ErrorSimulationHandlerTest extends UltraTestCase
{
    // Mocks
    protected Application&MockInterface $appMock;
    protected UltraLogManager&MockInterface $loggerMock;

    // Real Instance
    protected TestingConditionsManager $testingManagerInstance;

    // Handler instance
    protected ErrorSimulationHandler $handler;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->appMock = Mockery::mock(Application::class);
        $this->loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();

        // REMOVED default expectation for Application::environment() from setUp
        // Each test needing it will define its own ->once() expectation.
        // However, the constructor of TestingConditionsManager *might* call it.
        // Let's add ->shouldIgnoreMissing() to appMock to handle constructor call safely.
        $this->appMock->shouldIgnoreMissing(); // Allow constructor call without explicit expectation here

        // Create REAL instance of TestingConditionsManager
        $this->testingManagerInstance = new TestingConditionsManager($this->appMock);
        $this->testingManagerInstance->setTestingEnabled(true);

        // Instantiate the handler
        $this->handler = new ErrorSimulationHandler(
            $this->appMock,
            $this->testingManagerInstance,
            $this->loggerMock
        );
    }

    /**
     * 🧹 Clean up after each test.
     */
    protected function tearDown(): void
    {
        $this->testingManagerInstance->resetAllConditions();
        $this->testingManagerInstance->setTestingEnabled(true);
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases for shouldHandle ---

    /**
     * 🎯 Test [shouldHandle]: Returns true in non-production environment.
     * @dataProvider nonProductionEnvironmentsProvider
     */
    #[Test]
    #[DataProvider('nonProductionEnvironmentsProvider')] // Add DataProvider attribute
    public function shouldHandle_returns_true_in_non_production_env(string $environment): void
    {
        // Arrange
        // Set explicit expectation for THIS test
        $this->appMock->shouldReceive('environment')->once()->andReturn($environment);
        $errorConfig = ['type' => 'error'];

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertTrue($result, "Should handle in environment: $environment");
    }

    /**
     * Data provider for non-production environments.
     * @return array<string, array{0: string}>
     */
    public static function nonProductionEnvironmentsProvider(): array
    {
        return [
            'local' => ['local'],
            'development' => ['development'],
            'testing' => ['testing'],
            'staging' => ['staging'],
        ];
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false in production environment.
     */
    #[Test]
    public function shouldHandle_returns_false_in_production_env(): void
    {
        // Arrange
        // Set explicit expectation for THIS test
        $this->appMock->shouldReceive('environment')->once()->andReturn('production');
        $errorConfig = ['type' => 'error'];

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result); // Assert false now
    }

    // --- Test Cases for handle ---

    /**
     * 🎯 Test [handle]: Logs info including simulation status (true).
     * 🧪 Strategy: Set condition to true on REAL TestingConditionsManager, call handle, verify logger call.
     */
    #[Test]
    public function handle_logs_info_including_simulation_status_true(): void
    {
        // Arrange
        $errorCode = 'SIMULATED_ERROR';
        $errorConfig = ['type' => 'warning', 'devTeam_email_need' => false];
        $context = ['user_id' => 123];
        $exception = new \RuntimeException('Simulated');
        $isSimulated = true;

        $this->testingManagerInstance->setCondition($errorCode, true); // Set real condition

        // Expect call to logger->info
        $this->loggerMock->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $logContext) use ($errorCode, $isSimulated, $errorConfig, $context, $exception) {
                $this->assertEquals('UEM SimulationHandler: Error handled.', $message);
                $this->assertIsArray($logContext);
                $this->assertEquals($errorCode, $logContext['errorCode']);
                $this->assertEquals($isSimulated, $logContext['isSimulated']); // Verify true
                $this->assertEquals($errorConfig, $logContext['errorConfig']);
                $this->assertEquals($context, $logContext['context']);
                $this->assertEquals(get_class($exception), $logContext['exception']);
                return true;
            });

        // Act
        $this->handler->handle($errorCode, $errorConfig, $context, $exception);

        // Assert
        $this->assertTrue(true); // Avoid risky warning
    }

    /**
     * 🎯 Test [handle]: Logs info including simulation status (false).
     * 🧪 Strategy: Ensure condition is false on REAL TestingConditionsManager, call handle, verify logger call.
     */
    #[Test]
    public function handle_logs_info_including_simulation_status_false(): void
    {
         // Arrange
        $errorCode = 'NON_SIMULATED_ERROR';
        $errorConfig = ['type' => 'error'];
        $context = ['data' => 'real'];
        $exception = null;
        $isSimulated = false;

        // Ensure condition is not set (it's reset in tearDown)

        // Expect call to logger->info
        $this->loggerMock->shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $logContext) use ($errorCode, $isSimulated, $errorConfig, $context) {
                $this->assertEquals('UEM SimulationHandler: Error handled.', $message);
                $this->assertIsArray($logContext);
                $this->assertEquals($errorCode, $logContext['errorCode']);
                $this->assertEquals($isSimulated, $logContext['isSimulated']); // Verify false
                $this->assertEquals($errorConfig, $logContext['errorConfig']);
                $this->assertEquals($context, $logContext['context']);
                $this->assertNull($logContext['exception']);
                return true;
            });

        // Act
        $this->handler->handle($errorCode, $errorConfig, $context, $exception);

        // Assert
        $this->assertTrue(true); // Avoid risky warning
    }

} // End class ErrorSimulationHandlerTest