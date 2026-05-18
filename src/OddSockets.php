<?php

declare(strict_types=1);

namespace OddSockets;

use OddSockets\Config\OddSocketsConfig;

/**
 * Main OddSockets SDK entry point
 * 
 * Provides factory methods and convenience functions for creating OddSockets clients.
 * This class serves as the main export that works similar to the JavaScript SDK.
 */
class OddSockets
{
    /**
     * SDK Version
     */
    public const VERSION = '0.1.0-beta.1';

    /**
     * Create an OddSockets client with configuration
     * 
     * @param OddSocketsConfig|array|string $config Configuration object, array, or API key string
     * @return OddSocketsClient
     */
    public static function create(OddSocketsConfig|array|string $config): OddSocketsClient
    {
        if (is_string($config)) {
            $config = OddSocketsConfig::default($config);
        } elseif (is_array($config)) {
            if (!isset($config['apiKey'])) {
                throw new \InvalidArgumentException('API key is required');
            }
            
            $builder = OddSocketsConfig::builder($config['apiKey']);
            
            if (isset($config['userId'])) {
                $builder->userId($config['userId']);
            }
            if (isset($config['managerUrl'])) {
                $builder->managerUrl($config['managerUrl']);
            }
            if (isset($config['autoConnect'])) {
                $builder->autoConnect($config['autoConnect']);
            }
            if (isset($config['reconnectAttempts'])) {
                $builder->reconnectAttempts($config['reconnectAttempts']);
            }
            if (isset($config['heartbeatInterval'])) {
                $builder->heartbeatInterval($config['heartbeatInterval']);
            }
            if (isset($config['timeout'])) {
                $builder->timeout($config['timeout']);
            }
            
            $config = $builder->build();
        }

        return new OddSocketsClient($config);
    }

    /**
     * Create an OddSockets client with just an API key (convenience method)
     * 
     * @param string $apiKey The OddSockets API key
     * @return OddSocketsClient
     */
    public static function connect(string $apiKey): OddSocketsClient
    {
        return self::create($apiKey);
    }

    /**
     * Get SDK version
     * 
     * @return string
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Create a configuration builder
     * 
     * @param string $apiKey The OddSockets API key
     * @return \OddSockets\Config\OddSocketsConfigBuilder
     */
    public static function configBuilder(string $apiKey): \OddSockets\Config\OddSocketsConfigBuilder
    {
        return OddSocketsConfig::builder($apiKey);
    }

    /**
     * Validate an API key format
     * 
     * @param string $apiKey The API key to validate
     * @return bool
     */
    public static function isValidApiKey(string $apiKey): bool
    {
        return !empty($apiKey) && str_starts_with($apiKey, 'ak_');
    }

    /**
     * Get message size limits
     * 
     * @return array
     */
    public static function getMessageSizeLimits(): array
    {
        return [
            'maxMessageSize' => MessageSizeValidator::MAX_MESSAGE_SIZE,
            'maxMessageSizeKB' => MessageSizeValidator::MAX_MESSAGE_SIZE_KB
        ];
    }
}
