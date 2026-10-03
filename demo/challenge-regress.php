<?php

declare(strict_types=1);

/**
 * OddSockets PHP SDK - Challenge/Leaderboard/Achievement two-client regression.
 *
 * HONEST cross-client test against the LIVE QA platform via the manager LB.
 * Two clients (alice + bob), distinct userId, SAME apiKey (shared owner scope),
 * both subscribed to 'lobby'. Every assertion that claims a broadcast reached a
 * peer is checked on the OTHER client's public event surface, so it can only
 * have travelled through the OddSockets worker(s) - no local echo.
 *
 * Autoload: uses the SDK's OWN vendor autoloader (which maps both the OddSockets
 * namespace and ReactPHP), so it runs natively without a separate composer
 * install for the demo. The Dockerfile path is equivalent (composer symlink).
 */

$autoload = getenv('OS_AUTOLOAD') ?: (__DIR__ . '/../vendor/autoload.php');
require_once $autoload;

use OddSockets\OddSockets;
use OddSockets\Config\OddSocketsConfig;
use React\EventLoop\Loop;

$apiKey     = getenv('OS_KEY') ?: getenv('ODDSOCKETS_API_KEY');
$managerUrl = getenv('ODDSOCKETS_MANAGER_URL');

if (!$apiKey) { fwrite(STDERR, "Missing OS_KEY\n"); exit(1); }
if (!$managerUrl) { fwrite(STDERR, "Missing ODDSOCKETS_MANAGER_URL\n"); exit(1); }

$loop = Loop::get();
$run  = bin2hex(random_bytes(4));
$challengeId   = "chal-$run";
$achievementId = "ach-$run";

// ---- Assertion ledger ----------------------------------------------------
$results = [];
$note = function (string $name, bool $ok, string $detail = '') use (&$results) {
    $results[$name] = ['ok' => $ok, 'detail' => $detail];
    printf("[assert] %-42s %s%s\n", $name, $ok ? 'PASS' : 'FAIL', $detail ? "  ($detail)" : '');
};

$aliceWorker = '?'; $bobWorker = '?';

$alice = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('alice')->managerUrl($managerUrl)->autoConnect(false)->timeout(15)->build());
$bob = OddSockets::create(OddSocketsConfig::builder($apiKey)
    ->userId('bob')->managerUrl($managerUrl)->autoConnect(false)->timeout(15)->build());

// Captured only so the summary can report whether the two clients landed on
// the same instance or different ones. The id itself is never printed.
$alice->on('worker_assigned', function ($w) use (&$aliceWorker) {
    $aliceWorker = $w['workerId'] ?? '?'; });
$bob->on('worker_assigned', function ($w) use (&$bobWorker) {
    $bobWorker = $w['workerId'] ?? '?'; });

$describe = function ($e): string {
    if ($e instanceof \Throwable) return $e->getMessage();
    if (is_array($e)) return ($e['type'] ?? 'ERR') . ': ' . ($e['message'] ?? json_encode($e));
    return (string) $e;
};
$alice->on('error', function ($e) use ($describe) { fwrite(STDERR, "[alice] error " . $describe($e) . "\n"); });
$bob->on('error', function ($e) use ($describe) { fwrite(STDERR, "[bob]   error " . $describe($e) . "\n"); });

// ---- Inbound broadcast capture (public event surface) --------------------
$rx = [
    'alice_progress' => [], 'alice_rankchange' => [], 'alice_complete' => [],
    'alice_reply' => [],
    'bob_ach_progress' => [], 'bob_ach_unlock' => [], 'bob_invited' => [],
    'bob_cancelled' => [],
    'alice_invited' => [], // must stay EMPTY (inviter must not see own invite)
    'bob_ach_unlock_banner' => [], // achievement_unlock at <100 would be wrong
];
$get = fn($p, $k, $d = null) => is_array($p) ? ($p[$k] ?? $d) : $d;
// Some worker broadcasts wrap their fields in a {version,type,data:{...}}
// envelope (achievements); others are flat (invites). Unwrap to the payload
// that actually carries the domain fields. Declared as a named function so it
// is callable from any nested promise closure without threading it through use().
function os_body($p) { return (is_array($p) && isset($p['data']) && is_array($p['data'])) ? $p['data'] : $p; }

$alice->on('challenge_progress',       function ($d) use (&$rx) { $rx['alice_progress'][] = $d; });
$alice->on('leaderboard_rank_change',  function ($d) use (&$rx) { $rx['alice_rankchange'][] = $d; });
$alice->on('challenge_complete',       function ($d) use (&$rx) { $rx['alice_complete'][] = $d; });
$alice->on('challenge_reply_received', function ($d) use (&$rx) { $rx['alice_reply'][] = $d; });
$alice->on('challenge_invited',        function ($d) use (&$rx) { $rx['alice_invited'][] = $d; });

$bob->on('achievement_progress',       function ($d) use (&$rx) { $rx['bob_ach_progress'][] = $d; if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW achievement_progress=" . json_encode($d) . "\n"); });
$bob->on('achievement_unlock',         function ($d) use (&$rx) { $rx['bob_ach_unlock'][] = $d; if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW achievement_unlock=" . json_encode($d) . "\n"); });
$bob->on('challenge_invited',          function ($d) use (&$rx) { $rx['bob_invited'][] = $d; if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW challenge_invited=" . json_encode($d) . "\n"); });
$bob->on('challenge_invite_cancelled', function ($d) use (&$rx) { $rx['bob_cancelled'][] = $d; if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW challenge_invite_cancelled=" . json_encode($d) . "\n"); });

$finished = false;
$finish = function (int $code, string $msg) use (&$finished, $alice, $bob) {
    if ($finished) return; $finished = true;
    echo $msg . "\n";
    try { $alice->disconnect(); } catch (\Throwable $e) {}
    try { $bob->disconnect(); } catch (\Throwable $e) {}
    Loop::get()->addTimer(0.3, fn() => exit($code));
};

// A small promise-timeout helper: reject if the promise doesn't settle.
$withTimeout = function ($promise, float $secs, string $label) use ($loop) {
    $d = new \React\Promise\Deferred();
    $timer = $loop->addTimer($secs, function () use ($d, $label) {
        $d->reject(new \RuntimeException("timeout waiting for $label"));
    });
    $promise->then(
        function ($v) use ($d, $timer, $loop) { $loop->cancelTimer($timer); $d->resolve($v); },
        function ($e) use ($d, $timer, $loop) { $loop->cancelTimer($timer); $d->reject($e); }
    );
    return $d->promise();
};
// Wait for a condition (polled) up to $secs.
$waitFor = function (callable $cond, float $secs, string $label) use ($loop) {
    $d = new \React\Promise\Deferred();
    $deadline = microtime(true) + $secs;
    $t = $loop->addPeriodicTimer(0.15, function ($timer) use ($cond, $d, $loop, $deadline, $label) {
        if ($cond()) { $loop->cancelTimer($timer); $d->resolve(true); }
        elseif (microtime(true) > $deadline) { $loop->cancelTimer($timer); $d->resolve(false); }
    });
    return $d->promise();
};
$sleep = function (float $s) use ($loop) {
    $d = new \React\Promise\Deferred();
    $loop->addTimer($s, fn() => $d->resolve(null));
    return $d->promise();
};

$loop->addTimer(120.0, fn() => $finish(2, "\nTIMEOUT - regression did not complete within 120s"));

echo "[connect] connecting alice + bob via manager $managerUrl ...\n";

\React\Promise\all([$alice->connect(), $bob->connect()])->then(function () use (
    $alice, $bob, $loop, $challengeId, $achievementId, $note, $get, &$rx,
    $withTimeout, $waitFor, $sleep, $finish, $describe
) {
    echo "[connect] alice=" . $alice->getState() . " bob=" . $bob->getState() . "\n";
    $aCh = $alice->channel('lobby');
    $bCh = $bob->channel('lobby');

    return \React\Promise\all([
        $aCh->subscribe(function () {}, ['enablePresence' => true]),
        $bCh->subscribe(function () {}, ['enablePresence' => true]),
    ])->then(function () use (
        $alice, $bob, $loop, $challengeId, $achievementId, $note, $get, &$rx,
        $withTimeout, $waitFor, $sleep, $finish, $describe
    ) {
        echo "[both] subscribed to 'lobby'\n";
        return $sleep(0.6)->then(function () use (
            $alice, $bob, $challengeId, $achievementId, $note, $get, &$rx,
            $withTimeout, $waitFor, $sleep, $describe
        ) {
            // ---- 1. createChallenge -------------------------------------
            return $withTimeout($alice->enhanced->createChallenge([
                'challengeId' => $challengeId, 'metric' => 'score',
                'ranked' => true, 'channel' => 'lobby',
            ]), 12.0, 'challenge_create')->then(function ($ack) use ($note) {
                $note('1 createChallenge acked', true, 'challengeId=' . ($ack['challengeId'] ?? '?'));
            })->then(function () use (
                $alice, $bob, $challengeId, $achievementId, $note, $get, &$rx,
                $withTimeout, $waitFor, $sleep, $describe
            ) {
                // ---- 2. reportProgress alice=40, bob=55 -----------------
                $alice->enhanced->reportProgress([
                    'challengeId' => $challengeId, 'metric' => 'score', 'value' => 40,
                    'eventId' => 'a-40-' . bin2hex(random_bytes(3))]);
                $bob->enhanced->reportProgress([
                    'challengeId' => $challengeId, 'metric' => 'score', 'value' => 55,
                    'eventId' => 'b-55-' . bin2hex(random_bytes(3))]);
                // alice's client should see challenge_progress + leaderboard_rank_change
                return $waitFor(fn() => count($rx['alice_progress']) > 0 && count($rx['alice_rankchange']) > 0,
                    12.0, 'alice sees progress+rankchange')->then(function () use ($note, &$rx) {
                    $note('2 alice sees challenge_progress', count($rx['alice_progress']) > 0,
                        count($rx['alice_progress']) . ' event(s)');
                    $note('2 alice sees leaderboard_rank_change', count($rx['alice_rankchange']) > 0,
                        count($rx['alice_rankchange']) . ' event(s)');
                });
            })->then(function () use (
                $alice, $bob, $challengeId, $achievementId, $note, $get, &$rx,
                $withTimeout, $waitFor, $sleep, $describe
            ) {
                // ---- 3. getStandings (as bob) ---------------------------
                return $withTimeout($bob->enhanced->getStandings([
                    'challengeId' => $challengeId, 'limit' => 10,
                ]), 12.0, 'challenge_standings')->then(function ($ack) use ($note, $get) {
                    $standings = $get($ack, 'standings', []);
                    $byRank = [];
                    foreach ($standings as $s) { $byRank[(int) $get($s, 'rank')] = $s; }
                    $r1 = $byRank[1] ?? null; $r2 = $byRank[2] ?? null;
                    $ok = $r1 && $r2
                        && (int) $get($r1, 'value') === 55
                        && (int) $get($r2, 'value') === 40
                        && (int) $get($ack, 'yourRank') === 1; // bob asked -> bob is rank 1
                    $detail = 'rank1=' . ($r1 ? $get($r1,'identity').'@'.$get($r1,'value') : 'none')
                        . ' rank2=' . ($r2 ? $get($r2,'identity').'@'.$get($r2,'value') : 'none')
                        . ' yourRank=' . $get($ack, 'yourRank');
                    $note('3 getStandings bob@55#1 alice@40#2', $ok, $detail);
                });
            })->then(function () use (
                $alice, $bob, $challengeId, $achievementId, $note, $get, &$rx,
                $withTimeout, $waitFor, $sleep, $describe
            ) {
                // ---- 4. completeChallenge -------------------------------
                return $withTimeout($alice->enhanced->completeChallenge([
                    'challengeId' => $challengeId, 'outcome' => 'tied',
                    'eventId' => 'a-done-' . bin2hex(random_bytes(3)),
                ]), 12.0, 'alice complete')->then(function ($ack) use ($note, $get) {
                    $ok = $get($ack,'outcome') === 'tied'
                        && (int) $get($ack,'finalValue') === 40
                        && (int) $get($ack,'rank') === 2;
                    $note('4a alice complete(tied)=fv40 rank2', $ok,
                        'outcome=' . $get($ack,'outcome') . ' fv=' . $get($ack,'finalValue') . ' rank=' . $get($ack,'rank'));
                })->then(function () use ($bob, $challengeId, $note, $get, $withTimeout) {
                    return $withTimeout($bob->enhanced->completeChallenge([
                        'challengeId' => $challengeId, 'outcome' => 'conceded',
                        'eventId' => 'b-done-' . bin2hex(random_bytes(3)),
                    ]), 12.0, 'bob complete')->then(function ($ack) use ($note, $get) {
                        $ok = $get($ack,'outcome') === 'conceded'
                            && (int) $get($ack,'finalValue') === 55
                            && (int) $get($ack,'rank') === 1;
                        $note('4b bob complete(conceded)=fv55 rank1', $ok,
                            'outcome=' . $get($ack,'outcome') . ' fv=' . $get($ack,'finalValue') . ' rank=' . $get($ack,'rank'));
                    });
                });
            })->then(function () use (
                $alice, $bob, $achievementId, $note, $get, &$rx,
                $withTimeout, $waitFor, $sleep, $describe
            ) {
                // ---- 5a. unlockAchievement(50) -> bob progress, no banner
                $rx['bob_ach_progress'] = []; $rx['bob_ach_unlock'] = [];
                $alice->enhanced->unlockAchievement([
                    'achievementId' => $achievementId, 'name' => 'Halfway',
                    'percentComplete' => 50, 'channel' => 'lobby']);
                return $waitFor(fn() => count($rx['bob_ach_progress']) > 0, 12.0, 'bob ach progress')
                    ->then(function () use ($note, &$rx, $get, $sleep) {
                        $ev = isset($rx['bob_ach_progress'][0]) ? os_body($rx['bob_ach_progress'][0]) : null;
                        $status = $ev ? $get($ev, 'status') : null;
                        $note('5a bob achievement_progress in_progress',
                            $ev !== null && $status === 'in_progress', 'status=' . ($status ?? 'none'));
                        // brief settle to catch any erroneous unlock banner at 50%
                        return $sleep(0.8)->then(function () use ($note, &$rx) {
                            $note('5a no achievement_unlock banner @50', count($rx['bob_ach_unlock']) === 0,
                                count($rx['bob_ach_unlock']) . ' banner(s)');
                        });
                    });
            })->then(function () use (
                $alice, $bob, $achievementId, $note, $get, &$rx,
                $withTimeout, $waitFor, $sleep, $describe
            ) {
                // ---- 5b. unlockAchievement(100) -> bob unlock banner ----
                $rx['bob_ach_unlock'] = [];
                $alice->enhanced->unlockAchievement([
                    'achievementId' => $achievementId, 'name' => 'Complete',
                    'percentComplete' => 100, 'channel' => 'lobby']);
                return $waitFor(fn() => count($rx['bob_ach_unlock']) > 0, 12.0, 'bob ach unlock')
                    ->then(function () use ($note, &$rx, $get) {
                        $ev = isset($rx['bob_ach_unlock'][0]) ? os_body($rx['bob_ach_unlock'][0]) : null;
                        $status = $ev ? $get($ev, 'status') : null;
                        $note('5b bob achievement_unlock unlocked',
                            $ev !== null && $status === 'unlocked', 'status=' . ($status ?? 'none'));
                    });
            })->then(function () use (
                $alice, $achievementId, $note, $get, $withTimeout
            ) {
                // ---- 6. getAchievements reflects 100/unlocked -----------
                return $withTimeout($alice->enhanced->getAchievements([
                    'achievementId' => $achievementId,
                ]), 12.0, 'achievement_state')->then(function ($ack) use ($note, $get, $achievementId) {
                    $list = $get($ack, 'achievements', []);
                    $found = null;
                    foreach ($list as $a) { if ($get($a,'achievementId') === $achievementId) { $found = $a; break; } }
                    $ok = $found && (int) $get($found,'percentComplete') === 100 && $get($found,'status') === 'unlocked';
                    $note('6 getAchievements 100/unlocked', $ok,
                        $found ? ('pct=' . $get($found,'percentComplete') . ' status=' . $get($found,'status')) : 'not found');
                });
            })->then(function () use (
                $alice, $bob, $note, $get, &$rx, $withTimeout, $waitFor, $sleep, $describe, $challengeId
            ) {
                // ---- 7. sendChallengeInvite alice -> bob ----------------
                $rx['bob_invited'] = []; $rx['alice_invited'] = [];
                $payload = ['challengeId' => $challengeId, 'stake' => 'rematch', 'nonce' => bin2hex(random_bytes(4))];
                return $withTimeout($alice->enhanced->sendChallengeInvite([
                    'toUserId' => 'bob', 'type' => 'match', 'payload' => $payload, 'ttl' => 300,
                ]), 12.0, 'challenge_invite')->then(function ($ack) use ($note, $get, &$rx, $waitFor, $sleep, $payload) {
                    $inviteId = $get($ack, 'inviteId');
                    $note('7 sendChallengeInvite pending', $get($ack,'status') === 'pending' && $inviteId,
                        'inviteId=' . ($inviteId ?? 'none') . ' to=' . $get($ack,'toUserId'));
                    return $waitFor(fn() => count($rx['bob_invited']) > 0, 12.0, 'bob invited')
                        ->then(function () use ($note, &$rx, $get, $payload, $inviteId, $sleep) {
                            $ev = $rx['bob_invited'][0] ?? null;
                            $p = $ev ? $get($ev, 'payload', []) : [];
                            $fromRaw = $ev ? ($get($ev,'fromUserId') ?? $get($ev,'from')) : null;
                            $from = is_array($fromRaw) ? json_encode($fromRaw) : (string) ($fromRaw ?? 'none');
                            $note('7 bob sees challenge_invited w/payload',
                                $ev !== null && ($get($p,'nonce') === $payload['nonce']),
                                'from=' . $from);
                            // inviter must NOT see own invite
                            return $sleep(0.6)->then(function () use ($note, &$rx, $inviteId) {
                                $note('7 alice does NOT see own invite', count($rx['alice_invited']) === 0,
                                    count($rx['alice_invited']) . ' seen');
                                return $inviteId;
                            });
                        });
                });
            })->then(function ($inviteId) use (
                $alice, $bob, $note, $get, &$rx, $withTimeout, $waitFor, $sleep
            ) {
                // ---- 8. getChallengeInvites (bob) lists it --------------
                return $withTimeout($bob->enhanced->getChallengeInvites(), 12.0, 'challenge_invites')
                    ->then(function ($ack) use ($note, $get, $inviteId) {
                        $invites = $get($ack, 'invites', []);
                        $found = false;
                        foreach ($invites as $inv) { if ($get($inv,'inviteId') === $inviteId) { $found = true; break; } }
                        $note('8 bob getChallengeInvites lists it', $found, count($invites) . ' invite(s)');
                        return $inviteId;
                    });
            })->then(function ($inviteId) use (
                $alice, $bob, $note, $get, &$rx, $withTimeout, $waitFor, $sleep
            ) {
                // ---- 9. replyChallengeInvite(accept) -> alice sees reply
                $rx['alice_reply'] = [];
                return $withTimeout($bob->enhanced->replyChallengeInvite([
                    'inviteId' => $inviteId, 'accept' => true,
                ]), 12.0, 'challenge_reply')->then(function ($ack) use ($note, &$rx, $waitFor, $get) {
                    if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW reply_ack=" . json_encode($ack) . "\n");
                    return $waitFor(fn() => count($rx['alice_reply']) > 0, 12.0, 'alice reply received')
                        ->then(function () use ($note, &$rx, $get) {
                            $ev = $rx['alice_reply'][0] ?? null;
                            $accepted = $ev ? ($get($ev,'accept') === true || $get($ev,'status') === 'accepted') : false;
                            $note('9 alice sees challenge_reply_received', $ev !== null && $accepted,
                                'accept=' . ($ev ? var_export($get($ev,'accept'), true) : 'none'));
                        });
                });
            })->then(function () use (
                $alice, $bob, $note, $get, &$rx, $withTimeout, $waitFor, $sleep
            ) {
                // ---- 10. fresh invite + cancel -> bob sees cancelled ----
                $rx['bob_cancelled'] = []; $rx['bob_invited'] = [];
                return $withTimeout($alice->enhanced->sendChallengeInvite([
                    'toUserId' => 'bob', 'type' => 'match',
                    'payload' => ['nonce' => bin2hex(random_bytes(4))], 'ttl' => 300,
                ]), 12.0, 'challenge_invite#2')->then(function ($ack) use (
                    $alice, $note, $get, &$rx, $withTimeout, $waitFor
                ) {
                    if (getenv('OS_DEBUG')) fwrite(STDERR, "  RAW invite2_ack=" . json_encode($ack) . "\n");
                    $inviteId = $get($ack, 'inviteId');
                    return $withTimeout($alice->enhanced->cancelChallengeInvite([
                        'inviteId' => $inviteId,
                    ]), 12.0, 'challenge_invite_cancel')->then(function () use ($note, &$rx, $waitFor) {
                        return $waitFor(fn() => count($rx['bob_cancelled']) > 0, 12.0, 'bob cancelled')
                            ->then(function () use ($note, &$rx) {
                                $note('10 bob sees challenge_invite_cancelled', count($rx['bob_cancelled']) > 0,
                                    count($rx['bob_cancelled']) . ' event(s)');
                            });
                    });
                });
            });
        });
    });
})->then(function () use ($finish) {
    $finish(0, "\n[done] all steps executed");
})->otherwise(function ($e) use ($finish, $describe) {
    $finish(3, "\nFATAL " . $describe($e));
});

// ---- Summary on shutdown -------------------------------------------------
register_shutdown_function(function () use (&$results, &$aliceWorker, &$bobWorker) {
    echo "\n================ SUMMARY ================\n";
    $pass = 0; $fail = 0;
    foreach ($results as $name => $r) {
        printf("  %-42s %s\n", $name, $r['ok'] ? 'PASS' : 'FAIL');
        $r['ok'] ? $pass++ : $fail++;
    }
    echo "----------------------------------------\n";
    $known = $aliceWorker !== '?' && $bobWorker !== '?';
    echo "  workers: " . (!$known
        ? 'placement unknown'
        : ($aliceWorker !== $bobWorker
            ? 'alice and bob on different instances (CROSS-WORKER)'
            : 'alice and bob on the same instance')) . "\n";
    echo "  RESULT: " . ($fail === 0 && $pass > 0 ? "PASS ($pass)" : "FAIL ($fail failed / $pass passed)") . "\n";
    echo "========================================\n";
});

$loop->run();
