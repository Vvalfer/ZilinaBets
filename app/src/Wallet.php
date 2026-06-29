<?php

declare(strict_types=1);

namespace CasinoApp;

use PDO;

/**
 * Chip accounting. Every movement is applied to users.balance and recorded in
 * the append-only ledger, inside a write-locked transaction so concurrent
 * requests can never double-spend.
 */
final class Wallet
{
    /** Current balance, read inside an already-open transaction. */
    public static function balanceForUpdate(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Account not found.');
        }
        return (int) $row['balance'];
    }

    /**
     * Apply a signed delta to the balance and record it. Must run inside a
     * Db::transaction. Refuses to push the balance below zero.
     *
     * @return int the new balance.
     */
    public static function apply(PDO $pdo, int $userId, int $delta, string $reason, ?string $game = null): int
    {
        $current = self::balanceForUpdate($pdo, $userId);
        $next = $current + $delta;
        if ($next < 0) {
            throw new InsufficientFundsException('Not enough chips for this bet.');
        }

        $stmt = $pdo->prepare('UPDATE users SET balance = :b WHERE id = :id');
        $stmt->execute([':b' => $next, ':id' => $userId]);

        $stmt = $pdo->prepare(
            'INSERT INTO ledger (user_id, game, delta, balance_after, reason)
             VALUES (:uid, :g, :d, :b, :r)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':g'   => $game,
            ':d'   => $delta,
            ':b'   => $next,
            ':r'   => $reason,
        ]);

        return $next;
    }
}
