<?php

declare(strict_types=1);

/**
 * OddSockets PHP SDK - enhanced-events two-client regression
 *
 * Proves the RECEIVE path for enhanced (Slack-like) events: an action fired by
 * one client (bob) is broadcast by the worker and surfaces on the OTHER client's
 * public event listener (alice). Because publisher and subscriber are separate
 * connections, an event reaching alice can only have travelled through the
 * OddSockets worker - an honest end-to-end test, no local echo.
 *
 *   bob->enhanced->startTyping(...)  -> alice->on('user_typing')
 *   bob->enhanced->addReaction(...)  -> alice->on('reaction_added')
 */

require_once __DIR__ . '/vendor/autoload.php';

use OddSockets\OddSockets;
use OddSockets\Config\OddSocketsConfig;
use React\EventLoop\Loop;

$apiKey = getenv('ODDSOCKETS_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Missing ODDSOCKETS_API_KEY\n");
    exit(1);
}

$channelName = 'enh-' . bin2hex(random_bytes(5));
$loop = Loop::get();

$alice = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('alice')->autoConnect(false)->timeout(15)->build());
$bob = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('bob')->autoConnect(false)->timeout(15)->build());

$gotTyping = false;
$gotReaction = false;

// Enhanced broadcasts must surface on alice's PUBLIC event surface.
$alice->on('user_typing', function ($d) use (&$gotTyping) {
    if (is_array($d) && ($d['userId'] ?? null) === 'bob') {
        $gotTyping = true;
        echo "[alice] received 'user_typing' from bob (channel " . ($d['channel'] ?? '?') . ") - broadcast round-trip.\n";
    }
});
$alice->on('reaction_added', function ($d) use (&$gotReaction) {
    if (is_array($d) && isset($d['emoji'])) {
        $gotReaction = true;
        echo "[alice] received 'reaction_added' (" . $d['emoji'] . ") from " . ($d['userId'] ?? '?') . " - broadcast round-trip.\n";
    }
});

$finished = false;
$finish = function (int $code, string $msg) use (&$finished, $alice, $bob) {
    if ($finished) return;
    $finished = true;
    echo $msg . "\n";
    try { $alice->disconnect(); } catch (\Throwable $e) {}
    try { $bob->disconnect(); } catch (\Throwable $e) {}
    exit($code);
};

$loop->addTimer(20.0, fn() => $finish(2, "\nTIMEOUT - enhanced broadcast not received within 20s"));

echo "[connect] connecting both clients...\n";

\React\Promise\all([$alice->connect(), $bob->connect()])
    ->then(function () use ($alice, $bob, $channelName, $loop, &$gotTyping, &$gotReaction, $finish) {
        echo "[connect] alice = " . $alice->getState() . ", bob = " . $bob->getState() . "\n";

        $aliceCh = $alice->channel($channelName);
        $bobCh = $bob->channel($channelName);

        return \React\Promise\all([
            $aliceCh->subscribe(function () {}, ['enablePresence' => true]),
            $bobCh->subscribe(function () {}, ['enablePresence' => true]),
        ])->then(function () use ($alice, $bob, $bobCh, $channelName, $loop, &$gotTyping, &$gotReaction, $finish) {
            echo "[both] subscribed to {$channelName}\n";

            // Let room membership settle, then fire enhanced actions.
            $loop->addTimer(0.5, function () use ($alice, $bob, $bobCh, $channelName, $loop, &$gotTyping, &$gotReaction, $finish) {
                echo "[bob] enhanced->startTyping(bob) ...\n";
                $bob->enhanced->startTyping('bob', $channelName);

                $bobCh->publish(['text' => 'react to me'])->then(function ($ack) use ($bob, $channelName) {
                    $msgId = is_array($ack) ? ($ack['messageId'] ?? '?') : '?';
                    echo "[bob] published messageId={$msgId}, enhanced->addReaction :thumbsup: ...\n";
                    $bob->enhanced->addReaction($msgId, $channelName, ':thumbsup:', 'bob', 'Bob');
                });

                // Poll for both broadcasts.
                $poll = $loop->addPeriodicTimer(0.2, function ($timer) use (&$gotTyping, &$gotReaction, $loop, $finish) {
                    if ($gotTyping && $gotReaction) {
                        $loop->cancelTimer($timer);
                        $finish(0, "\nOK - enhanced broadcast receive-path verified (user_typing + reaction_added)");
                    }
                });
            });
        });
    })
    ->otherwise(fn($error) => $finish(1, "FATAL " . (is_object($error) ? $error->getMessage() : (string) $error)));

$loop->run();
