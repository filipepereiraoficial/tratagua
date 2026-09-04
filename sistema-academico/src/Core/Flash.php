<?php
namespace App\Core;

/** Mensagens de uma requisição para a próxima (padrão POST-Redirect-GET). */
class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $m): void { self::add('success', $m); }
    public static function error(string $m): void   { self::add('error', $m); }
    public static function warning(string $m): void { self::add('warning', $m); }
    public static function info(string $m): void    { self::add('info', $m); }

    /** @return array<int, array{type:string,message:string}> */
    public static function pull(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /** Guarda os dados de um formulário rejeitado para repovoar os campos. */
    public static function keepInput(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation']);
        $_SESSION['_old_input'] = $input;
    }

    public static function oldInput(): array
    {
        $old = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);
        return $old;
    }

    public static function keepErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function errors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }
}
