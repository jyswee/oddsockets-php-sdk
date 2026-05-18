<?php

declare(strict_types=1);

namespace OddSockets;

use OddSockets\Config\OddSocketsConfig;
use OddSockets\Exception\ConnectionException;
use OddSockets\Exception\AuthenticationException;
use OddSockets\Exception\TimeoutException;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;
use React\Socket\Connector;
use React\Stream\WritableResourceStream;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector as WsConnector;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

/**
 * OddSockets PHP SDK
 * 
 * Provides a simple interface to the OddSockets real-time messaging platform.
 * Automatically handles manager discovery and Worker load balancing internally.
 */
class OddSocketsClient implements EventEmitterInterface
{
    use EventEmitterTrait;

    private OddSocketsConfig $config;
    private LoopInterface $loop;
    private ?WebSocket $socket = null;
    private ?string $workerUrl = null;
    private ?string $workerId = null;
    private array $channels = [];
    private string $connectionState = 'disconnected'; // disconnected, connecting, connected, reconnecting
    private int $reconnectAttempts = 0;
    private int $reconnectDelay = 1000; // Start with 1 second
    private string $clientIdentifier;
    private ?array $sessionInfo = null;
    private ManagerDiscovery $managerDiscovery;
    private HttpClient $httpClient;

    public function __construct(OddSocketsConfig $config, ?LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->loop = $loop ?? Loop::get();
        $this->clientIdentifier = $this->generateClientIdentifier();
        $this->managerDiscovery = new ManagerDiscovery();
        $this->httpClient = new HttpClient([
            'timeout' => $config->getTimeout(),
            'headers' => [
                'User-Agent' => 'OddSockets-PHP-SDK/1.0.0'
            ]
        ]);

        // Auto-connect by default
        if ($config->isAutoConnect()) {
            $this->connect();
        }
    }

    /**
     * Connect to the OddSockets platform
     * Handles the Manager → Worker assignment internally
     */
    public function connect(): Promise
    {
        if ($this->connectionState === 'connecting' || $this->connectionState === 'connected') {
            return \React\Promise\resolve();
        }

        $this->connectionState = 'connecting';
        $this->emit('connecting');

        $deferred = new Deferred();

        // Step 1: Get worker assignment from manager
        $this->getWorkerAssignment()
            ->then(function () {
                // Step 2: Connect to assigned worker
                return $this->connectToWorker();
            })
            ->then(function () {
                $this->connectionState = 'connected';
                $this->reconnectAttempts = 0;
                $this->reconnectDelay = 1000;
                $this->emit('connected');
                $deferred->resolve();
            })
            ->otherwise(function (\Exception $error) use ($deferred) {
                $this->connectionState = 'disconnected';
                $this->emit('error', [$error]);

                // Auto-reconnect with exponential backoff
                if ($this->reconnectAttempts < $this->config->getReconnectAttempts()) {
                    $this->scheduleReconnect();
                } else {
                    $this->emit('max_reconnect_attempts_reached');
                }

                $deferred->reject($error);
            });

        return $deferred->promise();
    }

    /**
     * Disconnect from the platform
     */
    public function disconnect(): void
    {
        $this->connectionState = 'disconnected';

        if ($this->socket) {
            $this->socket->close();
            $this->socket = null;
        }

        $this->workerUrl = null;
        $this->workerId = null;
        $this->emit('disconnected');
    }

    /**
     * Get or create a channel
     */
    public function channel(string $channelName): Channel
    {
        if (empty($channelName)) {
            throw new \InvalidArgumentException('Channel name must be a non-empty string');
        }

        if (!isset($this->channels[$channelName])) {
            $this->channels[$channelName] = new Channel($channelName, $this);
        }

        return $this->channels[$channelName];
    }

    /**
     * Get current connection state
     */
    public function getState(): string
    {
        return $this->connectionState;
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connectionState === 'connected' && $this->socket !== null;
    }

    /**
     * Get assigned worker information
     */
    public function getWorkerInfo(): ?array
    {
        if (!$this->workerId || !$this->workerUrl) {
            return null;
        }

        return [
            'workerId' => $this->workerId,
            'workerUrl' => $this->workerUrl
        ];
    }

    /**
     * Publish multiple messages at once
     */
    public function publishBulk(array $messages): Promise
    {
        if (!$this->isConnected()) {
            return \React\Promise\reject(new ConnectionException('Not connected to OddSockets'));
        }

        $promises = [];

        foreach ($messages as $msg) {
            if (!isset($msg['channel']) || !isset($msg['message'])) {
                $promises[] = \React\Promise\resolve([
                    'success' => false,
                    'error' => 'Missing channel or message'
                ]);
                continue;
            }

            $channel = $this->channel($msg['channel']);
            $promises[] = $channel->publish($msg['message'], $msg['options'] ?? [])
                ->then(function ($result) {
                    return [
                        'success' => true,
                        'result' => $result
                    ];
                })
                ->otherwise(function (\Exception $error) {
                    return [
                        'success' => false,
                        'error' => $error->getMessage()
                    ];
                });
        }

        return \React\Promise\all($promises);
    }

    /**
     * Get client identifier used for session stickiness
     */
    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    /**
     * Get session information
     */
    public function getSessionInfo(): ?array
    {
        return $this->sessionInfo;
    }

    /**
     * Get the event loop
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * Internal: Get worker assignment from manager
     */
    private function getWorkerAssignment(): Promise
    {
        $deferred = new Deferred();

        try {
            // Discover the optimal manager URL automatically
            $managerUrl = $this->managerDiscovery->discoverManagerUrl($this->config->getApiKey());

            $this->httpClient->getAsync($managerUrl . '/api/cluster/select-worker', [
                'query' => [
                    'apiKey' => $this->config->getApiKey(),
                    'userId' => $this->config->getUserId() ?? $this->clientIdentifier,
                    'clientIdentifier' => $this->clientIdentifier
                ]
            ])->then(function ($response) use ($deferred, $managerUrl) {
                $data = json_decode($response->getBody()->getContents(), true);

                if (!$data || !isset($data['url'])) {
                    $deferred->reject(new ConnectionException('Invalid worker assignment response'));
                    return;
                }

                $this->workerUrl = $data['url'];
                $this->workerId = $data['workerId'];
                $this->sessionInfo = $data['session'] ?? null;

                $this->emit('worker_assigned', [[
                    'workerId' => $this->workerId,
                    'workerUrl' => $this->workerUrl,
                    'session' => $this->sessionInfo,
                    'clientIdentifier' => $this->clientIdentifier,
                    'managerUrl' => $managerUrl // Include discovered manager URL for debugging
                ]]);

                $deferred->resolve();
            })->otherwise(function (RequestException $error) use ($deferred) {
                // If manager is offline, try fallback logic
                if (strpos($error->getMessage(), 'Connection refused') !== false ||
                    strpos($error->getMessage(), 'Could not resolve host') !== false) {
                    $deferred->reject(new ConnectionException('Manager is offline. Cannot assign worker without session stickiness.'));
                } else {
                    $deferred->reject(new ConnectionException('Failed to get worker assignment: ' . $error->getMessage()));
                }
            });

        } catch (\Exception $error) {
            $deferred->reject($error);
        }

        return $deferred->promise();
    }

    /**
     * Internal: Connect to assigned worker
     */
    private function connectToWorker(): Promise
    {
        if (!$this->workerUrl) {
            return \React\Promise\reject(new ConnectionException('No worker URL available'));
        }

        $deferred = new Deferred();

        $connector = new WsConnector($this->loop);

        // Parse WebSocket URL from HTTP URL
        $wsUrl = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->workerUrl);

        $connector($wsUrl, ['Sec-WebSocket-Protocol' => 'oddsockets'], [
            'auth' => [
                'apiKey' => $this->config->getApiKey(),
                'userId' => $this->config->getUserId()
            ]
        ])->then(function (WebSocket $conn) use ($deferred) {
            $this->socket = $conn;
            $this->setupSocketEventHandlers();
            $deferred->resolve();
        })->otherwise(function (\Exception $error) use ($deferred) {
            $deferred->reject(new ConnectionException('Failed to connect to worker: ' . $error->getMessage()));
        });

        // Timeout fallback
        $this->loop->addTimer(15.0, function () use ($deferred) {
            if ($this->connectionState === 'connecting') {
                $deferred->reject(new TimeoutException('Connection timeout'));
            }
        });

        return $deferred->promise();
    }

    /**
     * Internal: Setup socket event handlers
     */
    private function setupSocketEventHandlers(): void
    {
        if (!$this->socket) {
            return;
        }

        // Handle disconnection
        $this->socket->on('close', function ($code = null, $reason = null) {
            $this->connectionState = 'disconnected';
            $this->emit('disconnected', [$reason ?? 'Connection closed']);

            // Auto-reconnect unless manually disconnected
            if ($code !== 1000) { // 1000 = normal closure
                $this->scheduleReconnect();
            }
        });

        // Handle errors
        $this->socket->on('error', function (\Exception $error) {
            $this->emit('error', [$error]);
        });

        // Handle incoming messages
        $this->socket->on('message', function ($msg) {
            try {
                $data = json_decode($msg->getPayload(), true);
                if (!$data) {
                    return;
                }

                $this->handleSocketMessage($data);
            } catch (\Exception $error) {
                $this->emit('error', [$error]);
            }
        });
    }

    /**
     * Internal: Handle socket messages
     */
    private function handleSocketMessage(array $data): void
    {
        $event = $data['event'] ?? null;
        $channelName = $data['channel'] ?? null;

        if (!$event) {
            return;
        }

        // Forward channel-related events to appropriate channels
        if ($channelName && isset($this->channels[$channelName])) {
            $channel = $this->channels[$channelName];

            switch ($event) {
                case 'message':
                    $channel->handleMessage($data);
                    break;
                case 'subscribed':
                    $channel->handleSubscribed($data);
                    break;
                case 'unsubscribed':
                    $channel->handleUnsubscribed($data);
                    break;
                case 'published':
                    $channel->handlePublished($data);
                    break;
                case 'presence':
                    $channel->handlePresence($data);
                    break;
                case 'presence_change':
                    $channel->handlePresenceChange($data);
                    break;
                case 'history':
                    $channel->handleHistory($data);
                    break;
            }
        }

        // Also emit on client for global listeners
        $this->emit($event, [$data]);
    }

    /**
     * Internal: Schedule reconnection with exponential backoff
     */
    private function scheduleReconnect(): void
    {
        if ($this->connectionState === 'connected') {
            return;
        }

        $this->connectionState = 'reconnecting';
        $this->reconnectAttempts++;

        $delay = min($this->reconnectDelay * pow(2, $this->reconnectAttempts - 1), 30000) / 1000; // Convert to seconds

        $this->emit('reconnecting', [[
            'attempt' => $this->reconnectAttempts,
            'maxAttempts' => $this->config->getReconnectAttempts(),
            'delay' => $delay * 1000 // Convert back to milliseconds for consistency
        ]]);

        $this->loop->addTimer($delay, function () {
            if ($this->connectionState === 'reconnecting') {
                $this->connect();
            }
        });
    }

    /**
     * Internal: Generate consistent client identifier for session stickiness
     */
    private function generateClientIdentifier(): string
    {
        // Create a consistent identifier based on API key and user ID
        $baseId = $this->config->getUserId() ?? 'default';
        $apiKeyHash = $this->hashString($this->config->getApiKey());
        return $apiKeyHash . '_' . $baseId;
    }

    /**
     * Internal: Simple hash function for API key
     */
    private function hashString(string $str): string
    {
        $hash = 0;
        $len = strlen($str);
        
        if ($len === 0) {
            return (string) $hash;
        }
        
        for ($i = 0; $i < $len; $i++) {
            $char = ord($str[$i]);
            $hash = (($hash << 5) - $hash) + $char;
            $hash = $hash & 0xFFFFFFFF; // Convert to 32-bit integer
        }
        
        return base_convert(abs($hash), 10, 36);
    }

    /**
     * Send data to the WebSocket
     */
    public function send(array $data): void
    {
        if ($this->socket && $this->isConnected()) {
            $this->socket->send(json_encode($data));
        }
    }

    /**
     * Emit data to the WebSocket (alias for send)
     */
    public function emit(string $event, array $data = []): void
    {
        if ($event === 'subscribe' || $event === 'unsubscribe' || $event === 'publish' || 
            $event === 'get_history' || $event === 'get_presence' || $event === 'update_state') {
            $this->send(array_merge(['event' => $event], $data[0] ?? []));
        } else {
            // Use EventEmitter's emit for local events
            parent::emit($event, $data);
        }
    }
}
