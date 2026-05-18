# OddSockets PHP SDK

PHP SDK for real-time messaging. Pub/sub, presence, message history. Requires PHP 8.0+.

## Get a free API key
```bash
curl -X POST https://oddsockets.com/api/agent-signup -H "Content-Type: application/json" -d '{"email":"you@example.com","agentName":"my-agent","platform":"php"}'
curl -X POST https://oddsockets.com/api/agent-signup/verify -H "Content-Type: application/json" -d '{"email":"you@example.com","code":"123456","agentName":"my-agent"}'
```

## Key files
- `src/OddSocketsClient.php` — Main client
- `src/Channel.php` — Channel ops
- `src/ManagerDiscovery.php` — Manager URL
- `src/Config/OddSocketsConfig.php` — Config

## Free tier: 100 MAU, 50 connections, 10K msg/day, 10 channels, 100MB/24h
