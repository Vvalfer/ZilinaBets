<?php

declare(strict_types=1);

namespace CasinoApp;

use CasinoEngine\AbstractRandom;

/**
 * A RandomSource that always returns one preset number for int().
 *
 * Used for multi-bet roulette: we draw the winning pocket ONCE with the real
 * CSPRNG, then evaluate each of the player's bets through the genuine engine
 * against that same number — so every bet is still resolved server-side by the
 * engine's own rules, not re-implemented here.
 */
final class FixedRandom extends AbstractRandom
{
    public function __construct(private readonly int $value)
    {
    }

    public function int(int $min, int $max): int
    {
        if ($this->value < $min) {
            return $min;
        }
        if ($this->value > $max) {
            return $max;
        }
        return $this->value;
    }
}
