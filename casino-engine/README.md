# Casino Engine

The **game-logic, fairness and tests** slice of our retro casino project. This
module decides every game outcome and payout. It is deliberately **pure PHP**:
it never touches the database, sessions or `$_POST`. It takes a bet, a source of
randomness and an optional state, and returns a result. That purity is what
makes it (a) unit-testable in a deterministic way, (b) clearly my own work, and
(c) impossible for the browser to influence.

> Chips are **virtual only**. There is no real money anywhere in this project.

## Why it is built this way (the 30-second defense)

- **Server-authoritative.** Every random draw and payout happens here, on the
  server. The client receives an already-decided outcome and only animates it.
  A player cannot change a dice roll, a card, or a payout by tampering with the
  request.
- **Randomness is injected, not hard-wired.** Games depend on a `RandomSource`
  interface. Production uses `CryptoRandom` (a CSPRNG via `random_int()`); tests
  use `SeededRandom` (deterministic) or a scripted double. This is the only way
  to write meaningful tests for a casino — "with this seed the wheel lands on
  17" becomes a real assertion.
- **Paytables are isolated.** Every multiplier, reel weight and odds value lives
  in `src/Paytables.php`. The house edge / RTP is computed *from* that table and
  verified *against* a simulation, so the fairness numbers are provable, not
  asserted by hand.

## Layout

```
engine/
  src/
    RandomSource.php       interface: int(), shuffle(), pickWeighted()
    AbstractRandom.php     shared shuffle + weighted pick
    CryptoRandom.php       production RNG (random_int)
    SeededRandom.php       deterministic RNG for tests (Park-Miller LCG)
    Game.php               the contract every game implements
    RoundResult.php        the uniform return type
    Paytables.php          all tunable numbers
    AbstractGame.php       shared bet-amount validation
    GameFactory.php        key -> game instance
    games/                 Roulette, Blackjack, Game421, Slots, DuckRace
    InvalidBetException, IllegalActionException
  tests/                   PHPUnit tests + Support doubles
  reference-api/index.php  standalone JSON API to demo the engine solo
  mock/                    stable JSON fixtures for the frontend
  tools/                   run-tests.php (no-deps runner), generate-mocks.php
  composer.json  phpunit.xml  autoload.php
```

## Running the tests

Two ways, **same test files**:

```bash
# 1) The real deal (for the repo / CI), needs internet once to install PHPUnit:
composer install
composer test            # or: ./vendor/bin/phpunit

# 2) Zero install, runs anywhere (used during development in a locked-down box):
php tools/run-tests.php
```

The no-deps runner exists only so the suite can run without network access; the
graded repository uses PHPUnit via Composer.

## The contract (the seam with the rest of the team)

Every game implements `Game`:

```php
interface Game {
    public function key(): string;
    public function validateBet(array $bet): void;                       // throws on invalid bet
    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult;
}
```

`RoundResult` is the single return type:

| field         | meaning                                                              |
|---------------|----------------------------------------------------------------------|
| `status`      | `resolved` or `in_progress` (stateful games)                         |
| `payout`      | **gross** chips to credit back: `0` lose, `2*bet` even win, `bet` push|
| `outcome`     | public data for the frontend to animate/show                         |
| `state`       | server-side state to persist (stateful games only), else `null`      |
| `nextActions` | allowed follow-ups, e.g. `['hit','stand','double']`                   |

**Accounting convention:** the backend debits the stake when the bet is placed,
then credits back `payout`. Player net for the round = `payout - stake`. All
payouts are whole chips (rounded).

Only **blackjack** and **421** are stateful; the others resolve in one call.

## Games, paytables and RTP

All numbers live in `src/Paytables.php`.

**Roulette** — European single-zero (0–36), house edge 2.70%.
Straight `36x`, color/parity/range `2x`, dozen `3x`.

**Slots** — 3 reels, identical 16-symbol strip (cherry×5, lemon×4, bell×3,
star×2, diamond×1, seven×1), pays on three of a kind:
cherry `8x`, lemon `14x`, bell `30x`, star `60x`, diamond `200x`, seven `400x`.
Theoretical RTP ≈ **0.924**, confirmed by a 1,000,000-spin deterministic
simulation (`RtpTest`).

**421** — 3 dice, up to 2 rerolls. 4-2-1 `11x`, aces (1-1-1) `7x`, any other
triple `4x`, run `3x`, nénette (2-2-1) `2x`, else lose.

**Duck race** — 5 ducks. Winner drawn from fixed true probabilities; the house
margin is baked into the payout odds (`prob * odds ≈ 0.90` per duck). Returns
the finishing order and a per-tick timeline for the animation.

**Blackjack** — 6-deck shoe, dealer draws to 17 (stands on all 17s), blackjack
pays 3:2, actions hit/stand/double. Split is a documented v1 extension. While a
hand is in progress the dealer's hole card stays in server state and is **not**
sent to the client.

## Fairness model

- Production randomness is a CSPRNG (`random_int()`), unpredictable to players.
- `validateBet()` is re-run server-side on every request, whatever the client
  claims.
- The pure engine has no inputs a client could use to skew a draw.
- RTP is provable: the test-suite computes the theoretical return from the
  paytable and checks a long simulation against it.

A stretch extension (not implemented) is a *provably-fair* scheme: publish
`hash(seed)` before the round and reveal the seed after, letting the player
verify the outcome was not changed. The `SeededRandom` abstraction already makes
this straightforward to add.

See `INTEGRATION.md` for how the backend and frontend plug in.
