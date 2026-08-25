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
    private ?string $managerUrl = null;
    private ?string $userId = null;
    private bool $autoConnect = true;
    private int $reconnectAttempts = 5;
    private int $heartbeatInterval = 30;
    private int $timeout = 10;
    private $tokenProvider = null;
    private int $tokenRefreshLeadMs = 120000;

    /**
     * Sets the API key.
     */
    public function apiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Sets the async token provider for game-client (minted-token) auth. When
     * set, the client resolves a fresh token from this callback before every
     * (re)connect instead of presenting a static API key (FEAT-2026-0824-0040).
     * The callback returns a token string, an array (['token' => ...,
     * 'expiresAt' => ..., 'exp' => ...]), or a React promise resolving to either.
     */
    public function tokenProvider(callable $tokenProvider): self
    {
        $this->tokenProvider = $tokenProvider;
        return $this;
    }

    /**
     * Sets how long before a minted token's expiry to refresh it (milliseconds).
     */
    public function tokenRefreshLeadMs(int $milliseconds): self
    {
        $this->tokenRefreshLeadMs = $milliseconds;
        return $this;
    }

    /**
     * Sets the manager URL. This value is used verbatim: no other endpoint is
     * ever tried on its behalf.
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
        $this->managerUrl = \OddSockets\ManagerDiscovery::DEFAULT_MANAGER_URL;
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
            timeout: $this->timeout,
            tokenProvider: $this->tokenProvider,
            tokenRefreshLeadMs: $this->tokenRefreshLeadMs
        );
    }
}
