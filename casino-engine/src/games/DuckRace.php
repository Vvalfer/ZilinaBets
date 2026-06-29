<?php

declare(strict_types=1);

namespace CasinoEngine\games;

use CasinoEngine\AbstractGame;
use CasinoEngine\InvalidBetException;
use CasinoEngine\Paytables;
use CasinoEngine\RandomSource;
use CasinoEngine\RoundResult;

/**
 * Duck race: 5 ducks, you bet on one to win. The winner is drawn server-side
 * from fixed true probabilities (Paytables::DUCK_PROBS); the payout odds
 * (Paytables::DUCK_ODDS) bake in the house margin (prob * odds ~= 0.90 per duck).
 *
 * The result also carries a finishing $order and a per-tick $timeline so the
 * frontend can animate a race that is consistent with the already-decided
 * winner — the animation is pure theatre, it cannot change the outcome.
 *
 * Bet: ['amount' => chips, 'duck' => 0..4].
 */
final class DuckRace extends AbstractGame
{
    private const TICKS = 20;

    public function key(): string
    {
        return 'duckrace';
    }

    public function validateBet(array $bet): void
    {
        $this->validateAmount($this->requireKey($bet, 'amount'));
        $duck = $this->requireKey($bet, 'duck');
        $count = count(Paytables::DUCK_PROBS);
        if (!is_int($duck) || $duck < 0 || $duck >= $count) {
            throw new InvalidBetException("Duck must be an integer 0..$count.");
        }
    }

    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult
    {
        $this->validateBet($bet);
        $amount = (int) $bet['amount'];
        $duck = (int) $bet['duck'];

        $winner = $rng->pickWeighted(Paytables::DUCK_PROBS);

        $won = $duck === $winner;
        $odds = Paytables::DUCK_ODDS[$duck];
        $payout = $won ? Paytables::roundChips($amount * $odds) : 0;

        [$order, $timeline] = $this->buildRace($winner, $rng);

        return new RoundResult(
            status: RoundResult::RESOLVED,
            payout: $payout,
            outcome: [
                'winner' => $winner,
                'order' => $order,
                'win' => $won,
                'odds' => $odds,
                'names' => Paytables::DUCK_NAMES,
                'timeline' => $timeline,
                'bet' => ['amount' => $amount, 'duck' => $duck],
            ],
        );
    }

    /**
     * Build a finishing order (winner first) and a per-tick position table,
     * all consistent with the pre-decided $winner.
     *
     * @return array{0: int[], 1: array<int, array<int, float>>}
     */
    private function buildRace(int $winner, RandomSource $rng): array
    {
        $count = count(Paytables::DUCK_PROBS);

        // Assign each duck a speed; force the winner to be strictly fastest.
        $speeds = [];
        $maxOther = 0;
        for ($i = 0; $i < $count; $i++) {
            $speeds[$i] = $rng->int(40, 85);
            if ($i !== $winner) {
                $maxOther = max($maxOther, $speeds[$i]);
            }
        }
        $speeds[$winner] = $maxOther + $rng->int(6, 18);

        // Finishing order: fastest first, index as a stable tie-break.
        $order = range(0, $count - 1);
        usort($order, static fn (int $a, int $b): int => ($speeds[$b] <=> $speeds[$a]) ?: ($a <=> $b));

        // Positions per tick (0..100), scaled so the winner hits 100 on the last tick.
        $scale = 100.0 / ($speeds[$winner] * self::TICKS);
        $timeline = [];
        for ($t = 1; $t <= self::TICKS; $t++) {
            $row = [];
            for ($i = 0; $i < $count; $i++) {
                $row[$i] = round(min(100.0, $speeds[$i] * $t * $scale), 2);
            }
            $timeline[] = $row;
        }

        return [$order, $timeline];
    }
}
