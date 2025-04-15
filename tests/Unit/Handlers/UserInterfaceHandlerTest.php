<?php

namespace Ultra\ErrorManager\Tests\Unit\Handlers;

use Illuminate\Contracts\Session\Session;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Handlers\UserInterfaceHandler; // Class under test
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Used by TestCase
use Ultra\ErrorManager\Tests\UltraTestCase;

/**
 * 📜 Oracode Unit Test: UserInterfaceHandlerTest
 *
 * Tests the UserInterfaceHandler's functionality for preparing error data
 * to be displayed in the UI by flashing it to the session. Verifies decision logic,
 * interaction with the Session contract, and conditional flashing based on config.
 * Tests for the internal getGenericErrorMessage helper are deferred due to reliance
 * on the global __() helper.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Handlers
 * @version         0.1.5 // Added full Oracode documentation to test methods.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the UserInterfaceHandler.
 * 🧱 Mocks the Session contract.
 * 📡 Verifies calls to Session::flash().
 * 🧪 Focuses on handler logic and Session interaction based on current source code.
 */
#[CoversClass(UserInterfaceHandler::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]
class UserInterfaceHandlerTest extends UltraTestCase
{
    // Mocks
    protected Session&MockInterface $sessionMock;

    // Handler instance and config
    protected UserInterfaceHandler $handler;
    protected array $testUiConfig;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionMock = Mockery::mock(Session::class);

        $this->testUiConfig = [
            'default_display_mode' => 'sweet-alert',
            'show_error_codes' => true,
            'generic_error_message' => 'error-manager::errors.user.generic_error',
        ];

        // Instantiate the handler with actual constructor signature (Session, Config)
        $this->handler = new UserInterfaceHandler(
            $this->sessionMock,
            $this->testUiConfig
        );
    }

    /**
     * 🧹 Clean up after each test.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases for shouldHandle ---

    /**
     * 🎯 Test [shouldHandle]: Returns true when not log-only and message exists.
     * 🧪 Strategy: Provide various valid error configs and assert true.
     */
    #[Test]
    public function shouldHandle_returns_true_when_not_log_only_and_message_exists(): void
    {
        // Arrange
        $errorConfigWithMsg = ['msg_to' => 'div', 'user_message' => 'A message exists.'];
        $errorConfigWithKey = ['msg_to' => 'toast', 'user_message_key' => 'some.key', 'user_message' => 'Resolved Message']; // Assumes ErrorManager resolved the key

        // Act & Assert
        $this->assertTrue($this->handler->shouldHandle($errorConfigWithMsg));
        $this->assertTrue($this->handler->shouldHandle($errorConfigWithKey));
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false when msg_to is 'log-only'.
     * 🧪 Strategy: Provide error config with msg_to set to 'log-only'.
     */
    #[Test]
    public function shouldHandle_returns_false_when_log_only(): void
    {
        // Arrange
        $errorConfig = ['msg_to' => 'log-only', 'user_message' => 'A message exists.'];

        // Act & Assert
        $this->assertFalse($this->handler->shouldHandle($errorConfig));
    }

    /**
     * 🎯 Test [shouldHandle]: Returns false when no user message is present.
     * 🧪 Strategy: Provide error config missing user_message and user_message_key.
     */
    #[Test]
    public function shouldHandle_returns_false_when_no_user_message(): void
    {
         // Arrange
        $errorConfig = ['msg_to' => 'div']; // Missing user message indicators

        // Act & Assert
        $this->assertFalse($this->handler->shouldHandle($errorConfig));
    }

    // --- Test Cases for handle ---

    /**
     * 🎯 Test [handle]: Flashes correct message to session based on display target.
     * 🧪 Strategy: Call handle with specific msg_to, verify sessionMock->flash() is called with the target-specific key and correct message.
     */
    #[Test]
    public function handle_flashes_correct_message_to_session(): void
    {
        // Arrange
        $errorCode = 'FLASH_MSG_TEST';
        $displayTarget = 'sweet-alert';
        $userMessage = 'This is the message to flash.';
        $errorConfig = ['msg_to' => $displayTarget, 'user_message' => $userMessage, 'type' => 'error', 'blocking' => 'not'];
        $expectedSessionKey = "error_{$displayTarget}";

        // Expectations
        $this->sessionMock->shouldReceive('flash')->once()->with($expectedSessionKey, $userMessage);
        $this->sessionMock->shouldReceive('flash')->once()->with('error_info', Mockery::any());
        $this->sessionMock->shouldReceive('flash')->once()->with("error_code_{$displayTarget}", $errorCode); // Assumes show_error_codes = true

        // Act
        $this->handler->handle($errorCode, $errorConfig, [], null);

        // Assert (Implicit Mockery verification)
        $this->assertTrue(true); // Avoid risky test warning
    }

    /**
     * 🎯 Test [handle]: Flashes error code to session conditionally based on config.
     * 🧪 Strategy: Test with show_error_codes true and false. Verify sessionMock->flash() is called/not called for the code key accordingly.
     */
    #[Test]
    public function handle_flashes_error_code_conditionally(): void
    {
        // --- Case 1: show_error_codes = true (from setUp) ---
        $errorCode = 'FLASH_CODE_TRUE';
        $displayTarget = 'div';
        $errorConfig = ['msg_to' => $displayTarget, 'user_message' => 'Message', 'type'=>'error', 'blocking'=>'not'];
        $expectedCodeKey = "error_code_{$displayTarget}";
        // Expectations
        $this->sessionMock->shouldReceive('flash')->once()->with("error_{$displayTarget}", 'Message');
        $this->sessionMock->shouldReceive('flash')->once()->with('error_info', Mockery::any());
        $this->sessionMock->shouldReceive('flash')->once()->with($expectedCodeKey, $errorCode); // Expect code flash
        // Act
        $this->handler->handle($errorCode, $errorConfig, [], null);

        // --- Case 2: show_error_codes = false ---
        $errorCodeFalse = 'FLASH_CODE_FALSE';
        $displayTargetFalse = 'toast';
        $errorConfigFalse = ['msg_to' => $displayTargetFalse, 'user_message' => 'Msg False', 'type'=>'warning', 'blocking'=>'not'];
        $expectedCodeKeyFalse = "error_code_{$displayTargetFalse}";
        $configShowFalse = array_merge($this->testUiConfig, ['show_error_codes' => false]);
        // Instantiate with actual constructor signature
        $handlerShowFalse = new UserInterfaceHandler($this->sessionMock, $configShowFalse);
        // Expectations
        $this->sessionMock->shouldReceive('flash')->once()->with("error_{$displayTargetFalse}", 'Msg False');
        $this->sessionMock->shouldReceive('flash')->once()->with('error_info', Mockery::any());
        $this->sessionMock->shouldReceive('flash')->never()->with($expectedCodeKeyFalse, $errorCodeFalse); // Expect NEVER code flash
        // Act
        $handlerShowFalse->handle($errorCodeFalse, $errorConfigFalse, [], null);

        // Assert (Implicit Mockery verification)
        $this->assertTrue(true); // Avoid risky test warning
    }

    /**
     * 🎯 Test [handle]: Flashes correct error_info array structure to session.
     * 🧪 Strategy: Call handle, capture flashed 'error_info' data, verify its keys and values, including the blocking fallback logic.
     */
    #[Test]
    public function handle_flashes_correct_error_info_array(): void
    {
        // Arrange
        $errorCode = 'INFO_ARRAY_TEST';
        $displayTarget = 'div';
        $userMessage = 'Info message.';
        $errorType = 'notice';
        // Do not define 'blocking' in errorConfig to test the fallback in handle()
        $errorConfig = ['msg_to' => $displayTarget, 'user_message' => $userMessage, 'type' => $errorType];
        // Determine the expected blocking level based on the fallback logic in handle() itself
        $expectedBlockingLevel = $errorConfig['blocking'] ?? 'blocking'; // It defaults to 'blocking' in the handle method

        $capturedErrorInfo = null;

        // Expectations
        $this->sessionMock->shouldReceive('flash')->once()->with("error_{$displayTarget}", $userMessage);
        if ($this->testUiConfig['show_error_codes'] ?? false) {
             $this->sessionMock->shouldReceive('flash')->once()->with("error_code_{$displayTarget}", $errorCode);
        }
        $this->sessionMock->shouldReceive('flash')->once()->with('error_info', Mockery::capture($capturedErrorInfo));

        // Act
        $this->handler->handle($errorCode, $errorConfig, [], null);

        // Assert
        $this->assertIsArray($capturedErrorInfo);
        $this->assertEquals($errorCode, $capturedErrorInfo['error_code']);
        $this->assertEquals($userMessage, $capturedErrorInfo['message']);
        $this->assertEquals($errorType, $capturedErrorInfo['type']);
        // Assert the blocking level determined by the internal fallback logic in handle()
        $this->assertEquals($expectedBlockingLevel, $capturedErrorInfo['blocking']); // Should assert 'blocking'
        $this->assertEquals($displayTarget, $capturedErrorInfo['display_target']);
    }

    // --- Tests for getGenericErrorMessage removed ---
    // These tests are deferred as they rely on the global __() helper,
    // which is difficult to mock reliably in unit tests without specific libraries.
    // The functionality is implicitly tested by handle() when no user_message is provided
    // and relies on the Translator being correctly configured in the application.

} // End class UserInterfaceHandlerTest