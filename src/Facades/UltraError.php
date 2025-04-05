<?php

namespace Ultra\ErrorManager\Facades;

use Illuminate\Support\Facades\Facade;
use Ultra\ErrorManager\ErrorManager;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * Facade for UltraErrorManager
 *
 * This facade provides a strongly-typed gateway to the internal ErrorManager,
 * exposing a safe and testable API to handle application errors.
 *
 * @method static ErrorManager registerHandler(ErrorHandlerInterface $handler)
 * @method static array getHandlers()
 * @method static ErrorManager defineError(string $errorCode, array $config)
 * @method static array|null getErrorConfig(string $errorCode)
 */
class UltraError extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ultra.error-manager';
    }

    /**
     * Handle an error based on its code, context and optional exception.
     *
     * Delegates the handling to the underlying ErrorManager.
     * If $throw is true, an UltraErrorException will be thrown.
     *
     * @param string $errorCode   Unique code identifying the error
     * @param array $context      Optional contextual information for logging / substitution
     * @param \Throwable|null $exception  Optional original exception
     * @param bool $throw         Whether to throw the exception or return a response
     * @return mixed              Response object or throws UltraErrorException
     *
     * @throws \Ultra\ErrorManager\Exceptions\UltraErrorException
     */
    public static function handle(string $errorCode, array $context = [], \Throwable $exception = null, bool $throw = false): mixed
    {
        return static::getFacadeRoot()->handle($errorCode, $context, $exception, $throw);
    }

    /**
     * Register a custom error handler at runtime.
     *
     * @param ErrorHandlerInterface $handler
     * @return ErrorManager
     */
    public static function registerHandler(ErrorHandlerInterface $handler): ErrorManager
    {
        return static::getFacadeRoot()->registerHandler($handler);
    }

    /**
     * Retrieve all registered error handlers.
     *
     * @return array
     */
    public static function getHandlers(): array
    {
        return static::getFacadeRoot()->getHandlers();
    }

    /**
     * Define a custom error configuration dynamically.
     *
     * @param string $errorCode
     * @param array $config
     * @return ErrorManager
     */
    public static function defineError(string $errorCode, array $config): ErrorManager
    {
        return static::getFacadeRoot()->defineError($errorCode, $config);
    }

    /**
     * Retrieve the configuration for a given error code.
     *
     * @param string $errorCode
     * @return array|null
     */
    public static function getErrorConfig(string $errorCode): ?array
    {
        return static::getFacadeRoot()->getErrorConfig($errorCode);
    }
}
