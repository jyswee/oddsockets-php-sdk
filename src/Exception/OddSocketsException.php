<?php

declare(strict_types=1);

namespace OddSockets\Exception;

use Exception;

/**
 * Base exception class for OddSockets SDK errors.
 */
class OddSocketsException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
