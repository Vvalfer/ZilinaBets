<?php

declare(strict_types=1);

namespace CasinoEngine;

abstract class AbstractGame implements Game
{
    /**
     * Validate and normalise a chip amount coming off the wire.
     *
     * JSON bodies often deliver numbers as strings, and a malicious client can
     * send anything, so we accept only a real integer or an all-digit string,
     * then enforce the table limits. Floats, negatives, "1e3", "10.5", and
     * arbitrary text are all rejected here, server-side.
     */
    protected function validateAmount(mixed $amount): int
    {
        $isInt = is_int($amount);
        $isDigitString = is_string($amount) && $amount !== '' && ctype_digit($amount);
        if (!$isInt && !$isDigitString) {
            throw new InvalidBetException('Bet amount must be a whole number of chips.');
        }

        $value = (int) $amount;
        if ($value < Paytables::MIN_BET) {
            throw new InvalidBetException('Bet is below the minimum of ' . Paytables::MIN_BET . ' chip(s).');
        }
        if ($value > Paytables::MAX_BET) {
            throw new InvalidBetException('Bet exceeds the maximum of ' . Paytables::MAX_BET . ' chips.');
        }
        return $value;
    }

    protected function requireKey(array $bet, string $key): mixed
    {
        if (!array_key_exists($key, $bet)) {
            throw new InvalidBetException("Missing required field '$key'.");
        }
        return $bet[$key];
    }
}
