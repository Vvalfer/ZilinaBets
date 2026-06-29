<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\Game421;
use CasinoEngine\IllegalActionException;
use CasinoEngine\RoundResult;
use CasinoEngine\Tests\Support\ScriptedRandom;
use PHPUnit\Framework\TestCase;

final class Game421Test extends TestCase
{
    public function testScoreClassifications(): void
    {
        $this->assertSame('421', Game421::score([4, 2, 1])['combo']);
        $this->assertSame('421', Game421::score([1, 4, 2])['combo']); // order independent
        $this->assertSame('aces', Game421::score([1, 1, 1])['combo']);
        $this->assertSame('triple', Game421::score([5, 5, 5])['combo']);
        $this->assertSame('run', Game421::score([3, 4, 5])['combo']);
        $this->assertSame('run', Game421::score([6, 4, 5])['combo']);
        $this->assertSame('nenette', Game421::score([2, 1, 2])['combo']);
        $this->assertSame('nothing', Game421::score([1, 3, 6])['combo']);
    }

    public function testScoreMultipliers(): void
    {
        $this->assertSame(11, Game421::score([4, 2, 1])['multiplier']);
        $this->assertSame(7, Game421::score([1, 1, 1])['multiplier']);
        $this->assertSame(4, Game421::score([6, 6, 6])['multiplier']);
        $this->assertSame(3, Game421::score([1, 2, 3])['multiplier']);
        $this->assertSame(2, Game421::score([1, 2, 2])['multiplier']);
        $this->assertSame(0, Game421::score([2, 4, 6])['multiplier']);
    }

    public function testInitialRollIsInProgressWithTwoRerolls(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [3, 3, 6];
        $result = (new Game421())->play(['amount' => 10], $rng);

        $this->assertSame(RoundResult::IN_PROGRESS, $result->status);
        $this->assertSame([3, 3, 6], $result->outcome['dice']);
        $this->assertSame(2, $result->outcome['rerollsLeft']);
        $this->assertContains('reroll', $result->nextActions);
        $this->assertContains('stand', $result->nextActions);
    }

    public function testStandResolvesWithCurrentDice(): void
    {
        $game = new Game421();
        $state = ['bet' => 10, 'dice' => [4, 2, 1], 'rerollsLeft' => 2];
        $result = $game->play(['action' => 'stand'], new ScriptedRandom(), $state);

        $this->assertTrue($result->isResolved());
        $this->assertSame(110, $result->payout); // 10 * 11 (the 421)
        $this->assertSame('421', $result->outcome['combo']);
    }

    public function testRerollKeepsChosenDice(): void
    {
        $game = new Game421();
        $rng = new ScriptedRandom();
        $rng->ints = [2]; // the single rerolled die becomes a 2
        $state = ['bet' => 5, 'dice' => [4, 1, 6], 'rerollsLeft' => 2];

        // keep dice 0 (=4) and 1 (=1), reroll die 2 -> 2  => [4,1,2] = 421
        $result = $game->play(['action' => 'reroll', 'keep' => [true, true, false]], $rng, $state);

        $this->assertSame(RoundResult::IN_PROGRESS, $result->status);
        $this->assertSame([4, 1, 2], $result->outcome['dice']);
        $this->assertSame(1, $result->outcome['rerollsLeft']);
    }

    public function testSecondRerollAutoResolves(): void
    {
        $game = new Game421();
        $rng = new ScriptedRandom();
        $rng->defaultInt = 1; // any rerolled die -> 1
        $state = ['bet' => 8, 'dice' => [4, 2, 5], 'rerollsLeft' => 1];

        // last reroll: keep 4 and 2, reroll die index 2 -> 1 => [4,2,1]
        $result = $game->play(['action' => 'reroll', 'keep' => [true, true, false]], $rng, $state);

        $this->assertTrue($result->isResolved(), 'Reaching 0 rerolls must auto-resolve.');
        $this->assertSame('421', $result->outcome['combo']);
        $this->assertSame(88, $result->payout);
    }

    public function testNothingCombinationLosesStake(): void
    {
        $game = new Game421();
        $state = ['bet' => 20, 'dice' => [1, 3, 6], 'rerollsLeft' => 0];
        $result = $game->play(['action' => 'stand'], new ScriptedRandom(), $state);
        $this->assertSame(0, $result->payout);
        $this->assertFalse($result->outcome['win']);
    }

    public function testRerollWhenNoneLeftIsIllegal(): void
    {
        $this->expectException(IllegalActionException::class);
        $game = new Game421();
        $state = ['bet' => 10, 'dice' => [1, 2, 3], 'rerollsLeft' => 0];
        $game->play(['action' => 'reroll', 'keep' => [false, false, false]], new ScriptedRandom(), $state);
    }
}
