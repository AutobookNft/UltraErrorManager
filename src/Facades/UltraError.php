<?php

namespace Ultra\ErrorManager\Facades;

use Illuminate\Support\Facades\Facade;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ultra\ErrorManager\ErrorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;

/**
 * UltraError – Facade per gestione errori Oracoded
 *
 * 🧷 Fallback a comportamento sicuro in ambienti di test
 * 🧪 Nessuna eccezione se il Facade root non è definito
 * 📡 Compatibile con orchestrazione Ultra e ambienti CLI
 */
class UltraError extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ultra.error-manager';
    }

    /**
     * 🧪 @test
     * 🧷 @fallback
     * Safe fallback for root-less environments
     *
     * @return ErrorManagerInterface
     */
    protected static function safeHandler(): ErrorManagerInterface
    {
        $root = static::getFacadeRoot();

        // fallback minimale inline
        return $root instanceof ErrorManagerInterface
            ? $root
            : new class implements ErrorManagerInterface {
                public function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): JsonResponse|RedirectResponse
                {
                    if ($throw) throw $exception ?? new \RuntimeException("Simulated error: $errorCode");
                    return new JsonResponse(['error' => $errorCode, 'context' => $context]);
                }
                public function registerHandler(ErrorHandlerInterface $handler): ErrorManagerInterface { return $this; }
                public function getHandlers(): array { return []; }
                public function defineError(string $errorCode, array $config): ErrorManagerInterface { return $this; }
                public function getErrorConfig(string $errorCode): ?array { return null; }
            };
    }

    public static function handle(string $errorCode, array $context = [], ?\Throwable $exception = null, bool $throw = false): JsonResponse|RedirectResponse
    {
        return static::safeHandler()->handle($errorCode, $context, $exception, $throw);
    }

    public static function registerHandler(ErrorHandlerInterface $handler): ErrorManagerInterface
    {
        return static::safeHandler()->registerHandler($handler);
    }

    public static function getHandlers(): array
    {
        return static::safeHandler()->getHandlers();
    }

    public static function defineError(string $errorCode, array $config): ErrorManagerInterface
    {
        return static::safeHandler()->defineError($errorCode, $config);
    }

    public static function getErrorConfig(string $errorCode): ?array
    {
        return static::safeHandler()->getErrorConfig($errorCode);
    }
}
