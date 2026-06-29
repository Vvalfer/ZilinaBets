<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\Blackjack;
use CasinoEngine\IllegalActionException;
use CasinoEngine\RoundResult;
use CasinoEngine\Tests\Support\Cards;
use CasinoEngine\Tests\Support\ScriptedRandom;
use PHPUnit\Framework\TestCase;

final class BlackjackTest extends TestCase
{
    /** Build a ScriptedRandom whose shuffle returns the given shoe (by rank). */
    private function rngWithShoe(array $ranks): ScriptedRandom
    {
        $rng = new ScriptedRandom();
        $rng->shuffleReturn = Cards::shoe($ranks);
        return $rng;
    }

    public function testHandValueAcesAdjust(): void
    {
        $this->assertSame(21, Blackjack::handValue(Cards::shoe(['A', 'K'])));
        $this->assertSame(21, Blackjack::handValue(Cards::shoe(['A', 'A', '9'])));
        $this->assertSame(17, Blackjack::handValue(Cards::shoe(['A', '6'])));      // soft 17
        $this->assertSame(17, Blackjack::handValue(Cards::shoe(['A', '6', '10']))); // ace demoted
        $this->assertSame(30, Blackjack::handValue(Cards::shoe(['K', 'Q', 'J'])));
    }

    public function testIsBlackjackOnlyForTwoCardTwentyOne(): void
    {
        $this->assertTrue(Blackjack::isBlackjack(Cards::shoe(['A', 'Q'])));
        $this->assertFalse(Blackjack::isBlackjack(Cards::shoe(['10', '6', '5']))); // 21 but 3 cards
    }

    public function testPlayerBlackjackPays3to2(): void
    {
        // deal order player,dealer,player,dealer => player [A,K], dealer [9,5]
        $rng = $this->rngWithShoe(['A', '9', 'K', '5']);
        $result = (new Blackjack())->play(['amount' => 10], $rng);

        $this->assertTrue($result->isResolved());
        $this->assertSame('blackjack', $result->outcome['result']);
        $this->assertSame(25, $result->payout); // round(10 * 2.5)
    }

    public function testBothBlackjackPush(): void
    {
        $rng = $this->rngWithShoe(['A', 'A', 'K', 'K']); // player [A,K], dealer [A,K]
        $result = (new Blackjack())->play(['amount' => 10], $rng);
        $this->assertSame('push', $result->outcome['result']);
        $this->assertSame(10, $result->payout); // stake returned
    }

    public function testInProgressHidesHoleCard(): void
    {
        $rng = $this->rngWithShoe(['K', '9', 'Q', '8']); // player 20, dealer up 9
        $result = (new Blackjack())->play(['amount' => 10], $rng);

        $this->assertSame(RoundResult::IN_PROGRESS, $result->status);
        $this->assertArrayHasKey('dealerUpCard', $result->outcome);
        $this->assertSame('9', $result->outcome['dealerUpCard']['rank']);
        $this->assertFalse(
            array_key_exists('dealer', $result->outcome),
            'The dealer hole card must not leak to the client while in progress.'
        );
    }

    public function testStandBeatsDealer(): void
    {
        $game = new Blackjack();
        $rng = $this->rngWithShoe(['K', '9', 'Q', '8']); // player 20, dealer 17
        $deal = $game->play(['amount' => 10], $rng);

        $result = $game->play(['action' => 'stand'], new ScriptedRandom(), $deal->state);
        $this->assertSame('win', $result->outcome['result']);
        $this->assertSame(20, $result->payout);
        $this->assertSame(17, $result->outcome['dealerTotal']);
    }

    public function testHitCanBust(): void
    {
        $game = new Blackjack();
        $rng = $this->rngWithShoe(['10', '9', '6', '8', '10']); // player 16, then draws 10
        $deal = $game->play(['amount' => 10], $rng);

        $result = $game->play(['action' => 'hit'], new ScriptedRandom(), $deal->state);
        $this->assertTrue($result->isResolved());
        $this->assertSame('lose', $result->outcome['result']);
        $this->assertGreaterThan(21, $result->outcome['playerTotal']);
        $this->assertSame(0, $result->payout);
    }

    public function testDealerBustsOnStand(): void
    {
        $game = new Blackjack();
        $rng = $this->rngWithShoe(['10', '10', '8', '6', '10']); // player 18, dealer 16 -> +10 = 26
        $deal = $game->play(['amount' => 10], $rng);

        $result = $game->play(['action' => 'stand'], new ScriptedRandom(), $deal->state);
        $this->assertSame('win', $result->outcome['result']);
        $this->assertGreaterThan(21, $result->outcome['dealerTotal']);
        $this->assertSame(20, $result->payout);
    }

    public function testDoubleWinsOnDoubledStake(): void
    {
        $game = new Blackjack();
        $rng = $this->rngWithShoe(['5', '9', '6', '8', '10']); // player 11 -> +10 = 21, dealer 17
        $deal = $game->play(['amount' => 10], $rng);

        $result = $game->play(['action' => 'double'], new ScriptedRandom(), $deal->state);
        $this->assertSame('win', $result->outcome['result']);
        $this->assertSame(40, $result->payout); // round(20 * 2.0)
        $this->assertSame(10, $result->outcome['additionalBet']);
        $this->assertTrue($result->outcome['doubled']);
    }

    public function testDoubleAfterHitIsIllegal(): void
    {
        $this->expectException(IllegalActionException::class);
        $game = new Blackjack();
        $rng = $this->rngWithShoe(['5', '9', '4', '8', '3', '7']); // player 9, hit -> 12 (3 cards)
        $deal = $game->play(['amount' => 10], $rng);
        $afterHit = $game->play(['action' => 'hit'], new ScriptedRandom(), $deal->state);
        $game->play(['action' => 'double'], new ScriptedRandom(), $afterHit->state);
    }
}
