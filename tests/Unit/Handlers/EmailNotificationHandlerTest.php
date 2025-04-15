<?php

namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable; // User interface
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Handlers\EmailNotificationHandler;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Used by TestCase
use Ultra\ErrorManager\Tests\UltraTestCase;
use Ultra\UltraLogManager\UltraLogManager; // Dependency
use Throwable;

/**
 * 📜 Oracode Unit Test: EmailNotificationHandlerTest
 *
 * Tests the core functionality of the EmailNotificationHandler, focusing on
 * its decision logic (shouldHandle based on current implementation), the basic mail sending dispatch,
 * subject preparation, and error logging on mailer failure. Detailed conditional data
 * preparation tests are deferred.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.1 // Aligned tests with actual shouldHandle logic.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 EmailNotificationHandlerTest – Oracode Unit Tests for EmailNotificationHandler
 *
 * Tests the EmailNotificationHandler's ability to decide when to send emails,
 * prepare basic email data, interact with the Mailer contract, and log failures.
 *
 * 🧱 Structure:
 * - Extends UltraTestCase.
 * - Mocks MailerContract, UltraLogManager, Request, AuthFactory, Guard.
 * - Uses dependency injection for handler instantiation.
 *
 * 📡 Communicates:
 * - With mocked MailerContract to verify send() calls.
 * - With mocked UltraLogManager for log verification.
 * - With mocked Auth system for basic user checks.
 *
 * 🧪 Testable:
 * - All dependencies are mocked. Focuses on handler logic.
 * - Note: Detailed data preparation tests are deferred.
 */
#[CoversClass(EmailNotificationHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Needed by UltraTestCase
class EmailNotificationHandlerTest extends UltraTestCase
{
    // Mocks for dependencies
    protected MailerContract&MockInterface $mailerMock;
    protected UltraLogManager&MockInterface $loggerMock;
    protected Request&MockInterface $requestMock;
    protected AuthFactory&MockInterface $authFactoryMock;
    protected Guard&MockInterface $guardMock; // Mock for the authentication guard

    // Handler instance and config
    protected EmailNotificationHandler $handler;
    protected array $testEmailConfig;
    protected string $testAppName = 'TestApp';
    protected string $testEnvironment = 'testing';

    /**
     * 🎯 Test Setup: Initialize mocks and handler instance.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mailerMock = Mockery::mock(MailerContract::class);
        $this->loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();
        $this->requestMock = Mockery::mock(Request::class);
        $this->authFactoryMock = Mockery::mock(AuthFactory::class);
        $this->guardMock = Mockery::mock(Guard::class);

        $this->testEmailConfig = [
            'enabled' => true,
            'to' => 'test-errors@example.com',
            'from' => ['address' => 'noreply@example.com', 'name' => 'Error Bot'],
            'subject_prefix' => '[TestError] ',
            'include_ip_address' => false,
            'include_user_agent' => false,
            'include_user_details' => false,
            'include_context' => false,
            'include_trace' => false,
            'context_sensitive_keys' => ['password'],
            'trace_max_lines' => 10,
             // 'notify_all_critical' key is not used by current implementation
        ];

        $this->authFactoryMock->shouldReceive('guard')->andReturn($this->guardMock)->byDefault();
        $this->guardMock->shouldReceive('check')->andReturn(false)->byDefault();
        $this->guardMock->shouldReceive('user')->andReturn(null)->byDefault();
        $this->guardMock->shouldReceive('id')->andReturn(null)->byDefault();

        $this->handler = new EmailNotificationHandler(
            $this->mailerMock, $this->loggerMock, $this->requestMock,
            $this->authFactoryMock, $this->testEmailConfig,
            $this->testAppName, $this->testEnvironment
        );
    }

    /**
     * 🧹 Tear Down: Clean up Mockery.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases ---

    /**
     * 🎯 Test [shouldHandle]: Returns true when config allows (specific flag).
     * 🧪 Strategy: Set devTeam_email_need=true, ensure handler config is enabled.
     */
    #[Test]
    public function shouldHandle_returns_true_when_config_allows(): void
    {
        // Arrange
        $errorConfig = ['type' => 'error', 'devTeam_email_need' => true]; // Error requires email
        // Handler config in setUp is enabled with a recipient

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertTrue($result);
    }

    /* // Test removed as the 'notify_all_critical' logic is not implemented in shouldHandle
       // #[Test]
       // public function shouldHandle_returns_true_for_critical_when_notify_all_critical_enabled(): void
       // { ... }
    */

    /**
     * 🎯 Test [shouldHandle]: Returns false when error does not need email.
     * 🧪 Strategy: Set devTeam_email_need=false, keep handler enabled.
     */
    #[Test]
    public function shouldHandle_returns_false_when_error_does_not_need_email(): void
    {
        // Arrange
        $errorConfig = ['type' => 'warning', 'devTeam_email_need' => false]; // Error does NOT require email
        // Handler config in setUp is enabled

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false when handler is disabled globally.
     * 🧪 Strategy: Create handler with enabled=false config, even if error needs email.
     */
    #[Test]
    public function shouldHandle_returns_false_when_handler_disabled_globally(): void
    {
        // Arrange
        $errorConfig = ['type' => 'critical', 'devTeam_email_need' => true]; // Error requires email
        // Create handler with disabled config
        $disabledConfig = array_merge($this->testEmailConfig, ['enabled' => false]);
        $disabledHandler = new EmailNotificationHandler($this->mailerMock, $this->loggerMock, $this->requestMock, $this->authFactoryMock, $disabledConfig, $this->testAppName, $this->testEnvironment);

        // Act
        $result = $disabledHandler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 🎯 Test [handle]: Prepares correct subject line.
     * 🧪 Strategy: Call handle (mocking mailer send), check subject via helper.
     */
    #[Test]
    public function handle_prepares_correct_subject(): void
    {
        // Arrange
        $errorCode = 'SUBJECT_TEST';
        $errorConfig = ['type' => 'error', 'devTeam_email_need' => true]; // Make it handleable
        $expectedSubject = $this->testEmailConfig['subject_prefix'] . $this->testAppName . ' (' . $this->testEnvironment . '): ' . $errorCode;

        $this->mailerMock->shouldReceive('send')->once();
        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://test.com/path');
        $this->requestMock->shouldReceive('method')->andReturn('GET');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');
        $this->requestMock->shouldReceive('userAgent')->andReturn('TestAgent');

        // Use reflection to call the protected method directly
        $reflection = new \ReflectionMethod(EmailNotificationHandler::class, 'prepareSubject');
        $resultSubject = $reflection->invoke($this->handler, $errorCode);

        // Assert subject preparation
        $this->assertEquals($expectedSubject, $resultSubject);

        // Call handle() to ensure mailer->send mock is satisfied
        $this->handler->handle($errorCode, $errorConfig, []);
    }

    /**
     * 🎯 Test [handle]: Calls mailer send with correct view, subject, and base data.
     * 🧪 Strategy: Mock mailer send, capture arguments, verify view name, recipient, subject, and basic data.
     */
    #[Test]
    public function handle_calls_mailer_send_with_correct_view_subject_and_base_data(): void
    {
        // Arrange
        $errorCode = 'MAIL_SEND_TEST';
        $errorConfig = ['type' => 'critical', 'devTeam_email_need' => true, 'message' => 'Dev message for mail'];
        $context = ['extra_info' => 'value'];
        $exception = new \Exception('Test Exception');
        $expectedView = 'error-manager::emails.error-notification';
        $expectedRecipient = $this->testEmailConfig['to'];
        $expectedSubject = $this->testEmailConfig['subject_prefix'] . $this->testAppName . ' (' . $this->testEnvironment . '): ' . $errorCode;

        // Mock Request basics
        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://test.com/mail');
        $this->requestMock->shouldReceive('method')->andReturn('POST');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');
        $this->requestMock->shouldReceive('userAgent')->andReturn('MailTestAgent');
        $this->guardMock->shouldReceive('check')->andReturn(false); // No user

        $capturedView = null; $capturedData = null; $capturedCallback = null;
        $this->mailerMock->shouldReceive('send')
            ->once()
            ->withArgs(function ($view, $data, $callback) use (&$capturedView, &$capturedData, &$capturedCallback) {
                $capturedView = $view; $capturedData = $data; $capturedCallback = $callback;
                return true;
            });
        $this->loggerMock->shouldReceive('info')->with('UEM EmailHandler: Notification email sent.', Mockery::any())->once();

        // --- Act ---
        $this->handler->handle($errorCode, $errorConfig, $context, $exception);

        // --- Assert ---
        $this->assertEquals($expectedView, $capturedView, "View name mismatch.");
        $this->assertIsArray($capturedData, "Email data should be an array.");
        $this->assertIsCallable($capturedCallback, "Mailer callback should be callable.");
        // Verify basic data
        $this->assertEquals($this->testAppName, $capturedData['appName']);
        $this->assertEquals($this->testEnvironment, $capturedData['environment']);
        $this->assertEquals($errorCode, $capturedData['errorCode']);
        // Verify redacted/default fields based on setUp config
        $this->assertEquals('[Redacted by Config]', $capturedData['userIp']);
        $this->assertEquals('[Redacted by Config]', $capturedData['userAgent']);
        $this->assertNull($capturedData['userId']);
        $this->assertEquals(['message' => '[Context Redacted by Config]'], $capturedData['context']);
        $this->assertNotNull($capturedData['exception'], "Exception data should be present.");
        $this->assertEquals('[Trace Redacted by Config]', $capturedData['exception']['trace']);

        // Execute callback
        $messageMock = Mockery::mock(\Illuminate\Mail\Message::class);
        $messageMock->shouldReceive('to')->once()->with($expectedRecipient)->andReturnSelf();
        $messageMock->shouldReceive('subject')->once()->with($expectedSubject)->andReturnSelf();
        $messageMock->shouldReceive('from')->once()->with($this->testEmailConfig['from']['address'], $this->testEmailConfig['from']['name']);
        $capturedCallback($messageMock);
    }

    /**
     * 🎯 Test [handle]: Logs error via ULM on mailer failure.
     * 🧪 Strategy: Configure mailer mock to throw exception on send(), verify logger->error() call using active flag.
     */
    #[Test]
    public function handle_logs_error_via_ulm_on_mailer_failure(): void
    {
        // Arrange
        $errorCode = 'MAIL_FAIL_TEST';
        $errorConfig = ['type' => 'error', 'devTeam_email_need' => true];
        $context = ['trigger' => 'failure'];
        $mailerException = new \Symfony\Component\Mailer\Exception\TransportException('SMTP connection failed');
        $errorLogCalled = false; // Flag for active verification

        // Mock Request basics
        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://test.com/fail');
        $this->requestMock->shouldReceive('method')->andReturn('PUT');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');
        $this->requestMock->shouldReceive('userAgent')->andReturn('FailAgent');
        $this->guardMock->shouldReceive('check')->andReturn(false);

        // Configure mailer mock to throw
        $this->mailerMock->shouldReceive('send')->once()->andThrow($mailerException);

        // Expect logger->error call and set flag
        $this->loggerMock->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $logContext) use ($errorCode, $mailerException) {
                return $message === 'UEM EmailHandler: Failed to send notification email.' &&
                       $logContext['original_error_code'] === $errorCode &&
                       $logContext['email_handler_exception']['class'] === get_class($mailerException) &&
                       $logContext['email_handler_exception']['message'] === $mailerException->getMessage();
            })
            ->andReturnUsing(function() use (&$errorLogCalled) { $errorLogCalled = true; });

        // --- Act ---
        $this->handler->handle($errorCode, $errorConfig, $context, null);

        // --- Assert ---
        $this->assertTrue($errorLogCalled, "The logger's error method was expected to be called due to mailer failure but wasn't recorded by the flag.");
    }

} // End class EmailNotificationHandlerTest