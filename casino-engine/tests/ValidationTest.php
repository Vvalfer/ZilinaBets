<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\GameFactory;
use CasinoEngine\InvalidBetException;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testFactoryBuildsEveryGame(): void
    {
        foreach (GameFactory::KEYS as $key) {
            $game = GameFactory::create($key);
            $this->assertSame($key, $game->key());
        }
    }

    public function testFactoryRejectsUnknownGame(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('craps');
    }

    public function testStatefulFlagging(): void
    {
        $this->assertTrue(GameFactory::isStateful('blackjack'));
        $this->assertTrue(GameFactory::isStateful('421'));
        $this->assertFalse(GameFactory::isStateful('slots'));
        $this->assertFalse(GameFactory::isStateful('roulette'));
    }

    public function testNegativeAmountRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('slots')->validateBet(['amount' => -5]);
    }

    public function testZeroAmountRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('slots')->validateBet(['amount' => 0]);
    }

    public function testFloatAmountRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('slots')->validateBet(['amount' => 10.5]);
    }

    public function testNonNumericStringRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('slots')->validateBet(['amount' => 'free-money']);
    }

    public function testDigitStringAccepted(): void
    {
        // JSON often delivers numbers as strings; an all-digit string is fine.
        GameFactory::create('slots')->validateBet(['amount' => '50']);
        $this->assertTrue(true);
    }

    public function testAboveMaxRejected(): void
    {
        $this->expectException(InvalidBetException::class);
        GameFactory::create('roulette')->validateBet(['amount' => 100000, 'type' => 'color', 'value' => 'red']);
    }
}
