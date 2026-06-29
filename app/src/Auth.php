<?php

declare(strict_types=1);

namespace CasinoApp;

/**
 * Account creation, login/logout, and "who is logged in" — backed by PHP
 * sessions. The session is the ONLY thing the server trusts to identify a
 * player; balances and game outcomes are never taken from the client.
 */
final class Auth
{
    private const MIN_USERNAME = 3;
    private const MAX_USERNAME = 20;
    private const MIN_PASSWORD = 6;
    private const MAX_LOGIN_FAILS = 15; // per IP, within the window below (15 min)

    /**
     * Create an account, grant the starting balance, and log the player in.
     * @return array{id:int,username:string,balance:int}
     * @throws \RuntimeException on validation / duplicate-username errors.
     */
    public static function register(string $username, string $password, int $startingBalance): array
    {
        $username = trim($username);
        self::validateCredentials($username, $password);

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $user = Db::transaction(static function (\PDO $pdo) use ($username, $hash, $startingBalance): array {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, password_hash, balance) VALUES (:u, :h, :b)'
                );
                $stmt->execute([':u' => $username, ':h' => $hash, ':b' => $startingBalance]);
                $id = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO ledger (user_id, game, delta, balance_after, reason)
                     VALUES (:uid, NULL, :d, :b, :r)'
                );
                $stmt->execute([
                    ':uid' => $id,
                    ':d'   => $startingBalance,
                    ':b'   => $startingBalance,
                    ':r'   => 'signup_bonus',
                ]);

                return ['id' => $id, 'username' => $username, 'balance' => $startingBalance];
            });
        } catch (\PDOException $e) {
            // UNIQUE constraint -> username already taken.
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new \RuntimeException('That username is already taken.');
            }
            // Any other DB error: log the detail, return a safe generic message
            // (never leak SQLSTATE / internals to the client).
            error_log('[casino] register: ' . $e->getMessage());
            throw new \RuntimeException('Could not create your account. Please try again.');
        }

        $user['isAdmin'] = self::isAdmin($user);
        self::startSessionFor($user['id']);
        return $user;
    }

    /**
     * Verify credentials and log the player in.
     * @return array{id:int,username:string,balance:int}
     * @throws \RuntimeException on bad credentials.
     */
    public static function login(string $username, string $password): array
    {
        $username = trim($username);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        self::throttleLogin($ip);

        try {
            $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE username = :u');
            $stmt->execute([':u' => $username]);
            $row = $stmt->fetch();
        } catch (\PDOException $e) {
            error_log('[casino] login: ' . $e->getMessage());
            throw new \RuntimeException('Could not log you in. Please try again.');
        }

        if (!$row || !password_verify($password, $row['password_hash'])) {
            self::recordLoginFailure($ip);
            throw new \RuntimeException('Invalid username or password.');
        }

        // Success: clear this IP's failed-attempt history.
        Db::pdo()->prepare('DELETE FROM login_attempts WHERE ip = :ip')->execute([':ip' => $ip]);

        $user = [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'balance'  => (int) $row['balance'],
        ];
        $user['isAdmin'] = self::isAdmin($user);
        self::startSessionFor((int) $row['id']);
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? false));
        }
        session_destroy();
    }

    /** @return array{id:int,username:string,balance:int}|null */
    public static function currentUser(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT id, username, balance FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $user = [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'balance'  => (int) $row['balance'],
        ];
        $user['isAdmin'] = self::isAdmin($user);
        return $user;
    }

    /** Return the current user or emit a 401 and stop. */
    public static function requireUser(): array
    {
        $user = self::currentUser();
        if ($user === null) {
            Http::error('You must be logged in.', 401, 'unauthorized');
        }
        return $user;
    }

    /**
     * Is this user an admin? Either their username is in the configured
     * 'admins' list, or — when no admins are configured — they are the very
     * first account (id 1), so the panel works out of the box for the operator.
     */
    public static function isAdmin(array $user): bool
    {
        $admins = (array) Db::config('admins', []);
        if (in_array($user['username'], $admins, true)) {
            return true;
        }
        return count($admins) === 0 && (int) $user['id'] === 1;
    }

    /** Return the current user if they are an admin, else emit 401/403 and stop. */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if (!self::isAdmin($user)) {
            Http::error('Admin access required.', 403, 'forbidden');
        }
        return $user;
    }

    private static function startSessionFor(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    /** Block an IP that has failed too many logins recently. */
    private static function throttleLogin(string $ip): void
    {
        $pdo = Db::pdo();
        // Cheap housekeeping so the table can't grow forever.
        $pdo->exec("DELETE FROM login_attempts WHERE created_at < datetime('now', '-1 day')");
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE ip = :ip AND created_at > datetime('now', '-15 minutes')"
        );
        $stmt->execute([':ip' => $ip]);
        if ((int) ($stmt->fetch()['c'] ?? 0) >= self::MAX_LOGIN_FAILS) {
            throw new \RuntimeException('Too many login attempts. Please wait a few minutes and try again.');
        }
    }

    private static function recordLoginFailure(string $ip): void
    {
        Db::pdo()->prepare('INSERT INTO login_attempts (ip) VALUES (:ip)')->execute([':ip' => $ip]);
    }

    private static function validateCredentials(string $username, string $password): void
    {
        $len = strlen($username);
        if ($len < self::MIN_USERNAME || $len > self::MAX_USERNAME) {
            throw new \RuntimeException('Username must be 3–20 characters.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            throw new \RuntimeException('Username may only contain letters, numbers and underscores.');
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new \RuntimeException('Password must be at least 6 characters.');
        }
    }
}
