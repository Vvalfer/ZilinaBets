<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * Production randomness, backed by PHP's random_int() (a cryptographically
 * secure PRNG). Outcomes cannot be predicted or reproduced by a player, which
 * is exactly what you want for real betting outcomes.
 */
final class CryptoRandom extends AbstractRandom
{
    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \InvalidArgumentException("int(): min ($min) > max ($max).");
        }
        return random_int($min, $max);
    }
}
