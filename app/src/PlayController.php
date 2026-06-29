<?php

declare(strict_types=1);

namespace CasinoApp;

use CasinoEngine\CryptoRandom;
use CasinoEngine\GameFactory;
use CasinoEngine\IllegalActionException;
use CasinoEngine\InvalidBetException;
use CasinoEngine\RoundResult;
use PDO;

/**
 * The server-authoritative bet handler. This is the production counterpart to
 * the engine's reference-api: it debits the stake from a real balance inside a
 * locked transaction, runs the pure engine, persists stateful rounds on the
 * server (never trusting client state), and credits the payout on resolve.
 *
 *   POST /api/play   { game, bet, roundId? }
 *     - fresh bet:        omit roundId. Debits stake, plays, returns outcome.
 *     - stateful follow:  send roundId + bet = { action, keep? }.
 */
final class PlayController
{
    public static function handle(): void
    {
        $user = Auth::requireUser();
        $body = Http::jsonBody();

        $roundId = isset($body['roundId']) && is_string($body['roundId']) ? $body['roundId'] : null;
        $bet = is_array($body['bet'] ?? null) ? $body['bet'] : [];

        try {
            $response = Db::transaction(static function (PDO $pdo) use ($user, $body, $bet, $roundId): array {
                return $roundId === null
                    ? self::freshBet($pdo, (int) $user['id'], (string) ($body['game'] ?? ''), $bet)
                    : self::continueRound($pdo, (int) $user['id'], $roundId, $bet);
            });
        } catch (InvalidBetException $e) {
            Http::error($e->getMessage(), 422, 'invalid_bet');
        } catch (IllegalActionException $e) {
            Http::error($e->getMessage(), 409, 'illegal_action');
        } catch (InsufficientFundsException $e) {
            Http::error($e->getMessage(), 402, 'insufficient_funds');
        } catch (\RuntimeException $e) {
            Http::error($e->getMessage(), 400, 'error');
        } catch (\Throwable $e) {
            // Anything unexpected (a bug, a DB error) still returns clean JSON,
            // and the locked transaction has already rolled back.
            Http::error('Something went wrong while resolving the round.', 500, 'internal');
        }

        Http::json($response);
    }

    /**
     * POST /api/roulette { bets: [ {type,value,amount}, ... ] }
     * One spin, several simultaneous bets. The pocket is drawn once with the
     * real CSPRNG; each bet is then resolved by the genuine engine against that
     * same number (via FixedRandom), so payouts stay server-authoritative.
     */
    public static function roulette(): void
    {
        $user = Auth::requireUser();
        $body = Http::jsonBody();
        $bets = is_array($body['bets'] ?? null) ? $body['bets'] : [];
        if (count($bets) === 0) {
            Http::error('Place at least one bet.', 422, 'invalid_bet');
        }
        if (count($bets) > 60) {
            Http::error('Too many bets on one spin.', 422, 'invalid_bet');
        }

        try {
            $response = Db::transaction(static function (PDO $pdo) use ($user, $bets): array {
                $game = GameFactory::create('roulette');

                $total = 0;
                foreach ($bets as $b) {
                    $bet = is_array($b) ? $b : [];
                    $game->validateBet($bet);            // throws InvalidBetException
                    $total += (int) $bet['amount'];
                }

                $balance = Wallet::apply($pdo, (int) $user['id'], -$total, 'bet', 'roulette');

                $number = (new CryptoRandom())->int(0, 36);
                $rng = new FixedRandom($number);

                $results = [];
                $payoutSum = 0;
                $color = '';
                foreach ($bets as $b) {
                    $r = $game->play($b, $rng);          // same number for every bet
                    $o = $r->outcome;
                    $color = $o['color'];
                    $payoutSum += $r->payout;
                    $results[] = [
                        'type'       => $o['bet']['type'],
                        'value'      => $o['bet']['value'],
                        'amount'     => $o['bet']['amount'],
                        'win'        => $o['win'],
                        'multiplier' => $o['multiplier'],
                        'payout'     => $r->payout,
                    ];
                }

                if ($payoutSum > 0) {
                    $balance = Wallet::apply($pdo, (int) $user['id'], $payoutSum, 'payout', 'roulette');
                }

                return [
                    'number'      => $number,
                    'color'       => $color,
                    'results'     => $results,
                    'totalStake'  => $total,
                    'totalPayout' => $payoutSum,
                    'net'         => $payoutSum - $total,
                    'balance'     => $balance,
                ];
            });
        } catch (InvalidBetException $e) {
            Http::error($e->getMessage(), 422, 'invalid_bet');
        } catch (InsufficientFundsException $e) {
            Http::error($e->getMessage(), 402, 'insufficient_funds');
        } catch (\RuntimeException $e) {
            Http::error($e->getMessage(), 400, 'error');
        } catch (\Throwable $e) {
            Http::error('Something went wrong resolving the spin.', 500, 'internal');
        }

        Http::json($response);
    }

    /** A brand-new bet: validate, debit stake, play, settle-or-persist. */
    private static function freshBet(PDO $pdo, int $userId, string $gameKey, array $bet): array
    {
        $game = GameFactory::create($gameKey);   // throws InvalidBetException on unknown game
        $game->validateBet($bet);                // throws InvalidBetException on bad bet

        $stake = (int) $bet['amount'];
        // Debit the stake first; refuses if the player can't cover it.
        $balance = Wallet::apply($pdo, $userId, -$stake, 'bet', $gameKey);

        // Secret "67" jackpot on the slots (~15%): pays 670x. Decided server-side
        // so the payout is authoritative; the paytable shows it only as "???".
        if ($gameKey === 'slots' && random_int(1, 1000) <= 150) {
            $payout = $stake * 670;
            $balance = Wallet::apply($pdo, $userId, $payout, 'payout', 'slots');
            $jackpot = new RoundResult(RoundResult::RESOLVED, $payout, [
                'reels'      => ['0', '6', '7'],
                'win'        => true,
                'symbol'     => 'sixseven',
                'multiplier' => 670,
                'jackpot'    => true,
                'bet'        => ['amount' => $stake],
            ]);
            return self::resolvedResponse($jackpot, $balance, $stake);
        }

        $result = $game->play($bet, new CryptoRandom(), null);

        if ($result->isResolved()) {
            $balance = self::credit($pdo, $userId, $gameKey, $result);
            return self::resolvedResponse($result, $balance, $stake);
        }

        // Stateful game still in progress: persist server-side, hand back a round id.
        $newRoundId = RoundStore::create($pdo, $userId, $gameKey, $stake, $result->state ?? []);
        return self::inProgressResponse($result, $balance, $newRoundId, $stake);
    }

    /** A follow-up action on a stored, in-progress round. */
    private static function continueRound(PDO $pdo, int $userId, string $roundId, array $bet): array
    {
        $round = RoundStore::load($pdo, $userId, $roundId);
        if ($round === null) {
            throw new IllegalActionException('Round not found or already finished.');
        }

        $gameKey = $round['game'];
        $game = GameFactory::create($gameKey);
        $action = is_string($bet['action'] ?? null) ? $bet['action'] : '';
        $totalStake = $round['stake'];
        $balance = Wallet::balanceForUpdate($pdo, $userId);

        // Blackjack "double" commits an extra stake equal to the original bet.
        // Debit it up front; if the engine then rejects the action the whole
        // transaction rolls back, so nothing is lost.
        if ($gameKey === 'blackjack' && $action === 'double') {
            $extra = $round['stake'];
            $balance = Wallet::apply($pdo, $userId, -$extra, 'bet_double', $gameKey);
            $totalStake += $extra;
        }

        $result = $game->play($bet, new CryptoRandom(), $round['state']);

        if ($result->isResolved()) {
            $balance = self::credit($pdo, $userId, $gameKey, $result);
            RoundStore::delete($pdo, $roundId);
            return self::resolvedResponse($result, $balance, $totalStake);
        }

        // Still in progress (blackjack hit, 421 reroll with rolls left): re-persist.
        RoundStore::update($pdo, $roundId, $result->state ?? [], $totalStake);
        return self::inProgressResponse($result, $balance, $roundId, $totalStake);
    }

    /** Credit the gross payout (if any) and return the new balance. */
    private static function credit(PDO $pdo, int $userId, string $gameKey, RoundResult $result): int
    {
        if ($result->payout > 0) {
            return Wallet::apply($pdo, $userId, $result->payout, 'payout', $gameKey);
        }
        return Wallet::balanceForUpdate($pdo, $userId);
    }

    private static function resolvedResponse(RoundResult $result, int $balance, int $totalStake): array
    {
        return [
            'status'      => RoundResult::RESOLVED,
            'outcome'     => $result->outcome,
            'payout'      => $result->payout,
            'net'         => $result->payout - $totalStake,
            'nextActions' => [],
            'roundId'     => null,
            'balance'     => $balance,
        ];
    }

    private static function inProgressResponse(RoundResult $result, int $balance, string $roundId, int $totalStake): array
    {
        return [
            'status'      => RoundResult::IN_PROGRESS,
            'outcome'     => $result->outcome,
            'payout'      => 0,
            'nextActions' => $result->nextActions,
            'roundId'     => $roundId,
            'stake'       => $totalStake,
            'balance'     => $balance,
        ];
    }
}
