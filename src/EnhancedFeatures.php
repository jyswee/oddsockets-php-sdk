<?php

namespace OddSockets;

use OddSockets\Exception\OddSocketsException;
use React\Promise\Promise;
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
    ): Promise {
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
        $this->client->emit('thread_reply', $params);

        return $deferred->promise();
    }

    public function getThread(string $threadId): Promise
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
        $this->client->emit('get_thread', ['threadId' => $threadId]);

        return $deferred->promise();
    }

    public function subscribeThread(string $threadId, string $userId): Promise
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
        $this->client->emit('subscribe_thread', ['threadId' => $threadId, 'userId' => $userId]);

        return $deferred->promise();
    }

    public function markThreadRead(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('mark_thread_read', ['threadId' => $threadId, 'userId' => $userId]);
    }

    public function followThread(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('follow_thread', ['threadId' => $threadId, 'userId' => $userId]);
    }

    public function unfollowThread(string $threadId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('unfollow_thread', ['threadId' => $threadId, 'userId' => $userId]);
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
        
        $this->client->emit('add_reaction', [
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
        
        $this->client->emit('remove_reaction', [
            'messageId' => $messageId,
            'channel' => $channel,
            'emoji' => $emoji,
            'userId' => $userId
        ]);
    }

    public function getReactions(string $messageId): Promise
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
        $this->client->emit('get_reactions', ['messageId' => $messageId]);

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
        
        $this->client->emit('mark_read', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function getUnreadCounts(string $userId, array $channels): Promise
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
        $this->client->emit('get_unread_counts', ['userId' => $userId, 'channels' => $channels]);

        return $deferred->promise();
    }

    public function markAllRead(string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('mark_all_read', ['channel' => $channel, 'userId' => $userId]);
    }

    // MARK: - Channel Events

    public function createChannel(
        string $name,
        string $type,
        string $description,
        string $topic,
        string $createdBy,
        string $createdByName
    ): Promise {
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
        $this->client->emit('create_channel', $params);

        return $deferred->promise();
    }

    public function updateChannel(string $channelId, array $updates, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('update_channel', [
            'channelId' => $channelId,
            'updates' => $updates,
            'userId' => $userId
        ]);
    }

    public function archiveChannel(string $channelId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('archive_channel', ['channelId' => $channelId, 'userId' => $userId]);
    }

    public function inviteToChannel(
        string $channelId,
        string $invitedUserId,
        string $invitedUserName,
        string $invitedBy
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('invite_to_channel', [
            'channelId' => $channelId,
            'invitedUserId' => $invitedUserId,
            'invitedUserName' => $invitedUserName,
            'invitedBy' => $invitedBy
        ]);
    }

    public function removeFromChannel(string $channelId, string $removedUserId, string $removedBy): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('remove_from_channel', [
            'channelId' => $channelId,
            'removedUserId' => $removedUserId,
            'removedBy' => $removedBy
        ]);
    }

    public function joinChannel(string $channelId, string $userId, string $userName): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('join_channel', [
            'channelId' => $channelId,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function leaveChannel(string $channelId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('leave_channel', ['channelId' => $channelId, 'userId' => $userId]);
    }

    public function getChannelMembers(string $channelId): Promise
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
        $this->client->emit('get_channel_members', ['channelId' => $channelId]);

        return $deferred->promise();
    }

    // MARK: - Direct Message Events

    public function createDM(array $userIds, string $type): Promise
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
        $this->client->emit('create_dm', ['userIds' => $userIds, 'type' => $type]);

        return $deferred->promise();
    }

    public function sendDM(
        string $conversationId,
        string $message,
        string $userId,
        string $userName
    ): void {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('send_dm', [
            'conversationId' => $conversationId,
            'message' => $message,
            'userId' => $userId,
            'userName' => $userName
        ]);
    }

    public function getDMConversations(string $userId, bool $includeArchived): Promise
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
        $this->client->emit('get_dm_conversations', [
            'userId' => $userId,
            'includeArchived' => $includeArchived
        ]);

        return $deferred->promise();
    }

    // MARK: - Notification Events

    public function subscribeNotifications(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('subscribe_notifications', ['userId' => $userId]);
    }

    public function markNotificationRead(string $notificationId, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('mark_notification_read', [
            'notificationId' => $notificationId,
            'userId' => $userId
        ]);
    }

    public function markAllNotificationsRead(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('mark_all_notifications_read', ['userId' => $userId]);
    }

    public function clearNotifications(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('clear_notifications', ['userId' => $userId]);
    }

    public function getNotifications(string $userId, int $limit, ?string $status = null): Promise
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
        $this->client->emit('get_notifications', $params);

        return $deferred->promise();
    }

    // MARK: - Presence Events

    public function setStatus(string $userId, string $status): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('set_status', ['userId' => $userId, 'status' => $status]);
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
        
        $this->client->emit('set_custom_status', $params);
    }

    public function clearCustomStatus(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('clear_custom_status', ['userId' => $userId]);
    }

    public function setDND(string $userId, ?string $until = null): void
    {
        if (!$this->client->isConnected()) return;
        
        $params = ['userId' => $userId];
        if ($until !== null) {
            $params['until'] = $until;
        }
        
        $this->client->emit('set_dnd', $params);
    }

    public function clearDND(string $userId): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('clear_dnd', ['userId' => $userId]);
    }

    public function startTyping(string $userId, string $channel): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('start_typing', ['userId' => $userId, 'channel' => $channel]);
    }

    public function stopTyping(string $userId, string $channel): void
    {
        if (!$this->client->isConnected()) return;
        $this->client->emit('stop_typing', ['userId' => $userId, 'channel' => $channel]);
    }

    public function getUserPresence(array $userIds): Promise
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
        $this->client->emit('get_user_presence', ['userIds' => $userIds]);

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
        
        $this->client->emit('edit_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'newContent' => $newContent,
            'userId' => $userId
        ]);
    }

    public function deleteMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('delete_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function pinMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('pin_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function unpinMessage(string $messageId, string $channel, string $userId): void
    {
        if (!$this->client->isConnected()) return;
        
        $this->client->emit('unpin_message', [
            'messageId' => $messageId,
            'channel' => $channel,
            'userId' => $userId
        ]);
    }

    public function getPinnedMessages(string $channel): Promise
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
        $this->client->emit('get_pinned_messages', ['channel' => $channel]);

        return $deferred->promise();
    }

    // MARK: - Search Events

    public function searchMessages(string $query, string $userId, int $limit): Promise
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
        $this->client->emit('search_messages', [
            'query' => $query,
            'userId' => $userId,
            'limit' => $limit
        ]);

        return $deferred->promise();
    }

    public function filterMessages(array $filters): Promise
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
        $this->client->emit('filter_messages', $filters);

        return $deferred->promise();
    }

    public function searchInChannel(string $channel, string $query, int $limit): Promise
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
        $this->client->emit('search_in_channel', [
            'channel' => $channel,
            'query' => $query,
            'limit' => $limit
        ]);

        return $deferred->promise();
    }

    public function searchByUser(string $userId, ?string $query = null, int $limit = 10): Promise
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
        $this->client->emit('search_by_user', $params);

        return $deferred->promise();
    }
}
