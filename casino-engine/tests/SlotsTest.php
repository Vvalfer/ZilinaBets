<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\Slots;
use CasinoEngine\Paytables;
use CasinoEngine\Tests\Support\ScriptedRandom;
use PHPUnit\Framework\TestCase;

final class SlotsTest extends TestCase
{
    public function testEvaluateThreeOfAKind(): void
    {
        $this->assertSame(
            ['win' => true, 'symbol' => 'seven', 'multiplier' => 400],
            Slots::evaluate(['seven', 'seven', 'seven'])
        );
        $this->assertSame(
            ['win' => true, 'symbol' => 'cherry', 'multiplier' => 8],
            Slots::evaluate(['cherry', 'cherry', 'cherry'])
        );
    }

    public function testEvaluateNonMatchingLoses(): void
    {
        $eval = Slots::evaluate(['cherry', 'cherry', 'lemon']);
        $this->assertFalse($eval['win']);
        $this->assertSame(0, $eval['multiplier']);
    }

    public function testForcedJackpotPaysOut(): void
    {
        // The first strip slot is 'cherry'; forcing int()=0 three times = 3 cherries.
        $rng = new ScriptedRandom();
        $rng->defaultInt = 0;
        $result = (new Slots())->play(['amount' => 5], $rng);

        $this->assertTrue($result->outcome['win']);
        $this->assertSame('cherry', $result->outcome['symbol']);
        $this->assertSame(40, $result->payout); // 5 * 8
    }

    public function testForcedSevenJackpot(): void
    {
        // 'seven' is the last slot of the strip.
        $last = count(Paytables::SLOTS_STRIP) - 1;
        $rng = new ScriptedRandom();
        $rng->defaultInt = $last;
        $result = (new Slots())->play(['amount' => 1], $rng);

        $this->assertSame('seven', $result->outcome['symbol']);
        $this->assertSame(400, $result->payout);
    }
}
