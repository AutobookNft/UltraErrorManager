<?php

namespace Ultra\ErrorManager\Tests\Unit\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application; // Needed by TestingConditionsManager
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Ultra\ErrorManager\Http\Controllers\ErrorSimulationController; // Class under test
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Dependency
use Ultra\ErrorManager\Services\TestingConditionsManager; // Dependency (Real Instance)
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Used by TestCase setup
use Ultra\ErrorManager\Tests\UltraTestCase;

/**
 * 📜 Oracode Unit Test: ErrorSimulationControllerTest
 *
 * Tests the ErrorSimulationController API actions for managing error simulations.
 * Verifies correct interaction with ErrorManager, TestingConditionsManager, and ConfigRepository,
 * and validates the JSON responses returned.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Http\Controllers
 * @version         0.1.2 // Using assertEqualsCanonicalizing for array comparison.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the ErrorSimulationController.
 * 🧱 Mocks ErrorManagerInterface, ConfigRepository, Request, Application. Uses REAL TestingConditionsManager.
 * 📡 Verifies method calls on mocked services and the state of the real TestingConditionsManager. Asserts JSON responses.
 * 🧪 Focuses on controller action logic and dependency interactions.
 */
#[CoversClass(ErrorSimulationController::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)]
#[UsesClass(TestingConditionsManager::class)]
class ErrorSimulationControllerTest extends UltraTestCase
{
    // Mocks
    protected ErrorManagerInterface&MockInterface $errorManagerMock;
    protected TestingConditionsManager $testingConditionsManager; // Real instance
    protected ConfigRepository&MockInterface $configMock;
    protected Request&MockInterface $requestMock;
    protected Application&MockInterface $appMock; // For TestingConditionsManager

    // Controller instance
    protected ErrorSimulationController $controller;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->errorManagerMock = Mockery::mock(ErrorManagerInterface::class);
        $this->configMock = Mockery::mock(ConfigRepository::class);
        $this->requestMock = Mockery::mock(Request::class);
        $this->appMock = Mockery::mock(Application::class);
        $this->appMock->shouldIgnoreMissing();

        // Expect constructor call for Application mock
        $this->appMock->shouldReceive('environment')->once()->andReturn('testing');
        $this->testingConditionsManager = new TestingConditionsManager($this->appMock);
        $this->testingConditionsManager->setTestingEnabled(true);

        $this->controller = new ErrorSimulationController(
            $this->errorManagerMock,
            $this->testingConditionsManager,
            $this->configMock
        );
    }

    /**
     * 🧹 Clean up after each test.
     */
    protected function tearDown(): void
    {
        $this->testingConditionsManager->resetAllConditions();
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases ---

    /**
     * 🎯 Test [activateSimulation]: Successfully activates a simulation for a valid code.
     * 🧪 Strategy: Mock getErrorConfig to return config, call action, verify TestingConditionsManager state and JSON response.
     */
    #[Test]
    public function activateSimulation_activates_simulation_for_valid_code(): void
    {
        $errorCode = 'VALID_CODE';
        $this->errorManagerMock->shouldReceive('getErrorConfig')->once()->with($errorCode)->andReturn(['type' => 'error']);

        $response = $this->controller->activateSimulation($this->requestMock, $errorCode);

        $this->assertTrue($this->testingConditionsManager->isTesting($errorCode));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true, 'message' => "Error simulation activated for '{$errorCode}'.", 'errorCode' => $errorCode], $response->getData(true));
    }

    /**
     * 🎯 Test [activateSimulation]: Returns 404 if error code does not exist.
     * 🧪 Strategy: Mock getErrorConfig to return null, call action, verify 404 JSON response.
     */
    #[Test]
    public function activateSimulation_returns_404_if_error_code_not_found(): void
    {
        $errorCode = 'INVALID_CODE';
        $this->errorManagerMock->shouldReceive('getErrorConfig')->once()->with($errorCode)->andReturnNull();

        $response = $this->controller->activateSimulation($this->requestMock, $errorCode);

        $this->assertFalse($this->testingConditionsManager->isTesting($errorCode));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(['success' => false, 'message' => "Error code '{$errorCode}' does not exist in UEM configuration."], $response->getData(true));
    }

    /**
     * 🎯 Test [deactivateSimulation]: Successfully deactivates a simulation.
     * 🧪 Strategy: Set condition to true, call action, verify TestingConditionsManager state and JSON response.
     */
    #[Test]
    public function deactivateSimulation_deactivates_simulation(): void
    {
        $errorCode = 'CODE_TO_DEACTIVATE';
        $this->testingConditionsManager->setCondition($errorCode, true);
        $this->assertTrue($this->testingConditionsManager->isTesting($errorCode));

        $response = $this->controller->deactivateSimulation($this->requestMock, $errorCode);

        $this->assertFalse($this->testingConditionsManager->isTesting($errorCode));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true, 'message' => "Error simulation deactivated for '{$errorCode}'."], $response->getData(true));
    }

    /**
     * 🎯 Test [listActiveSimulations]: Returns correct list of active simulations.
     * 🧪 Strategy: Set conditions, call action, verify JSON response content using assertEqualsCanonicalizing.
     */
    #[Test]
    public function listActiveSimulations_returns_correct_list(): void
    {
        // Arrange
        $this->testingConditionsManager->setCondition('ACTIVE_1', true);
        $this->testingConditionsManager->setCondition('INACTIVE', false);
        $this->testingConditionsManager->setCondition('ACTIVE_2', true);
        $expectedActive = ['ACTIVE_1', 'ACTIVE_2'];

        // Act
        $response = $this->controller->listActiveSimulations($this->requestMock);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['activeSimulations']);
        // Use assertEqualsCanonicalizing for order-independent array comparison
        $this->assertEqualsCanonicalizing($expectedActive, $responseData['activeSimulations'], "Active simulations array mismatch.");
        $this->assertEquals(count($expectedActive), $responseData['count']);
    }

    /**
     * 🎯 Test [resetAllSimulations]: Resets all conditions and returns success.
     * 🧪 Strategy: Set conditions, call action, verify state is reset and JSON response is correct.
     */
    #[Test]
    public function resetAllSimulations_resets_conditions(): void
    {
        $this->testingConditionsManager->setCondition('COND_A', true);
        $this->testingConditionsManager->setCondition('COND_B', true);
        $this->assertNotEmpty($this->testingConditionsManager->getActiveConditions());

        $response = $this->controller->resetAllSimulations($this->requestMock);

        $this->assertEmpty($this->testingConditionsManager->getActiveConditions());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true, 'message' => "All error simulations have been reset."], $response->getData(true));
    }

    /**
     * 🎯 Test [listErrorCodes]: Returns all codes when no type filter applied.
     * 🧪 Strategy: Mock config->get, mock request->query to return null, verify JSON response using assertEqualsCanonicalizing.
     */
    #[Test]
    public function listErrorCodes_returns_all_codes_when_no_filter(): void
    {
        // Arrange
        $allCodes = ['CODE_A' => ['type' => 'error'], 'CODE_C' => ['type' => 'critical'], 'CODE_B' => ['type' => 'warning']];
        $expectedCodes = ['CODE_A', 'CODE_B', 'CODE_C']; // Expected keys
        $this->configMock->shouldReceive('get')->once()->with('error-manager.errors', [])->andReturn($allCodes);
        $this->requestMock->shouldReceive('query')->once()->with('type')->andReturnNull();

        // Act
        $response = $this->controller->listErrorCodes($this->requestMock);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['errorCodes']);
        // Use assertEqualsCanonicalizing
        $this->assertEqualsCanonicalizing($expectedCodes, $responseData['errorCodes'], "Error codes array mismatch.");
        $this->assertEquals(count($expectedCodes), $responseData['count']);
        $this->assertNull($responseData['filter']);
    }

    /**
     * 🎯 Test [listErrorCodes]: Returns filtered codes when type filter is applied.
     * 🧪 Strategy: Mock config->get, mock request->query, mock errorManager->getErrorConfig, verify JSON response using assertEqualsCanonicalizing.
     */
    #[Test]
    public function listErrorCodes_returns_filtered_codes_when_type_filter_applied(): void
    {
        // Arrange
        $filterType = 'critical';
        $allConfigs = [
            'CODE_A' => ['type' => 'error', 'message' => '...'], 'CODE_B' => ['type' => 'warning', 'message' => '...'],
            'CODE_C' => ['type' => 'critical', 'message' => '...'], 'CODE_D' => ['type' => 'critical', 'message' => '...'],
            'CODE_E' => ['type' => 'notice', 'message' => '...'],
        ];
        $expectedCodes = ['CODE_C', 'CODE_D']; // Only critical codes expected
        $this->configMock->shouldReceive('get')->once()->with('error-manager.errors', [])->andReturn($allConfigs);
        $this->requestMock->shouldReceive('query')->once()->with('type')->andReturn($filterType);

        // Mock errorManager->getErrorConfig calls needed by the controller's loop
        foreach ($allConfigs as $code => $config) {
            $this->errorManagerMock->shouldReceive('getErrorConfig')->with($code)->andReturn($config);
        }

        // Act
        $response = $this->controller->listErrorCodes($this->requestMock);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['errorCodes']);
        // Use assertEqualsCanonicalizing
        $this->assertEqualsCanonicalizing($expectedCodes, $responseData['errorCodes'], "Filtered error codes array mismatch.");
        $this->assertEquals(count($expectedCodes), $responseData['count']);
        $this->assertEquals(['type' => $filterType], $responseData['filter']);
    }

} // End class ErrorSimulationControllerTest