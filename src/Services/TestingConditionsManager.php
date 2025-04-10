<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Services;

use Illuminate\Contracts\Foundation\Application; // Dependency for environment check

/**
 * 🎯 TestingConditionsManager – Oracoded Service for Test State Simulation
 *
 * Manages testing conditions primarily for simulating error scenarios during
 * development or automated testing. Designed to be registered as a singleton
 * in the service container, replacing the previous static singleton pattern.
 * Allows enabling/disabling testing globally and setting/checking specific
 * condition flags (e.g., 'UCM_NOT_FOUND'). Provides static shortcuts for
 * convenience, mainly intended for use via the `TestingConditions` Facade or
 * directly within test suites.
 *
 * 🧱 Structure:
 * - Holds an array of active conditions (`$conditions`).
 * - Holds a flag indicating if testing mode is globally enabled (`$testingEnabled`).
 * - Requires Application contract injected for environment checking.
 * - Provides instance methods for managing conditions.
 * - Provides static shortcut methods that resolve the singleton instance from the container.
 *
 * 📡 Communicates:
 * - Reads application environment via injected Application contract.
 * - Provides state information to callers (e.g., ErrorSimulationHandler).
 * - Static methods interact with the Laravel service container (`app()`).
 *
 * 🧪 Testable:
 * - Core logic relies on injected Application contract (mockable).
 * - State (`$conditions`, `$testingEnabled`) is managed internally and accessible/modifiable via methods.
 * - Static methods require container setup for testing or direct instance manipulation.
 */
final class TestingConditionsManager // Mark as final, remove static singleton props
{
    /**
     * 🧱 @property Testing conditions store.
     * Associative array where key is condition name and value is boolean state.
     * @var array<string, bool>
     */
    private array $conditions = [];

    /**
     * 🧱 @property Global testing enabled flag.
     * Determined by environment on construction, can be overridden.
     * @var bool
     */
    private bool $testingEnabled;

    /**
     * 🧱 @dependency Application instance.
     * Used to check the environment.
     * @var Application
     */
    protected readonly Application $app; // Use readonly property

    /**
     * 🎯 Constructor: Initializes with Application dependency and sets initial state.
     * Designed to be called by the Service Container when registered as a singleton.
     *
     * @param Application $app Laravel Application instance.
     */
    public function __construct(Application $app) // Public constructor for DI
    {
        $this->app = $app;
        // Set initial testing state based on environment using injected app
        $this->testingEnabled = $this->app->environment() !== 'production';
    }

    /**
     * 🔧 Set testing mode enabled/disabled globally.
     * Overrides the environment-based default.
     *
     * @param bool $enabled True to enable, false to disable.
     * @return self Returns instance for fluent interface.
     */
    public function setTestingEnabled(bool $enabled): self // Changed return type to self
    {
        $this->testingEnabled = $enabled;
        return $this;
    }

    /**
     * 🩺 Check if testing mode is currently globally enabled.
     *
     * @return bool True if testing is enabled.
     */
    public function isTestingEnabled(): bool // Keep return type hint
    {
        return $this->testingEnabled;
    }

    /**
     * 🔧 Set a specific testing condition flag.
     *
     * @param string $condition Condition name (e.g., 'UCM_NOT_FOUND').
     * @param bool $value State to set (true = active, false = inactive).
     * @return self Returns instance for fluent interface.
     */
    public function setCondition(string $condition, bool $value): self // Changed return type to self
    {
        $this->conditions[$condition] = $value;
        return $this;
    }

    /**
     * 🩺 Check if a specific condition flag is active.
     * Considers both the specific flag and the global `$testingEnabled` state.
     *
     * @param string $condition Condition name to check.
     * @return bool True if the condition is active AND testing is globally enabled.
     */
    public function isTesting(string $condition): bool // Keep return type hint
    {
        // Must be globally enabled AND the specific condition must be true
        return $this->testingEnabled && ($this->conditions[$condition] ?? false);
    }

    /**
     * 📡 Get all currently active testing conditions.
     * Returns only conditions explicitly set to `true`.
     *
     * @return array<string, true> Associative array of active condition names.
     */
    public function getActiveConditions(): array // Keep return type hint
    {
        // Filter only conditions set to true
        return array_filter($this->conditions, fn ($value) => $value === true);
    }

    /**
     * 🧹 Reset all specific testing condition flags to inactive (false).
     * Does NOT change the global `$testingEnabled` state.
     *
     * @return self Returns instance for fluent interface.
     */
    public function resetAllConditions(): self // Changed return type to self
    {
        $this->conditions = [];
        return $this;
    }

    // --- Static Shortcut Methods ---
    // Provided for convenience (e.g., Facade, test setup).
    // These resolve the singleton instance from the container.

    /**
     * ⚡️ Static Shortcut: Enable a specific testing condition.
     * Resolves the singleton instance and calls `setCondition(..., true)`.
     *
     * @param string $condition The condition name (e.g. 'UCM_NOT_FOUND').
     * @return void
     */
    public static function set(string $condition): void
    {
        // Resolve instance from container and call instance method
        app(self::class)->setCondition($condition, true);
    }

    /**
     * ⚡️ Static Shortcut: Disable a specific testing condition.
     * Resolves the singleton instance and calls `setCondition(..., false)`.
     *
     * @param string $condition The condition name.
     * @return void
     */
    public static function clear(string $condition): void
    {
         // Resolve instance from container and call instance method
        app(self::class)->setCondition($condition, false);
    }

    /**
     * ⚡️ Static Shortcut: Reset all specific testing conditions.
     * Resolves the singleton instance and calls `resetAllConditions()`.
     *
     * @return void
     */
    public static function reset(): void
    {
         // Resolve instance from container and call instance method
        app(self::class)->resetAllConditions();
    }
}