<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * Every tunable number lives here, isolated from game logic. Change the house
 * edge / RTP by editing this file only; the rules code never hard-codes a
 * multiplier. This separation is also what makes the RTP tests meaningful:
 * the theoretical return is computed FROM these tables and compared against a
 * simulation that USES these tables.
 */
final class Paytables
{
    // --- Global bet limits (chips) -----------------------------------------
    public const MIN_BET = 1;
    // Effectively no ceiling: players may go all-in. The real cap is the
    // player's balance, enforced by the backend wallet. Kept finite (well under
    // PHP_INT_MAX) so amount * multiplier can never overflow.
    public const MAX_BET = 1000000000;

    // --- Roulette (single-zero / European, house edge 2.70%) ----------------
    /** Gross multiplier per winning bet type. */
    public const ROULETTE = [
        'straight' => 36, // single number  (35:1)
        'color'    => 2,  // red / black    (1:1)
        'parity'   => 2,  // odd / even      (1:1)
        'range'    => 2,  // low(1-18)/high(19-36) (1:1)
        'dozen'    => 3,  // 1st/2nd/3rd dozen (2:1)
    ];
    /** Red pockets on a European wheel; everything else 1..36 is black, 0 is green. */
    public const ROULETTE_RED = [
        1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36,
    ];

    // --- Slots (3 reels, identical strip, win on 3-of-a-kind) ---------------
    // Strip length 16: cherry x5, lemon x4, bell x3, star x2, diamond x1, seven x1.
    // Theoretical RTP = sum over symbols of (count/16)^3 * payout  ~= 0.924.
    // Cherry-heavy strip: small wins land often, big symbols are rare.
    public const SLOTS_STRIP = [
        'cherry', 'cherry', 'cherry', 'cherry', 'cherry', 'cherry',
        'cherry', 'cherry', 'cherry', 'cherry', 'cherry', 'cherry',
        'lemon', 'lemon', 'lemon',
        'bell', 'bell',
        'star',
        'diamond',
        'seven',
    ];
    /** Gross multiplier for three of a kind (gentler than before). */
    public const SLOTS_PAYOUTS = [
        'cherry'  => 2,
        'lemon'   => 5,
        'bell'    => 10,
        'star'    => 25,
        'diamond' => 100,
        'seven'   => 500,
    ];
    public const SLOTS_GLYPHS = [
        'cherry'  => '\u{1F352}',
        'lemon'   => '\u{1F34B}',
        'bell'    => '\u{1F514}',
        'star'    => '\u{2B50}',
        'diamond' => '\u{1F48E}',
        'seven'   => '7\u{FE0F}\u{20E3}',
    ];

    // --- 421 (3 dice, up to 2 rerolls) --------------------------------------
    /** Gross multiplier per combination. See Game421::score(). */
    public const COMBO_421 = [
        '421'     => 11, // 4-2-1, the namesake
        'aces'    => 7,  // 1-1-1 (brelan d'as)
        'triple'  => 4,  // any other x-x-x
        'run'     => 3,  // 3 consecutive values
        'nenette' => 2,  // 2-2-1 (la nénette)
        'nothing' => 0,
    ];

    // --- Blackjack ----------------------------------------------------------
    public const BLACKJACK_DECKS = 6;
    /** Gross multipliers, applied to the effective stake (doubled if doubled). */
    public const BLACKJACK = [
        'win'       => 2.0,
        'blackjack' => 2.5, // natural 21, pays 3:2
        'push'      => 1.0, // stake returned
        'lose'      => 0.0,
    ];

    // --- Duck race (5 ducks) ------------------------------------------------
    /** Equal chance for every duck — pure luck. */
    public const DUCK_PROBS = [0.20, 0.20, 0.20, 0.20, 0.20];
    /** Flat payout: every duck pays 4x on a win (20% house edge at 1-in-5). */
    public const DUCK_ODDS = [4.0, 4.0, 4.0, 4.0, 4.0];
    public const DUCK_NAMES = ['Flaque', 'Coin-Coin', 'Bréchet', 'Palmé', 'Caneton'];

    public static function roundChips(float $value): int
    {
        return (int) round($value);
    }
}
