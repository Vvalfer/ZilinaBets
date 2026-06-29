<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * The contract every game implements. This is the "seam" with the rest of the
 * team: the backend calls these methods from its routes and persists the
 * result; the frontend renders/animates RoundResult::$outcome.
 *
 * Engine classes are PURE: they never read $_POST, sessions, or the database.
 * Everything they need is passed in (bet, RandomSource, optional state).
 */
interface Game
{
    /** Stable identifier, e.g. 'roulette'. */
    public function key(): string;

    /**
     * Validate an INITIAL bet (amount + targets). Re-run server-side on every
     * request regardless of what the client claims.
     *
     * @throws InvalidBetException when the bet is not acceptable.
     */
    public function validateBet(array $bet): void;

    /**
     * Resolve a bet, or advance a stateful game by one action.
     *
     *   - First call: $state is null. $bet holds the initial bet.
     *   - Follow-up (stateful games): $state holds the persisted state and
     *     $bet holds the action payload (e.g. ['action' => 'hit']).
     *
     * @throws InvalidBetException|IllegalActionException
     */
    public function play(array $bet, RandomSource $rng, ?array $state = null): RoundResult;
}
