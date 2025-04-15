<?php

namespace Ultra\ErrorManager\Tests\Unit\Services;

use Illuminate\Contracts\Foundation\Application; // Dependency Interface
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider; // For constructor test
use Ultra\ErrorManager\Services\TestingConditionsManager; // Class under test
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Used by TestCase
use Ultra\ErrorManager\Tests\UltraTestCase;
// Removed ULM import as it's not directly used or mocked here

/**
 * 📜 Oracode Unit Test: TestingConditionsManagerTest
 *
 * Tests the TestingConditionsManager service, which manages simulation states
 * for testing purposes. Verifies initialization based on environment, setting and
 * checking global/specific conditions, and resetting state. Uses a real instance
 * of the (final) manager and mocks its Application dependency.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Services
 * @version         0.1.1 // Corrected coverage annotations.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the TestingConditionsManager service.
 * 🧱 Mocks Application contract. Uses REAL TestingConditionsManager instance.
 * 📡 Verifies internal state changes via public getter methods.
 * 🧪 Focuses on the logic of managing testing flags.
 */
#[CoversClass(TestingConditionsManager::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Needed for TestCase setup
// Removed UsesClass for Application and ULM
class TestingConditionsManagerTest extends UltraTestCase
{
    // Mock for the single dependency
    protected Application&MockInterface $appMock;

    // Real instance of the class under test
    protected TestingConditionsManager $manager;

    /**
     * ⚙️ Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->appMock = Mockery::mock(Application::class);
        // Allow constructor call without explicit expectation in setUp
        $this->appMock->shouldIgnoreMissing();

        // Create the real instance, injecting the mock
        // We need to mock environment() call from constructor *before* creating instance
         $this->appMock->shouldReceive('environment')->once()->andReturn('testing'); // Provide expectation for constructor call
        $this->manager = new TestingConditionsManager($this->appMock);
        $this->manager->setTestingEnabled(true); // Ensure testing is globally enabled for most tests
    }

    /**
     * 🧹 Clean up after each test.
     */
    protected function tearDown(): void
    {
        // Optional: Reset state if needed, though new instance per test might be cleaner
        // $this->manager->resetAllConditions();
        // $this->manager->setTestingEnabled(true);
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases ---

    /**
     * 🎯 Test [constructor]: Sets initial testingEnabled state based on environment.
     * @dataProvider environmentInitialStateProvider
     */
    #[Test]
    #[DataProvider('environmentInitialStateProvider')]
    public function constructor_sets_enabled_based_on_environment(string $environment, bool $expectedEnabledState): void
    {
        // Arrange: Need a fresh mock for this specific test
        $appMockForTest = Mockery::mock(Application::class);
        $appMockForTest->shouldReceive('environment')->once()->andReturn($environment);

        // Act: Create a new instance for this specific environment
        $manager = new TestingConditionsManager($appMockForTest);

        // Assert
        $this->assertEquals($expectedEnabledState, $manager->isTestingEnabled());
    }

    /**
     * Data provider for constructor test.
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function environmentInitialStateProvider(): array
    {
        return [
            'production' => ['production', false],
            'local' => ['local', true],
            'testing' => ['testing', true],
            'development' => ['development', true],
            'staging' => ['staging', true],
        ];
    }

    /**
     * 🎯 Test [setTestingEnabled]: Overrides the environment-based default.
     */
    #[Test]
    public function setTestingEnabled_overrides_environment_default(): void
    {
        // Arrange: Manager created in setUp (env='testing', enabled=true initially)
        $this->assertTrue($this->manager->isTestingEnabled(), "Pre-condition: Should be enabled by default.");

        // Act 1: Disable manually
        $this->manager->setTestingEnabled(false);
        // Assert 1
        $this->assertFalse($this->manager->isTestingEnabled(), "Should be disabled after setTestingEnabled(false)");

        // Act 2: Re-enable manually
        $this->manager->setTestingEnabled(true);
        // Assert 2
        $this->assertTrue($this->manager->isTestingEnabled(), "Should be enabled after setTestingEnabled(true)");
    }

    /**
     * 🎯 Test [setCondition]: Sets a specific condition flag correctly.
     */
    #[Test]
    public function setCondition_sets_flag_correctly(): void
    {
        // Arrange
        $condition = 'UCM_MOCK_ACTIVE';
        $this->manager->setTestingEnabled(true); // Ensure global flag is on

        // Act 1: Set condition to true
        $this->manager->setCondition($condition, true);

        // Assert 1
        $this->assertTrue($this->manager->isTesting($condition));
        $this->assertEquals([$condition => true], $this->manager->getActiveConditions());

        // Act 2: Set condition to false
        $this->manager->setCondition($condition, false);

        // Assert 2
        $this->assertFalse($this->manager->isTesting($condition));
        $this->assertEquals([], $this->manager->getActiveConditions());
    }

    /**
     * 🎯 Test [isTesting]: Returns true only when globally enabled AND condition is true.
     */
    #[Test]
    public function isTesting_returns_true_when_enabled_globally_and_condition_set(): void
    {
        $condition = 'FEATURE_FLAG_X';

        // Case 1: Global=true, Condition=true -> Expect true
        $this->manager->setTestingEnabled(true);
        $this->manager->setCondition($condition, true);
        $this->assertTrue($this->manager->isTesting($condition), "Failed: Global=true, Condition=true");

        // Reset condition for next case (tearDown handles full reset)
        $this->manager->setCondition($condition, false);

        // Case 2: Global=false, Condition=true -> Expect false
        $this->manager->setTestingEnabled(false);
        $this->manager->setCondition($condition, true);
        $this->assertFalse($this->manager->isTesting($condition), "Failed: Global=false, Condition=true");

        // Case 3: Global=true, Condition=false -> Expect false
        $this->manager->setTestingEnabled(true);
        $this->manager->setCondition($condition, false);
        $this->assertFalse($this->manager->isTesting($condition), "Failed: Global=true, Condition=false");

        // Case 4: Global=false, Condition=false -> Expect false
        $this->manager->setTestingEnabled(false);
        $this->manager->setCondition($condition, false);
        $this->assertFalse($this->manager->isTesting($condition), "Failed: Global=false, Condition=false");
    }

    /**
     * 🎯 Test [isTesting]: Returns false when globally disabled, even if condition is set.
     */
    #[Test]
    public function isTesting_returns_false_when_disabled_globally(): void
    {
        $condition = 'SHOULD_BE_IGNORED';
        $this->manager->setCondition($condition, true);
        $this->manager->setTestingEnabled(false); // Disable globally AFTER setting condition

        $this->assertFalse($this->manager->isTesting($condition));
    }

    /**
     * 🎯 Test [isTesting]: Returns false when condition is not set, even if globally enabled.
     */
    #[Test]
    public function isTesting_returns_false_when_condition_not_set(): void
    {
        $condition = 'NEVER_SET_CONDITION';
        $this->manager->setTestingEnabled(true); // Enable globally

        $this->assertFalse($this->manager->isTesting($condition)); // Condition was never set
    }

    /**
     * 🎯 Test [getActiveConditions]: Returns only conditions set to true.
     */
    #[Test]
    public function getActiveConditions_returns_only_true_conditions(): void
    {
        $this->manager->setCondition('COND_TRUE_1', true);
        $this->manager->setCondition('COND_FALSE_1', false);
        $this->manager->setCondition('COND_TRUE_2', true);

        $active = $this->manager->getActiveConditions();

        $this->assertEquals(['COND_TRUE_1' => true, 'COND_TRUE_2' => true], $active);
        $this->assertCount(2, $active);
    }

    /**
     * 🎯 Test [resetAllConditions]: Clears specific condition flags.
     */
    #[Test]
    public function resetAllConditions_clears_specific_conditions(): void
    {
        $this->manager->setTestingEnabled(true);
        $this->manager->setCondition('COND_A', true);
        $this->manager->setCondition('COND_B', true);

        $this->manager->resetAllConditions(); // Act

        $this->assertFalse($this->manager->isTesting('COND_A'));
        $this->assertEmpty($this->manager->getActiveConditions());
    }

    /**
     * 🎯 Test [resetAllConditions]: Does not change the global testingEnabled flag.
     */
    #[Test]
    public function resetAllConditions_does_not_change_global_enabled_flag(): void
    {
        // Case 1: Enabled globally
        $this->manager->setTestingEnabled(true);
        $this->manager->setCondition('COND_X', true);
        $this->manager->resetAllConditions();
        $this->assertTrue($this->manager->isTestingEnabled(), "Global flag should remain true.");

        // Case 2: Disabled globally
        $this->manager->setTestingEnabled(false);
        $this->manager->setCondition('COND_Y', true);
        $this->manager->resetAllConditions();
        $this->assertFalse($this->manager->isTestingEnabled(), "Global flag should remain false.");
    }

    // NOTE: Test for static methods is deferred.

} // End class TestingConditionsManagerTest