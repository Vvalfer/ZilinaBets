<?php

declare(strict_types=1);

/**
 * App configuration. Every value can be overridden by an environment variable,
 * so the same code runs locally and in production without edits.
 *
 *   CASINO_DB_PATH           path to the SQLite file
 *   CASINO_STARTING_BALANCE  chips granted to a new account
 *   CASINO_SESSION_NAME      session cookie name
 *   CASINO_ADMINS            comma-separated admin usernames (e.g. "Admin,sylvie")
 */

$env = static fn (string $key, string $default): string =>
    (getenv($key) !== false && getenv($key) !== '') ? (string) getenv($key) : $default;

$admins = array_values(array_filter(array_map(
    'trim',
    explode(',', $env('CASINO_ADMINS', 'Admin'))
)));

return [
    // Zero-install autoloader shipped with the pure engine.
    'engine_autoload'  => __DIR__ . '/../casino-engine/autoload.php',

    // SQLite database file (created automatically on first run).
    'db_path'          => $env('CASINO_DB_PATH', __DIR__ . '/data/casino.sqlite'),

    // Chips granted to every brand-new account. Virtual only — no real money.
    'starting_balance' => (int) $env('CASINO_STARTING_BALANCE', '1000'),

    // Session cookie name.
    'session_name'     => $env('CASINO_SESSION_NAME', 'neon_casino_sid'),

    // Admin accounts (by username) that can see the admin panel.
    'admins'           => $admins,
];
