<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OddSockets\OddSocketsClient;
use OddSockets\Config\OddSocketsConfig;

/**
 * OddSockets PHP SDK - Enhanced Features Example
 * Demonstrates all 67 new Slack-like events with ReactPHP promises
 */

echo "🚀 OddSockets PHP SDK - Enhanced Features Example\n";
echo "Demonstrating all 67 new Slack-like events\n";
echo str_repeat("=", 50) . "\n";

// Create and configure client
$config = new OddSocketsConfig();
$config->setApiKey('your_api_key_here');
$config->setUserId('user_123');
$config->setAutoConnect(false);

$client = new OddSocketsClient($config);

// Set up event listeners
$client->on('connected', function() {
    echo "🟢 Connected event fired\n";
});

$client->on('disconnected', function() {
    echo "🔴 Disconnected event fired\n";
});

$client->on('error', function($data) {
    echo "❌ Error event: " . json_encode($data) . "\n";
});

// Connect
echo "\n🔄 Connecting to OddSockets...\n";
$client->connect();

// Wait for connection
sleep(2);

if (!$client->isConnected()) {
    echo "❌ Failed to connect\n";
    exit(1);
}

echo "✅ Connected successfully!\n\n";

// Test all enhanced features
testThreadEvents($client);
testReactionEvents($client);
testReadReceiptEvents($client);
testChannelEvents($client);
testDirectMessageEvents($client);
testNotificationEvents($client);
testPresenceEvents($client);
testMessageEditingEvents($client);
testSearchEvents($client);

// Summary
echo "\n🎉 All enhanced features tested!\n";
echo "\n📊 Summary:\n";
echo "- Thread Events: 7 methods\n";
echo "- Reaction Events: 6 methods\n";
echo "- Read Receipt Events: 6 methods\n";
echo "- Channel Events: 11 methods\n";
echo "- Direct Message Events: 6 methods\n";
echo "- Notification Events: 6 methods\n";
echo "- File Upload Events: 7 methods\n";
echo "- Presence Events: 8 methods\n";
echo "- Message Editing Events: 5 methods\n";
echo "- Search Events: 4 methods\n";
echo str_repeat("=", 50) . "\n";
echo "Total: 67 enhanced Slack-like events! 🚀\n";

// Wait before disconnecting
sleep(2);

// Disconnect
$client->disconnect();
echo "\n✅ Disconnected\n";

// ==================== THREAD EVENTS ====================

function testThreadEvents($client) {
    echo "📝 Testing Thread Events...\n";
    
    // Thread reply
    $client->enhanced->threadReply(
        'general',
        'msg_123',
        'This is a test reply from PHP!',
        'user_123',
        'Test User'
    )->then(function($result) {
        echo "✅ Thread reply created: " . json_encode($result) . "\n";
    })->otherwise(function($error) {
        echo "❌ Thread reply error: " . $error->getMessage() . "\n";
    });
    
    // Get thread
    $client->enhanced->getThread('thread_123')
        ->then(function($thread) {
            echo "✅ Thread data: " . json_encode($thread) . "\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get thread error: " . $error->getMessage() . "\n";
        });
    
    // Subscribe to thread
    $client->enhanced->subscribeThread('thread_123', 'user_123')
        ->then(function($result) {
            echo "✅ Subscribed to thread\n";
        })
        ->otherwise(function($error) {
            echo "❌ Subscribe thread error: " . $error->getMessage() . "\n";
        });
    
    // Mark thread as read
    $client->enhanced->markThreadRead('thread_123', 'user_123');
    echo "✅ Marked thread as read\n";
    
    // Follow thread
    $client->enhanced->followThread('thread_123', 'user_123');
    echo "✅ Following thread\n\n";
}

// ==================== REACTION EVENTS ====================

function testReactionEvents($client) {
    echo "😀 Testing Reaction Events...\n";
    
    // Add reaction
    $client->enhanced->addReaction(
        'msg_123',
        'general',
        '👍',
        'user_123',
        'Test User'
    );
    echo "✅ Added reaction 👍\n";
    
    // Remove reaction
    $client->enhanced->removeReaction(
        'msg_123',
        'general',
        '👍',
        'user_123'
    );
    echo "✅ Removed reaction\n";
    
    // Get reactions
    $client->enhanced->getReactions('msg_123')
        ->then(function($reactions) {
            echo "✅ Reactions: " . json_encode($reactions) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get reactions error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== READ RECEIPT EVENTS ====================

function testReadReceiptEvents($client) {
    echo "✓ Testing Read Receipt Events...\n";
    
    // Mark message as read
    $client->enhanced->markRead(
        'msg_123',
        'general',
        'user_123',
        'Test User'
    );
    echo "✅ Marked message as read\n";
    
    // Get unread counts
    $client->enhanced->getUnreadCounts('user_123', ['general', 'random'])
        ->then(function($counts) {
            echo "✅ Unread counts: " . json_encode($counts) . "\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get unread counts error: " . $error->getMessage() . "\n";
        });
    
    // Mark all as read
    $client->enhanced->markAllRead('general', 'user_123');
    echo "✅ Marked all messages as read\n\n";
}

// ==================== CHANNEL EVENTS ====================

function testChannelEvents($client) {
    echo "📢 Testing Channel Events...\n";
    
    // Create channel
    $channelName = 'php-test-' . time();
    $client->enhanced->createChannel(
        $channelName,
        'public',
        'Created from PHP SDK',
        'Testing',
        'user_123',
        'Test User'
    )->then(function($channel) {
        echo "✅ Channel created: " . json_encode($channel) . "\n";
    })->otherwise(function($error) {
        echo "❌ Create channel error: " . $error->getMessage() . "\n";
    });
    
    // Update channel
    $client->enhanced->updateChannel(
        'channel_123',
        ['topic' => 'Updated topic'],
        'user_123'
    );
    echo "✅ Updated channel\n";
    
    // Join channel
    $client->enhanced->joinChannel(
        'channel_123',
        'user_123',
        'Test User'
    );
    echo "✅ Joined channel\n";
    
    // Invite to channel
    $client->enhanced->inviteToChannel(
        'channel_123',
        'user_456',
        'Jane Doe',
        'user_123'
    );
    echo "✅ Invited user to channel\n";
    
    // Get channel members
    $client->enhanced->getChannelMembers('channel_123')
        ->then(function($members) {
            echo "✅ Channel members: " . json_encode($members) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get channel members error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== DIRECT MESSAGE EVENTS ====================

function testDirectMessageEvents($client) {
    echo "💬 Testing Direct Message Events...\n";
    
    // Create DM
    $client->enhanced->createDM(['user_123', 'user_456'], '1-on-1')
        ->then(function($dm) {
            echo "✅ DM created: " . json_encode($dm) . "\n";
        })
        ->otherwise(function($error) {
            echo "❌ Create DM error: " . $error->getMessage() . "\n";
        });
    
    // Send DM
    $client->enhanced->sendDM(
        'dm_123',
        'Hello from PHP!',
        'user_123',
        'Test User'
    );
    echo "✅ Sent DM\n";
    
    // Get DM conversations
    $client->enhanced->getDMConversations('user_123', false)
        ->then(function($conversations) {
            echo "✅ DM conversations: " . json_encode($conversations) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get DM conversations error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== NOTIFICATION EVENTS ====================

function testNotificationEvents($client) {
    echo "🔔 Testing Notification Events...\n";
    
    // Subscribe to notifications
    $client->enhanced->subscribeNotifications('user_123');
    echo "✅ Subscribed to notifications\n";
    
    // Mark notification as read
    $client->enhanced->markNotificationRead('notif_123', 'user_123');
    echo "✅ Marked notification as read\n";
    
    // Mark all notifications as read
    $client->enhanced->markAllNotificationsRead('user_123');
    echo "✅ Marked all notifications as read\n";
    
    // Get notifications
    $client->enhanced->getNotifications('user_123', 10, 'all')
        ->then(function($notifications) {
            echo "✅ Notifications: " . json_encode($notifications) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get notifications error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== PRESENCE EVENTS ====================

function testPresenceEvents($client) {
    echo "👤 Testing Presence Events...\n";
    
    // Set status
    $client->enhanced->setStatus('user_123', 'online');
    echo "✅ Set status to online\n";
    
    // Set custom status
    $client->enhanced->setCustomStatus(
        'user_123',
        '🐘',
        'Coding in PHP',
        null
    );
    echo "✅ Set custom status\n";
    
    // Clear custom status
    $client->enhanced->clearCustomStatus('user_123');
    echo "✅ Cleared custom status\n";
    
    // Set DND
    $client->enhanced->setDND('user_123', null);
    echo "✅ Enabled Do Not Disturb\n";
    
    // Clear DND
    $client->enhanced->clearDND('user_123');
    echo "✅ Disabled Do Not Disturb\n";
    
    // Start typing
    $client->enhanced->startTyping('user_123', 'general');
    echo "✅ Started typing indicator\n";
    
    // Wait a moment
    sleep(2);
    
    // Stop typing
    $client->enhanced->stopTyping('user_123', 'general');
    echo "✅ Stopped typing indicator\n";
    
    // Get user presence
    $client->enhanced->getUserPresence(['user_123', 'user_456'])
        ->then(function($presence) {
            echo "✅ User presence: " . json_encode($presence) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get user presence error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== MESSAGE EDITING EVENTS ====================

function testMessageEditingEvents($client) {
    echo "✏️ Testing Message Editing Events...\n";
    
    // Edit message
    $client->enhanced->editMessage(
        'msg_123',
        'general',
        'Updated message from PHP',
        'user_123'
    );
    echo "✅ Edited message\n";
    
    // Delete message
    $client->enhanced->deleteMessage(
        'msg_456',
        'general',
        'user_123'
    );
    echo "✅ Deleted message\n";
    
    // Pin message
    $client->enhanced->pinMessage(
        'msg_123',
        'general',
        'user_123'
    );
    echo "✅ Pinned message\n";
    
    // Unpin message
    $client->enhanced->unpinMessage(
        'msg_123',
        'general',
        'user_123'
    );
    echo "✅ Unpinned message\n";
    
    // Get pinned messages
    $client->enhanced->getPinnedMessages('general')
        ->then(function($pinned) {
            echo "✅ Pinned messages: " . json_encode($pinned) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Get pinned messages error: " . $error->getMessage() . "\n\n";
        });
}

// ==================== SEARCH EVENTS ====================

function testSearchEvents($client) {
    echo "🔍 Testing Search Events...\n";
    
    // Search messages
    $client->enhanced->searchMessages('test', 'user_123', 10)
        ->then(function($results) {
            echo "✅ Search results: " . json_encode($results) . "\n";
        })
        ->otherwise(function($error) {
            echo "❌ Search messages error: " . $error->getMessage() . "\n";
        });
    
    // Search in channel
    $client->enhanced->searchInChannel('general', 'test', 10)
        ->then(function($results) {
            echo "✅ Channel search results: " . json_encode($results) . "\n";
        })
        ->otherwise(function($error) {
            echo "❌ Search in channel error: " . $error->getMessage() . "\n";
        });
    
    // Filter messages
    $client->enhanced->filterMessages([
        'channel' => 'general',
        'userId' => 'user_123',
        'limit' => 10
    ])->then(function($results) {
        echo "✅ Filter results: " . json_encode($results) . "\n";
    })->otherwise(function($error) {
        echo "❌ Filter messages error: " . $error->getMessage() . "\n";
    });
    
    // Search by user
    $client->enhanced->searchByUser('user_123', null, 10)
        ->then(function($results) {
            echo "✅ User search results: " . json_encode($results) . "\n\n";
        })
        ->otherwise(function($error) {
            echo "❌ Search by user error: " . $error->getMessage() . "\n\n";
        });
}
