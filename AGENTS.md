# Agent Integration Guide — PHP

POST https://oddsockets.com/api/agent-signup then /verify with 6-digit code.

```php
$client = new OddSocketsClient(new OddSocketsConfig(['apiKey' => 'ak_...']));
$client->connect();
$ch = $client->channel('agent-coordination');
$ch->subscribe(fn($msg) => print_r($msg));
$ch->publish(['task' => 'summarize']);
```

Free: 100 MAU | 50 connections | 10K msg/day | 10 channels | 100MB/24h
