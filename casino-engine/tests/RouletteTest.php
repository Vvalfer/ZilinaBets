<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\Roulette;
use CasinoEngine\InvalidBetException;
use CasinoEngine\Tests\Support\ScriptedRandom;
use PHPUnit\Framework\TestCase;

final class RouletteTest extends TestCase
{
    public function testColorMapping(): void
    {
        $this->assertSame('green', Roulette::colorOf(0));
        $this->assertSame('red', Roulette::colorOf(1));
        $this->assertSame('black', Roulette::colorOf(2));
        $this->assertSame('red', Roulette::colorOf(36));
    }

    public function testStraightWinPays36x(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [17];
        $result = (new Roulette())->play(['amount' => 10, 'type' => 'straight', 'value' => 17], $rng);

        $this->assertTrue($result->isResolved());
        $this->assertSame(360, $result->payout); // 10 * 36
        $this->assertSame(17, $result->outcome['number']);
        $this->assertTrue($result->outcome['win']);
    }

    public function testStraightLossPaysNothing(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [18];
        $result = (new Roulette())->play(['amount' => 10, 'type' => 'straight', 'value' => 17], $rng);
        $this->assertSame(0, $result->payout);
        $this->assertFalse($result->outcome['win']);
    }

    public function testColorWinPays2x(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [1]; // 1 is red
        $result = (new Roulette())->play(['amount' => 25, 'type' => 'color', 'value' => 'red'], $rng);
        $this->assertSame(50, $result->payout);
    }

    public function testDozenWinPays3x(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [5]; // 1st dozen
        $result = (new Roulette())->play(['amount' => 10, 'type' => 'dozen', 'value' => 1], $rng);
        $this->assertSame(30, $result->payout);
    }

    public function testZeroLosesEvenMoneyBets(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [0];
        $result = (new Roulette())->play(['amount' => 50, 'type' => 'color', 'value' => 'red'], $rng);
        $this->assertSame(0, $result->payout);
        $this->assertSame('green', $result->outcome['color']);
    }

    public function testZeroWinsStraightOnZero(): void
    {
        $rng = new ScriptedRandom();
        $rng->ints = [0];
        $result = (new Roulette())->play(['amount' => 2, 'type' => 'straight', 'value' => 0], $rng);
        $this->assertSame(72, $result->payout); // 2 * 36
    }

    public function testInvalidTypeRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        (new Roulette())->validateBet(['amount' => 10, 'type' => 'corner', 'value' => 1]);
    }

    public function testInvalidStraightTargetRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        (new Roulette())->validateBet(['amount' => 10, 'type' => 'straight', 'value' => 37]);
    }
}
