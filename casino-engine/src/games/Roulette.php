<?php

declare(strict_types=1);

namespace CasinoEngine\games;

use CasinoEngine\AbstractGame;
use CasinoEngine\InvalidBetException;
use CasinoEngine\Paytables;
use CasinoEngine\RandomSource;
use CasinoEngine\RoundResult;

/**
 * European roulette: pockets 0..36, a single green zero (house edge 2.70%).
 * The winning number is drawn server-side; the client only animates it.
 *
 * Supported bets (bet['type'] => bet['value']):
 *   straight => 0..36           pays 36x
 *   color    => 'red'|'black'   pays 2x
 *   parity   => 'odd'|'even'    pays 2x
 *   range    => 'low'|'high'    pays 2x   (low = 1..18, high = 19..36)
 *   dozen    => 1|2|3           pays 3x
 * Zero loses every bet except a straight bet placed on 0.
 */
final class Roulette extends AbstractGame
{
    public function key(): string
    {
        return 'roulette';
    }

    public static function colorOf(int $number): string
    {
        if ($number === 0) {
            return 'green';
        }
        return in_array($number, Paytables::ROULETTE_RED, true) ? 'red' : 'black';
    }

    public function validateBet(array $bet): void
    {
        $this->validateAmount($this->requireKey($bet, 'amount'));
        $type = $this->requireKey($bet, 'type');
        $value = $bet['value'] ?? null;

        switch ($type) {
            case 'straight':
                if (!is_int($value) || $value < 0 || $value > 36) {
                    throw new InvalidBetException('Straight bet must target a number 0..36.');
                }
                break;
            case 'color':
                $this->assertIn($value, ['red', 'black'], 'color');
                break;
            case 'parity':
                $this->assertIn($value, ['odd', 'even'], 'parity');
                break;
            case 'range':
                $this->assertIn($value, ['low', 'high'], 'range');
                break;
            case 'dozen':
                if (!in_array($value, [1, 2, 3], true)) {
                    throw new InvalidBetException('Dozen bet must be 1, 2 or 3.');
                }
                break;
            default:
                throw new InvalidBetException("Unknown roulette bet type '$type'.");
        }
    }

    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult
    {
        $this->validateBet($bet);
        $amount = (int) $bet['amount'];
        $type = $bet['type'];
        $value = $bet['value'] ?? null;

        $number = $rng->int(0, 36);
        $color = self::colorOf($number);

        $won = $this->isWinningBet($number, $color, $type, $value);
        $multiplier = $won ? Paytables::ROULETTE[$type] : 0;
        $payout = Paytables::roundChips($amount * $multiplier);

        return new RoundResult(
            status: RoundResult::RESOLVED,
            payout: $payout,
            outcome: [
                'number' => $number,
                'color' => $color,
                'win' => $won,
                'multiplier' => $multiplier,
                'bet' => ['type' => $type, 'value' => $value, 'amount' => $amount],
            ],
        );
    }

    private function isWinningBet(int $number, string $color, string $type, mixed $value): bool
    {
        return match ($type) {
            'straight' => $number === $value,
            'color'    => $number !== 0 && $color === $value,
            'parity'   => $number !== 0 && (($number % 2 === 0) ? 'even' : 'odd') === $value,
            'range'    => $number !== 0 && (($number <= 18) ? 'low' : 'high') === $value,
            'dozen'    => $number !== 0 && (int) ceil($number / 12) === $value,
            default    => false,
        };
    }

    private function assertIn(mixed $value, array $allowed, string $label): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidBetException(
                ucfirst($label) . ' bet must be one of: ' . implode(', ', $allowed) . '.'
            );
        }
    }
}
