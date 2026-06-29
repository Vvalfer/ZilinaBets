<?php

declare(strict_types=1);

namespace CasinoApp;

use PDO;

/**
 * Admin views and actions. Every endpoint requires an admin session.
 * Read figures are derived from the append-only ledger, so they are auditable;
 * writes (adjust / delete) also go through the ledger or cascade cleanly.
 */
final class AdminController
{
    /** GET /api/admin/players — one row per account. */
    public static function players(): void
    {
        Auth::requireAdmin();

        $rows = Db::pdo()->query(
            "SELECT u.id, u.username, u.balance, u.created_at,
                    (SELECT COUNT(*) FROM ledger l
                       WHERE l.user_id = u.id AND l.reason = 'bet') AS rounds,
                    (SELECT COALESCE(SUM(-l.delta), 0) FROM ledger l
                       WHERE l.user_id = u.id AND l.reason IN ('bet', 'bet_double')) AS wagered
             FROM users u
             ORDER BY u.id"
        )->fetchAll();

        $players = array_map(static fn (array $r): array => [
            'id'        => (int) $r['id'],
            'username'  => $r['username'],
            'balance'   => (int) $r['balance'],
            'createdAt' => $r['created_at'],
            'rounds'    => (int) $r['rounds'],
            'wagered'   => (int) $r['wagered'],
        ], $rows);

        Http::json(['players' => $players, 'count' => count($players)]);
    }

    /** GET /api/admin/stats — activity aggregated per game. */
    public static function stats(): void
    {
        Auth::requireAdmin();

        $rows = Db::pdo()->query(
            "SELECT game,
                    SUM(CASE WHEN reason IN ('bet','bet_double') THEN -delta ELSE 0 END) AS wagered,
                    SUM(CASE WHEN reason = 'payout' THEN delta ELSE 0 END) AS paid,
                    SUM(CASE WHEN reason = 'bet' THEN 1 ELSE 0 END) AS rounds
             FROM ledger
             WHERE game IS NOT NULL
             GROUP BY game
             ORDER BY game"
        )->fetchAll();

        $stats = array_map(static function (array $r): array {
            $wagered = (int) $r['wagered'];
            $paid = (int) $r['paid'];
            return [
                'game'    => $r['game'],
                'rounds'  => (int) $r['rounds'],
                'wagered' => $wagered,
                'paid'    => $paid,
                'house'   => $wagered - $paid,
                'rtp'     => $wagered > 0 ? round($paid / $wagered, 4) : null,
            ];
        }, $rows);

        Http::json(['stats' => $stats]);
    }

    /** GET /api/admin/history?userId=N — balance over time for one player. */
    public static function history(): void
    {
        Auth::requireAdmin();
        $userId = (int) ($_GET['userId'] ?? 0);
        if ($userId <= 0) {
            Http::error('Missing userId.', 400, 'bad_request');
        }
        $stmt = Db::pdo()->prepare(
            'SELECT delta, balance_after, reason, game, created_at
             FROM ledger WHERE user_id = :id ORDER BY id'
        );
        $stmt->execute([':id' => $userId]);
        $points = array_map(static fn (array $r): array => [
            'balance' => (int) $r['balance_after'],
            'delta'   => (int) $r['delta'],
            'reason'  => $r['reason'],
            'game'    => $r['game'],
            'at'      => $r['created_at'],
        ], $stmt->fetchAll());

        Http::json(['history' => $points]);
    }

    /** POST /api/admin/adjust { userId, delta } — add or remove chips. */
    public static function adjust(): void
    {
        Auth::requireAdmin();
        $body = Http::jsonBody();
        $userId = (int) ($body['userId'] ?? 0);
        $delta = (int) ($body['delta'] ?? 0);
        if ($userId <= 0 || $delta === 0) {
            Http::error('Provide a userId and a non-zero delta.', 400, 'bad_request');
        }

        $balance = Db::transaction(static function (PDO $pdo) use ($userId, $delta): int {
            $current = Wallet::balanceForUpdate($pdo, $userId); // throws if missing
            // Clamp a removal so the balance never goes negative.
            $applied = ($current + $delta < 0) ? -$current : $delta;
            return Wallet::apply($pdo, $userId, $applied, 'admin_adjust');
        });

        Http::json(['balance' => $balance]);
    }

    /** POST /api/admin/delete { userId } — remove an account (cascade). */
    public static function deleteAccount(): void
    {
        $admin = Auth::requireAdmin();
        $body = Http::jsonBody();
        $userId = (int) ($body['userId'] ?? 0);
        if ($userId <= 0) {
            Http::error('Missing userId.', 400, 'bad_request');
        }
        if ($userId === (int) $admin['id']) {
            Http::error('You cannot delete your own admin account.', 400, 'self_delete');
        }
        // ledger + rounds are removed by ON DELETE CASCADE (foreign_keys is ON).
        $stmt = Db::pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        Http::json(['ok' => true, 'deleted' => $stmt->rowCount()]);
    }
}
