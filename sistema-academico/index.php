<?php
/**
 * Painel Pedagógico — front controller.
 * Todo acesso passa por aqui: bootstrap, sessão, rotas e tratamento de erros.
 */

declare(strict_types=1);

define('APP_ROOT', __DIR__);

// ---------------------------------------------------------------- autoload
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once APP_ROOT . '/src/helpers.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$config = require APP_ROOT . '/config/config.php';

date_default_timezone_set($config['app']['timezone']);
mb_internal_encoding('UTF-8');

$debug = (bool) $config['app']['debug'];
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

// Cabeçalhos de segurança básicos.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$request = new Request();
$GLOBALS['__base_path'] = $request->basePath;

View::setPath(APP_ROOT . '/views');
Session::start((int) $config['app']['session_lifetime']);

$GLOBALS['__config'] = $config;

/** Registra a exceção e mostra uma página de erro sem vazar detalhes. */
$fail = static function (Throwable $e) use ($debug, $request): void {
    $logDir = APP_ROOT . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents(
        $logDir . '/app-' . date('Y-m-d') . '.log',
        sprintf("[%s] %s: %s em %s:%d\n%s\n\n", date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()),
        FILE_APPEND
    );

    http_response_code(500);
    if ($request->wantsJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $debug ? $e->getMessage() : 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    try {
        View::render('errors/500', [
            'title'   => 'Erro interno',
            'message' => $debug ? $e->getMessage() : null,
            'trace'   => $debug ? $e->getTraceAsString() : null,
        ], 'layouts/blank');
    } catch (Throwable) {
        echo '<h1>Erro interno do servidor</h1>';
    }
};

try {
    Database::connect($config['db']);

    // Banco ainda não instalado: direciona para o instalador.
    if (!Database::tableExists('users')) {
        if ($request->path !== '/instalar') {
            header('Location: ' . url('/instalar'));
            exit;
        }
    }

    $router = new Router();
    require APP_ROOT . '/src/routes.php';
    $router->dispatch($request);
} catch (Throwable $e) {
    $fail($e);
}
