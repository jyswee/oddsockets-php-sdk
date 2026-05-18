<?php

declare(strict_types=1);

namespace OddSockets\Config;

/**
 * Builder class for creating OddSocketsConfig instances using a fluent interface.
 *
 * This builder provides a PHP-idiomatic way to construct configuration objects
 * with method chaining and sensible defaults.
 */
final class OddSocketsConfigBuilder
{
    private string $apiKey = '';
    private string $managerUrl = 'https://manager1.oddsockets.tyga.network';
    private ?string $userId = null;
    private bool $autoConnect = true;
    private int $reconnectAttempts = 5;
    private int $heartbeatInterval = 30;
    private int $timeout = 10;

    /**
     * Sets the API key.
     */
    public function apiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Sets the manager URL.
     */
    public function managerUrl(string $managerUrl): self
    {
        $this->managerUrl = $managerUrl;
        return $this;
    }

    /**
     * Sets the user ID.
     */
    public function userId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Sets whether to auto-connect.
     */
    public function autoConnect(bool $autoConnect = true): self
    {
        $this->autoConnect = $autoConnect;
        return $this;
    }

    /**
     * Sets the reconnect attempts.
     */
    public function reconnectAttempts(int $attempts): self
    {
        $this->reconnectAttempts = $attempts;
        return $this;
    }

    /**
     * Sets the heartbeat interval in seconds.
     */
    public function heartbeatInterval(int $seconds): self
    {
        $this->heartbeatInterval = $seconds;
        return $this;
    }

    /**
     * Sets the timeout in seconds.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Sets common development configuration.
     */
    public function development(): self
    {
        $this->managerUrl = 'http://localhost:3001';
        $this->timeout = 30;
        $this->heartbeatInterval = 10;
        return $this;
    }

    /**
     * Sets common production configuration.
     */
    public function production(): self
    {
        $this->managerUrl = 'https://manager1.oddsockets.tyga.network';
        $this->timeout = 10;
        $this->heartbeatInterval = 30;
        return $this;
    }

    /**
     * Builds the configuration.
     *
     * @throws \InvalidArgumentException if configuration is invalid
     */
    public function build(): OddSocketsConfig
    {
        return new OddSocketsConfig(
            apiKey: $this->apiKey,
            managerUrl: $this->managerUrl,
            userId: $this->userId,
            autoConnect: $this->autoConnect,
            reconnectAttempts: $this->reconnectAttempts,
            heartbeatInterval: $this->heartbeatInterval,
            timeout: $this->timeout
        );
    }
}
