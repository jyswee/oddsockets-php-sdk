# OddSockets PHP SDK - Demo

A tiny, runnable program that proves a real real-time round-trip against OddSockets
using **two independent clients**: **connect -> subscribe -> publish -> receive**.

Because the subscriber (`alice`) and the publisher (`bob`) are separate connections,
a message that reaches the subscriber can only have travelled through the OddSockets
worker - so this doubles as an honest end-to-end regression test (no mocks, no local
echo). The SDK speaks genuine Socket.IO (Engine.IO v4) over a WebSocket to the
assigned worker, exactly like the JavaScript and Python SDKs.

## Proof it's real

`demo/PROOF.txt` is a captured transcript of this demo running in Docker against the
live platform. Reproduce it yourself in one command (see below) - here is a real run:

```
[connect] connecting both clients...
[alice] worker w002-oddsockets-1
[bob]   worker w002-oddsockets-1
[connect] alice = connected, bob = connected
[alice] subscribed to demo-a74e32cbeb (presence on)
[bob] published, ack = { messageId: 722b60d7-..., subscriberCount: 1 }
[alice] received bob's message (nonce matched) - real round-trip.
[alice] presence: 1 user(s).
[alice] unsubscribed.

OK - cross-client round-trip verified
```

## 1. Get a free API key

Two-step email verification (no card required):

```bash
# Step 1 - request a code
curl -X POST https://oddsockets.com/api/agent-signup \
  -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","agentName":"demo","platform":"php"}'

# Step 2 - verify and receive your apiKey
curl -X POST https://oddsockets.com/api/agent-signup/verify \
  -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","code":"123456","agentName":"demo"}'
```

The verify response contains your `apiKey` (starts with `ak_`).

## 2. Run it in Docker (recommended)

No local PHP/Composer toolchain needed. Build from the repo root so the SDK source
is in context:

```bash
docker build -f demo/Dockerfile -t oddsockets-php-demo .
docker run --rm -e ODDSOCKETS_API_KEY="ak_your_key_here" oddsockets-php-demo
```

A successful run prints `OK - cross-client round-trip verified` and exits `0`.

## 2b. Run it locally with PHP

Requires PHP 8.1+ and Composer. `composer install` resolves the SDK from the parent
package via a local `path` repository, so the demo is clone-and-run without
publishing anything.

```bash
cd demo
composer install
export ODDSOCKETS_API_KEY="ak_your_key_here"
php roundtrip.php
```

The key is read from `ODDSOCKETS_API_KEY` and never hardcoded; if it is missing the
script prints the signup instructions above and exits non-zero.

## The code, step by step

Create two clients - a subscriber and a publisher - each on its own connection:

```php
use OddSockets\OddSockets;
use OddSockets\Config\OddSocketsConfig;

$subscriber = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('alice')->autoConnect(false)->build());
$publisher  = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('bob')->autoConnect(false)->build());

React\Promise\all([$subscriber->connect(), $publisher->connect()]);
```

Subscribe on the subscriber (presence enabled):

```php
$inbox = $subscriber->channel('my-channel');
$inbox->subscribe(function ($message) {
    echo "received\n";
}, ['enablePresence' => true]);
```

Publish from the *other* client - this is what makes the test honest:

```php
$outbox = $publisher->channel('my-channel');
$outbox->publish(['text' => 'hello from bob'])->then(function ($ack) {
    echo "messageId: {$ack['messageId']}\n";
});
```

Inspect presence, then tear down cleanly:

```php
$inbox->getPresence()->then(function ($presence) {
    echo "count: {$presence['count']}\n";
});
$inbox->unsubscribe();
$subscriber->disconnect();
$publisher->disconnect();
```

## What it demonstrates

- Manager discovery + automatic worker assignment (fully transparent)
- `client->channel(name)` -> `channel->subscribe(cb, opts)` -> `channel->publish(msg)`
- **Cross-client delivery**: a message published by `bob` is delivered to `alice`'s
  subscription in real time - provably through the worker, not a local echo
- Presence tracking, unsubscribe, and graceful disconnect
- A 15-second timeout so a stalled round-trip is reported as a failure (non-zero exit)

## Files

- `Dockerfile` - builds the SDK from source and runs the two-client demo on `php:8.2-cli`.
- `PROOF.txt` - captured transcript of a real containerised run against the platform.
- `roundtrip.php` - the two-client round-trip program.
- `composer.json` - resolves the SDK via a local `path` repository.
