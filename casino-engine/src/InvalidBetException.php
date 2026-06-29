<?php

declare(strict_types=1);

namespace CasinoEngine;

/** Thrown when a bet fails server-side validation (amount, type, target...). */
class InvalidBetException extends \InvalidArgumentException
{
}
