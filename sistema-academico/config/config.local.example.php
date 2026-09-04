<?php
/**
 * Copie este arquivo para config.local.php e ajuste os dados do seu servidor.
 * config.local.php é ignorado pelo Git.
 */
return [
    'db' => [
        'driver' => 'mysql',
        'mysql'  => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'tratagua_academico',
            'username' => 'usuario',
            'password' => 'senha',
        ],
    ],
    'app' => [
        'debug' => false,
    ],
];
