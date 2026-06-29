<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Support;

use CasinoEngine\RandomSource;

/**
 * A fully scripted RandomSource for tests. Unlike SeededRandom (which is
 * deterministic but still "random-looking"), this lets a test force exact
 * values, so we can construct precise scenarios:
 *
 *   - queue int() results (dice, roulette number, reel index draws),
 *   - return a fixed shuffled deck (blackjack shoe order),
 *   - force the weighted pick (duck-race winner).
 */
final class ScriptedRandom implements RandomSource
{
    /** @var int[] consumed in order by int(); falls back to $defaultInt. */
    public array $ints = [];
    public int $defaultInt = 1;

    /** @var int[] consumed in order by pickWeighted(); falls back to $defaultWeighted. */
    public array $weighted = [];
    public int $defaultWeighted = 0;

    /** @var array|null returned verbatim by shuffle() if set. */
    public ?array $shuffleReturn = null;

    public function int(int $min, int $max): int
    {
        $value = array_shift($this->ints) ?? $this->defaultInt;
        return max($min, min($max, $value));
    }

    public function shuffle(array $items): array
    {
        return $this->shuffleReturn ?? array_values($items);
    }

    public function pickWeighted(array $weights): int
    {
        return array_shift($this->weighted) ?? $this->defaultWeighted;
    }
}
