<?php

namespace Ultra\ErrorManager\Interfaces;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * ErrorManagerInterface – Oracoded Contract for Error Handling
 *
 * 🎯 Defines the behavior of any Ultra-compatible error manager.
 * 🧱 Used by UltraErrorManager and its facade UltraError.
 * 🧪 Supports static, dynamic, and fallback-defined error flows.
 * 🔐 Allows interception and mutation of error handling strategy.
 */
interface ErrorManagerInterface
{
    /**
     * 🧬 Handle the error lifecycle for the given code
     *
     * Resolves config, dispatches handlers, and either returns a structured
     * response or throws an UltraErrorException, based on $throw.
     *
     * @param string $errorCode
     * @param array $context
     * @param ?\Throwable|null $exception
     * @param bool $throw
     * @return JsonResponse|RedirectResponse
     */
    public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): JsonResponse|RedirectResponse;

    /**
     * 🧱 Register a custom error handler
     *
     * @param ErrorHandlerInterface $handler
     * @return self
     */
    public function registerHandler(ErrorHandlerInterface $handler): self;

    /**
     * 🧠 Retrieve all registered error handlers
     *
     * @return array<int, ErrorHandlerInterface>
     */
    public function getHandlers(): array;

    /**
     * 🔧 Define a dynamic error configuration
     *
     * @param string $errorCode
     * @param array $config
     * @return self
     */
    public function defineError(string $errorCode, array $config): self;

    /**
     * 📡 Retrieve configuration for the given error code
     *
     * @param string $errorCode
     * @return array|null
     */
    public function getErrorConfig(string $errorCode): ?array;
}
