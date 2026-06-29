<?php

declare(strict_types=1);

namespace CasinoApp;

use PDO;

/**
 * Thin SQLite (PDO) wrapper: one shared connection, schema migration, and a
 * locked-transaction helper used for anything that touches a balance.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /** Read a config value (set via configure() in bootstrap). */
    public static function config(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = self::$config['db_path'];
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Concurrency + integrity for the built-in dev server.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                balance       INTEGER NOT NULL,
                created_at    TEXT    NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        // Server-side round state for stateful games (blackjack, 421).
        // The client never sees or sends this — it is keyed by a server round id.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rounds (
                id         TEXT    PRIMARY KEY,
                user_id    INTEGER NOT NULL,
                game       TEXT    NOT NULL,
                stake      INTEGER NOT NULL,
                state_json TEXT    NOT NULL,
                created_at TEXT    NOT NULL DEFAULT (datetime(\'now\')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        // Append-only ledger so every chip movement is auditable.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ledger (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL,
                game          TEXT,
                delta         INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                reason        TEXT    NOT NULL,
                created_at    TEXT    NOT NULL DEFAULT (datetime(\'now\')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        // Failed-login log, for per-IP brute-force throttling.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ip         TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, created_at)');
    }

    /**
     * Run $fn inside an IMMEDIATE (write-locked) transaction. Anything that
     * reads-then-writes a balance must go through here to avoid double-spend.
     *
     * @template T
     * @param callable(PDO):T $fn
     * @return T
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        // Raw BEGIN IMMEDIATE (PDO's beginTransaction is only DEFERRED on SQLite),
        // so we track open/closed ourselves instead of using PDO::inTransaction().
        $pdo->exec('BEGIN IMMEDIATE');
        $open = true;
        try {
            $result = $fn($pdo);
            $pdo->exec('COMMIT');
            $open = false;
            return $result;
        } catch (\Throwable $e) {
            if ($open) {
                $pdo->exec('ROLLBACK');
            }
            throw $e;
        }
    }
}
