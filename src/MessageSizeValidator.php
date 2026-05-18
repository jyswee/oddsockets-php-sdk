<?php

declare(strict_types=1);

namespace OddSockets;

use InvalidArgumentException;

/**
 * Message size validator for OddSockets messages.
 * 
 * Validates message size limits to match industry standards (PubNub, Socket.IO)
 * for reliable real-time messaging.
 */
class MessageSizeValidator
{
    /**
     * Message size limits (industry standard - matches PubNub)
     */
    public const MAX_MESSAGE_SIZE = 32768; // 32KB in bytes
    public const MAX_MESSAGE_SIZE_KB = 32;

    /**
     * Validate message size
     * 
     * @param mixed $message Message to validate
     * @return int Message size in bytes
     * @throws InvalidArgumentException If message exceeds size limit
     */
    public static function validateMessageSize(mixed $message): int
    {
        $messageStr = is_string($message) ? $message : json_encode($message);
        $messageSize = strlen($messageStr);
        
        if ($messageSize > self::MAX_MESSAGE_SIZE) {
            $messageSizeKB = round($messageSize / 1024);
            throw new InvalidArgumentException(
                "Message size ({$messageSizeKB}KB) exceeds maximum allowed size of " . 
                self::MAX_MESSAGE_SIZE_KB . "KB. This limit matches industry standards " .
                "(PubNub, Socket.IO) for reliable real-time messaging."
            );
        }
        
        return $messageSize;
    }
}
