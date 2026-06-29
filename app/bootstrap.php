<?php

declare(strict_types=1);

/**
 * Shared bootstrap: loads config, the pure casino engine, the app classes,
 * and starts the session. Required by the router on every request.
 */

$config = require __DIR__ . '/config.php';

// API hygiene: never echo PHP warnings/notices/stack traces into a JSON body
// (that would leak paths and internals). Log them server-side instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Log PHP errors to a file alongside the database (outside the web root).
$logDir = \dirname((string) $config['db_path']);
if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

// The pure, server-authoritative game engine (game logic, RNG, paytables).
require_once $config['engine_autoload'];

// App-side classes (DB, auth, wallet, round persistence, HTTP helpers).
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/Http.php';
require_once __DIR__ . '/src/InsufficientFundsException.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Wallet.php';
require_once __DIR__ . '/src/RoundStore.php';
require_once __DIR__ . '/src/FixedRandom.php';
require_once __DIR__ . '/src/PlayController.php';
require_once __DIR__ . '/src/CrapsController.php';
require_once __DIR__ . '/src/AdminController.php';

// Make config reachable to the classes that need it.
\CasinoApp\Db::configure($config);

// Are we on HTTPS? (Also true when behind a TLS-terminating reverse proxy that
// forwards X-Forwarded-Proto.) When true, the session cookie is marked Secure.
$secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

// Session: the only thing we trust to identify a logged-in player.
session_name($config['session_name']);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $secure,
    'path'     => '/',
]);
session_start();

return $config;
