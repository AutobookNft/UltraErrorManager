<?php

namespace Ultra\ErrorManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ultra\ErrorManager\ErrorManager registerHandler(\Ultra\ErrorManager\Interfaces\ErrorHandlerInterface $handler)
 * @method static array getHandlers()
 * @method static \Ultra\ErrorManager\ErrorManager defineError(string $errorCode, array $config)
 * @method static array|null getErrorConfig(string $errorCode)
 * @method static mixed handle(string $errorCode, array $context = [], \Throwable $exception = null)
 *
 * @see \Ultra\ErrorManager\ErrorManager
 */
class UltraError extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ultra.error-manager';
    }
}
