<?php

declare(strict_types=1);

use CasinoApp\AdminController;
use CasinoApp\Auth;
use CasinoApp\CrapsController;
use CasinoApp\Http;
use CasinoApp\PlayController;
use CasinoApp\Wallet;
use CasinoEngine\GameFactory;
use CasinoEngine\Paytables;

/**
 * Front controller for the Retro Casino.
 *
 * Run with the built-in PHP server, document root = this folder:
 *     php -S localhost:8000 -t app/public app/public/router.php
 *
 * - /api/*  -> JSON endpoints (auth, wallet, play)
 * - else    -> static asset if it exists, otherwise the SPA shell (index.html)
 */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ---- Static files & SPA shell (handled BEFORE any session/DB work) --------
if (!str_starts_with($path, '/api/')) {
    $file = realpath(__DIR__ . $path);
    // Serve an existing asset directly (built-in server handles mime types).
    if ($path !== '/' && $file !== false && is_file($file) && str_starts_with($file, __DIR__)) {
        return false;
    }
    // Everything else falls back to the single-page shell.
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/index.html');
    return true;
}

// ---- API: load app + engine, open session --------------------------------
$config = require __DIR__ . '/../bootstrap.php';

// CSRF mitigation: reject cross-site state-changing requests. Combined with the
// SameSite=Lax session cookie, this stops another origin from acting as the user.
if ($method === 'POST' && !sameOrigin()) {
    Http::error('Cross-origin request blocked.', 403, 'forbidden');
}

$route = $method . ' ' . $path;

try {

switch ($route) {
    case 'GET /api/meta':
        Http::json([
            'games'           => GameFactory::KEYS,
            'stateful'        => GameFactory::STATEFUL,
            'minBet'          => Paytables::MIN_BET,
            'maxBet'          => Paytables::MAX_BET,
            'startingBalance' => $config['starting_balance'],
            'roulette'        => Paytables::ROULETTE,
            'rouletteRed'     => Paytables::ROULETTE_RED,
            'slotsPayouts'    => Paytables::SLOTS_PAYOUTS,
            'combo421'        => Paytables::COMBO_421,
            'blackjack'       => Paytables::BLACKJACK,
            'duckNames'       => Paytables::DUCK_NAMES,
            'duckOdds'        => Paytables::DUCK_ODDS,
        ]);
        break;

    case 'GET /api/me':
        Http::json(['user' => Auth::currentUser()]);
        break;

    case 'POST /api/register':
        $b = Http::jsonBody();
        try {
            $user = Auth::register(
                (string) ($b['username'] ?? ''),
                (string) ($b['password'] ?? ''),
                (int) $config['starting_balance']
            );
            Http::json(['user' => $user]);
        } catch (\RuntimeException $e) {
            Http::error($e->getMessage(), 400, 'register_failed');
        }
        break;

    case 'POST /api/login':
        $b = Http::jsonBody();
        try {
            $user = Auth::login((string) ($b['username'] ?? ''), (string) ($b['password'] ?? ''));
            Http::json(['user' => $user]);
        } catch (\RuntimeException $e) {
            Http::error($e->getMessage(), 401, 'login_failed');
        }
        break;

    case 'POST /api/logout':
        Auth::logout();
        Http::json(['ok' => true]);
        break;

    case 'POST /api/play':
        PlayController::handle();
        break;

    case 'POST /api/roulette':
        PlayController::roulette();
        break;

    case 'POST /api/craps':
        CrapsController::handle();
        break;

    case 'GET /api/admin/players':
        AdminController::players();
        break;

    case 'GET /api/admin/stats':
        AdminController::stats();
        break;

    case 'GET /api/admin/history':
        AdminController::history();
        break;

    case 'POST /api/admin/adjust':
        AdminController::adjust();
        break;

    case 'POST /api/admin/delete':
        AdminController::deleteAccount();
        break;

    default:
        Http::error('Not found.', 404, 'not_found');
}

} catch (\Throwable $e) {
    // Last line of defence: any uncaught error becomes a clean JSON 500,
    // never an HTML stack trace.
    error_log('[casino] ' . $e->getMessage());
    Http::error('Internal server error.', 500, 'internal');
}

/** True if the request is same-origin (or has no Origin header at all). */
function sameOrigin(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true; // no Origin header (e.g. curl, some same-origin requests)
    }
    $oHost = parse_url($origin, PHP_URL_HOST);
    $oPort = parse_url($origin, PHP_URL_PORT);
    $originHost = $oHost . ($oPort ? ':' . $oPort : '');
    return strcasecmp($originHost, $host) === 0;
}
