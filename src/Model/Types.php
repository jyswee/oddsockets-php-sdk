<?php

declare(strict_types=1);

namespace OddSockets\Model;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Represents the connection state of the OddSockets client.
 */
enum ConnectionState: string
{
    case DISCONNECTED = 'disconnected';
    case CONNECTING = 'connecting';
    case CONNECTED = 'connected';
    case RECONNECTING = 'reconnecting';
    case FAILED = 'failed';

    public function isConnected(): bool
    {
        return $this === self::CONNECTED;
    }

    public function isConnecting(): bool
    {
        return $this === self::CONNECTING || $this === self::RECONNECTING;
    }

    public function isDisconnected(): bool
    {
        return $this === self::DISCONNECTED || $this === self::FAILED;
    }
}

/**
 * Represents different event types emitted by the OddSockets client.
 */
enum EventType: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case RECONNECTED = 'reconnected';
    case ERROR = 'error';
    case MESSAGE = 'message';
    case PRESENCE = 'presence';
    case WORKER_ASSIGNED = 'worker_assigned';
    case MAX_RECONNECT_ATTEMPTS_REACHED = 'max_reconnect_attempts_reached';

    public function isConnectionEvent(): bool
    {
        return match ($this) {
            self::CONNECTED, self::DISCONNECTED, self::RECONNECTED, 
            self::WORKER_ASSIGNED, self::MAX_RECONNECT_ATTEMPTS_REACHED => true,
            default => false,
        };
    }

    public function isMessageEvent(): bool
    {
        return match ($this) {
            self::MESSAGE, self::PRESENCE => true,
            default => false,
        };
    }
}

/**
 * Represents presence information for a channel.
 */
final class PresenceInfo implements JsonSerializable
{
    /**
     * @param string[] $users
     */
    public function __construct(
        private readonly string $channel,
        private readonly array $users,
        private readonly int $count,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable()
    ) {
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return string[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function isUserPresent(string $userId): bool
    {
        return in_array($userId, $this->users, true);
    }

    public function isEmpty(): bool
    {
        return $this->count === 0 || empty($this->users);
    }

    public function getPresenceRatio(int $maxCapacity): float
    {
        return $maxCapacity <= 0 ? 0.0 : $this->count / $maxCapacity;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: $data['channel'],
            users: $data['users'] ?? [],
            count: $data['count'] ?? 0,
            timestamp: isset($data['timestamp']) 
                ? new DateTimeImmutable($data['timestamp']) 
                : new DateTimeImmutable()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'channel' => $this->channel,
            'users' => $this->users,
            'count' => $this->count,
            'timestamp' => $this->timestamp->format(DateTimeImmutable::ATOM),
        ];
    }
}

/**
 * Represents the result of a publish operation.
 */
final class PublishResult implements JsonSerializable
{
    public function __construct(
        private readonly string $messageId,
        private readonly DateTimeImmutable $timestamp,
        private readonly string $channel,
        private readonly bool $success
    ) {
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'] ?? $data['messageId'] ?? '',
            timestamp: isset($data['timestamp']) 
                ? new DateTimeImmutable($data['timestamp']) 
                : new DateTimeImmutable(),
            channel: $data['channel'] ?? '',
            success: $data['success'] ?? false
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'messageId' => $this->messageId,
            'timestamp' => $this->timestamp->format(DateTimeImmutable::ATOM),
            'channel' => $this->channel,
            'success' => $this->success,
        ];
    }
}

/**
 * Represents a message for bulk publishing.
 */
final class BulkMessage implements JsonSerializable
{
    public function __construct(
        private readonly string $channel,
        private readonly mixed $message = null,
        private readonly ?PublishOptions $options = null
    ) {
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }

    public function getOptions(): ?PublishOptions
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'channel' => $this->channel,
            'message' => $this->message,
            'options' => $this->options?->jsonSerialize(),
        ];
    }
}

/**
 * Represents the result of a bulk publish operation.
 */
final class BulkResult implements JsonSerializable
{
    public function __construct(
        private readonly bool $success,
        private readonly ?PublishResult $result = null,
        private readonly ?string $error = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return !$this->success;
    }

    public function getResult(): ?PublishResult
    {
        return $this->result;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorMessage(string $defaultMessage = 'Unknown error'): string
    {
        return $this->error ?? $defaultMessage;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = null;
        if (isset($data['result']) && is_array($data['result'])) {
            $result = PublishResult::fromArray($data['result']);
        }

        return new self(
            success: $data['success'] ?? false,
            result: $result,
            error: $data['error'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'result' => $this->result?->jsonSerialize(),
            'error' => $this->error,
        ];
    }
}

/**
 * Options for channel subscription.
 */
final class SubscribeOptions implements JsonSerializable
{
    public function __construct(
        private readonly bool $enablePresence = false,
        private readonly bool $retainHistory = false,
        private readonly ?string $filterExpression = null
    ) {
    }

    public function isPresenceEnabled(): bool
    {
        return $this->enablePresence;
    }

    public function isHistoryRetained(): bool
    {
        return $this->retainHistory;
    }

    public function getFilterExpression(): ?string
    {
        return $this->filterExpression;
    }

    public static function withPresence(): self
    {
        return new self(enablePresence: true);
    }

    public static function withHistory(): self
    {
        return new self(retainHistory: true);
    }

    public static function withPresenceAndHistory(): self
    {
        return new self(enablePresence: true, retainHistory: true);
    }

    public static function chatChannel(): self
    {
        return new self(enablePresence: true, retainHistory: true);
    }

    public static function notificationChannel(): self
    {
        return new self(enablePresence: false, retainHistory: false);
    }

    public static function dataChannel(): self
    {
        return new self(enablePresence: false, retainHistory: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'enablePresence' => $this->enablePresence,
            'retainHistory' => $this->retainHistory,
            'filterExpression' => $this->filterExpression,
        ];
    }
}

/**
 * Options for message publishing.
 */
final class PublishOptions implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        private readonly ?int $ttl = null,
        private readonly ?array $metadata = null,
        private readonly bool $storeInHistory = false
    ) {
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function isStoredInHistory(): bool
    {
        return $this->storeInHistory;
    }

    public static function withHistory(): self
    {
        return new self(storeInHistory: true);
    }

    public static function withTTL(int $seconds): self
    {
        return new self(ttl: $seconds);
    }

    public static function chatMessage(): self
    {
        return new self(
            storeInHistory: true,
            metadata: ['type' => 'chat']
        );
    }

    public static function systemMessage(): self
    {
        return new self(
            storeInHistory: true,
            metadata: ['type' => 'system', 'priority' => 'high']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ttl' => $this->ttl,
            'metadata' => $this->metadata,
            'storeInHistory' => $this->storeInHistory,
        ];
    }
}

/**
 * Options for retrieving message history.
 */
final class HistoryOptions implements JsonSerializable
{
    public function __construct(
        private readonly ?int $limit = null,
        private readonly ?DateTimeImmutable $start = null,
        private readonly ?DateTimeImmutable $end = null,
        private readonly bool $reverse = false
    ) {
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getStart(): ?DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): ?DateTimeImmutable
    {
        return $this->end;
    }

    public function isReverse(): bool
    {
        return $this->reverse;
    }

    public static function limit(int $count): self
    {
        return new self(limit: $count);
    }

    public static function recent(int $count): self
    {
        return new self(limit: $count, reverse: true);
    }

    public static function lastHour(int $count = 100): self
    {
        return new self(
            limit: $count,
            start: new DateTimeImmutable('-1 hour'),
            reverse: true
        );
    }

    public static function lastDay(int $count = 1000): self
    {
        return new self(
            limit: $count,
            start: new DateTimeImmutable('-1 day'),
            reverse: true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'limit' => $this->limit,
            'start' => $this->start?->format(DateTimeImmutable::ATOM),
            'end' => $this->end?->format(DateTimeImmutable::ATOM),
            'reverse' => $this->reverse,
        ];
    }
}

/**
 * Common message types for structured messaging.
 */
final class MessageTypes
{
    /**
     * A chat message structure.
     */
    public static function chatMessage(
        string $text,
        string $username,
        string $messageType = 'chat'
    ): array {
        return [
            'text' => $text,
            'username' => $username,
            'messageType' => $messageType,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * A notification message structure.
     *
     * @param array<string, mixed>|null $data
     */
    public static function notificationMessage(
        string $title,
        string $body,
        string $category = 'general',
        string $priority = 'normal',
        ?array $data = null
    ): array {
        return [
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'priority' => $priority,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'data' => $data,
        ];
    }

    /**
     * A system message structure.
     *
     * @param array<string, mixed>|null $metadata
     */
    public static function systemMessage(
        string $event,
        string $description,
        ?array $metadata = null
    ): array {
        return [
            'event' => $event,
            'description' => $description,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'metadata' => $metadata,
        ];
    }

    /**
     * A data event message structure.
     */
    public static function dataEvent(
        string $eventType,
        mixed $payload,
        ?string $source = null
    ): array {
        return [
            'eventType' => $eventType,
            'payload' => $payload,
            'source' => $source,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];
    }
}

/**
 * Constants used throughout the SDK.
 */
final class Constants
{
    public const SDK_VERSION = '0.1.0-beta.1';
    public const SDK_NAME = 'OddSockets-PHP-SDK';
    public const USER_AGENT = self::SDK_NAME . '/' . self::SDK_VERSION;
    public const DEFAULT_MANAGER_URL = 'https://manager1.oddsockets.tyga.network';
    public const DEFAULT_TIMEOUT_SECONDS = 10;
    public const DEFAULT_HEARTBEAT_INTERVAL_SECONDS = 30;
    public const DEFAULT_RECONNECT_ATTEMPTS = 5;
    public const MAX_MESSAGE_HISTORY_SIZE = 100;
}

/**
 * Utility functions for creating common data structures.
 */
final class OddSocketsUtils
{
    public static function generateMessageId(): string
    {
        return 'msg_' . str_replace('-', '', strtolower((string) \Ramsey\Uuid\Uuid::uuid4()));
    }

    public static function generateUserId(): string
    {
        return 'user_' . str_replace('-', '', strtolower((string) \Ramsey\Uuid\Uuid::uuid4()));
    }

    public static function bulkMessage(
        string $channel,
        mixed $message,
        ?PublishOptions $options = null
    ): BulkMessage {
        return new BulkMessage($channel, $message, $options);
    }

    /**
     * @param string[] $messages
     * @return BulkMessage[]
     */
    public static function bulkMessages(
        string $channel,
        array $messages,
        ?PublishOptions $options = null
    ): array {
        return array_map(
            fn(string $message) => new BulkMessage($channel, $message, $options),
            $messages
        );
    }
}
