<?php

declare(strict_types=1);

namespace CasinoEngine;

/**
 * The single, uniform result type returned by every game.
 *
 * Accounting convention (agreed with the backend):
 *   - The stake is debited by the backend WHEN the bet is placed.
 *   - $payout is the GROSS number of chips to credit back to the player.
 *       * losing round            => payout = 0
 *       * even-money win of bet B  => payout = 2 * B   (stake + winnings)
 *       * push / tie of bet B      => payout = 1 * B   (stake returned)
 *   - Player net for the round = payout - stake.
 *
 * Payouts are always whole chips (rounded), never fractional.
 */
final class RoundResult
{
    public const RESOLVED = 'resolved';
    public const IN_PROGRESS = 'in_progress';

    /**
     * @param string     $status      RESOLVED or IN_PROGRESS.
     * @param int        $payout      Gross chips to credit back (0 if nothing).
     * @param array      $outcome     Public data for the frontend to animate/show.
     * @param array|null $state       Server-side state to persist (stateful games only).
     * @param string[]   $nextActions Allowed follow-up actions (e.g. ['hit','stand']).
     */
    public function __construct(
        public readonly string $status,
        public readonly int $payout,
        public readonly array $outcome,
        public readonly ?array $state = null,
        public readonly array $nextActions = [],
    ) {
    }

    public function isResolved(): bool
    {
        return $this->status === self::RESOLVED;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'payout' => $this->payout,
            'outcome' => $this->outcome,
            'state' => $this->state,
            'nextActions' => $this->nextActions,
        ];
    }
}
