<?php

declare(strict_types=1);

namespace CasinoEngine\games;

use CasinoEngine\AbstractGame;
use CasinoEngine\IllegalActionException;
use CasinoEngine\Paytables;
use CasinoEngine\RandomSource;
use CasinoEngine\RoundResult;

/**
 * Blackjack against the house. The only stateful game in the engine.
 *
 * Rules implemented:
 *   - 6-deck shoe, shuffled fresh each round, dealt player, dealer, player, dealer.
 *   - Blackjack (natural 21) pays 3:2.
 *   - Dealer draws to 17 and stands on all 17s (including soft 17).
 *   - Player actions: hit, stand, double (double only as the first decision).
 *   - Split is intentionally out of scope for v1 (documented extension).
 *
 * Flow:
 *   - Deal (state null): bet = ['amount' => chips]. Resolves immediately on a
 *     natural; otherwise IN_PROGRESS with nextActions.
 *   - Action: bet = ['action' => 'hit'|'stand'|'double'], state carried over.
 *
 * While IN_PROGRESS only the dealer's up-card is revealed in $outcome; the hole
 * card stays in $state on the server and is never sent to the client.
 */
final class Blackjack extends AbstractGame
{
    private const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    private const SUITS = ['S', 'H', 'D', 'C'];

    public function key(): string
    {
        return 'blackjack';
    }

    public function validateBet(array $bet): void
    {
        $this->validateAmount($this->requireKey($bet, 'amount'));
    }

    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult
    {
        if ($state === null) {
            return $this->deal($bet, $rng);
        }
        return $this->act($bet, $state);
    }

    private function deal(array $bet, RandomSource $rng): RoundResult
    {
        $this->validateBet($bet);
        $amount = (int) $bet['amount'];

        $shoe = $this->buildShoe($rng);
        // Dealt one at a time, alternating: player, dealer, player, dealer.
        $player = [];
        $dealer = [];
        $player[] = array_shift($shoe);
        $dealer[] = array_shift($shoe);
        $player[] = array_shift($shoe);
        $dealer[] = array_shift($shoe);

        $playerNatural = self::isBlackjack($player);
        $dealerNatural = self::isBlackjack($dealer);

        if ($playerNatural || $dealerNatural) {
            if ($playerNatural && $dealerNatural) {
                return $this->settle($amount, $player, $dealer, 'push');
            }
            return $playerNatural
                ? $this->settle($amount, $player, $dealer, 'blackjack')
                : $this->settle($amount, $player, $dealer, 'lose');
        }

        return new RoundResult(
            status: RoundResult::IN_PROGRESS,
            payout: 0,
            outcome: [
                'player' => $player,
                'playerTotal' => self::handValue($player),
                'dealerUpCard' => $dealer[0],
            ],
            state: ['bet' => $amount, 'shoe' => $shoe, 'player' => $player, 'dealer' => $dealer],
            nextActions: ['hit', 'stand', 'double'],
        );
    }

    private function act(array $bet, array $state): RoundResult
    {
        $action = $bet['action'] ?? null;
        $amount = (int) $state['bet'];
        $shoe = $state['shoe'];
        $player = $state['player'];
        $dealer = $state['dealer'];

        switch ($action) {
            case 'hit':
                $player[] = array_shift($shoe);
                $total = self::handValue($player);
                if ($total > 21) {
                    return $this->settle($amount, $player, $dealer, 'lose');
                }
                if ($total === 21) {
                    return $this->dealerPlaysAndSettle($amount, $player, $dealer, $shoe);
                }
                return new RoundResult(
                    status: RoundResult::IN_PROGRESS,
                    payout: 0,
                    outcome: [
                        'player' => $player,
                        'playerTotal' => $total,
                        'dealerUpCard' => $dealer[0],
                    ],
                    state: ['bet' => $amount, 'shoe' => $shoe, 'player' => $player, 'dealer' => $dealer],
                    nextActions: ['hit', 'stand'], // no double after taking a card
                );

            case 'stand':
                return $this->dealerPlaysAndSettle($amount, $player, $dealer, $shoe);

            case 'double':
                if (count($player) !== 2) {
                    throw new IllegalActionException('Double is only allowed as the first decision.');
                }
                $effective = $amount * 2;
                $player[] = array_shift($shoe);
                if (self::handValue($player) > 21) {
                    $result = $this->settle($effective, $player, $dealer, 'lose');
                } else {
                    $result = $this->dealerPlaysAndSettle($effective, $player, $dealer, $shoe);
                }
                // Tell the backend to debit the extra stake equal to the original bet.
                $outcome = $result->outcome;
                $outcome['additionalBet'] = $amount;
                $outcome['doubled'] = true;
                return new RoundResult($result->status, $result->payout, $outcome);

            default:
                throw new IllegalActionException("Unknown blackjack action '" . (string) $action . "'.");
        }
    }

    private function dealerPlaysAndSettle(int $stake, array $player, array $dealer, array $shoe): RoundResult
    {
        while (self::handValue($dealer) < 17) {
            $dealer[] = array_shift($shoe);
        }
        $playerTotal = self::handValue($player);
        $dealerTotal = self::handValue($dealer);

        if ($dealerTotal > 21 || $playerTotal > $dealerTotal) {
            $result = 'win';
        } elseif ($playerTotal === $dealerTotal) {
            $result = 'push';
        } else {
            $result = 'lose';
        }
        return $this->settle($stake, $player, $dealer, $result);
    }

    private function settle(int $stake, array $player, array $dealer, string $result): RoundResult
    {
        $multiplier = Paytables::BLACKJACK[$result];
        $payout = Paytables::roundChips($stake * $multiplier);

        return new RoundResult(
            status: RoundResult::RESOLVED,
            payout: $payout,
            outcome: [
                'player' => $player,
                'dealer' => $dealer,
                'playerTotal' => self::handValue($player),
                'dealerTotal' => self::handValue($dealer),
                'result' => $result, // win | lose | push | blackjack
                'stake' => $stake,
            ],
        );
    }

    private function buildShoe(RandomSource $rng): array
    {
        $cards = [];
        for ($d = 0; $d < Paytables::BLACKJACK_DECKS; $d++) {
            foreach (self::SUITS as $suit) {
                foreach (self::RANKS as $rank) {
                    $cards[] = ['rank' => $rank, 'suit' => $suit];
                }
            }
        }
        return $rng->shuffle($cards);
    }

    /** Best hand total, treating aces as 11 then demoting to 1 as needed. */
    public static function handValue(array $hand): int
    {
        $total = 0;
        $aces = 0;
        foreach ($hand as $card) {
            $rank = $card['rank'];
            if ($rank === 'A') {
                $aces++;
                $total += 11;
            } elseif (in_array($rank, ['K', 'Q', 'J'], true)) {
                $total += 10;
            } else {
                $total += (int) $rank;
            }
        }
        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }
        return $total;
    }

    public static function isBlackjack(array $hand): bool
    {
        return count($hand) === 2 && self::handValue($hand) === 21;
    }
}
