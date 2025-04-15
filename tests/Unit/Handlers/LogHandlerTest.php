<?php
/**
 * 📜 Oracode Unit Test: LogHandlerTest
 *
 * Tests the LogHandler's functionality for logging errors via UltraLogManager,
 * ensuring correct log level mapping, context preparation, and GDPR-compliant
 * handling of sensitive data. Verifies interaction with UltraLogManager and
 * error configuration processing.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.0 // Initial structure
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 LogHandlerTest – Oracoded Unit Tests for LogHandler
 *
 * Tests the LogHandler's ability to process errors, map error types to PSR-3 log levels,
 * prepare structured log contexts, and handle exceptions. Ensures robust logging
 * behavior without database dependencies.
 *
 * 🧱 Structure:
 * - Extends UltraTestCase for isolated unit testing.
 * - Mocks UltraLogManager for logging operations.
 * - Tests public methods and verifies log output structure.
 *
 * 📡 Communicates:
 * - With mocked UltraLogManager for log verification.
 * - No database interaction, pure unit testing.
 *
 * 🧪 Testable:
 * - Uses Mockery for dependency isolation.
 * - Tests in isolation without external dependencies.
 */
namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Handlers\LogHandler;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\ErrorManager\Tests\UltraTestCase;
use Ultra\UltraLogManager\UltraLogManager;

#[CoversClass(LogHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]


class LogHandlerTest extends UltraTestCase
{
    protected UltraLogManager $loggerMock;
    protected LogHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(UltraLogManager::class);
        $this->handler = new LogHandler($this->loggerMock);
    }

    /**
     * 🎯 Test [shouldHandle]: Verifica che shouldHandle restituisca sempre true.
     * 🧪 Strategy: Passa una configurazione errore qualsiasi e verifica il ritorno.
     */
    #[Test]
    public function shouldHandle_returns_true(): void
    {
        // Arrange
        $errorConfig = ['type' => 'error'];

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 🎯 Test [handle]: Verifica che handle chiami il corretto metodo di log ULM basato sul tipo di errore.
     * 🧪 Strategy: Testa mappatura type -> livello PSR-3 per casi chiave (critical, error, warning).
     */
    #[Test]
    public function handle_calls_correct_ulm_log_level_method(): void
    {
        // Arrange
        $testCases = [
            ['type' => 'critical', 'method' => 'critical'],
            ['type' => 'error', 'method' => 'error'],
            ['type' => 'warning', 'method' => 'warning'],
            ['type' => 'notice', 'method' => 'notice'],
            ['type' => 'unknown', 'method' => 'error'], // Fallback
        ];

        foreach ($testCases as $case) {
            $errorCode = 'TEST_ERROR';
            $errorConfig = ['type' => $case['type']];
            $context = ['key' => 'value'];

            // Mock
            $this->loggerMock->shouldReceive($case['method'])
                ->once()
                ->withArgs(function ($message, $loggedContext) use ($errorCode, $context, $case) {
                    $this->assertStringStartsWith("[$errorCode] ", $message);
                    $this->assertArrayHasKey('original_context', $loggedContext);
                    $this->assertEquals($context, $loggedContext['original_context']);
                    $this->assertEquals($errorCode, $loggedContext['uem_error_code']);
                    $this->assertEquals($case['type'] ?: 'error', $loggedContext['uem_error_type']);
                    return true;
                });

            // Act
            $this->handler->handle($errorCode, $errorConfig, $context, null);
        }
    }

    /**
     * 🎯 Test [handle]: Verifica che handle passi il messaggio e contesto corretti a ULM.
     * 🧪 Strategy: Controlla formato messaggio e struttura contesto (inclusi metadati).
     */
    #[Test]
    public function handle_passes_correct_message_and_context_to_ulm(): void
    {
        // Arrange
        $errorCode = 'STATIC_ERROR';
        $errorConfig = [
            'type' => 'error',
            'dev_message' => 'Static error occurred',
        ];
        $context = ['user_id' => 123, 'action' => 'upload'];

        // Mock
        $this->loggerMock->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $loggedContext) use ($errorCode, $context) {
                $this->assertEquals("[$errorCode] Static error occurred", $message);
                $this->assertArrayHasKey('original_context', $loggedContext);
                $this->assertEquals($context, $loggedContext['original_context']);
                $this->assertArrayHasKey('uem_error_code', $loggedContext);
                $this->assertArrayHasKey('uem_error_type', $loggedContext);
                $this->assertArrayHasKey('uem_blocking', $loggedContext);
                $this->assertArrayHasKey('logged_at', $loggedContext);
                return true;
            });

        // Act
        $this->handler->handle($errorCode, $errorConfig, $context, null);
    }

    /**
     * 🎯 Test [handle]: Verifica che handle includa dettagli eccezione nel contesto.
     * 🧪 Strategy: Passa un’eccezione e verifica che i suoi dettagli siano nel contesto ULM.
     */
    #[Test]
    public function handle_includes_exception_details_in_context(): void
    {
        // Arrange
        $errorCode = 'STATIC_ERROR';
        $errorConfig = ['type' => 'error'];
        $context = ['key' => 'value'];
        $exception = new \Exception('Test exception', 500);

        // Mock
        $this->loggerMock->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $loggedContext) use ($errorCode, $context, $exception) {
                $this->assertStringStartsWith("[$errorCode] ", $message);
                $this->assertArrayHasKey('original_context', $loggedContext);
                $this->assertEquals($context, $loggedContext['original_context']);
                $this->assertArrayHasKey('uem_error_code', $loggedContext);
                $this->assertArrayHasKey('exception', $loggedContext);
                $this->assertEquals(get_class($exception), $loggedContext['exception']['class']);
                $this->assertEquals($exception->getMessage(), $loggedContext['exception']['message']);
                $this->assertEquals($exception->getCode(), $loggedContext['exception']['code']);
                $this->assertNotEmpty($loggedContext['exception']['trace']);
                return true;
            });

        // Act
        $this->handler->handle($errorCode, $errorConfig, $context, $exception);
    }
}