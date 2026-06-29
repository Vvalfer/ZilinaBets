<?php

declare(strict_types=1);

namespace CasinoApp;

use PDO;

/**
 * Server-side persistence of in-progress rounds for the STATEFUL games
 * (blackjack, 421). The engine's `state` (which for blackjack includes the
 * hidden hole card and the rest of the shoe) is stored here, keyed by a
 * server-generated round id, and is NEVER sent to or accepted from the client.
 */
final class RoundStore
{
    /** Persist a new in-progress round and return its server-generated id. */
    public static function create(PDO $pdo, int $userId, string $game, int $stake, array $state): string
    {
        $id = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare(
            'INSERT INTO rounds (id, user_id, game, stake, state_json)
             VALUES (:id, :uid, :g, :s, :j)'
        );
        $stmt->execute([
            ':id'  => $id,
            ':uid' => $userId,
            ':g'   => $game,
            ':s'   => $stake,
            ':j'   => json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
        return $id;
    }

    /**
     * Load a round that belongs to this user.
     * @return array{game:string,stake:int,state:array}|null
     */
    public static function load(PDO $pdo, int $userId, string $roundId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT game, stake, state_json FROM rounds WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $roundId, ':uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $state = json_decode($row['state_json'], true);
        return [
            'game'  => $row['game'],
            'stake' => (int) $row['stake'],
            'state' => is_array($state) ? $state : [],
        ];
    }

    /** Replace the stored state (and optionally the recorded stake) for a round. */
    public static function update(PDO $pdo, string $roundId, array $state, ?int $stake = null): void
    {
        if ($stake === null) {
            $stmt = $pdo->prepare('UPDATE rounds SET state_json = :j WHERE id = :id');
            $stmt->execute([':j' => json_encode($state, JSON_UNESCAPED_UNICODE), ':id' => $roundId]);
        } else {
            $stmt = $pdo->prepare('UPDATE rounds SET state_json = :j, stake = :s WHERE id = :id');
            $stmt->execute([
                ':j' => json_encode($state, JSON_UNESCAPED_UNICODE),
                ':s' => $stake,
                ':id' => $roundId,
            ]);
        }
    }

    public static function delete(PDO $pdo, string $roundId): void
    {
        $stmt = $pdo->prepare('DELETE FROM rounds WHERE id = :id');
        $stmt->execute([':id' => $roundId]);
    }
}
