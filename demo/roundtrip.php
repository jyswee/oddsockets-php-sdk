<?php

declare(strict_types=1);

/**
 * OddSockets PHP SDK - runnable two-client demo
 *
 * A genuine end-to-end round-trip using TWO independent clients:
 *   - a SUBSCRIBER (user "alice") that listens on a channel
 *   - a PUBLISHER  (user "bob")   that sends one message
 *
 * Because they are separate connections, a message reaching the subscriber can
 * ONLY have travelled through the OddSockets worker - it cannot be a local echo.
 * A matched nonce here is proof of a real round-trip. Uses the SAME SDK a
 * consumer installs, over real Socket.IO. No mocks.
 *
 * Exercised surface: connect -> subscribe (+presence) -> publish -> receive
 * -> presence -> unsubscribe -> disconnect.
 */

require_once __DIR__ . '/vendor/autoload.php';

use OddSockets\OddSockets;
use OddSockets\Config\OddSocketsConfig;
use React\EventLoop\Loop;

$apiKey = getenv('ODDSOCKETS_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Missing ODDSOCKETS_API_KEY. Get a free key (see README), then:\n");
    fwrite(STDERR, "  export ODDSOCKETS_API_KEY=\"ak_...\"\n");
    exit(1);
}

// A unique channel and nonce so we only ever match our own run.
$channelName = 'demo-' . bin2hex(random_bytes(5));
$nonce = bin2hex(random_bytes(8));

$loop = Loop::get();

// Two independent clients on the same platform.
$subscriber = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('alice')->autoConnect(false)->timeout(15)->build());
$publisher = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('bob')->autoConnect(false)->timeout(15)->build());

$subscriber->on('worker_assigned', function ($w) {
    echo "[alice] worker " . ($w['workerId'] ?? '?') . "\n";
});
$publisher->on('worker_assigned', function ($w) {
    echo "[bob]   worker " . ($w['workerId'] ?? '?') . "\n";
});
// The SDK surfaces two kinds of "error": transport-level Throwables and
// worker application errors delivered as an associative array ({type,message}).
$describeError = function ($e): string {
    if ($e instanceof \Throwable) {
        return $e->getMessage();
    }
    if (is_array($e)) {
        return ($e['type'] ?? 'ERROR') . ': ' . ($e['message'] ?? json_encode($e));
    }
    return (string) $e;
};
$subscriber->on('error', function ($e) use ($describeError) {
    fwrite(STDERR, "[alice] error " . $describeError($e) . "\n");
});
$publisher->on('error', function ($e) use ($describeError) {
    fwrite(STDERR, "[bob]   error " . $describeError($e) . "\n");
});

// Pull the nonce out of whatever envelope shape the worker delivers.
$extractNonce = function ($payload): ?string {
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['nonce'])) {
        return (string) $payload['nonce'];
    }
    foreach (['message', 'data'] as $wrap) {
        if (isset($payload[$wrap]) && is_array($payload[$wrap]) && isset($payload[$wrap]['nonce'])) {
            return (string) $payload[$wrap]['nonce'];
        }
    }
    return null;
};

$finished = false;
$finish = function (int $code, string $msg) use (&$finished, $subscriber, $publisher) {
    if ($finished) {
        return;
    }
    $finished = true;
    echo $msg . "\n";
    try { $subscriber->disconnect(); } catch (\Throwable $e) {}
    try { $publisher->disconnect(); } catch (\Throwable $e) {}
    exit($code);
};

// Overall timeout: a stalled round-trip is a failure.
$loop->addTimer(15.0, function () use ($finish) {
    $finish(2, "\nTIMEOUT - no cross-client delivery within 15s");
});

echo "[connect] connecting both clients...\n";

\React\Promise\all([$subscriber->connect(), $publisher->connect()])
    ->then(function () use ($subscriber, $publisher, $channelName, $nonce, $extractNonce, $finish) {
        echo "[connect] alice = " . $subscriber->getState() . ", bob = " . $publisher->getState() . "\n";

        // Subscriber joins with presence enabled.
        $inbox = $subscriber->channel($channelName);

        $inbox->subscribe(function ($message) use ($inbox, $nonce, $extractNonce, $finish) {
            if ($extractNonce($message) !== $nonce) {
                return;
            }
            echo "[alice] received bob's message (nonce matched) - real round-trip.\n";
            $done = function () use ($finish) {
                $finish(0, "\nOK - cross-client round-trip verified");
            };
            $inbox->getPresence()->then(function ($presence) use ($inbox, $done) {
                $count = $presence['count'] ?? $presence['occupancy'] ?? count($presence['occupants'] ?? []);
                echo "[alice] presence: {$count} user(s).\n";
                $inbox->unsubscribe()->then(function () use ($done) {
                    echo "[alice] unsubscribed.\n";
                    $done();
                }, $done);
            }, $done);
        }, ['enablePresence' => true])->then(function () use ($subscriber, $publisher, $channelName, $nonce) {
            echo "[alice] subscribed to {$channelName} (presence on)\n";

            // Publisher sends from its OWN connection.
            $outbox = $publisher->channel($channelName);
            return $outbox->publish([
                'text'  => 'hello from bob',
                'nonce' => $nonce,
                'from'  => 'bob',
            ]);
        })->then(function ($ack) {
            $msgId = is_array($ack) ? ($ack['messageId'] ?? '?') : '?';
            $subs = is_array($ack) ? ($ack['subscriberCount'] ?? '?') : '?';
            echo "[bob] published, ack = { messageId: {$msgId}, subscriberCount: {$subs} }\n";
        })->otherwise(function ($error) use ($finish) {
            $finish(3, "FATAL " . $error->getMessage());
        });
    })->otherwise(function ($error) use ($finish) {
        $finish(1, "FATAL " . $error->getMessage());
    });

$loop->run();
