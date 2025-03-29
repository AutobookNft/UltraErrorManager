<?php

namespace Ultra\ErrorManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ultra\ErrorManager\Services\TestingConditionsManager setTestingEnabled(bool $enabled)
 * @method static bool isTestingEnabled()
 * @method static \Ultra\ErrorManager\Services\TestingConditionsManager setCondition(string $condition, bool $value)
 * @method static bool isTesting(string $condition)
 * @method static array getActiveConditions()
 * @method static \Ultra\ErrorManager\Services\TestingConditionsManager resetAllConditions()
 *
 * @see \Ultra\ErrorManager\Services\TestingConditionsManager
 */
class TestingConditions extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ultra.testing-conditions';
    }
}
