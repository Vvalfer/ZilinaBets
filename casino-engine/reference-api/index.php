<?php

declare(strict_types=1);

/**
 * Reference API — a thin, standalone JSON wrapper around the engine so the
 * game logic can be demonstrated on its own, without the team's real backend.
 *
 * Run it:
 *     php -S localhost:8000 reference-api/index.php
 *
 * Then:
 *     GET  /                          -> usage + game list
 *     POST /play  { game, bet, state } -> RoundResult as JSON
 *
 * IMPORTANT (server-authoritative model): this demo accepts `state` from the
 * client for convenience. The PRODUCTION backend must instead persist state
 * server-side (keyed by a round id) and never trust client-sent state, so a
 * player cannot rewrite their own blackjack hand. The engine itself stays
 * identical either way — that is the whole point of keeping it pure.
 */

require __DIR__ . '/../autoload.php';

use CasinoEngine\CryptoRandom;
use CasinoEngine\GameFactory;
use CasinoEngine\IllegalActionException;
use CasinoEngine\InvalidBetException;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    echo json_encode([
        'name' => 'Casino engine — reference API',
        'games' => GameFactory::KEYS,
        'stateful' => GameFactory::STATEFUL,
        'usage' => [
            'endpoint' => 'POST /play',
            'body' => ['game' => 'roulette', 'bet' => ['amount' => 10, 'type' => 'color', 'value' => 'red']],
            'note' => 'For stateful games, send back the "state" object from the previous response, with bet = {action: ...}.',
        ],
        'examples' => [
            'roulette' => ['game' => 'roulette', 'bet' => ['amount' => 10, 'type' => 'straight', 'value' => 17]],
            'slots' => ['game' => 'slots', 'bet' => ['amount' => 5]],
            'duckrace' => ['game' => 'duckrace', 'bet' => ['amount' => 10, 'duck' => 2]],
            '421 (deal)' => ['game' => '421', 'bet' => ['amount' => 10]],
            'blackjack (deal)' => ['game' => 'blackjack', 'bet' => ['amount' => 10]],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Body must be a JSON object.']);
    exit;
}

$gameKey = (string) ($payload['game'] ?? '');
$bet = is_array($payload['bet'] ?? null) ? $payload['bet'] : [];
$state = is_array($payload['state'] ?? null) ? $payload['state'] : null;

try {
    $game = GameFactory::create($gameKey);
    $result = $game->play($bet, new CryptoRandom(), $state);
    echo json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (InvalidBetException $e) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_bet', 'message' => $e->getMessage()]);
} catch (IllegalActionException $e) {
    http_response_code(409);
    echo json_encode(['error' => 'illegal_action', 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal', 'message' => $e->getMessage()]);
}
