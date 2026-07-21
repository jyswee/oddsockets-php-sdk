<?php

declare(strict_types=1);

namespace OddSockets;

use OddSockets\Config\OddSocketsConfig;
use OddSockets\Exception\OddSocketsException;
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
use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

/**
 * OddSockets PHP SDK
 * 
 * Provides a simple interface to the OddSockets real-time messaging platform.
 * Automatically handles manager discovery and Worker load balancing internally.
 */
class OddSocketsClient implements EventEmitterInterface
{
    use EventEmitterTrait {
        emit as protected localEmit;
    }

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
    private Browser $httpClient;
    private ?Deferred $pendingConnect = null;

    public function __construct(OddSocketsConfig $config, ?LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->loop = $loop ?? Loop::get();
        $this->clientIdentifier = $this->generateClientIdentifier();
        $this->managerDiscovery = new ManagerDiscovery();
        // Use the ReactPHP HTTP client so the manager request runs on the same
        // event loop as the WebSocket transport (a Guzzle async promise would
        // never tick under Loop::run()).
        $this->httpClient = (new Browser($this->loop))
            ->withTimeout($config->getTimeout());

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
            return \React\Promise\resolve(null);
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
            ->then(function () use ($deferred) {
                $this->connectionState = 'connected';
                $this->reconnectAttempts = 0;
                $this->reconnectDelay = 1000;
                $this->emit('connected');
                $deferred->resolve(null);
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

            $query = http_build_query([
                'apiKey' => $this->config->getApiKey(),
                'userId' => $this->config->getUserId() ?? $this->clientIdentifier,
                'clientIdentifier' => $this->clientIdentifier,
            ]);
            $url = $managerUrl . '/api/cluster/select-worker?' . $query;

            $this->httpClient->get($url, [
                'User-Agent' => 'OddSockets-PHP-SDK/1.0.0',
            ])->then(function (ResponseInterface $response) use ($deferred, $managerUrl) {
                $data = json_decode((string) $response->getBody(), true);

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

                $deferred->resolve(null);
            })->otherwise(function (\Throwable $error) use ($deferred) {
                $message = $error->getMessage();
                if (strpos($message, 'refused') !== false ||
                    strpos($message, 'resolve') !== false ||
                    strpos($message, 'name resolution') !== false) {
                    $deferred->reject(new ConnectionException('Manager is offline. Cannot assign worker without session stickiness.'));
                } else {
                    $deferred->reject(new ConnectionException('Failed to get worker assignment: ' . $message));
                }
            });

        } catch (\Exception $error) {
            $deferred->reject($error);
        }

        return $deferred->promise();
    }

    /**
     * Internal: Connect to assigned worker
     *
     * Opens a WebSocket to the worker's Socket.IO endpoint and performs the
     * Engine.IO v4 / Socket.IO handshake. The returned promise resolves only
     * once the server acknowledges our Socket.IO CONNECT packet, not merely
     * when the raw WebSocket opens.
     */
    private function connectToWorker(): Promise
    {
        if (!$this->workerUrl) {
            return \React\Promise\reject(new ConnectionException('No worker URL available'));
        }

        $deferred = new Deferred();
        $this->pendingConnect = $deferred;

        $connector = new WsConnector($this->loop);

        // The worker runs a Socket.IO server: connect on the Engine.IO v4
        // WebSocket transport path, not the bare worker root.
        $base = str_replace(['http://', 'https://'], ['ws://', 'wss://'], rtrim($this->workerUrl, '/'));
        $wsUrl = $base . '/socket.io/?EIO=4&transport=websocket';

        $connector($wsUrl)->then(function (WebSocket $conn) {
            // The raw socket is open, but we are not "connected" until the
            // Socket.IO CONNECT handshake completes (see the message handler).
            $this->socket = $conn;
            $this->setupSocketEventHandlers();
        })->otherwise(function (\Exception $error) {
            if ($this->pendingConnect !== null) {
                $d = $this->pendingConnect;
                $this->pendingConnect = null;
                $d->reject(new ConnectionException('Failed to connect to worker: ' . $error->getMessage()));
            }
        });

        // Timeout fallback covering both the WS open and the Socket.IO handshake.
        $this->loop->addTimer(15.0, function () {
            if ($this->pendingConnect !== null) {
                $d = $this->pendingConnect;
                $this->pendingConnect = null;
                $d->reject(new TimeoutException('Connection timeout'));
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

        // Handle incoming Engine.IO / Socket.IO frames.
        $this->socket->on('message', function ($msg) {
            try {
                $this->handleFrame((string) $msg->getPayload());
            } catch (\Exception $error) {
                $this->localEmit('error', [$error]);
            }
        });
    }

    /**
     * Internal: Decode a raw Engine.IO frame and act on it.
     *
     * Engine.IO packet types (first char): 0=OPEN, 2=PING, 3=PONG, 4=MESSAGE.
     * A MESSAGE (4) wraps a Socket.IO packet whose type is the next char:
     * 0=CONNECT, 1=DISCONNECT, 2=EVENT, 3=ACK, 4=CONNECT_ERROR.
     */
    private function handleFrame(string $payload): void
    {
        if ($payload === '') {
            return;
        }

        $engineType = $payload[0];

        // Engine.IO PING -> reply PONG so the server does not time us out.
        if ($engineType === '2') {
            $this->socket->send('3');
            return;
        }

        // Engine.IO PONG (unsolicited in our flow) - nothing to do.
        if ($engineType === '3') {
            return;
        }

        // Engine.IO OPEN: the handshake is ready, now send the Socket.IO
        // CONNECT packet carrying our auth (apiKey/userId land in
        // socket.handshake.auth on the worker).
        if ($engineType === '0') {
            $auth = [
                'apiKey' => $this->config->getApiKey(),
                'userId' => $this->config->getUserId() ?? $this->clientIdentifier,
            ];
            $this->socket->send('40' . json_encode($auth));
            return;
        }

        // Engine.IO MESSAGE wrapping a Socket.IO packet.
        if ($engineType === '4') {
            $socketType = $payload[1] ?? '';
            $body = substr($payload, 2);

            switch ($socketType) {
                case '0': // Socket.IO CONNECT ack -> we are fully connected.
                    if ($this->pendingConnect !== null) {
                        $d = $this->pendingConnect;
                        $this->pendingConnect = null;
                        $d->resolve(null);
                    }
                    return;

                case '1': // Socket.IO DISCONNECT
                    $this->connectionState = 'disconnected';
                    $this->localEmit('disconnected', ['Server disconnect']);
                    return;

                case '2': // Socket.IO EVENT: ["event", payload]
                    // Skip an optional numeric ack id before the JSON array.
                    $bracket = strpos($body, '[');
                    if ($bracket !== false) {
                        $body = substr($body, $bracket);
                    }
                    $decoded = json_decode($body, true);
                    if (is_array($decoded) && isset($decoded[0])) {
                        $this->dispatchEvent((string) $decoded[0], $decoded[1] ?? []);
                    }
                    return;

                case '4': // Socket.IO CONNECT_ERROR (e.g. auth failure)
                    $decoded = json_decode($body, true);
                    $message = is_array($decoded)
                        ? ($decoded['message'] ?? 'Connection rejected')
                        : (is_string($decoded) ? $decoded : 'Connection rejected');
                    $error = new AuthenticationException($message);
                    $this->localEmit('error', [$error]);
                    if ($this->pendingConnect !== null) {
                        $d = $this->pendingConnect;
                        $this->pendingConnect = null;
                        $d->reject($error);
                    }
                    return;
            }
        }
    }

    /**
     * Internal: Route a decoded Socket.IO event to channels and listeners.
     */
    private function dispatchEvent(string $event, $payload): void
    {
        $data = is_array($payload) ? $payload : ['data' => $payload];

        // Worker errors arrive as {type, message}. Normalise them to an
        // exception so every 'error' listener consistently receives a Throwable
        // (matching the transport-level errors the SDK emits itself).
        if ($event === 'error') {
            $message = ($data['type'] ?? 'ERROR') . ': ' . ($data['message'] ?? 'Unknown error');
            $error = ($data['type'] ?? '') === 'PERMISSION_DENIED'
                ? new AuthenticationException($message)
                : new OddSocketsException($message);
            $this->localEmit('error', [$error]);
            return;
        }

        $channelName = $data['channel'] ?? null;

        // Forward channel-related events to the appropriate channel.
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

        // Also surface the event to global client listeners.
        $this->localEmit($event, [$data]);
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
        
        return base_convert((string) abs($hash), 10, 36);
    }

    /**
     * Send a low-level event to the worker.
     *
     * Accepts the historical shape ['event' => name, ...payload] and encodes it
     * as a proper Socket.IO EVENT frame.
     */
    public function send(array $data): void
    {
        $event = $data['event'] ?? null;
        if ($event === null) {
            return;
        }
        unset($data['event']);
        $this->sendEvent((string) $event, $data);
    }

    /**
     * Internal: Encode and transmit a Socket.IO EVENT packet.
     *
     * Wire format: Engine.IO MESSAGE (4) + Socket.IO EVENT (2) + JSON array
     * ["event", payload], i.e. `42["subscribe",{...}]`.
     */
    private function sendEvent(string $event, array $payload): void
    {
        if ($this->socket === null) {
            return;
        }
        // Encode an empty payload as {} (object), not [] (array), to match the
        // JavaScript SDK's argument shape.
        $args = [$event, empty($payload) ? new \stdClass() : $payload];
        $this->socket->send('42' . json_encode($args));
    }

    /**
     * Emit an event.
     *
     * Operation events are sent to the worker over Socket.IO; all other events
     * are dispatched locally via the EventEmitter.
     */
    public function emit($event, array $data = [])
    {
        if ($event === 'subscribe' || $event === 'unsubscribe' || $event === 'publish' ||
            $event === 'get_history' || $event === 'get_presence' || $event === 'update_state') {
            // Channel passes the payload directly as the associative $data array.
            $this->sendEvent($event, $data);
        } else {
            // Use EventEmitter's emit for local events
            $this->localEmit($event, $data);
        }
    }
}
