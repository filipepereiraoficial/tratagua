<?php
namespace App\Core;

/** Middlewares declarativos usados pelo roteador. */
class Middleware
{
    public static function handle(string $name, Request $request): void
    {
        [$name, $argument] = array_pad(explode(':', $name, 2), 2, null);

        switch ($name) {
            case 'auth':
                if (!Auth::check()) {
                    if ($request->wantsJson()) {
                        self::json(401, 'Sessão expirada. Faça login novamente.');
                    }
                    Session::set('_intended', $request->path);
                    if (Session::pull('_expired')) {
                        Flash::warning('Sua sessão expirou por inatividade. Entre novamente.');
                    }
                    self::redirect('/login');
                }
                // Enquanto a troca de senha obrigatória não for feita, só as rotas
                // de senha e logout ficam acessíveis.
                if (Auth::user()['must_change_password']
                    && !in_array($request->path, ['/senha', '/logout'], true)) {
                    Flash::warning('Por segurança, defina uma nova senha antes de continuar.');
                    self::redirect('/senha');
                }
                break;

            case 'role':
                $allowed = array_map('trim', explode('|', (string) $argument));
                // O aluno entra direto no painel dele em vez de ver um 403 seco.
                if (Auth::hasRole('aluno') && !in_array('aluno', $allowed, true)) {
                    if ($request->wantsJson()) {
                        self::json(403, 'Você não tem permissão para esta operação.');
                    }
                    self::redirect('/minha-evolucao');
                }
                if (!Auth::hasRole(...$allowed)) {
                    if ($request->wantsJson()) {
                        self::json(403, 'Você não tem permissão para esta operação.');
                    }
                    http_response_code(403);
                    View::render('errors/403', ['title' => 'Acesso negado'], 'layouts/blank');
                    exit;
                }
                break;
        }
    }

    public static function abortCsrf(Request $request): void
    {
        if ($request->wantsJson()) {
            self::json(419, 'Token de segurança inválido ou expirado.');
        }
        Flash::error('Token de segurança inválido ou expirado. Envie o formulário novamente.');
        self::redirect($request->path);
    }

    private static function json(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
