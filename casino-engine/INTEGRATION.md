# Integration guide

How the backend and frontend plug into the engine. The engine stays pure; this
document is the boundary on each side.

## For the backend

Typical flow for a single bet (instant games: roulette, slots, duck race):

```php
use CasinoEngine\GameFactory;
use CasinoEngine\CryptoRandom;
use CasinoEngine\InvalidBetException;

// 1. The player has a balance; the stake comes from the request.
$game = GameFactory::create($gameKey);          // throws on unknown game

try {
    $game->validateBet($bet);                    // server-side, every time
} catch (InvalidBetException $e) {
    // 422 to the client; do not touch the balance.
}

// 2. Debit the stake INSIDE a locked DB transaction (anti double-spend),
//    after checking balance >= stake.
$db->beginTransaction();
//    ... SELECT ... FOR UPDATE the balance row, verify funds, subtract stake ...

// 3. Resolve.
$result = $game->play($bet, new CryptoRandom());

// 4. Credit back result->payout, record the round, commit.
//    Player net = result->payout - stake.
$db->commit();

// 5. Return $result->toArray() as JSON.
```

### Stateful games (blackjack, 421)

The first call has `state = null` and returns `status = in_progress` with a
`state` array and `nextActions`. **Persist that state server-side**, keyed by a
round id you generate. On the next request:

```php
$state = $repository->loadState($roundId);       // NOT from the client
$result = $game->play(['action' => $action], new CryptoRandom(), $state);
if ($result->isResolved()) {
    // settle: credit payout, delete the stored state
} else {
    $repository->saveState($roundId, $result->state);
}
```

Do **not** trust a `state` sent by the client — that would let a player rewrite
their own blackjack hand. The reference API accepts client state only because it
is a local demo with no balances.

`double` returns `outcome.additionalBet` (equal to the original stake): debit
that extra stake too before crediting the (doubled) payout.

### Errors to map

| Exception                 | Suggested HTTP | Meaning                          |
|---------------------------|----------------|----------------------------------|
| `InvalidBetException`     | 422            | bad amount / target / unknown game|
| `IllegalActionException`  | 409            | action not allowed in this state |

### Validation already done for you

`validateAmount()` accepts a real integer or an all-digit string (JSON often
sends numbers as strings) and rejects floats, negatives, zero, out-of-range and
non-numeric input. Per-game target validation (roulette type/value, duck index)
is in each `validateBet()`. You still own auth, balance checks and persistence.

## For the frontend

You consume `response.outcome`. The outcome is already final — your job is to
animate towards it, never to decide it.

Per-game `outcome` shape (see live samples in `mock/`):

- **roulette**: `{ number, color, win, multiplier, bet }`
- **slots**: `{ reels:[s,s,s], win, symbol, multiplier, bet }`
- **421**: `{ dice:[a,b,c], combo, multiplier, win, bet }` (plus `rerollsLeft`
  while in progress)
- **duckrace**: `{ winner, order, win, odds, names, timeline, bet }` —
  `timeline[t]` is `{duckIndex: position 0..100}` for each tick; the winner
  reaches 100 on the last tick.
- **blackjack** in progress: `{ player:[cards], playerTotal, dealerUpCard }`
  (hole card intentionally hidden); resolved:
  `{ player, dealer, playerTotal, dealerTotal, result, stake }` where `result`
  is `win|lose|push|blackjack`.

The `mock/` folder has a stable, ready-to-use response for each game (generated
deterministically), so you can build animations before the backend is wired up.

## Demoing the engine on its own

```bash
php -S localhost:8000 reference-api/index.php
# GET  http://localhost:8000/                       -> usage + examples
# POST http://localhost:8000/play  {game, bet, state}
```

Example:

```bash
curl -s localhost:8000/play -H 'Content-Type: application/json' \
  -d '{"game":"slots","bet":{"amount":5}}'
```
