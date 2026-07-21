<?php

declare(strict_types=1);

namespace OddSockets;

use OddSockets\Exception\ChannelException;
use OddSockets\Exception\MessageValidationException;
use OddSockets\Exception\TimeoutException;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\Promise\Promise;
use React\Promise\Deferred;

/**
 * Channel class for pub/sub messaging
 * 
 * Provides methods for subscribing, publishing, and managing presence
 * on a specific channel within the OddSockets platform.
 */
class Channel implements EventEmitterInterface
{
    use EventEmitterTrait;

    private string $name;
    private OddSocketsClient $client;
    private bool $subscribed = false;
    private bool $subscribing = false;
    private array $options = [];
    private array $presence = [];
    private array $messageHistory = [];
    private int $maxHistorySize = 100;

    public function __construct(string $name, OddSocketsClient $client)
    {
        $this->name = $name;
        $this->client = $client;
    }

    /**
     * Subscribe to the channel
     * 
     * @param callable $callback Message callback function
     * @param array $options Subscription options
     * @return Promise<void>
     */
    public function subscribe(callable $callback, array $options = []): Promise
    {
        if ($this->subscribed || $this->subscribing) {
            // Add callback to existing subscription
            $this->on('message', $callback);
            return \React\Promise\resolve(null);
        }

        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        $this->subscribing = true;
        $this->options = array_merge([
            'maxHistory' => 100,
            'retainHistory' => true,
            'enablePresence' => false,
        ], $options);

        $this->maxHistorySize = $this->options['maxHistory'];

        $deferred = new Deferred();

        // Set up one-time listeners for subscription response
        $onSubscribed = function (array $data) use ($callback, $deferred, &$onSubscribed, &$onError) {
            if ($data['channel'] === $this->name) {
                $this->subscribed = true;
                $this->subscribing = false;
                $this->on('message', $callback);

                $this->client->removeListener('subscribed', $onSubscribed);
                $this->client->removeListener('error', $onError);

                $this->emit('subscribed', [$data]);
                $deferred->resolve(null);
            }
        };

        $onError = function (\Exception $error) use ($deferred, &$onSubscribed, &$onError) {
            $this->subscribing = false;
            $this->client->removeListener('subscribed', $onSubscribed);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('subscribed', $onSubscribed);
        $this->client->on('error', $onError);

        // Send subscription request
        $this->client->emit('subscribe', [
            'channel' => $this->name,
            'options' => $this->options
        ]);

        // Timeout fallback
        $this->client->getLoop()->addTimer(10.0, function () use ($deferred, $onSubscribed, $onError) {
            if ($this->subscribing) {
                $this->client->removeListener('subscribed', $onSubscribed);
                $this->client->removeListener('error', $onError);
                $this->subscribing = false;
                $deferred->reject(new TimeoutException('Subscription timeout'));
            }
        });

        return $deferred->promise();
    }

    /**
     * Unsubscribe from the channel
     * 
     * @return Promise<void>
     */
    public function unsubscribe(): Promise
    {
        if (!$this->subscribed) {
            return \React\Promise\resolve(null);
        }

        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        $deferred = new Deferred();

        $onUnsubscribed = function (array $data) use ($deferred, &$onUnsubscribed, &$onError) {
            if ($data['channel'] === $this->name) {
                $this->subscribed = false;
                $this->removeAllListeners('message');

                $this->client->removeListener('unsubscribed', $onUnsubscribed);
                $this->client->removeListener('error', $onError);

                $this->emit('unsubscribed', [$data]);
                $deferred->resolve(null);
            }
        };

        $onError = function (\Exception $error) use ($deferred, &$onUnsubscribed, &$onError) {
            $this->client->removeListener('unsubscribed', $onUnsubscribed);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('unsubscribed', $onUnsubscribed);
        $this->client->on('error', $onError);

        $this->client->emit('unsubscribe', [
            'channel' => $this->name
        ]);

        // Timeout fallback
        $this->client->getLoop()->addTimer(5.0, function () use ($deferred, $onUnsubscribed, $onError) {
            $this->client->removeListener('unsubscribed', $onUnsubscribed);
            $this->client->removeListener('error', $onError);
            $deferred->reject(new TimeoutException('Unsubscription timeout'));
        });

        return $deferred->promise();
    }

    /**
     * Publish a message to the channel
     * 
     * @param mixed $message Message to publish (string, object, or array)
     * @param array $options Publishing options
     * @return Promise<array> Publication result
     */
    public function publish(mixed $message, array $options = []): Promise
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        // Validate message size before publishing
        try {
            MessageSizeValidator::validateMessageSize($message);
        } catch (\InvalidArgumentException $error) {
            return \React\Promise\reject(new MessageValidationException($error->getMessage()));
        }

        $deferred = new Deferred();

        $onPublished = function (array $data) use ($deferred, &$onPublished, &$onError) {
            if ($data['channel'] === $this->name) {
                $this->client->removeListener('published', $onPublished);
                $this->client->removeListener('error', $onError);
                $deferred->resolve($data);
            }
        };

        $onError = function (\Exception $error) use ($deferred, &$onPublished, &$onError) {
            $this->client->removeListener('published', $onPublished);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('published', $onPublished);
        $this->client->on('error', $onError);

        $this->client->emit('publish', [
            'channel' => $this->name,
            'message' => $message,
            'options' => $options
        ]);

        // Timeout fallback
        $this->client->getLoop()->addTimer(10.0, function () use ($deferred, $onPublished, $onError) {
            $this->client->removeListener('published', $onPublished);
            $this->client->removeListener('error', $onError);
            $deferred->reject(new TimeoutException('Publish timeout'));
        });

        return $deferred->promise();
    }

    /**
     * Get message history for the channel
     * 
     * @param array $options History options
     * @return Promise<array> Message history
     */
    public function getHistory(array $options = []): Promise
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        $deferred = new Deferred();

        $onHistory = function (array $data) use ($deferred, &$onHistory, &$onError) {
            if ($data['channel'] === $this->name) {
                $this->client->removeListener('history', $onHistory);
                $this->client->removeListener('error', $onError);
                $deferred->resolve($data['messages'] ?? []);
            }
        };

        $onError = function (\Exception $error) use ($deferred, &$onHistory, &$onError) {
            $this->client->removeListener('history', $onHistory);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('history', $onHistory);
        $this->client->on('error', $onError);

        $this->client->emit('get_history', array_merge([
            'channel' => $this->name,
            'count' => 50
        ], $options));

        // Timeout fallback
        $this->client->getLoop()->addTimer(10.0, function () use ($deferred, $onHistory, $onError) {
            $this->client->removeListener('history', $onHistory);
            $this->client->removeListener('error', $onError);
            $deferred->reject(new TimeoutException('History request timeout'));
        });

        return $deferred->promise();
    }

    /**
     * Get current presence information
     * 
     * @return Promise<array> Presence information
     */
    public function getPresence(): Promise
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        $deferred = new Deferred();

        $onPresence = function (array $data) use ($deferred, &$onPresence, &$onError) {
            if ($data['channel'] === $this->name) {
                $this->client->removeListener('presence', $onPresence);
                $this->client->removeListener('error', $onError);
                $deferred->resolve($data);
            }
        };

        $onError = function (\Exception $error) use ($deferred, &$onPresence, &$onError) {
            $this->client->removeListener('presence', $onPresence);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('presence', $onPresence);
        $this->client->on('error', $onError);

        $this->client->emit('get_presence', [
            'channel' => $this->name
        ]);

        // Timeout fallback
        $this->client->getLoop()->addTimer(5.0, function () use ($deferred, $onPresence, $onError) {
            $this->client->removeListener('presence', $onPresence);
            $this->client->removeListener('error', $onError);
            $deferred->reject(new TimeoutException('Presence request timeout'));
        });

        return $deferred->promise();
    }

    /**
     * Update user state
     * 
     * @param array $state User state object
     * @return Promise<void>
     */
    public function updateState(array $state): Promise
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new ChannelException('Client is not connected'));
        }

        $deferred = new Deferred();

        $onStateUpdated = function (array $data) use ($deferred, &$onStateUpdated, &$onError) {
            $this->client->removeListener('state_updated', $onStateUpdated);
            $this->client->removeListener('error', $onError);
            $deferred->resolve($data);
        };

        $onError = function (\Exception $error) use ($deferred, &$onStateUpdated, &$onError) {
            $this->client->removeListener('state_updated', $onStateUpdated);
            $this->client->removeListener('error', $onError);
            $deferred->reject($error);
        };

        $this->client->on('state_updated', $onStateUpdated);
        $this->client->on('error', $onError);

        $this->client->emit('update_state', [
            'state' => $state
        ]);

        // Timeout fallback
        $this->client->getLoop()->addTimer(5.0, function () use ($deferred, $onStateUpdated, $onError) {
            $this->client->removeListener('state_updated', $onStateUpdated);
            $this->client->removeListener('error', $onError);
            $deferred->reject(new TimeoutException('State update timeout'));
        });

        return $deferred->promise();
    }

    /**
     * Get channel subscription status
     */
    public function isSubscribed(): bool
    {
        return $this->subscribed;
    }

    /**
     * Get channel name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get current presence map
     */
    public function getPresenceMap(): array
    {
        return $this->presence;
    }

    /**
     * Get cached message history
     */
    public function getCachedHistory(): array
    {
        return $this->messageHistory;
    }

    /**
     * Internal: Handle incoming message
     */
    public function handleMessage(array $data): void
    {
        // Add to history if enabled
        if ($this->options['retainHistory'] ?? true) {
            $this->messageHistory[] = $data;
            
            // Trim history if too large
            if (count($this->messageHistory) > $this->maxHistorySize) {
                $this->messageHistory = array_slice($this->messageHistory, -$this->maxHistorySize);
            }
        }
        
        $this->emit('message', [$data]);
    }

    /**
     * Internal: Handle subscription confirmation
     */
    public function handleSubscribed(array $data): void
    {
        $this->emit('subscribed', [$data]);
    }

    /**
     * Internal: Handle unsubscription confirmation
     */
    public function handleUnsubscribed(array $data): void
    {
        $this->emit('unsubscribed', [$data]);
    }

    /**
     * Internal: Handle publish confirmation
     */
    public function handlePublished(array $data): void
    {
        $this->emit('published', [$data]);
    }

    /**
     * Internal: Handle presence information
     */
    public function handlePresence(array $data): void
    {
        // Update presence map
        if (isset($data['occupants'])) {
            $this->presence = [];
            foreach ($data['occupants'] as $occupant) {
                $this->presence[$occupant['userId']] = $occupant;
            }
        }
        
        $this->emit('presence', [$data]);
    }

    /**
     * Internal: Handle presence changes
     */
    public function handlePresenceChange(array $data): void
    {
        // Update presence map
        if ($data['action'] === 'join') {
            $this->presence[$data['user']['userId']] = $data['user'];
        } elseif ($data['action'] === 'leave') {
            unset($this->presence[$data['user']['userId']]);
        }
        
        $this->emit('presence_change', [$data]);
    }

    /**
     * Internal: Handle message history
     */
    public function handleHistory(array $data): void
    {
        $this->emit('history', [$data]);
    }
}
