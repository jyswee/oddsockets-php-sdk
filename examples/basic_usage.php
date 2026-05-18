<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OddSockets\OddSockets;
use OddSockets\Config\OddSocketsConfig;
use React\EventLoop\Loop;

// Example 1: Simple connection with API key
echo "=== Example 1: Simple Connection ===\n";

$client = OddSockets::create('ak_your_api_key_here');

$client->on('connected', function () {
    echo "Connected to OddSockets!\n";
});

$client->on('error', function ($error) {
    echo "Error: " . $error->getMessage() . "\n";
});

// Example 2: Advanced configuration
echo "\n=== Example 2: Advanced Configuration ===\n";

$config = OddSocketsConfig::builder('ak_your_api_key_here')
    ->userId('user123')
    ->autoConnect(false)
    ->reconnectAttempts(3)
    ->timeout(15)
    ->build();

$advancedClient = OddSockets::create($config);

// Example 3: Channel operations
echo "\n=== Example 3: Channel Operations ===\n";

$client->on('connected', function () use ($client) {
    echo "Setting up channel...\n";
    
    $channel = $client->channel('my-channel');
    
    // Subscribe to messages
    $channel->subscribe(function ($message) {
        echo "Received message: " . json_encode($message) . "\n";
    })->then(function () {
        echo "Successfully subscribed to channel\n";
    })->otherwise(function ($error) {
        echo "Subscription failed: " . $error->getMessage() . "\n";
    });
    
    // Publish a message after a short delay
    Loop::get()->addTimer(2.0, function () use ($channel) {
        $channel->publish([
            'text' => 'Hello from PHP SDK!',
            'timestamp' => time(),
            'user' => 'php-client'
        ])->then(function ($result) {
            echo "Message published successfully\n";
        })->otherwise(function ($error) {
            echo "Publish failed: " . $error->getMessage() . "\n";
        });
    });
});

// Example 4: Bulk publishing
echo "\n=== Example 4: Bulk Publishing ===\n";

$client->on('connected', function () use ($client) {
    $messages = [
        [
            'channel' => 'notifications',
            'message' => ['type' => 'info', 'text' => 'System update available'],
            'options' => ['ttl' => 3600]
        ],
        [
            'channel' => 'alerts',
            'message' => ['type' => 'warning', 'text' => 'High CPU usage detected'],
            'options' => ['ttl' => 1800]
        ],
        [
            'channel' => 'logs',
            'message' => ['level' => 'info', 'message' => 'User logged in'],
            'options' => []
        ]
    ];
    
    $client->publishBulk($messages)->then(function ($results) {
        echo "Bulk publish results:\n";
        foreach ($results as $i => $result) {
            if ($result['success']) {
                echo "  Message $i: Success\n";
            } else {
                echo "  Message $i: Failed - " . $result['error'] . "\n";
            }
        }
    });
});

// Example 5: Event handling
echo "\n=== Example 5: Event Handling ===\n";

$client->on('connecting', function () {
    echo "Connecting to OddSockets...\n";
});

$client->on('worker_assigned', function ($data) {
    echo "Assigned to worker: " . $data['workerId'] . "\n";
    echo "Worker URL: " . $data['workerUrl'] . "\n";
});

$client->on('reconnecting', function ($data) {
    echo "Reconnecting... Attempt " . $data['attempt'] . "/" . $data['maxAttempts'] . "\n";
});

$client->on('disconnected', function ($reason) {
    echo "Disconnected: " . $reason . "\n";
});

// Example 6: Channel presence and history
echo "\n=== Example 6: Channel Presence and History ===\n";

$client->on('connected', function () use ($client) {
    $channel = $client->channel('presence-demo');
    
    $channel->subscribe(function ($message) {
        echo "Message: " . json_encode($message) . "\n";
    }, [
        'enablePresence' => true,
        'retainHistory' => true,
        'maxHistory' => 50
    ])->then(function () use ($channel) {
        echo "Subscribed with presence enabled\n";
        
        // Get current presence
        return $channel->getPresence();
    })->then(function ($presence) use ($channel) {
        echo "Current presence: " . json_encode($presence) . "\n";
        
        // Get message history
        return $channel->getHistory(['count' => 10]);
    })->then(function ($history) {
        echo "Message history: " . json_encode($history) . "\n";
    })->otherwise(function ($error) {
        echo "Error: " . $error->getMessage() . "\n";
    });
});

// Example 7: Error handling and validation
echo "\n=== Example 7: Error Handling ===\n";

try {
    // This will fail due to invalid API key format
    $invalidClient = OddSockets::create('invalid_key');
} catch (InvalidArgumentException $e) {
    echo "Caught validation error: " . $e->getMessage() . "\n";
}

// Check API key validity
$apiKey = 'ak_test_key_123';
if (OddSockets::isValidApiKey($apiKey)) {
    echo "API key format is valid\n";
} else {
    echo "API key format is invalid\n";
}

// Get message size limits
$limits = OddSockets::getMessageSizeLimits();
echo "Message size limits: " . json_encode($limits) . "\n";

// Example 8: Manual connection control
echo "\n=== Example 8: Manual Connection Control ===\n";

$manualClient = OddSockets::create([
    'apiKey' => 'ak_your_api_key_here',
    'autoConnect' => false
]);

echo "Client state: " . $manualClient->getState() . "\n";

$manualClient->connect()->then(function () use ($manualClient) {
    echo "Manually connected! State: " . $manualClient->getState() . "\n";
    
    // Get worker info
    $workerInfo = $manualClient->getWorkerInfo();
    if ($workerInfo) {
        echo "Connected to worker: " . json_encode($workerInfo) . "\n";
    }
    
    // Get client identifier for session stickiness
    echo "Client identifier: " . $manualClient->getClientIdentifier() . "\n";
    
    // Disconnect after 5 seconds
    Loop::get()->addTimer(5.0, function () use ($manualClient) {
        $manualClient->disconnect();
        echo "Manually disconnected\n";
    });
})->otherwise(function ($error) {
    echo "Connection failed: " . $error->getMessage() . "\n";
});

echo "\n=== Starting Event Loop ===\n";
echo "Press Ctrl+C to exit\n\n";

// Start the event loop
Loop::get()->run();
