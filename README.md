# OddSockets PHP SDK

Official PHP SDK for OddSockets real-time messaging platform. Pub/sub, presence, message history.

## Install

```bash
composer require jyswee/oddsockets-php-sdk
```

## Quick Start

```php
use OddSockets\OddSockets;
use React\EventLoop\Loop;

$client = OddSockets::create(['apiKey' => 'YOUR_API_KEY', 'userId' => 'my-agent']);

$client->connect()->then(function () use ($client) {
    $channel = $client->channel('my-channel');
    $channel->subscribe(function ($msg) {
        echo "Received: " . json_encode($msg) . "\n";
    });
    $channel->publish(['text' => 'Hello from PHP']);
});

Loop::get()->run();
```

The SDK is built on [ReactPHP](https://reactphp.org/) — `connect()`, `subscribe()`
and `publish()` return promises and run on the event loop.

## Manager URL

The client asks a manager for a worker. Point it at a self-hosted or QA manager
with `managerUrl`:

```php
$client = OddSockets::create([
    'apiKey' => 'YOUR_API_KEY',
    'managerUrl' => 'https://manager.internal.example',
]);
```

Resolution order, highest first:

1. `managerUrl` in the config array / `OddSocketsConfigBuilder::managerUrl()`
2. the `ODDSOCKETS_MANAGER_URL` environment variable
3. the public endpoint `https://connect.oddsockets.tyga.network`

Whatever resolves is used verbatim. If it is unreachable the connection fails
with that error — the SDK never quietly falls back to another manager, because
that would send a QA or self-hosted deployment to production unnoticed. A value
that is not an absolute `http://` or `https://` URL throws an
`InvalidArgumentException` reading `Invalid managerUrl: <value>`.

## Token auth for game clients (`tokenProvider`)

Game and app clients should never ship a static API key. Instead, mint a
short-lived realtime token from your own backend and hand it to the SDK through a
`tokenProvider` callback. The client resolves a **fresh** token before every
(re)connect, presents it on the manager/worker handshake in place of an API key,
and silently refreshes it ahead of expiry.

The callback returns a token string, an array shaped like the mint response
(`['token' => ..., 'expiresAt' => ..., 'exp' => ...]`), or a React promise
resolving to either — so it can run its own async HTTP on the event loop:

```php
use OddSockets\Config\OddSocketsConfig;
use OddSockets\OddSocketsClient;
use React\Http\Browser;

$http = new Browser();

$config = OddSocketsConfig::builderWithTokenProvider(function () use ($http) {
    // Your backend exchanges the player's session for a realtime token.
    return $http->post('https://your-backend.example/realtime-token')
        ->then(fn ($resp) => json_decode((string) $resp->getBody(), true));
    // -> ['token' => 'eyJ...', 'expiresAt' => '2026-01-01T00:00:00Z']
})
    ->userId('player-42')
    ->build();

$client = new OddSocketsClient($config);

// Fired after each silent pre-expiry refresh.
$client->on('token_refreshed', function (array $info) {
    // $info['expiresAt'] — epoch ms of the new token
});
```

No `apiKey` is required when a `tokenProvider` is set. Tune how early the token
refreshes with `->tokenRefreshLeadMs(120000)` (default two minutes).

## Enhanced Features

Beyond core pub/sub, OddSockets ships a Slack-like **enhanced surface** — reactions,
typing indicators, threads, read receipts, presence/status, notifications, DMs,
channel management, message editing and search. It lives on the public
`$client->enhanced` property. The pattern is always the same:

1. **Send** an action with a `$client->enhanced->*` method (camelCase).
2. **Receive** the paired broadcast with `$client->on('<event>', $handler)`.

```php
use OddSockets\OddSockets;
use React\EventLoop\Loop;

$client = OddSockets::create(['apiKey' => 'YOUR_API_KEY', 'userId' => 'alice']);

$client->connect()->then(function () use ($client) {
    $channel = $client->channel('room-42');
    $channel->subscribe(function ($msg) {}, ['enablePresence' => true]);

    // Receive-path: broadcasts from other users on the channel
    $client->on('user_typing', fn($e) => print("{$e['userId']} is typing\n"));
    $client->on('reaction_added', fn($e) => print("{$e['userId']} reacted {$e['emoji']}\n"));
    $client->on('thread_reply', fn($e) => print("New reply\n"));

    // Send-path: enhanced actions over the live socket
    $client->enhanced->startTyping('alice', 'room-42');
    $client->enhanced->addReaction('msg-1', 'room-42', ':thumbsup:', 'alice', 'Alice');
    $client->enhanced->threadReply('room-42', 'msg-1', 'Replying in the thread', 'alice', 'Alice');
});

Loop::get()->run();
```

Each area exposes methods on `$client->enhanced`; the worker broadcasts the paired
events which you handle with `$client->on(...)`. Query methods (`get*`, `search*`)
return ReactPHP promises that resolve with the worker response.

| Area | Requests (`$client->enhanced->*`) | Broadcast events (`$client->on`) |
|------|-----------------------------------|----------------------------------|
| Typing | `startTyping`, `stopTyping` | `user_typing`, `user_stopped_typing` |
| Reactions | `addReaction`, `removeReaction`, `getReactions` | `reaction_added`, `reaction_removed` |
| Threads | `threadReply`, `getThread`, `subscribeThread`, `followThread`, `markThreadRead` | `thread_reply`, `thread_subscribed`, `thread_followed`, `thread_read_updated` |
| Read receipts | `markRead`, `markAllRead`, `getUnreadCounts` | `user_read`, `unread_count_updated`, `all_marked_read` |
| Messages | `editMessage`, `deleteMessage`, `pinMessage`, `unpinMessage`, `getPinnedMessages`, `searchMessages` | `message_edited`, `message_deleted`, `message_pinned`, `message_unpinned` |
| Presence & status | `setStatus`, `setCustomStatus`, `setDND`, `getUserPresence` | `user_status_changed`, `custom_status_updated`, `dnd_status_changed` |
| Channels | `createChannel`, `updateChannel`, `archiveChannel`, `inviteToChannel`, `joinChannel`, `leaveChannel` | `channel_created`, `channel_updated`, `user_invited`, `user_joined_channel`, `user_left_channel` |
| DMs | `createDM`, `sendDM`, `getDMConversations` | `dm_created`, `dm_received` |
| Notifications | `subscribeNotifications`, `getNotifications`, `markNotificationRead`, `clearNotifications` | `notification`, `notification_read`, `notifications_cleared` |
| Search | `searchMessages`, `searchInChannel`, `searchByUser`, `filterMessages` | (promise results) |

For any worker event not wrapped above, subscribe with the raw
`$client->on('<event>', $handler)` API — all enhanced broadcasts are forwarded onto
the client surface.

## Get an API Key

```bash
curl -X POST https://oddsockets.com/api/agent-signup \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "agentName": "my-agent", "platform": "php"}'
# Verify with 6-digit code:
curl -X POST https://oddsockets.com/api/agent-signup/verify \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "code": "123456", "agentName": "my-agent"}'
```

## Plans

No free tier — every plan starts with a 7-day free trial.

| | Starter | Pro | Scale | Enterprise |
|---|---|---|---|---|
| **Price** | $29/mo | $99/mo | $299/mo | Contact sales |
| **Messages/mo** | 5M | 25M | 100M | Unlimited |
| **Peak connections** | 200 | 1,000 | 5,000 | Unlimited |
| **MAU** | Unlimited | Unlimited | Unlimited | Unlimited |
| **Extra messages** | $2.50/M | $1.60/M | $1.00/M | Included |

Current pricing: [oddsockets.com/#pricing](https://oddsockets.com/#pricing).

All limits are enforced in real time.

## Get Accredited

<a href="https://tyga.games/accreditation"><img src="https://prodmedia.tyga.host/public/tyga.cloud/landing/tyga.games/tygagames-black-words.svg" alt="tyga.games accreditation" height="44"></a>

Prove you can build and operate real-time features on OddSockets — channels, presence, pub/sub, delivery guarantees and production liveops — on the stack itself. Three tiers (**TCU / TCA / TCP**), certified through **tyga.games** and delivered on ClassaaS.

[**Get accredited on tyga.games →**](https://tyga.games/accreditation)

## Support

- [Documentation](https://docs.oddsockets.com/sdks/php)
- [Issue Tracker](https://github.com/jyswee/oddsockets-php-sdk/issues)
- [Email Support](mailto:support@oddsockets.com)

## License

MIT License - Copyright (c) 2026 Joe Wee, Tyga.Cloud Ltd. See [LICENSE](LICENSE) for details.
