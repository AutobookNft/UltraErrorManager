<?php

namespace Ultra\ErrorManager\Exceptions;

use Exception;

/**
 * Custom exception for errors handled by UltraErrorManager
 *
 * This exception extends the base Exception class to add
 * support for string error codes and optional context.
 *
 * @package Ultra\ErrorManager\Exceptions
 */
class UltraErrorException extends Exception
{
    /**
     * Error code in string format
     *
     * @var string|null
     */
    protected $stringCode;

    /**
     * Optional contextual data associated with the error
     *
     * @var array
     */
    protected array $context = [];

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Numeric error code
     * @param \Throwable|null $previous Previous exception in chain
     * @param string|null $stringCode String error code
     * @param array $context Optional context data
     */
    public function __construct($message = "", $code = 0, ?\Throwable $previous = null, $stringCode = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->stringCode = $stringCode;
        $this->context = $context;
    }

    /**
     * Get the error code in string format
     *
     * @return string|null
     */
    public function getStringCode()
    {
        return $this->stringCode;
    }

    /**
     * Set the error code in string format
     *
     * @param string $stringCode
     * @return $this
     */
    public function setStringCode($stringCode)
    {
        $this->stringCode = $stringCode;
        return $this;
    }

    /**
     * Get the contextual data associated with this error
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
