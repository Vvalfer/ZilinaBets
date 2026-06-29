<?php

declare(strict_types=1);

namespace CasinoEngine\games;

use CasinoEngine\AbstractGame;
use CasinoEngine\Paytables;
use CasinoEngine\RandomSource;
use CasinoEngine\RoundResult;

/**
 * Three-reel slot machine. Each reel draws independently from the same
 * weighted strip (see Paytables::SLOTS_STRIP). A line pays only on three of a
 * kind. The strip weights are what set the RTP, which the test-suite verifies
 * both analytically and by simulation.
 *
 * Bet: ['amount' => chips].
 */
final class Slots extends AbstractGame
{
    public function key(): string
    {
        return 'slots';
    }

    public function validateBet(array $bet): void
    {
        $this->validateAmount($this->requireKey($bet, 'amount'));
    }

    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult
    {
        $this->validateBet($bet);
        $amount = (int) $bet['amount'];

        $strip = Paytables::SLOTS_STRIP;
        $reels = [];
        for ($i = 0; $i < 3; $i++) {
            $reels[] = $strip[$rng->int(0, count($strip) - 1)];
        }

        $eval = self::evaluate($reels);
        $payout = Paytables::roundChips($amount * $eval['multiplier']);

        return new RoundResult(
            status: RoundResult::RESOLVED,
            payout: $payout,
            outcome: [
                'reels' => $reels,
                'win' => $eval['win'],
                'symbol' => $eval['symbol'],
                'multiplier' => $eval['multiplier'],
                'bet' => ['amount' => $amount],
            ],
        );
    }

    /**
     * Pure evaluation of three reel symbols.
     * @return array{win:bool,symbol:?string,multiplier:int}
     */
    public static function evaluate(array $reels): array
    {
        if (count($reels) === 3 && $reels[0] === $reels[1] && $reels[1] === $reels[2]) {
            $symbol = $reels[0];
            return [
                'win' => true,
                'symbol' => $symbol,
                'multiplier' => Paytables::SLOTS_PAYOUTS[$symbol] ?? 0,
            ];
        }
        return ['win' => false, 'symbol' => null, 'multiplier' => 0];
    }
}
