<?php
/**
 * Configuração padrão do Painel Pedagógico.
 *
 * Para produção, copie config.local.example.php para config.local.php e ajuste
 * apenas o que mudar — o arquivo local sobrescreve estes valores e fica fora do
 * versionamento.
 */

$config = [
    // 'sqlite' funciona sem nenhuma configuração; 'mysql' para produção.
    'db' => [
        'driver'   => 'sqlite',
        'sqlite'   => ['path' => dirname(__DIR__) . '/storage/painel.sqlite'],
        'mysql'    => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'tratagua_academico',
            'username' => '',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
    ],

    'app' => [
        'name'     => 'Painel Pedagógico',
        'timezone' => 'America/Sao_Paulo',
        'locale'   => 'pt_BR',
        // false em produção: erros viram página 500 genérica + log.
        'debug'    => false,
        // Minutos de inatividade até a sessão expirar.
        'session_lifetime' => 240,
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        foreach ($override as $section => $values) {
            $config[$section] = is_array($values) && isset($config[$section]) && is_array($config[$section])
                ? array_replace_recursive($config[$section], $values)
                : $values;
        }
    }
}

return $config;
