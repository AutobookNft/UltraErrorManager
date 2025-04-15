<?php

namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Handlers\DatabaseLogHandler;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\ErrorManager\Tests\UltraTestCase;
use Ultra\UltraLogManager\UltraLogManager;

/**
 * 📜 Oracode Unit Test: DatabaseLogHandlerSanitizeTest
 *
 * Tests the sanitization functionality of DatabaseLogHandler, ensuring GDPR-compliant
 * redaction of sensitive data in context arrays. Focuses on the protected sanitizeContext
 * method, verifying behavior with various data types and nested structures.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.0 // Initial structure
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 DatabaseLogHandlerSanitizeTest – Oracoded Unit Tests for Sanitization
 *
 * Tests the DatabaseLogHandler's sanitizeContext method for GDPR compliance, ensuring
 * sensitive keys are redacted, nested arrays are handled recursively, and various data
 * types are processed correctly without database interaction.
 *
 * 🧱 Structure:
 * - Extends UltraTestCase without RefreshDatabase for isolated unit tests.
 * - Mocks UltraLogManager for potential logging operations.
 * - Uses reflection to test the protected sanitizeContext method.
 *
 * 📡 Communicates:
 * - With mocked UltraLogManager for debug logging.
 * - No database interaction, pure unit testing.
 *
 * 🧪 Testable:
 * - Uses Mockery for dependency isolation.
 * - Tests in isolation without database dependencies.
 */
#[CoversClass(DatabaseLogHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]
class DatabaseLogHandlerSanitizeTest extends UltraTestCase
{
    protected UltraLogManager&MockInterface $loggerMock;
    protected DatabaseLogHandler $handler;

    /**
     * 🎯 Test Setup: Initialize test environment.
     * 🧪 Strategy: Mock UltraLogManager, instantiate handler with config.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();
        $this->handler = new DatabaseLogHandler($this->loggerMock, [
            'enabled' => true,
            'sensitive_keys' => ['password', 'token'],
        ]);
    }

    /**
     * 🎯 Test [sanitizeContext]: Redacts sensitive keys.
     * 🧪 Strategy: Pass sensitive keys, verify redaction via reflection.
     */
    #[Test]
    public function sanitizeContext_redacts_sensitive_keys(): void
    {
        // Arrange
        $context = [
            'password' => 'secret123',
            'token' => 'abc123',
            'safe' => 'value',
        ];

        // Act
        $reflection = new \ReflectionMethod(DatabaseLogHandler::class, 'sanitizeContext');
        $sanitized = $reflection->invoke($this->handler, $context);

        // Assert
        $this->assertEquals([
            'password' => '[REDACTED]',
            'token' => '[REDACTED]',
            'safe' => 'value',
        ], $sanitized);
    }

    /**
     * 🎯 Test [sanitizeContext]: Handles nested arrays.
     * 🧪 Strategy: Pass nested context, verify recursive redaction via reflection.
     */
    #[Test]
    public function sanitizeContext_handles_nested_arrays(): void
    {
        // Arrange
        $context = [
            'safe' => 'value',
            'nested' => [
                'password' => 'secret123',
                'token' => 'abc123',
                'inner' => ['token' => 'xyz789'],
            ],
        ];

        // Act
        $reflection = new \ReflectionMethod(DatabaseLogHandler::class, 'sanitizeContext');
        $sanitized = $reflection->invoke($this->handler, $context);

        // Assert
        $this->assertEquals([
            'safe' => 'value',
            'nested' => [
                'password' => '[REDACTED]',
                'token' => '[REDACTED]',
                'inner' => ['token' => '[REDACTED]'],
            ],
        ], $sanitized);
    }

    /**
     * 🎯 Test [sanitizeContext]: Handles various data types.
     * 🧪 Strategy: Pass mixed types, verify handling via reflection.
     */
    #[Test]
    public function sanitizeContext_handles_various_data_types(): void
    {
        // Arrange
        $context = [
            'password' => 'secret123',
            'string' => 'test',
            'number' => 42,
            'bool' => true,
            'null' => null,
            'object' => new \stdClass(),
            'resource' => fopen('php://memory', 'r'),
        ];

        // Act
        $reflection = new \ReflectionMethod(DatabaseLogHandler::class, 'sanitizeContext');
        $sanitized = $reflection->invoke($this->handler, $context);

        // Assert
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('test', $sanitized['string']);
        $this->assertEquals(42, $sanitized['number']);
        $this->assertTrue($sanitized['bool']);
        $this->assertNull($sanitized['null']);
        $this->assertStringStartsWith('[Object:', $sanitized['object']);
        $this->assertStringStartsWith('[Resource:', $sanitized['resource']);
    }
}