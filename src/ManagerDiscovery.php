<?php

declare(strict_types=1);

namespace OddSockets;

/**
 * Simple Manager Discovery Service
 * 
 * Always connects to the main manager endpoint which handles
 * all routing and load balancing transparently.
 */
class ManagerDiscovery
{
    private string $managerUrl = 'https://manager1.oddsockets.tyga.network';

    /**
     * Get the manager URL (always returns the main endpoint)
     * 
     * @param string $apiKey The OddSockets API key (not used, kept for compatibility)
     * @return string The manager URL
     */
    public function discoverManagerUrl(string $apiKey): string
    {
        return $this->managerUrl;
    }

    /**
     * Clear cache (no-op, kept for compatibility)
     */
    public function clearCache(): void
    {
        // No cache to clear in simplified version
    }
}
