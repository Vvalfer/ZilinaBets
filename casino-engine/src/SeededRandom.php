<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * Deterministic randomness for TESTS ONLY.
 *
 * Implements the Park-Miller "minimal standard" LCG:
 *     state = (state * 16807) mod (2^31 - 1)
 *
 * Same seed => same sequence => repeatable test assertions and a non-flaky,
 * always-identical statistical (RTP) simulation. Never use this for real
 * outcomes: it is fully predictable by design.
 */
final class SeededRandom extends AbstractRandom
{
    private const MODULUS = 2147483647;     // 2^31 - 1 (prime)
    private const MULTIPLIER = 16807;

    private int $state;

    public function __construct(int $seed)
    {
        $state = $seed % self::MODULUS;
        if ($state <= 0) {
            $state += self::MODULUS - 1; // keep it in 1..MODULUS-1, never 0
        }
        $this->state = $state;
    }

    private function next(): int
    {
        // 16807 * (2^31-2) fits comfortably in a 64-bit PHP int, no overflow.
        $this->state = (self::MULTIPLIER * $this->state) % self::MODULUS;
        return $this->state; // 1 .. MODULUS-1
    }

    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \InvalidArgumentException("int(): min ($min) > max ($max).");
        }
        $range = $max - $min + 1;
        return $min + (($this->next() - 1) % $range);
    }
}
