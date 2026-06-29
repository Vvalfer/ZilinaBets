<?php

declare(strict_types=1);

namespace CasinoEngine;

use CasinoEngine\games\Blackjack;
use CasinoEngine\games\DuckRace;
use CasinoEngine\games\Game421;
use CasinoEngine\games\Roulette;
use CasinoEngine\games\Slots;

/**
 * Maps a stable game key to its implementation. The backend uses this so its
 * routes stay tiny: read the key, build the game, call play(), persist.
 */
final class GameFactory
{
    public const KEYS = ['roulette', 'blackjack', '421', 'slots', 'duckrace'];

    /** Games that carry server-side state across multiple requests. */
    public const STATEFUL = ['blackjack', '421'];

    public static function create(string $key): Game
    {
        return match ($key) {
            'roulette' => new Roulette(),
            'blackjack' => new Blackjack(),
            '421' => new Game421(),
            'slots' => new Slots(),
            'duckrace' => new DuckRace(),
            default => throw new InvalidBetException("Unknown game '$key'."),
        };
    }

    public static function isStateful(string $key): bool
    {
        return in_array($key, self::STATEFUL, true);
    }
}
