<?php

declare(strict_types=1);

namespace CasinoEngine;

/** Thrown when a stateful game receives an action that is not allowed now. */
class IllegalActionException extends \LogicException
{
}
