<?php
namespace App\Core;

use App\Models\User;

/** Autenticação por sessão com proteção contra força bruta. */
class Auth
{
    private static ?array $user = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }
        $user = User::find((int) $id);
        if (!$user || !$user['is_active']) {
            Session::destroy();
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'], $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public static function attempt(string $email, string $password, string $ip, int $maxAttempts, int $lockoutMinutes): array
    {
        $email = mb_strtolower(trim($email));

        if (self::isLockedOut($email, $ip, $maxAttempts, $lockoutMinutes)) {
            return ['ok' => false, 'message' => "Muitas tentativas. Aguarde {$lockoutMinutes} minutos e tente novamente."];
        }

        $user = User::findByEmail($email);
        $valid = $user !== null
            && (int) $user['is_active'] === 1
            && password_verify($password, $user['password_hash']);

        self::logAttempt($email, $ip, $valid);

        if (!$valid) {
            return ['ok' => false, 'message' => 'E-mail ou senha inválidos.'];
        }

        // Reidrata o hash se o custo padrão do PHP mudou.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], $password, (bool) $user['must_change_password']);
        }

        session_regenerate_id(true);
        Session::set('user_id', (int) $user['id']);
        User::touchLogin((int) $user['id']);
        self::$user = null;

        return ['ok' => true, 'message' => 'Bem-vindo(a), ' . $user['name'] . '!'];
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }

    private static function logAttempt(string $email, string $ip, bool $success): void
    {
        Database::insert('login_attempts', [
            'email'   => $email,
            'ip'      => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }

    private static function isLockedOut(string $email, string $ip, int $maxAttempts, int $lockoutMinutes): bool
    {
        $since = date('Y-m-d H:i:s', time() - $lockoutMinutes * 60);
        $failures = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE email = ? AND ip = ? AND success = 0 AND attempted_at >= ?',
            [$email, $ip, $since],
            0
        );
        return $failures >= $maxAttempts;
    }
}
