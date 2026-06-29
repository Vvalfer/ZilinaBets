# Retro Casino '85 — web app

The **backend + frontend** that turn the pure `casino-engine` into a playable,
good-looking casino. The engine decides every outcome; this app wraps it with
accounts, a real chip balance, server-side round state, and an 80s arcade UI
(Memphis palette, hard-edged shadows, real spinning/dealing animations).

```
casino-engine/
  casino-engine/   ← the pure engine (unchanged)
  app/             ← this: backend (PHP) + frontend (HTML/CSS/JS)
```

## Run it

Requires **PHP 8.1+** with the `pdo_sqlite` extension (bundled with PHP by
default — the same PHP that runs the engine).

From the `casino-engine/` folder (the one containing both `app/` and
`casino-engine/`):

```bash
php -S localhost:8000 -t app/public app/public/router.php
```

Then open **http://localhost:8000**, sign up (you get 1000 free chips), and play.

That's the only command. The SQLite database is created automatically on first
run at `app/data/casino.sqlite`. To wipe all accounts and balances, delete that
file.

## How it fits together

The project is split three ways, exactly as `INTEGRATION.md` describes:

- **Engine** (`casino-engine/`) — pure game logic and RNG. Decides outcomes.
- **Backend** (`app/src`, `app/public/router.php`) — accounts, balances,
  transactions, and the server-authoritative bet flow. Calls the engine.
- **Frontend** (`app/public/assets`) — the 80s arcade UI. It only *animates* an
  outcome the server already decided; it never decides anything.

## Server-authoritative model

Every bet goes through `POST /api/play` and is resolved on the server:

1. The stake is debited from the player's balance inside a **write-locked
   SQLite transaction** (no double-spend), after checking they can afford it.
2. The pure engine plays the round with a CSPRNG.
3. For the **stateful** games (blackjack, 421) the engine's `state` — including
   blackjack's hidden hole card — is stored **server-side**, keyed by a round id.
   The client never sees or sends it, so a player can't rewrite their own hand.
4. The gross `payout` is credited back. Player net = `payout − stake`.

Every chip movement is also written to an append-only `ledger` table.

## API

| Method | Path            | Purpose                                            |
|--------|-----------------|----------------------------------------------------|
| GET    | `/api/meta`     | Paytables, limits, game list (drives the UI)       |
| GET    | `/api/me`       | Current logged-in user (or `null`)                 |
| POST   | `/api/register` | Create account `{username, password}` → 1000 chips |
| POST   | `/api/login`    | Log in `{username, password}`                       |
| POST   | `/api/logout`   | Log out                                            |
| POST   | `/api/play`     | Place a bet / take an action (see below)            |
| POST   | `/api/roulette` | Resolve one roulette spin against several bets      |
| POST   | `/api/craps`    | Craps pass-line roll (come-out / point)             |
| GET    | `/api/admin/players` | (admin) all accounts: balance, rounds, total wagered |
| GET    | `/api/admin/stats`   | (admin) per-game wagered / paid / house margin / RTP |

**Play — fresh bet:** `{ "game": "slots", "bet": { "amount": 5 } }`
**Play — stateful follow-up:** `{ "roundId": "…", "bet": { "action": "hit" } }`

Errors come back as JSON with the right HTTP status: `422` invalid bet, `409`
illegal action, `402` insufficient funds, `401` not logged in.

## Files

```
app/
  config.php            starting balance, paths, session name
  bootstrap.php         loads engine + app classes, starts the session
  src/
    Db.php              PDO SQLite, schema migration, locked transactions
    Auth.php            register / login / logout / session
    Wallet.php          balance + append-only ledger, debit/credit
    RoundStore.php      server-side state for blackjack & 421
    PlayController.php   the server-authoritative /api/play flow
    Http.php            JSON request/response helpers
    InsufficientFundsException.php
  public/
    router.php          front controller (static + API)
    index.html          single-page shell
    assets/css/styles.css
    assets/js/api.js     API client + shared helpers
    assets/js/app.js     auth, lobby, routing
    assets/js/games/*.js  roulette, slots, blackjack, 421, duckrace
  data/                 SQLite db lives here (gitignored)
```

## Admin panel

Admins see a **★ Admin** button in the header that opens a read-only panel:
every account (balance, rounds played, total wagered) and per-game activity
(wagered, paid out, house margin, RTP).

Who is an admin: by default the **first registered account** (id 1). To set it
explicitly, list usernames in `config.php`:

```php
'admins' => ['sylvie'],
```

## Notes / scope

- **Virtual chips only.** There is no real money anywhere.
- Built to run on PHP's **built-in dev server** for a local, single-player
  experience. The session cookie is `SameSite=Lax`; for a public deployment
  you'd add CSRF tokens, HTTPS, and run behind a real web server.
- Passwords are hashed with `password_hash()` (bcrypt).
