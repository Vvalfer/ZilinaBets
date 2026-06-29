# Deploying the Retro Casino

The app is a PHP front-controller (`app/public/router.php`) plus static assets,
backed by SQLite. The **web root must be `app/public/`** — nothing above it
should be web-accessible.

## Option A — Docker (recommended)

From the `casino-engine/` folder (the one with the `Dockerfile`):

```bash
docker compose up -d --build
```

Browse **http://localhost:8080**. The SQLite database persists in the
`casino-data` volume. Configure via `docker-compose.yml`:

- `CASINO_ADMINS` — comma-separated admin usernames (default `Admin`)
- `CASINO_STARTING_BALANCE` — chips for new accounts (default `1000`)

To put it on the internet, run this container behind a TLS reverse proxy
(Caddy, Traefik, Nginx, or your host's load balancer) that forwards
`X-Forwarded-Proto: https` — the app then marks the session cookie `Secure`
automatically.

## Option B — Apache / shared hosting

1. Upload the whole `casino-engine/` folder.
2. Point the site's document root at `app/public/`.
3. Ensure `mod_rewrite`, `mod_headers` are on (the bundled
   `app/public/.htaccess` handles routing, headers and caching).
4. Make `app/data/` writable by the web server, and confirm it is **not**
   reachable from the web (there's a deny-all `.htaccess` as a safety net).
5. Requires **PHP 8.1+** with `pdo_sqlite`.

## Option C — Nginx + PHP-FPM

Use `deploy/nginx.conf` as a starting point (adjust `root` and the FPM socket).
It includes optional login rate-limiting.

## Environment variables

| Variable                  | Default                  | Purpose                          |
|---------------------------|--------------------------|----------------------------------|
| `CASINO_DB_PATH`          | `app/data/casino.sqlite` | SQLite file location             |
| `CASINO_STARTING_BALANCE` | `1000`                   | Chips granted to a new account   |
| `CASINO_SESSION_NAME`     | `neon_casino_sid`        | Session cookie name              |
| `CASINO_ADMINS`           | `Admin`                  | Comma-separated admin usernames  |

## Production checklist

- [ ] **HTTPS.** Serve over TLS. Behind a proxy, forward `X-Forwarded-Proto` so
      the session cookie becomes `Secure` (handled in `bootstrap.php`).
- [ ] **Admins.** Set `CASINO_ADMINS` to the real admin username(s).
- [ ] **Data dir.** `app/data/` is writable and not web-served. Back up
      `app/data/casino.sqlite` regularly.
- [ ] **Errors.** `display_errors` is off; PHP errors are logged to
      `app/data/php-error.log`. Rotate/monitor it.
- [ ] **Brute force.** Login is throttled per IP (15 fails / 15 min) in the app.
      Add proxy-level rate-limiting too (see `deploy/nginx.conf`). If you're
      behind a proxy, make sure the app sees the real client IP.
- [ ] **Engine demo.** The engine's `reference-api/` is *not* under the web root,
      so it is not exposed — keep it that way.

## What's already hardened

- Server-authoritative game outcomes (the client only animates results).
- All SQL is parameterized (PDO prepared statements).
- Balance changes run in write-locked transactions; balances can't go negative.
- Passwords hashed with bcrypt; session ID regenerated on login.
- CSRF: same-origin check on state-changing POSTs + `SameSite=Lax` cookie.
- Security headers: `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`.
- Uncaught errors return a generic JSON 500 — no stack traces leak.

## Notes / scope

Virtual chips only — no real money. SQLite suits modest traffic; for high
concurrency, migrate the `Db` layer to MySQL/Postgres (all queries are standard
parameterized SQL).
