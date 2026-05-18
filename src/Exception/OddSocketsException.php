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

/**
 * Exception thrown when connection to OddSockets fails.
 */
class ConnectionException extends OddSocketsException
{
}

/**
 * Exception thrown when authentication fails.
 */
class AuthenticationException extends OddSocketsException
{
}

/**
 * Exception thrown when a timeout occurs.
 */
class TimeoutException extends OddSocketsException
{
}

/**
 * Exception thrown when message validation fails.
 */
class MessageValidationException extends OddSocketsException
{
}

/**
 * Exception thrown when channel operations fail.
 */
class ChannelException extends OddSocketsException
{
}
