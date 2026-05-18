<?php

declare(strict_types=1);

namespace OddSockets\Model;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Represents a message received from OddSockets.
 *
 * This class encapsulates all information about a message including its content,
 * metadata, and routing information.
 */
final class Message implements JsonSerializable
{
    public function __construct(
        private readonly string $id,
        private readonly string $channel,
        private readonly mixed $data = null,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
        private readonly ?string $userId = null,
        private readonly ?array $metadata = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Gets a metadata value by key.
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Checks if this message has metadata.
     */
    public function hasMetadata(): bool
    {
        return !empty($this->metadata);
    }

    /**
     * Checks if this message has data.
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Creates a new message with generated ID.
     *
     * @param array<string, mixed>|null $metadata
     */
    public static function create(
        string $channel,
        mixed $data = null,
        ?string $userId = null,
        ?array $metadata = null
    ): self {
        return new self(
            id: 'msg_' . str_replace('-', '', strtolower((string) \Ramsey\Uuid\Uuid::uuid4())),
            channel: $channel,
            data: $data,
            userId: $userId,
            metadata: $metadata
        );
    }

    /**
     * Creates a message from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            channel: $data['channel'],
            data: $data['data'] ?? null,
            timestamp: isset($data['timestamp']) 
                ? new DateTimeImmutable($data['timestamp']) 
                : new DateTimeImmutable(),
            userId: $data['userId'] ?? $data['user_id'] ?? null,
            metadata: $data['metadata'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'data' => $this->data,
            'timestamp' => $this->timestamp->format(DateTimeImmutable::ATOM),
            'userId' => $this->userId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
