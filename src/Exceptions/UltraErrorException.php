<?php

declare(strict_types=1); // Strict types

namespace Ultra\ErrorManager\Exceptions;

use Exception; // Base PHP Exception
use Throwable; // For previous exception type hint

/**
 * 🎯 UltraErrorException – Oracoded Exception for UEM Handled Errors
 *
 * Represents an exception specifically generated or wrapped by the UltraErrorManager.
 * It carries the resolved string error code (`$stringCode`) and the relevant
 * context (`$context`) associated with the error event, in addition to standard
 * Exception properties. This allows for more specific catching and handling
 * higher up the call stack or in global exception handlers.
 *
 * 🧱 Structure:
 * - Extends PHP's base `Exception`.
 * - Holds a nullable string `$stringCode` (the resolved UEM error code).
 * - Holds an `$context` array.
 * - Provides getters (`getStringCode`, `getContext`) and a setter (`setStringCode`).
 *
 * 📡 Communicates:
 * - Carries error code and context data to exception handlers/catch blocks.
 *
 * 🧪 Testable:
 * - Standard exception, testable by throwing and catching.
 * - Properties accessible via getters for assertions.
 *
 * 🛡️ GDPR Considerations:
 * - The `$context` array might contain PII. Exception handlers logging or displaying
 *   this exception should be aware and sanitize if necessary.
 */
final class UltraErrorException extends Exception // Mark as final
{
    /**
     * 🧱 @property UEM String Error Code
     * The resolved symbolic error code (e.g., 'VALIDATION_ERROR') from UEM.
     * Can be null if the exception wasn't created with one.
     *
     * @var string|null
     */
    protected ?string $stringCode; // Added nullable string type hint

    /**
     * 🧱 @property Error Context
     * Associative array containing contextual data related to the error event.
     * Might contain PII, handle with care.
     *
     * @var array<string, mixed>
     */
    protected array $context; // Keep protected to allow potential extension if not final later

    /**
     * 🎯 Constructor: Initializes the UEM-specific exception.
     *
     * @param string $message The primary exception message (often the user/dev message).
     * @param int $code The HTTP status code or internal numeric code (defaults to 0).
     * @param Throwable|null $previous The previous throwable used for exception chaining.
     * @param string|null $stringCode The resolved UEM symbolic error code.
     * @param array<string, mixed> $context Optional context data related to the error.
     */
    public function __construct(
        string $message = "", // Add string type hint
        int $code = 0, // Add int type hint
        ?Throwable $previous = null,
        ?string $stringCode = null, // Add nullable string type hint
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->stringCode = $stringCode;
        $this->context = $context;
    }

    /**
     * 📡 Get the UEM string error code.
     *
     * @return string|null The symbolic error code, or null if not set.
     */
    public function getStringCode(): ?string // Added nullable string return type hint
    {
        return $this->stringCode;
    }

    /**
     * 🔧 Set or override the UEM string error code.
     * Allows modifying the code after the exception is created, though generally discouraged.
     *
     * @param string|null $stringCode The new symbolic error code.
     * @return self Returns the exception instance for fluent interface.
     */
    public function setStringCode(?string $stringCode): self // Added nullable string type hint and self return type
    {
        $this->stringCode = $stringCode;
        return $this;
    }

    /**
     * 📡 Get the contextual data associated with this error.
     * 🛡️ Contains potentially sensitive data.
     *
     * @return array<string, mixed> The context array.
     */
    public function getContext(): array // Keep return type hint
    {
        return $this->context;
    }
}