<?php

namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Http\Client\PendingRequest; // For mocking the HTTP client chain
use Illuminate\Http\Client\Response; // For mocking the response
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Handlers\SlackNotificationHandler;
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\ErrorManager\Tests\UltraTestCase;
use Ultra\UltraLogManager\UltraLogManager;
use Throwable;

/**
 * 📜 Oracode Unit Test: SlackNotificationHandlerTest
 *
 * Tests the core functionality of the SlackNotificationHandler, focusing on
 * its decision logic (shouldHandle), dispatching HTTP requests to the Slack webhook,
 * preparing a basic payload structure, and logging handler errors or Slack API errors.
 * Detailed conditional payload generation based on config flags is deferred.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.0 // Initial simplified version
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 SlackNotificationHandlerTest – Oracode Unit Tests for SlackNotificationHandler
 *
 * Verifies the handler correctly decides when to send notifications, interacts with the
 * HTTP client factory, sends a structurally valid payload, and logs outcomes.
 *
 * 🧱 Structure:
 * - Extends UltraTestCase.
 * - Mocks HttpClientFactory, PendingRequest, Response, UltraLogManager, Request.
 * - Uses dependency injection.
 *
 * 📡 Communicates:
 * - With mocked HTTP Client to verify POST requests.
 * - With mocked UltraLogManager for log verification.
 *
 * 🧪 Testable:
 * - All dependencies are mocked.
 * - Focuses on core dispatch and error logging logic.
 * - Note: Detailed payload formatting tests are deferred.
 */
#[CoversClass(SlackNotificationHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Needed by UltraTestCase
class SlackNotificationHandlerTest extends UltraTestCase
{
    // Mocks
    protected HttpClientFactory&MockInterface $httpFactoryMock;
    protected PendingRequest&MockInterface $httpClientMock; // Mock the PendingRequest returned by factory
    protected UltraLogManager&MockInterface $loggerMock;
    protected Request&MockInterface $requestMock;

    // Handler instance and config
    protected SlackNotificationHandler $handler;
    protected array $testSlackConfig;
    protected string $testAppName = 'TestSlackApp';
    protected string $testEnvironment = 'slack_test';
    protected string $testWebhookUrl = 'https://hooks.slack.com/services/TEST/WEBHOOK/URL';

    /**
     * 🎯 Test Setup: Initialize mocks and handler instance.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->httpFactoryMock = Mockery::mock(HttpClientFactory::class);
        $this->httpClientMock = Mockery::mock(PendingRequest::class); // Mock the PendingRequest
        $this->loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();
        $this->requestMock = Mockery::mock(Request::class);

        // Configure factory to return the pending request mock
        $this->httpFactoryMock->shouldReceive('timeout')->andReturn($this->httpClientMock)->byDefault();

        $this->testSlackConfig = [
            'enabled' => true,
            'webhook_url' => $this->testWebhookUrl,
            'channel' => '#test-alerts',
            'username' => 'TestBot',
            'icon_emoji' => ':test:',
            // Default to minimal data inclusion for core tests
            'include_ip_address' => false,
            'include_user_details' => false,
            'include_context' => false,
            'include_trace_snippet' => false,
            'notify_all_critical' => false, // Test specific 'notify_slack' flag
            'context_sensitive_keys' => ['password'],
            'trace_max_lines' => 5,
            'context_max_length' => 500,
        ];

        // Instantiate the handler
        $this->handler = new SlackNotificationHandler(
            $this->httpFactoryMock,
            $this->loggerMock,
            $this->requestMock,
            $this->testSlackConfig,
            $this->testAppName,
            $this->testEnvironment
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
     * 🧪 Strategy: Set notify_slack=true, ensure handler config is enabled with URL.
     */
    #[Test]
    public function shouldHandle_returns_true_when_config_allows(): void
    {
        // Arrange
        $errorConfig = ['type' => 'error', 'notify_slack' => true]; // Error requires slack
        // Handler config in setUp is enabled with webhook

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertTrue($result);
    }

     /**
     * 🎯 Test [shouldHandle]: Returns true for critical when notify_all_critical enabled.
     * 🧪 Strategy: Set type=critical, enable notify_all_critical in handler config.
     */
    #[Test]
    public function shouldHandle_returns_true_for_critical_when_notify_all_critical_enabled(): void
    {
        // Arrange
        $errorConfig = ['type' => 'critical', 'notify_slack' => false]; // Flag is false, but type is critical
        $criticalConfig = array_merge($this->testSlackConfig, ['notify_all_critical' => true]);
        $criticalHandler = new SlackNotificationHandler($this->httpFactoryMock, $this->loggerMock, $this->requestMock, $criticalConfig, $this->testAppName, $this->testEnvironment);

        // Act
        $result = $criticalHandler->shouldHandle($errorConfig);

        // Assert
        $this->assertTrue($result);
    }


    /**
     * 🎯 Test [shouldHandle]: Returns false when error does not need Slack notification.
     * 🧪 Strategy: Set notify_slack=false, keep handler enabled.
     */
    #[Test]
    public function shouldHandle_returns_false_when_error_does_not_need_slack(): void
    {
        // Arrange
        $errorConfig = ['type' => 'warning', 'notify_slack' => false]; // Error does NOT require slack
        // Handler config in setUp is enabled

        // Act
        $result = $this->handler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false when handler is disabled globally.
     * 🧪 Strategy: Create handler with enabled=false config.
     */
    #[Test]
    public function shouldHandle_returns_false_when_handler_disabled_globally(): void
    {
        // Arrange
        $errorConfig = ['type' => 'critical', 'notify_slack' => true]; // Error requires slack
        $disabledConfig = array_merge($this->testSlackConfig, ['enabled' => false]);
        $disabledHandler = new SlackNotificationHandler($this->httpFactoryMock, $this->loggerMock, $this->requestMock, $disabledConfig, $this->testAppName, $this->testEnvironment);

        // Act
        $result = $disabledHandler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false when webhook URL is missing.
     * 🧪 Strategy: Create handler with missing webhook_url config.
     */
    #[Test]
    public function shouldHandle_returns_false_when_webhook_url_is_missing(): void
    {
        // Arrange
        $errorConfig = ['type' => 'critical', 'notify_slack' => true];
        $noWebhookConfig = array_merge($this->testSlackConfig, ['webhook_url' => null]); // Remove webhook
        $noWebhookHandler = new SlackNotificationHandler($this->httpFactoryMock, $this->loggerMock, $this->requestMock, $noWebhookConfig, $this->testAppName, $this->testEnvironment);

        // Act
        $result = $noWebhookHandler->shouldHandle($errorConfig);

        // Assert
        $this->assertFalse($result);
    }

        /**
     * 🎯 Test [handle]: Calls httpClient post with correct URL and base payload structure.
     * 🧪 Strategy: Mock HTTP client, verify POST call arguments (URL, basic payload structure).
     */
    #[Test]
    public function handle_calls_httpClient_post_with_correct_url_and_base_payload(): void
    {
        // --- Arrange ---
        $errorCode = 'SLACK_SEND_TEST';
        $errorConfig = ['type' => 'error', 'notify_slack' => true, 'message' => 'Slack test dev message'];
        $context = ['user_id' => 42];
        $expectedUrl = $this->testWebhookUrl;

        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://slack.test/action');
        $this->requestMock->shouldReceive('method')->andReturn('POST');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('successful')->once()->andReturn(true);

        $this->httpClientMock->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $payload) use ($expectedUrl, $errorCode) {
                $urlMatch = ($url === $expectedUrl);
                $payloadValid = is_array($payload) &&
                                isset($payload['attachments'][0]['blocks']) && is_array($payload['attachments'][0]['blocks']) &&
                                isset($payload['username']) && isset($payload['icon_emoji']) &&
                                isset($payload['attachments'][0]['blocks'][0]['text']['text']) &&
                                str_contains($payload['attachments'][0]['blocks'][0]['text']['text'], $errorCode);
                return $urlMatch && $payloadValid;
            })
            ->andReturn($responseMock);

        $this->loggerMock->shouldReceive('info')
            ->once()
            ->with('UEM SlackHandler: Notification sent successfully.', ['errorCode' => $errorCode]);

        // --- Act ---
        $this->handler->handle($errorCode, $errorConfig, $context, null);

        // --- Assert ---
        // Mockery implicitly verifies the expectations above.
        // Add a trivial assertion to remove the "risky" warning.
        $this->assertTrue(true, "Test executed to completion."); // Or assert anything that makes sense here.
    }

    /**
     * 🎯 Test [handle]: Logs error via ULM on HTTP client failure (exception).
     * 🧪 Strategy: Configure HTTP client mock to throw exception on post(), verify logger->error() call.
     */
    #[Test]
    public function handle_logs_error_via_ulm_on_http_client_failure(): void
    {
        // Arrange
        $errorCode = 'SLACK_HTTP_FAIL';
        $errorConfig = ['type' => 'critical', 'notify_slack' => true];
        $context = [];
        $httpException = new \Illuminate\Http\Client\ConnectionException('Could not connect to Slack');

        // Flag for active verification
        $errorLogCalled = false;

        // Mock Request basics
        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://slack.test/fail');
        $this->requestMock->shouldReceive('method')->andReturn('GET');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');

        // Configure httpClient mock to throw exception
        $this->httpClientMock->shouldReceive('post')
            ->once()
            ->with($this->testWebhookUrl, Mockery::any())
            ->andThrow($httpException);

        // Expect logger->error call
        $this->loggerMock->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $logContext) use ($errorCode, $httpException) {
                return $message === 'UEM SlackHandler: Exception during Slack notification preparation or sending.' &&
                       isset($logContext['original_error_code']) && $logContext['original_error_code'] === $errorCode &&
                       isset($logContext['slack_handler_exception']['class']) && $logContext['slack_handler_exception']['class'] === get_class($httpException);
            })
             ->andReturnUsing(function() use (&$errorLogCalled) { $errorLogCalled = true; });

        // --- Act ---
        $this->handler->handle($errorCode, $errorConfig, $context, null);

        // --- Assert ---
        $this->assertTrue($errorLogCalled, "Logger error method not called on HTTP client failure.");
    }

    /**
     * 🎯 Test [handle]: Logs warning via ULM on Slack API error response (non-successful).
     * 🧪 Strategy: Configure HTTP client mock to return a non-successful Response, verify logger->warning() call.
     */
    #[Test]
    public function handle_logs_warning_via_ulm_on_slack_api_error_response(): void
    {
        // Arrange
        $errorCode = 'SLACK_API_ERROR';
        $errorConfig = ['type' => 'warning', 'notify_slack' => true];
        $context = [];
        $slackErrorBody = 'invalid_payload';
        $slackStatusCode = 400;

        // Flag for active verification
        $warningLogCalled = false;

        // Mock Request basics
        $this->requestMock->shouldReceive('fullUrl')->andReturn('http://slack.test/api-error');
        $this->requestMock->shouldReceive('method')->andReturn('POST');
        $this->requestMock->shouldReceive('ip')->andReturn('127.0.0.1');

        // Mock non-successful HTTP response
        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('successful')->once()->andReturn(false);
        $responseMock->shouldReceive('status')->once()->andReturn($slackStatusCode);
        $responseMock->shouldReceive('body')->once()->andReturn($slackErrorBody);

        // Expect the POST call and return the error response
        $this->httpClientMock->shouldReceive('post')
            ->once()
            ->with($this->testWebhookUrl, Mockery::any())
            ->andReturn($responseMock);

        // Expect logger->warning call
        $this->loggerMock->shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $logContext) use ($errorCode, $slackStatusCode, $slackErrorBody) {
                return $message === 'UEM SlackHandler: Failed to send notification.' &&
                       isset($logContext['errorCode']) && $logContext['errorCode'] === $errorCode &&
                       isset($logContext['slack_status']) && $logContext['slack_status'] === $slackStatusCode &&
                       isset($logContext['slack_response_body']) && $logContext['slack_response_body'] === $slackErrorBody;
            })
            ->andReturnUsing(function() use (&$warningLogCalled) { $warningLogCalled = true; });


        // --- Act ---
        $this->handler->handle($errorCode, $errorConfig, $context, null);

        // --- Assert ---
        $this->assertTrue($warningLogCalled, "Logger warning method not called on Slack API error response.");
    }

    // NOTE: Deferred tests for detailed payload structure and conditional fields:
    // - prepareSlackMessage_builds_correct_blocks_structure()
    // - prepareSlackMessage_includes_fields_conditionally()
    // - prepareSlackMessage_includes_exception_conditionally()
    // - prepareSlackMessage_includes_sanitized_limited_context_conditionally()

} // End class SlackNotificationHandlerTest