<?php

namespace Ultra\ErrorManager\Interfaces;

use Throwable;

/**
 * ErrorHandlerInterface – Oracoded Contract for Reactive Error Logic
 *
 * 🎯 Defines the structure of a runtime-pluggable error handler.
 * 📡 Used by UltraErrorManager to determine whether and how
 *     a registered handler should react to a given error.
 * 🔐 Allows conditional injection of side-effects, alerts, metrics, etc.
 *
 * @package Ultra\ErrorManager\Interfaces
 */
interface ErrorHandlerInterface
{
    /**
     * 🧠 Determine if this handler is applicable to the given error
     *
     * Evaluates the error configuration to decide whether this handler
     * should process the event. Often based on type, code, blocking level, etc.
     *
     * 🧱 Filter logic for dispatch stage
     * 🧪 Used inside dispatchHandlers()
     *
     * @param array $errorConfig Resolved error configuration
     * @return bool Whether this handler should execute
     */
    public function shouldHandle(array $errorConfig): bool;

    /**
     * 🔁 Execute side-effects or transformations for this error
     *
     * Performs any operation associated with this error, such as:
     * - Logging to external systems
     * - Sending alerts
     * - Triggering compensating actions
     *
     * 🧱 Core behavior logic
     * 🧪 Safe to call multiple times (idempotent recommended)
     *
     * @param string $errorCode The symbolic error code
     * @param array $errorConfig The configuration metadata for the error
     * @param array $context Contextual data available for substitution/logs
     * @param Throwable|null $exception Optional original throwable
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void;
}
