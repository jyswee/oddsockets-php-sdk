<?php

namespace OddSockets;

use OddSockets\Exception\OddSocketsException;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;

/**
 * Enhanced Features for OddSockets PHP SDK
 * Provides 67 new Slack-like events with ReactPHP promises
 */
class EnhancedFeatures
{
    private OddSocketsClient $client;
    private int $timeout = 10;

    public function __construct(OddSocketsClient $client)
    {
        $this->client = $client;
    }

    // MARK: - Thread Events

    public function threadReply(
        string $channel,
        string $parentMessageId,
        string $message,
        string $userId,
        string $userName
    ): PromiseInterface {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();
        
        $params = [
            'channel' => $channel,
            'parentMessageId' => $parentMessageId,
            'message' => $message,
            'userId' => $userId,
            'userName' => $userName
        ];

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'thread_reply') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('thread_reply_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('thread_reply', $params);

        return $deferred->promise();
    }

    public function getThread(string $threadId): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_thread') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('thread_data', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_thread', ['threadId' => $threadId]);

        return $deferred->promise();
    }

    public function subscribeThread(string $threadId, string $userId): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'subscribe_thread') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('thread_subscribed', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('subscribe_thread', ['threadId' => $threadId, 'userId' => $userId]);

        return $deferred->promise();
    }

    public function markThreadRead(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('mark_thread_read', ['threadId' => $threadId, 'userId' => $userId]);
    }

    public function followThread(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('follow_thread', ['threadId' => $threadId, 'userId' => $userId]);
    }

    public function unfollowThread(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('unfollow_thread', ['threadId' => $threadId, 'userId' => $userId]);
    }

    // MARK: - Reaction Events

    public function addReaction(
        string $messageId,
        string $channel,
        string $emoji,
        string $userId,
        string $userName
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('add_reaction', [
            'messageId' => $messageId,
            'channel' => $channel,
            'emoji' => $emoji,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function removeReaction(
        string $messageId,
        string $channel,
        string $emoji,
        string $userId
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('remove_reaction', [
            'messageId' => $messageId,
            'channel' => $channel,
            'emoji' => $emoji,
            'userId' => $userId
        ]);
    }

    public function getReactions(string $messageId): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_reactions') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('message_reactions', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_reactions', ['messageId' => $messageId]);

        return $deferred->promise();
    }

    // MARK: - Read Receipt Events

    public function markRead(
        string $messageId,
        string $channel,
        string $userId,
        string $userName
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('mark_read', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function getUnreadCounts(string $userId, array $channels): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_unread_counts') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('unread_counts', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_unread_counts', ['userId' => $userId, 'channels' => $channels]);

        return $deferred->promise();
    }

    public function markAllRead(string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('mark_all_read', ['channel' => $channel, 'userId' => $userId]);
    }

    // MARK: - Channel Events

    public function createChannel(
        string $name,
        string $type,
        string $description,
        string $topic,
        string $createdBy,
        string $createdByName
    ): PromiseInterface {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $params = [
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'topic' => $topic,
            'createdBy' => $createdBy,
            'createdByName' => $createdByName,
            'members' => []
        ];

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'create_channel') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('channel_create_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('create_channel', $params);

        return $deferred->promise();
    }

    public function updateChannel(string $channelId, array $updates, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('update_channel', [
            'channelId' => $channelId,
            'updates' => $updates,
            'userId' => $userId
        ]);
    }

    public function archiveChannel(string $channelId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('archive_channel', ['channelId' => $channelId, 'userId' => $userId]);
    }

    public function inviteToChannel(
        string $channelId,
        string $invitedUserId,
        string $invitedUserName,
        string $invitedBy
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('invite_to_channel', [
            'channelId' => $channelId,
            'invitedUserId' => $invitedUserId,
            'invitedUserName' => $invitedUserName,
            'invitedBy' => $invitedBy
        ]);
    }

    public function removeFromChannel(string $channelId, string $removedUserId, string $removedBy): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('remove_from_channel', [
            'channelId' => $channelId,
            'removedUserId' => $removedUserId,
            'removedBy' => $removedBy
        ]);
    }

    public function joinChannel(string $channelId, string $userId, string $userName): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('join_channel', [
            'channelId' => $channelId,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function leaveChannel(string $channelId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('leave_channel', ['channelId' => $channelId, 'userId' => $userId]);
    }

    public function getChannelMembers(string $channelId): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_channel_members') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('channel_members', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_channel_members', ['channelId' => $channelId]);

        return $deferred->promise();
    }

    // MARK: - Direct Message Events

    public function createDM(array $userIds, string $type): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'create_dm') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('dm_create_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('create_dm', ['userIds' => $userIds, 'type' => $type]);

        return $deferred->promise();
    }

    public function sendDM(
        string $conversationId,
        string $message,
        string $userId,
        string $userName
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('send_dm', [
            'conversationId' => $conversationId,
            'message' => $message,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function getDMConversations(string $userId, bool $includeArchived): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_dm_conversations') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('dm_conversations', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_dm_conversations', [
            'userId' => $userId,
            'includeArchived' => $includeArchived
        ]);

        return $deferred->promise();
    }

    // MARK: - Notification Events

    public function subscribeNotifications(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('subscribe_notifications', ['userId' => $userId]);
    }

    public function markNotificationRead(string $notificationId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('mark_notification_read', [
            'notificationId' => $notificationId,
            'userId' => $userId
        ]);
    }

    public function markAllNotificationsRead(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('mark_all_notifications_read', ['userId' => $userId]);
    }

    public function clearNotifications(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('clear_notifications', ['userId' => $userId]);
    }

    public function getNotifications(string $userId, int $limit, ?string $status = null): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $params = ['userId' => $userId, 'limit' => $limit];
        if ($status !== null) {
            $params['status'] = $status;
        }

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_notifications') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('notifications_data', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_notifications', $params);

        return $deferred->promise();
    }

    // MARK: - Presence Events

    public function setStatus(string $userId, string $status): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('set_status', ['userId' => $userId, 'status' => $status]);
    }

    public function setCustomStatus(
        string $userId,
        string $emoji,
        string $text,
        ?string $expiresAt = null
    ): void {
        if (!$this->client->isConnected()) return;
        
        $params = ['userId' => $userId, 'emoji' => $emoji, 'text' => $text];
        if ($expiresAt !== null) {
            $params['expiresAt'] = $expiresAt;
        }
        
        $this->client->emitToWorker('set_custom_status', $params);
    }

    public function clearCustomStatus(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('clear_custom_status', ['userId' => $userId]);
    }

    public function setDND(string $userId, ?string $until = null): void
    {
        if (!$this->client->isConnected()) return;
        
        $params = ['userId' => $userId];
        if ($until !== null) {
            $params['until'] = $until;
        }
        
        $this->client->emitToWorker('set_dnd', $params);
    }

    public function clearDND(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('clear_dnd', ['userId' => $userId]);
    }

    public function startTyping(string $userId, string $channel): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('start_typing', ['userId' => $userId, 'channel' => $channel]);
    }

    public function stopTyping(string $userId, string $channel): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('stop_typing', ['userId' => $userId, 'channel' => $channel]);
    }

    public function getUserPresence(array $userIds): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_user_presence') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('user_presence_data', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_user_presence', ['userIds' => $userIds]);

        return $deferred->promise();
    }

    // MARK: - Message Editing Events

    public function editMessage(
        string $messageId,
        string $channel,
        string $newContent,
        string $userId
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('edit_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'newContent' => $newContent,
            'userId' => $userId
        ]);
    }

    public function deleteMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('delete_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function pinMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('pin_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function unpinMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emitToWorker('unpin_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function getPinnedMessages(string $channel): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'get_pinned_messages') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('pinned_messages', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('get_pinned_messages', ['channel' => $channel]);

        return $deferred->promise();
    }

    // MARK: - Search Events

    public function searchMessages(string $query, string $userId, int $limit): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'search_messages') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('search_results', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('search_messages', [
            'query' => $query,
            'userId' => $userId,
            'limit' => $limit
        ]);

        return $deferred->promise();
    }

    public function filterMessages(array $filters): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'filter_messages') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('filter_results', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('filter_messages', $filters);

        return $deferred->promise();
    }

    public function searchInChannel(string $channel, string $query, int $limit): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'search_in_channel') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('channel_search_results', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('search_in_channel', [
            'channel' => $channel,
            'query' => $query,
            'limit' => $limit
        ]);

        return $deferred->promise();
    }

    public function searchByUser(string $userId, ?string $query = null, int $limit = 10): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $params = ['userId' => $userId, 'limit' => $limit];
        if ($query !== null) {
            $params['query'] = $query;
        }

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'search_by_user') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('user_search_results', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('search_by_user', $params);

        return $deferred->promise();
    }

    // MARK: - Challenge / Leaderboard / Achievement Events

    /**
     * Create a competitive challenge (match, tournament leg, quest, ...).
     *
     * @param array $params {challengeId, metric, ranked?, channel?,
     *                       resultWebhookUrl?, standingsUrl?}. `ranked` enables a
     *                       leaderboard for the challenge; the metric is the value
     *                       progress/completion is measured against.
     * @return Promise Resolves with the server ack data on success, rejects with
     *                 an OddSocketsException on a 'challenge_create' error.
     */
    public function createChallenge(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_create') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_create_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_create', $params);

        return $deferred->promise();
    }

    /**
     * Report incremental progress toward a challenge (fire-and-forget).
     *
     * @param array $params {challengeId, value, metric?, eventId?, cohort?,
     *                       platform?, channel?}. The server echoes
     *                       'challenge_progress' (and 'leaderboard_rank_change'
     *                       when the reporter's rank moves) to room members.
     */
    public function reportProgress(array $params): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('challenge_progress', $params);
    }

    /**
     * Complete (or resolve) a challenge for the caller.
     *
     * @param array $params {challengeId, outcome, eventId?, reward?}. `outcome`
     *                       is one of completed, failed, expired, conceded, tied
     *                       (win = completed with rank 1, loss = failed,
     *                       draw = tied, resign = conceded, timeout = expired).
     * @return Promise Resolves with the server ack data on success, rejects with
     *                 an OddSocketsException on a 'challenge_complete' error.
     */
    public function completeChallenge(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_complete') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_complete_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_complete', $params);

        return $deferred->promise();
    }

    /**
     * Unlock (or advance progress toward) an achievement (fire-and-forget).
     *
     * @param array $params {achievementId, name?, tier?, percentComplete?,
     *                       challengeId?, channel?}. A percentComplete below 100
     *                       broadcasts 'achievement_progress'; at 100 or omitted
     *                       it broadcasts 'achievement_unlock'.
     */
    public function unlockAchievement(array $params): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emitToWorker('achievement_unlock', $params);
    }

    /**
     * Fetch the standings (leaderboard slice) for a ranked challenge.
     *
     * @param array $params {challengeId, limit?=20, offset?=0}.
     * @return Promise Resolves with {standings:[{identity, value, rank, cohort,
     *                 platform}], yourRank}, rejects with an OddSocketsException
     *                 on a 'challenge_standings' error.
     */
    public function getStandings(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_standings') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_standings_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_standings', $params);

        return $deferred->promise();
    }

    /**
     * Query achievement state (unlocked + in-progress) for the caller.
     *
     * @param array $params {achievementId?}. Omit `achievementId` to return all
     *                       achievements for the caller.
     * @return Promise Resolves with {achievements:[...]}, rejects with an
     *                 OddSocketsException on an 'achievement_query' error.
     */
    public function getAchievements(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'achievement_query') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('achievement_state', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('achievement_query', $params);

        return $deferred->promise();
    }

    /**
     * Send a directed challenge invite to another user.
     *
     * @param array $params {toUserId, type?='match', payload?<=8KB, ttl?=300,
     *                       channel?, inviteId?}.
     * @return Promise Resolves with {inviteId, toUserId, type, status:'pending',
     *                 expiresAt}, rejects with an OddSocketsException on a
     *                 'challenge_invite' error. The invitee receives a
     *                 'challenge_invited' broadcast.
     */
    public function sendChallengeInvite(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_invite') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_invite_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_invite', $params);

        return $deferred->promise();
    }

    /**
     * Reply to a received challenge invite (accept or decline).
     *
     * @param array $params {inviteId, accept, reason?}. The inviter receives a
     *                       'challenge_reply_received' broadcast.
     * @return Promise Resolves with the server ack data on success, rejects with
     *                 an OddSocketsException on a 'challenge_reply' error.
     */
    public function replyChallengeInvite(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_reply') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_reply_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_reply', $params);

        return $deferred->promise();
    }

    /**
     * Cancel a pending challenge invite the caller previously sent.
     *
     * @param array $params {inviteId}. The invitee receives a
     *                       'challenge_invite_cancelled' broadcast.
     * @return Promise Resolves with the server ack data on success, rejects with
     *                 an OddSocketsException on a 'challenge_invite_cancel' error.
     */
    public function cancelChallengeInvite(array $params): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_invite_cancel') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_invite_cancel_success', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_invite_cancel', $params);

        return $deferred->promise();
    }

    /**
     * List the pending challenge invites addressed to the caller.
     *
     * @return Promise Resolves with {invites:[...]}, rejects with an
     *                 OddSocketsException on a 'challenge_invites_query' error.
     */
    public function getChallengeInvites(): PromiseInterface
    {
        if (!$this->client->isConnected()) {
            return \React\Promise\reject(new OddSocketsException('Not connected to OddSockets'));
        }

        $deferred = new Deferred();

        $successHandler = function ($data) use ($deferred) {
            $deferred->resolve($data);
        };

        $errorHandler = function ($data) use ($deferred) {
            if (isset($data['event']) && $data['event'] === 'challenge_invites_query') {
                $deferred->reject(new OddSocketsException($data['message'] ?? 'Unknown error'));
            }
        };

        $this->client->once('challenge_invites', $successHandler);
        $this->client->once('error', $errorHandler);
        $this->client->emitToWorker('challenge_invites_query', []);

        return $deferred->promise();
    }
}
