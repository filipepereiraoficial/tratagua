<?php
namespace App\Models;

use App\Core\Database;

/**
 * Parâmetros configuráveis das regras pedagógicas.
 * Os padrões abaixo são os do documento de regras de cálculo.
 */
class Setting
{
    public const DEFAULTS = [
        // Faixas de classificação por aproveitamento
        'faixa_dominio'            => '80',
        'faixa_intermediario'      => '60',
        // Pesos do Índice de Desenvolvimento
        'peso_desempenho'          => '0.40',
        'peso_evolucao'            => '0.25',
        'peso_frequencia'          => '0.15',
        'peso_consistencia'        => '0.20',
        // Cortes de classificação do aluno
        'id_evolucao'              => '75',
        'id_atencao'               => '55',
        // Amostras mínimas
        'min_questoes_assunto'     => '3',
        'min_avaliacoes_evolucao'  => '3',
        'min_avaliacoes_indice'    => '2',
        'janela_recente'           => '3',
        // Fatores de normalização
        'fator_evolucao'           => '5',
        'fator_consistencia'       => '2',
        // Alertas
        'frequencia_minima'        => '75',
        'media_alerta'             => '60',
        'queda_alerta'             => '10',
        'evolucao_alerta'          => '10',
        'limite_dificuldade'       => '60',
        'ocorrencias_persistente'  => '3',
        'justificada_conta'        => '0',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $values = self::DEFAULTS;
        try {
            foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
                $values[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            // Banco ainda não instalado: usa os padrões.
        }
        return self::$cache = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (string) self::get($key) === '1';
    }

    public static function put(string $key, string $value): void
    {
        $exists = (int) Database::value('SELECT COUNT(*) FROM settings WHERE setting_key = ?', [$key], 0) > 0;
        if ($exists) {
            Database::update('settings', ['setting_value' => $value], 'setting_key = :k', ['k' => $key]);
        } else {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        self::$cache = null;
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                self::put($key, (string) $value);
            }
        }
    }

    public static function resetToDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            self::put($key, $value);
        }
    }

    /** Pesos do Índice de Desenvolvimento normalizados para somar 1. */
    public static function weights(): array
    {
        $weights = [
            'desempenho'   => self::float('peso_desempenho'),
            'evolucao'     => self::float('peso_evolucao'),
            'frequencia'   => self::float('peso_frequencia'),
            'consistencia' => self::float('peso_consistencia'),
        ];
        $sum = array_sum($weights);
        if ($sum <= 0) {
            return ['desempenho' => 0.40, 'evolucao' => 0.25, 'frequencia' => 0.15, 'consistencia' => 0.20];
        }
        return array_map(static fn ($w) => $w / $sum, $weights);
    }
}
