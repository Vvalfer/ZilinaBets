<?php

declare(strict_types=1);

namespace CasinoApp;

use CasinoEngine\CryptoRandom;
use PDO;

/**
 * Craps — the Pass Line bet, resolved server-side.
 *
 *   Come-out roll: 7 or 11 win, 2 / 3 / 12 lose, anything else becomes the point.
 *   Point phase:   roll the point again to win, roll a 7 to lose, else roll on.
 *   Pass line pays even money (payout = 2x stake on a win).
 *
 * Stateful: the point + stake live in the rounds table (game = 'craps'), keyed
 * by a server round id — never trusted from the client. Endpoint POST /api/craps
 *   - come-out:   { bet: { amount } }
 *   - next rolls: { roundId }
 */
final class CrapsController
{
    private const MIN_BET = 1;

    public static function handle(): void
    {
        $user = Auth::requireUser();
        $body = Http::jsonBody();
        $roundId = isset($body['roundId']) && is_string($body['roundId']) ? $body['roundId'] : null;

        try {
            $response = Db::transaction(static function (PDO $pdo) use ($user, $body, $roundId): array {
                return $roundId === null
                    ? self::comeOut($pdo, (int) $user['id'], $body)
                    : self::pointRoll($pdo, (int) $user['id'], $roundId);
            });
        } catch (InsufficientFundsException $e) {
            Http::error($e->getMessage(), 402, 'insufficient_funds');
        } catch (\RuntimeException $e) {
            Http::error($e->getMessage(), 400, 'error');
        } catch (\Throwable $e) {
            Http::error('Something went wrong on the roll.', 500, 'internal');
        }

        Http::json($response);
    }

    /** @return array{0:int,1:int,2:int} two dice + their sum */
    private static function rollDice(CryptoRandom $rng): array
    {
        $a = $rng->int(1, 6);
        $b = $rng->int(1, 6);
        return [$a, $b, $a + $b];
    }

    private static function comeOut(PDO $pdo, int $userId, array $body): array
    {
        $bet = is_array($body['bet'] ?? null) ? $body['bet'] : [];
        $amount = self::validateAmount($bet['amount'] ?? null);

        $balance = Wallet::apply($pdo, $userId, -$amount, 'bet', 'craps');
        [$a, $b, $sum] = self::rollDice(new CryptoRandom());

        if ($sum === 7 || $sum === 11) {
            $balance = Wallet::apply($pdo, $userId, $amount * 2, 'payout', 'craps');
            return self::done([$a, $b], $sum, 'win', null, $amount * 2, $amount, $balance);
        }
        if ($sum === 2 || $sum === 3 || $sum === 12) {
            return self::done([$a, $b], $sum, 'lose', null, 0, $amount, $balance);
        }

        // Point established.
        $rid = RoundStore::create($pdo, $userId, 'craps', $amount, ['stake' => $amount, 'point' => $sum]);
        return [
            'status'  => 'in_progress',
            'outcome' => ['dice' => [$a, $b], 'sum' => $sum, 'phase' => 'point', 'point' => $sum, 'result' => null],
            'roundId' => $rid,
            'balance' => $balance,
        ];
    }

    private static function pointRoll(PDO $pdo, int $userId, string $roundId): array
    {
        $round = RoundStore::load($pdo, $userId, $roundId);
        if ($round === null || $round['game'] !== 'craps') {
            throw new \RuntimeException('Round not found or already finished.');
        }
        $amount = (int) $round['state']['stake'];
        $point = (int) $round['state']['point'];
        $balance = Wallet::balanceForUpdate($pdo, $userId);

        [$a, $b, $sum] = self::rollDice(new CryptoRandom());

        if ($sum === $point) {
            $balance = Wallet::apply($pdo, $userId, $amount * 2, 'payout', 'craps');
            RoundStore::delete($pdo, $roundId);
            return self::done([$a, $b], $sum, 'win', $point, $amount * 2, $amount, $balance);
        }
        if ($sum === 7) {
            RoundStore::delete($pdo, $roundId);
            return self::done([$a, $b], $sum, 'lose', $point, 0, $amount, $balance);
        }

        return [
            'status'  => 'in_progress',
            'outcome' => ['dice' => [$a, $b], 'sum' => $sum, 'phase' => 'point', 'point' => $point, 'result' => null],
            'roundId' => $roundId,
            'balance' => $balance,
        ];
    }

    private static function done(array $dice, int $sum, string $result, ?int $point, int $payout, int $stake, int $balance): array
    {
        return [
            'status'  => 'resolved',
            'outcome' => ['dice' => $dice, 'sum' => $sum, 'phase' => 'resolved', 'point' => $point, 'result' => $result],
            'payout'  => $payout,
            'net'     => $payout - $stake,
            'roundId' => null,
            'balance' => $balance,
        ];
    }

    private static function validateAmount(mixed $amount): int
    {
        $isInt = is_int($amount);
        $isDigit = is_string($amount) && $amount !== '' && ctype_digit($amount);
        if (!$isInt && !$isDigit) {
            throw new \RuntimeException('Bet amount must be a whole number of chips.');
        }
        $v = (int) $amount;
        if ($v < self::MIN_BET) {
            throw new \RuntimeException('Bet is below the minimum of ' . self::MIN_BET . ' chip.');
        }
        return $v;
    }
}
