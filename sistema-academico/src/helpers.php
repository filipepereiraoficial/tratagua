<?php
/**
 * Funções auxiliares globais de apresentação.
 * Regra do projeto: TODA saída de dado vindo do banco passa por e().
 */

use App\Core\Csrf;

/** Escapa para HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Monta uma URL absoluta a partir da base em que o sistema está publicado. */
function url(string $path = '/', array $query = []): string
{
    $base = $GLOBALS['__base_path'] ?? '';
    $path = '/' . ltrim($path, '/');
    $url  = $base . ($path === '/' ? '/' : rtrim($path, '/'));
    if ($query !== []) {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
    }
    return $url === '' ? '/' : $url;
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return Csrf::field();
}

/** Formata data ISO para dd/mm/aaaa. */
function data_br(?string $date, string $fallback = '—'): string
{
    if (!$date) {
        return $fallback;
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : $fallback;
}

function datahora_br(?string $datetime, string $fallback = '—'): string
{
    if (!$datetime) {
        return $fallback;
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : $fallback;
}

/** Número com vírgula decimal. */
function num(mixed $value, int $decimals = 1): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, $decimals, ',', '.');
}

/** Percentual formatado; devolve travessão quando não há dado. */
function pct(mixed $value, int $decimals = 1): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, $decimals, ',', '.') . '%';
}

/** Classe CSS da faixa de desempenho (verde / âmbar / vermelho). */
function faixa_classe(?float $percentual, float $dominio = 80.0, float $intermediario = 60.0): string
{
    if ($percentual === null) {
        return 'neutro';
    }
    if ($percentual >= $dominio) {
        return 'bom';
    }
    if ($percentual >= $intermediario) {
        return 'medio';
    }
    return 'ruim';
}

/** Rótulos legíveis para os enums do banco. */
function rotulo(string $grupo, ?string $valor): string
{
    $mapa = [
        'status_aluno'   => ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'concluido' => 'Concluído'],
        'status_turma'   => ['planejada' => 'Planejada', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'],
        'tipo_avaliacao' => ['prova' => 'Prova', 'simulado' => 'Simulado', 'atividade' => 'Atividade', 'exercicio' => 'Exercício', 'diagnostica' => 'Avaliação diagnóstica', 'revisao' => 'Avaliação de revisão'],
        'status_avaliacao' => ['planejada' => 'Planejada', 'aplicada' => 'Aplicada', 'corrigida' => 'Corrigida'],
        'dificuldade'    => ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'],
        'presenca'       => ['presente' => 'Presente', 'falta' => 'Falta', 'falta_justificada' => 'Falta justificada', 'atraso' => 'Atraso'],
        'resultado'      => ['correta' => 'Acertou', 'incorreta' => 'Errou', 'nao_respondida' => 'Não respondeu'],
        'papel'          => ['admin' => 'Administrador', 'professor' => 'Professor', 'aluno' => 'Aluno'],
        'classificacao'  => ['evolucao' => 'Em evolução', 'intermediario' => 'Intermediário', 'atencao' => 'Precisa de atenção', 'sem_dados' => 'Sem dados suficientes'],
        'vinculo'        => ['ativo' => 'Ativo', 'transferido' => 'Transferido', 'concluido' => 'Concluído', 'trancado' => 'Trancado'],
    ];
    return $mapa[$grupo][$valor] ?? ($valor ?? '—');
}

/** Iniciais para o avatar textual. */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $primeira = mb_substr($partes[0] ?? '', 0, 1);
    $ultima   = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';
    return mb_strtoupper($primeira . $ultima);
}

/** Marca a opção selecionada preservando o valor digitado antes de um erro. */
function old(array $old, string $key, mixed $default = ''): mixed
{
    return $old[$key] ?? $default;
}
