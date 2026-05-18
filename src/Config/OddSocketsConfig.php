<?php

declare(strict_types=1);

namespace OddSockets\Config;

use InvalidArgumentException;

/**
 * Configuration class for OddSockets client.
 *
 * This class holds all configuration parameters needed to connect to OddSockets.
 * Use OddSocketsConfigBuilder for a fluent configuration experience.
 */
final class OddSocketsConfig
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $managerUrl = 'https://manager1.oddsockets.tyga.network',
        private readonly ?string $userId = null,
        private readonly bool $autoConnect = true,
        private readonly int $reconnectAttempts = 5,
        private readonly int $heartbeatInterval = 30,
        private readonly int $timeout = 10
    ) {
        $this->validate();
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getManagerUrl(): string
    {
        return $this->managerUrl;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function isAutoConnect(): bool
    {
        return $this->autoConnect;
    }

    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }

    public function getHeartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Creates a configuration with just an API key using default values.
     */
    public static function default(string $apiKey): self
    {
        return new self(apiKey: $apiKey);
    }

    /**
     * Creates a builder with an API key pre-set.
     */
    public static function builder(string $apiKey): OddSocketsConfigBuilder
    {
        return (new OddSocketsConfigBuilder())->apiKey($apiKey);
    }

    /**
     * Validates the configuration.
     *
     * @throws InvalidArgumentException if configuration is invalid
     */
    private function validate(): void
    {
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException('API key is required');
        }

        if (!str_starts_with($this->apiKey, 'ak_')) {
            throw new InvalidArgumentException('Invalid API key format');
        }

        if (empty($this->managerUrl)) {
            throw new InvalidArgumentException('Manager URL is required');
        }

        if (!filter_var($this->managerUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid manager URL format');
        }

        if ($this->reconnectAttempts < 0) {
            throw new InvalidArgumentException('Reconnect attempts must be non-negative');
        }

        if ($this->heartbeatInterval <= 0) {
            throw new InvalidArgumentException('Heartbeat interval must be positive');
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }
    }

    /**
     * Returns an array representation of the configuration.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'apiKey' => $this->apiKey,
            'managerUrl' => $this->managerUrl,
            'userId' => $this->userId,
            'autoConnect' => $this->autoConnect,
            'reconnectAttempts' => $this->reconnectAttempts,
            'heartbeatInterval' => $this->heartbeatInterval,
            'timeout' => $this->timeout,
        ];
    }
}
