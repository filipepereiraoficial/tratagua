<?php
/**
 * Instalador por linha de comando.
 *
 *   php database/migrate.php                      cria o schema
 *   php database/migrate.php --seed               schema + carga inicial
 *   php database/migrate.php --seed --demo        + dados de demonstração
 *   php database/migrate.php --fresh --seed       apaga tudo e recria
 *
 * Credenciais do administrador podem vir por argumento:
 *   --admin-email=... --admin-password=... --admin-name="..."
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Este script deve ser executado pela linha de comando.\n");
}

define('APP_ROOT', dirname(__DIR__));

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

use App\Core\Database;
use App\Models\Setting;

$config = require APP_ROOT . '/config/config.php';
date_default_timezone_set($config['app']['timezone']);

$opcoes = [];
foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argumento, $m)) {
        $opcoes[$m[1]] = $m[2] ?? true;
    }
}

$pdo    = Database::connect($config['db']);
$driver = Database::driver();
echo "Banco: {$driver}\n";

$tabelas = [
    'activity_log', 'login_attempts', 'alert_dismissals', 'settings', 'grades', 'student_answers',
    'question_options', 'questions', 'assessments', 'attendances', 'lesson_topics', 'lessons',
    'topics', 'class_subjects', 'subjects', 'enrollments', 'classes', 'courses', 'users', 'students',
];

if (!empty($opcoes['fresh'])) {
    echo "Removendo tabelas existentes…\n";
    if ($driver === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    } else {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }
    foreach ($tabelas as $tabela) {
        $pdo->exec("DROP TABLE IF EXISTS {$tabela}");
    }
    if ($driver === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } else {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

$arquivo = APP_ROOT . '/database/schema.' . ($driver === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
$sql = file_get_contents($arquivo);
if ($sql === false) {
    exit("Não foi possível ler {$arquivo}\n");
}

$linhas = [];
foreach (preg_split('/\R/', $sql) ?: [] as $linha) {
    $limpa = trim($linha);
    if ($limpa === '' || str_starts_with($limpa, '--')) {
        continue;
    }
    $linhas[] = $linha;
}
$comandos = array_filter(array_map('trim', explode(';', implode("\n", $linhas))), static fn ($c) => $c !== '');

foreach ($comandos as $comando) {
    $pdo->exec($comando);
}
echo 'Schema aplicado (' . count($comandos) . " comandos).\n";

Setting::resetToDefaults();
echo "Parâmetros padrão gravados.\n";

if (!empty($opcoes['seed'])) {
    require_once APP_ROOT . '/database/seed.php';
    $resultado = painel_seed([
        'name'     => (string) ($opcoes['admin-name'] ?? 'Filipe Pereira'),
        'email'    => (string) ($opcoes['admin-email'] ?? 'manowfilipe@gmail.com'),
        'password' => (string) ($opcoes['admin-password'] ?? 'Fp$$1999'),
    ], !empty($opcoes['demo']));
    echo $resultado['message'] . "\n";
}

echo "Concluído.\n";
