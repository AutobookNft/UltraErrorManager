<?php

namespace Ultra\ErrorManager\Services;

/**
 * Testing Conditions Manager
 *
 * A singleton service that manages testing conditions for simulating
 * various scenarios, particularly error conditions during development.
 *
 * @package Ultra\ErrorManager\Services
 */
class TestingConditionsManager
{
    /**
     * Singleton instance
     *
     * @var TestingConditionsManager|null
     */
    private static $instance = null;

    /**
     * Testing conditions
     *
     * @var array
     */
    private $conditions = [];

    /**
     * Whether testing mode is enabled
     *
     * @var bool
     */
    private $testingEnabled = false;

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        // Set testing enabled based on environment
        $this->testingEnabled = app()->environment() !== 'production';
    }

    /**
     * Get singleton instance
     *
     * @return TestingConditionsManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set testing enabled/disabled
     *
     * @param bool $enabled
     * @return $this
     */
    public function setTestingEnabled(bool $enabled)
    {
        $this->testingEnabled = $enabled;
        return $this;
    }

    /**
     * Check if testing is enabled
     *
     * @return bool
     */
    public function isTestingEnabled()
    {
        return $this->testingEnabled;
    }

    /**
     * Set a testing condition
     *
     * @param string $condition Condition name
     * @param bool $value Condition value
     * @return $this
     */
    public function setCondition(string $condition, bool $value)
    {
        $this->conditions[$condition] = $value;
        return $this;
    }

    /**
     * Check if a specific condition is being tested
     *
     * @param string $condition Condition name
     * @return bool True if condition is being tested
     */
    public function isTesting(string $condition)
    {
        // If testing is disabled globally, always return false
        if (!$this->testingEnabled) {
            return false;
        }

        return $this->conditions[$condition] ?? false;
    }

    /**
     * Get all active testing conditions
     *
     * @return array
     */
    public function getActiveConditions()
    {
        return array_filter($this->conditions, function ($value) {
            return $value === true;
        });
    }

    /**
     * Reset all testing conditions
     *
     * @return $this
     */
    public function resetAllConditions()
    {
        $this->conditions = [];
        return $this;
    }

    /**
     * Enable a specific testing condition.
     *
     * This is a static shortcut to set a condition to `true`.
     * Useful in unit tests to simulate errors or custom flows.
     *
     * @param string $condition  The condition name (e.g. 'UCM_NOT_FOUND')
     * @return void
     */
    public static function set(string $condition): void
    {
        self::getInstance()->setCondition($condition, true);
    }

    /**
     * Disable a specific testing condition.
     *
     * This is a static shortcut to set a condition to `false`.
     * Useful to clean up after tests or reset a simulated state.
     *
     * @param string $condition  The condition name
     * @return void
     */
    public static function clear(string $condition): void
    {
        self::getInstance()->setCondition($condition, false);
    }

    /**
     * Reset all active testing conditions.
     *
     * This method clears all simulated states and disables testing flags.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::getInstance()->resetAllConditions();
    }

}
