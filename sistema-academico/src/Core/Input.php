<?php
namespace App\Core;

/**
 * Leitura tolerante de campos vindos de formulários: a chave pode simplesmente
 * não existir no payload, e "vazio" deve virar NULL no banco — nunca 0 ou "".
 */
class Input
{
    /** Texto opcional: string vazia (ou ausente) vira null. */
    public static function text(array $data, string $key, ?string $default = null): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        return $value === '' ? $default : $value;
    }

    /** Inteiro opcional: vazio ou ausente vira null. */
    public static function int(array $data, string $key, ?int $default = null): ?int
    {
        $value = trim((string) ($data[$key] ?? ''));
        return $value === '' ? $default : (int) $value;
    }

    /** Decimal com fallback — aceita vírgula como separador. */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = trim((string) ($data[$key] ?? ''));
        return $value === '' ? $default : (float) str_replace(',', '.', $value);
    }

    /** Id de relacionamento: 0, vazio e ausente viram null. */
    public static function id(array $data, string $key): ?int
    {
        $value = (int) ($data[$key] ?? 0);
        return $value > 0 ? $value : null;
    }
}
