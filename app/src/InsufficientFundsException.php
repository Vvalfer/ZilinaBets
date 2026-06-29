<?php

declare(strict_types=1);

namespace CasinoApp;

/** Thrown when a bet (or extra stake) exceeds the player's balance. */
final class InsufficientFundsException extends \RuntimeException
{
}
