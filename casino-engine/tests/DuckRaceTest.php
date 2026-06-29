<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\DuckRace;
use CasinoEngine\InvalidBetException;
use CasinoEngine\Paytables;
use CasinoEngine\Tests\Support\ScriptedRandom;
use PHPUnit\Framework\TestCase;

final class DuckRaceTest extends TestCase
{
    public function testWinningDuckPaysOdds(): void
    {
        $rng = new ScriptedRandom();
        $rng->weighted = [2]; // duck 2 wins
        $result = (new DuckRace())->play(['amount' => 10, 'duck' => 2], $rng);

        $this->assertTrue($result->outcome['win']);
        $this->assertSame(2, $result->outcome['winner']);
        // 10 * 4.50 = 45
        $this->assertSame(45, $result->payout);
    }

    public function testLosingDuckPaysNothing(): void
    {
        $rng = new ScriptedRandom();
        $rng->weighted = [0]; // duck 0 wins
        $result = (new DuckRace())->play(['amount' => 10, 'duck' => 3], $rng);

        $this->assertFalse($result->outcome['win']);
        $this->assertSame(0, $result->payout);
    }

    public function testWinnerFinishesFirstInOrder(): void
    {
        $rng = new ScriptedRandom();
        $rng->weighted = [4];
        $rng->defaultInt = 50; // stable speeds; engine still forces winner fastest
        $result = (new DuckRace())->play(['amount' => 5, 'duck' => 4], $rng);

        $order = $result->outcome['order'];
        $this->assertSame(4, $order[0], 'The winner must be first in the finishing order.');
        $this->assertCount(count(Paytables::DUCK_PROBS), $order);
    }

    public function testTimelineEndsWithWinnerAtFinish(): void
    {
        $rng = new ScriptedRandom();
        $rng->weighted = [1];
        $rng->defaultInt = 60;
        $result = (new DuckRace())->play(['amount' => 5, 'duck' => 1], $rng);

        $timeline = $result->outcome['timeline'];
        $lastRow = $timeline[count($timeline) - 1];
        $this->assertSame(100.0, $lastRow[1], 'Winner should reach 100 on the final tick.');
        foreach ($lastRow as $duck => $pos) {
            if ($duck !== 1) {
                $this->assertLessThanOrEqual(100.0, $pos);
            }
        }
    }

    public function testInvalidDuckRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        (new DuckRace())->validateBet(['amount' => 10, 'duck' => 9]);
    }
}
