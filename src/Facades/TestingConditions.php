<?php

namespace Ultra\ErrorManager\Facades;

use Illuminate\Support\Facades\Facade;
use Ultra\ErrorManager\Services\TestingConditionsManager;

/**
 * Facade for TestingConditionsManager
 *
 * Used to simulate controlled test scenarios during development.
 * Supports toggling flags that alter behavior of critical services in test mode.
 */
class TestingConditions extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ultra.testing-conditions';
    }

    /**
     * Enable or disable testing globally.
     *
     * @param bool $enabled
     * @return TestingConditionsManager
     */
    public static function setTestingEnabled(bool $enabled): TestingConditionsManager
    {
        return static::getFacadeRoot()->setTestingEnabled($enabled);
    }

    /**
     * Check if testing mode is enabled.
     *
     * @return bool
     */
    public static function isTestingEnabled(): bool
    {
        return static::getFacadeRoot()->isTestingEnabled();
    }

    /**
     * Define or override a specific test condition.
     *
     * @param string $condition
     * @param bool $value
     * @return TestingConditionsManager
     */
    public static function setCondition(string $condition, bool $value): TestingConditionsManager
    {
        return static::getFacadeRoot()->setCondition($condition, $value);
    }

    /**
     * Check if a specific condition is currently active.
     *
     * @param string $condition
     * @return bool
     */
    public static function isTesting(string $condition): bool
    {
        return static::getFacadeRoot()->isTesting($condition);
    }

    /**
     * Retrieve all currently active conditions (i.e. set to `true`).
     *
     * @return array
     */
    public static function getActiveConditions(): array
    {
        return static::getFacadeRoot()->getActiveConditions();
    }

    /**
     * Reset all test conditions and disable testing flags.
     *
     * @return TestingConditionsManager
     */
    public static function resetAllConditions(): TestingConditionsManager
    {
        return static::getFacadeRoot()->resetAllConditions();
    }

    /**
     * Enable a test condition statically.
     *
     * Shortcut to `setCondition(..., true)`
     *
     * @param string $condition
     * @return void
     */
    public static function set(string $condition): void
    {
        static::getFacadeRoot()::set($condition);
    }

    /**
     * Disable a test condition statically.
     *
     * Shortcut to `setCondition(..., false)`
     *
     * @param string $condition
     * @return void
     */
    public static function clear(string $condition): void
    {
        static::getFacadeRoot()::clear($condition);
    }

    /**
     * Reset all test conditions statically.
     *
     * Shortcut to `resetAllConditions()`
     *
     * @return void
     */
    public static function reset(): void
    {
        static::getFacadeRoot()::reset();
    }
}
