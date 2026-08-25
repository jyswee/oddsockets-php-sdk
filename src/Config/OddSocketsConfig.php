<?php

declare(strict_types=1);

namespace OddSockets\Config;

use InvalidArgumentException;
use OddSockets\ManagerDiscovery;

/**
 * Configuration class for OddSockets client.
 *
 * This class holds all configuration parameters needed to connect to OddSockets.
 * Use OddSocketsConfigBuilder for a fluent configuration experience.
 */
final class OddSocketsConfig
{
    /**
     * Async token provider for game-client (minted-token) auth. When set, the
     * client resolves a fresh token from this callback before every (re)connect
     * instead of presenting a static API key (FEAT-2026-0824-0040). The callback
     * may return a token string, or an array shaped like the control-plane mint
     * response (['token' => ..., 'expiresAt' => ..., 'exp' => ...]); it may also
     * return a React promise resolving to either shape.
     */
    private readonly ?\Closure $tokenProvider;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly ?string $managerUrl = null,
        private readonly ?string $userId = null,
        private readonly bool $autoConnect = true,
        private readonly int $reconnectAttempts = 5,
        private readonly int $heartbeatInterval = 30,
        private readonly int $timeout = 10,
        ?callable $tokenProvider = null,
        private readonly int $tokenRefreshLeadMs = 120000
    ) {
        $this->tokenProvider = $tokenProvider !== null
            ? \Closure::fromCallable($tokenProvider)
            : null;
        $this->validate();
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * The async token provider, or null when using static API-key auth.
     */
    public function getTokenProvider(): ?\Closure
    {
        return $this->tokenProvider;
    }

    /**
     * How long before a minted token's expiry to refresh it (milliseconds).
     */
    public function getTokenRefreshLeadMs(): int
    {
        return $this->tokenRefreshLeadMs;
    }

    /**
     * True when this config authenticates with minted tokens rather than a
     * static API key.
     */
    public function hasTokenProvider(): bool
    {
        return $this->tokenProvider !== null;
    }

    /**
     * The manager URL as configured, or null when the caller has not set one.
     *
     * Resolution of the fallbacks is left to ManagerDiscovery so that "not
     * configured" stays distinguishable from "deliberately set to production".
     */
    public function getManagerUrl(): ?string
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
     * Creates a builder for game-client (minted-token) auth with the token
     * provider pre-set and no static API key (FEAT-2026-0824-0040).
     */
    public static function builderWithTokenProvider(callable $tokenProvider): OddSocketsConfigBuilder
    {
        return (new OddSocketsConfigBuilder())->tokenProvider($tokenProvider);
    }

    /**
     * Validates the configuration.
     *
     * @throws InvalidArgumentException if configuration is invalid
     */
    private function validate(): void
    {
        // Static API-key checks only apply when no token provider is configured.
        // A game-client config authenticates with minted tokens and carries no
        // ak_ key (FEAT-2026-0824-0040).
        if ($this->tokenProvider === null) {
            if (empty($this->apiKey)) {
                throw new InvalidArgumentException('API key is required');
            }

            if (!str_starts_with($this->apiKey, 'ak_')) {
                throw new InvalidArgumentException('Invalid API key format');
            }
        }

        if ($this->managerUrl !== null) {
            ManagerDiscovery::normalizeUrl($this->managerUrl);
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
