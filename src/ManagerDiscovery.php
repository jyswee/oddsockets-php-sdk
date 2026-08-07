<?php

declare(strict_types=1);

namespace OddSockets;

use InvalidArgumentException;

/**
 * Resolves the manager endpoint used for worker assignment.
 *
 * A manager pointed at the wrong cluster still answers happily, so silently
 * substituting a default would connect a self-hosted or QA deployment to
 * production without any visible symptom. The configured value is therefore
 * used verbatim, and the default endpoint applies only when nothing has been
 * configured at all - never as a recovery path for an unreachable manager.
 */
class ManagerDiscovery
{
    public const DEFAULT_MANAGER_URL = 'https://connect.oddsockets.tyga.network';

    public const ENV_MANAGER_URL = 'ODDSOCKETS_MANAGER_URL';

    public function __construct(private readonly ?string $managerUrl = null)
    {
    }

    /**
     * Resolve the manager URL to use.
     *
     * Precedence: explicit configuration, then the ODDSOCKETS_MANAGER_URL
     * environment variable, then the default endpoint.
     *
     * @return string The manager URL, without a trailing slash
     * @throws InvalidArgumentException if the resolved URL is not an absolute http(s) URL
     */
    public function discoverManagerUrl(): string
    {
        if ($this->managerUrl !== null && $this->managerUrl !== '') {
            return self::normalizeUrl($this->managerUrl);
        }

        $fromEnv = getenv(self::ENV_MANAGER_URL);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return self::normalizeUrl($fromEnv);
        }

        return self::DEFAULT_MANAGER_URL;
    }

    /**
     * Validate a manager URL and strip any trailing slashes.
     *
     * @throws InvalidArgumentException if the URL is not an absolute http(s) URL
     */
    public static function normalizeUrl(string $url): string
    {
        $candidate = rtrim(trim($url), '/');

        if (
            $candidate === ''
            || preg_match('#^https?://#i', $candidate) !== 1
            || filter_var($candidate, FILTER_VALIDATE_URL) === false
        ) {
            throw new InvalidArgumentException('Invalid managerUrl: ' . $url);
        }

        return $candidate;
    }
}
