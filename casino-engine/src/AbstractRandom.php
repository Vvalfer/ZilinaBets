<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * Shared implementation of shuffle() and pickWeighted() built purely on top
 * of int(). Concrete sources only have to implement int(), and they get
 * correct, consistent higher-level helpers for free.
 */
abstract class AbstractRandom implements RandomSource
{
    abstract public function int(int $min, int $max): int;

    public function shuffle(array $items): array
    {
        $items = array_values($items);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }

    public function pickWeighted(array $weights): int
    {
        $weights = array_values($weights);
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new \InvalidArgumentException('Weights must sum to a positive number.');
        }

        // Draw at a fixed resolution. Using int() over a huge range would lose
        // all precision with a 31-bit seeded generator, so we cap the draw and
        // scale, which keeps the pick well-distributed for any RandomSource.
        $resolution = 1_000_000;
        $threshold = ($this->int(1, $resolution) / $resolution) * $total;

        $acc = 0.0;
        foreach ($weights as $index => $weight) {
            $acc += $weight;
            if ($threshold <= $acc) {
                return $index;
            }
        }
        return count($weights) - 1; // floating-point safety net
    }
}
