<?php

declare(strict_types=1);

namespace CasinoEngine\Tests;

use CasinoEngine\games\Roulette;
use CasinoEngine\games\Slots;
use CasinoEngine\Paytables;
use CasinoEngine\SeededRandom;
use PHPUnit\Framework\TestCase;

/**
 * Statistical fairness tests. These use SeededRandom so they are deterministic
 * (never flaky) yet still exercise the full play() path over many rounds. The
 * point: the money actually paid out over a long run matches the math derived
 * from the paytable. This is the headline fairness argument for the defense.
 */
final class RtpTest extends TestCase
{
    /** Theoretical slots RTP computed directly from the strip + payout table. */
    private function theoreticalSlotsRtp(): float
    {
        $strip = Paytables::SLOTS_STRIP;
        $len = count($strip);
        $counts = array_count_values($strip);

        $rtp = 0.0;
        foreach (Paytables::SLOTS_PAYOUTS as $symbol => $mult) {
            $p = ($counts[$symbol] ?? 0) / $len;
            $rtp += ($p ** 3) * $mult; // P(three identical) * payout
        }
        return $rtp;
    }

    public function testSlotsTheoreticalRtpIsAround92Percent(): void
    {
        $rtp = $this->theoreticalSlotsRtp();
        $this->assertGreaterThan(0.90, $rtp);
        $this->assertLessThan(0.95, $rtp);
    }

    public function testSlotsSimulatedRtpMatchesTheory(): void
    {
        $theoretical = $this->theoreticalSlotsRtp();

        $game = new Slots();
        $rng = new SeededRandom(1);
        $spins = 1_000_000;
        $bet = 1;
        $wagered = 0;
        $returned = 0;

        for ($i = 0; $i < $spins; $i++) {
            $wagered += $bet;
            $returned += $game->play(['amount' => $bet], $rng)->payout;
        }
        $simulated = $returned / $wagered;

        // Deterministic seed => this value is fixed; tolerance covers the
        // residual Monte-Carlo noise of the high-variance symbols.
        $this->assertGreaterThan($theoretical - 0.025, $simulated);
        $this->assertLessThan($theoretical + 0.025, $simulated);
    }

    public function testRouletteEvenMoneyHouseEdge(): void
    {
        // European single-zero even-money bet: expected RTP = 36/37 ~= 0.973.
        $game = new Roulette();
        $rng = new SeededRandom(13);
        $rounds = 200_000;
        $bet = 1;
        $wagered = 0;
        $returned = 0;

        for ($i = 0; $i < $rounds; $i++) {
            $wagered += $bet;
            $returned += $game->play(['amount' => $bet, 'type' => 'color', 'value' => 'red'], $rng)->payout;
        }
        $simulated = $returned / $wagered;

        $this->assertGreaterThan(0.94, $simulated);
        $this->assertLessThan(1.00, $simulated);
    }
}
