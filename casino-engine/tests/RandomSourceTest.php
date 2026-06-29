<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\CryptoRandom;
use CasinoEngine\SeededRandom;
use PHPUnit\Framework\TestCase;

final class RandomSourceTest extends TestCase
{
    public function testSeededIsDeterministic(): void
    {
        $a = new SeededRandom(42);
        $b = new SeededRandom(42);
        for ($i = 0; $i < 50; $i++) {
            $this->assertSame($a->int(0, 1000), $b->int(0, 1000), 'Same seed must give same sequence.');
        }
    }

    public function testDifferentSeedsDiverge(): void
    {
        $a = new SeededRandom(1);
        $b = new SeededRandom(2);
        $different = false;
        for ($i = 0; $i < 20; $i++) {
            if ($a->int(0, 1_000_000) !== $b->int(0, 1_000_000)) {
                $different = true;
                break;
            }
        }
        $this->assertTrue($different, 'Different seeds should produce different sequences.');
    }

    public function testIntRespectsBounds(): void
    {
        $rng = new SeededRandom(7);
        for ($i = 0; $i < 500; $i++) {
            $v = $rng->int(3, 9);
            $this->assertGreaterThanOrEqual(3, $v);
            $this->assertLessThanOrEqual(9, $v);
        }
    }

    public function testCryptoIntRespectsBounds(): void
    {
        $rng = new CryptoRandom();
        for ($i = 0; $i < 500; $i++) {
            $v = $rng->int(1, 6);
            $this->assertGreaterThanOrEqual(1, $v);
            $this->assertLessThanOrEqual(6, $v);
        }
    }

    public function testShuffleIsAPermutation(): void
    {
        $rng = new SeededRandom(99);
        $input = range(1, 20);
        $shuffled = $rng->shuffle($input);
        $this->assertCount(20, $shuffled);
        sort($shuffled);
        $this->assertSame($input, $shuffled, 'Shuffle must keep exactly the same elements.');
    }

    public function testWeightedPickHonoursDistribution(): void
    {
        // Heavily skewed weights: index 0 should dominate.
        $rng = new SeededRandom(123);
        $counts = [0, 0, 0];
        for ($i = 0; $i < 10000; $i++) {
            $counts[$rng->pickWeighted([0.8, 0.15, 0.05])]++;
        }
        $this->assertGreaterThan($counts[1], $counts[0]);
        $this->assertGreaterThan($counts[2], $counts[1]);
    }
}
