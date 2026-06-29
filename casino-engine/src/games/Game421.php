<?php

declare(strict_types=1);

namespace CasinoEngine\games;

use CasinoEngine\AbstractGame;
use CasinoEngine\IllegalActionException;
use CasinoEngine\InvalidBetException;
use CasinoEngine\Paytables;
use CasinoEngine\RandomSource;
use CasinoEngine\RoundResult;

/**
 * 421 — a French three-dice game, here as a solo-vs-house casino variant.
 *
 * Flow (stateful):
 *   1. Initial roll: state is null, bet = ['amount' => chips]. Three dice are
 *      rolled. The round is IN_PROGRESS with 2 rerolls available.
 *   2. Reroll: bet = ['action' => 'reroll', 'keep' => [bool, bool, bool]].
 *      Dice marked keep=true are kept, the rest are rerolled. When no rerolls
 *      remain, the round resolves automatically.
 *   3. Stand: bet = ['action' => 'stand'] resolves immediately with current dice.
 *
 * Scoring (see Paytables::COMBO_421):
 *   4-2-1                => x11   (the namesake)
 *   1-1-1                => x7    (brelan d'as)
 *   any other triple     => x4
 *   3 consecutive values => x3    (run / suite)
 *   2-2-1                => x2    (la nénette)
 *   anything else        => lose
 */
final class Game421 extends AbstractGame
{
    private const MAX_REROLLS = 2;

    public function key(): string
    {
        return '421';
    }

    public function validateBet(array $bet): void
    {
        $this->validateAmount($this->requireKey($bet, 'amount'));
    }

    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult
    {
        if ($state === null) {
            return $this->initialRoll($bet, $rng);
        }
        return $this->applyAction($bet, $rng, $state);
    }

    private function initialRoll(array $bet, RandomSource $rng): RoundResult
    {
        $this->validateBet($bet);
        $amount = (int) $bet['amount'];
        $dice = [$rng->int(1, 6), $rng->int(1, 6), $rng->int(1, 6)];

        return new RoundResult(
            status: RoundResult::IN_PROGRESS,
            payout: 0,
            outcome: ['dice' => $dice, 'rerollsLeft' => self::MAX_REROLLS],
            state: ['bet' => $amount, 'dice' => $dice, 'rerollsLeft' => self::MAX_REROLLS],
            nextActions: ['reroll', 'stand'],
        );
    }

    private function applyAction(array $bet, RandomSource $rng, array $state): RoundResult
    {
        $action = $bet['action'] ?? null;
        $amount = (int) $state['bet'];
        $dice = $state['dice'];
        $rerollsLeft = (int) $state['rerollsLeft'];

        if ($action === 'stand') {
            return $this->resolve($dice, $amount);
        }

        if ($action !== 'reroll') {
            throw new IllegalActionException("Unknown 421 action '" . (string) $action . "'.");
        }
        if ($rerollsLeft <= 0) {
            throw new IllegalActionException('No rerolls remaining.');
        }

        $keep = $bet['keep'] ?? [false, false, false];
        if (!is_array($keep) || count($keep) !== 3) {
            throw new InvalidBetException("'keep' must be an array of exactly 3 booleans.");
        }
        for ($i = 0; $i < 3; $i++) {
            if (!$keep[$i]) {
                $dice[$i] = $rng->int(1, 6);
            }
        }
        $rerollsLeft--;

        if ($rerollsLeft === 0) {
            return $this->resolve($dice, $amount); // last reroll auto-resolves
        }

        return new RoundResult(
            status: RoundResult::IN_PROGRESS,
            payout: 0,
            outcome: ['dice' => $dice, 'rerollsLeft' => $rerollsLeft],
            state: ['bet' => $amount, 'dice' => $dice, 'rerollsLeft' => $rerollsLeft],
            nextActions: ['reroll', 'stand'],
        );
    }

    private function resolve(array $dice, int $amount): RoundResult
    {
        $score = self::score($dice);
        $payout = Paytables::roundChips($amount * $score['multiplier']);

        return new RoundResult(
            status: RoundResult::RESOLVED,
            payout: $payout,
            outcome: [
                'dice' => $dice,
                'combo' => $score['combo'],
                'multiplier' => $score['multiplier'],
                'win' => $payout > 0,
                'bet' => ['amount' => $amount],
            ],
        );
    }

    /**
     * Pure scoring of three dice.
     * @return array{combo:string,multiplier:int}
     */
    public static function score(array $dice): array
    {
        $sorted = $dice;
        sort($sorted); // ascending

        $combo = self::classify($sorted);
        return ['combo' => $combo, 'multiplier' => Paytables::COMBO_421[$combo]];
    }

    private static function classify(array $sorted): string
    {
        if ($sorted === [1, 2, 4]) {
            return '421';
        }
        if ($sorted[0] === $sorted[1] && $sorted[1] === $sorted[2]) {
            return $sorted[0] === 1 ? 'aces' : 'triple';
        }
        if ($sorted[1] === $sorted[0] + 1 && $sorted[2] === $sorted[1] + 1) {
            return 'run'; // three consecutive values
        }
        if ($sorted === [1, 2, 2]) {
            return 'nenette';
        }
        return 'nothing';
    }
}
