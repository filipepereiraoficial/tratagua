<?php
namespace App\Core;

/** Token anti-CSRF exigido em toda requisição que altera estado. */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $token): bool
    {
        $expected = $_SESSION['_csrf_token'] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
