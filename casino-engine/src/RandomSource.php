<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * Source of randomness used by every game.
 *
 * The whole point of this abstraction: a game never calls rand()/mt_rand()
 * directly. It asks a RandomSource. That gives us two things at once:
 *
 *   - In production we inject {@see CryptoRandom} (cryptographically secure,
 *     impossible to predict or influence from the browser).
 *   - In tests we inject {@see SeededRandom} (deterministic) or a scripted
 *     double, so "with this seed the wheel lands on 17" becomes a real,
 *     repeatable assertion. You cannot meaningfully unit-test a casino
 *     without this.
 */
interface RandomSource
{
    /** Uniform integer in the inclusive range [$min, $max]. */
    public function int(int $min, int $max): int;

    /** Return a shuffled copy of $items (Fisher-Yates), keys reset to 0..n-1. */
    public function shuffle(array $items): array;

    /**
     * Pick an index according to positive weights.
     * Weights may be floats (e.g. probabilities) or ints (e.g. reel-strip counts).
     * Returns the 0-based position of the chosen weight.
     */
    public function pickWeighted(array $weights): int;
}
