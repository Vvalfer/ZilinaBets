<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Support;

/**
 * Tiny helper to build blackjack cards/shoes by rank in tests.
 * Suit is cosmetic for value purposes, so we default it.
 */
final class Cards
{
    public static function c(string $rank, string $suit = 'S'): array
    {
        return ['rank' => $rank, 'suit' => $suit];
    }

    /**
     * Build a shoe (array of cards) from a list of ranks.
     * @param string[] $ranks
     */
    public static function shoe(array $ranks): array
    {
        return array_map(static fn (string $r): array => self::c($r), $ranks);
    }
}
