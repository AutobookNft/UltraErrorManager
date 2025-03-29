<?php

namespace Ultra\ErrorManager\Exceptions;

use Exception;

/**
 * Custom exception for errors handled by UltraErrorManager
 *
 * This exception extends the base Exception class to add
 * support for string error codes used throughout the system.
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
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Numeric error code
     * @param \Throwable|null $previous Previous exception in chain
     * @param string|null $stringCode String error code
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null, $stringCode = null)
    {
        parent::__construct($message, $code, $previous);
        $this->stringCode = $stringCode;
    }

    /**
     * Get the error code in string format
     *
     * @return string|null String error code
     */
    public function getStringCode()
    {
        return $this->stringCode;
    }

    /**
     * Set the error code in string format
     *
     * @param string $stringCode String error code
     * @return $this For method chaining
     */
    public function setStringCode($stringCode)
    {
        $this->stringCode = $stringCode;
        return $this;
    }
}
