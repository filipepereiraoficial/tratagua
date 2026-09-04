<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Classificação de desenvolvimento dos alunos.
 * Não ordena por "maior nota": usa o Índice de Desenvolvimento, que combina
 * desempenho, evolução, frequência e consistência (docs/04, item 4.8).
 */
class RankingService
{
    /**
     * @param array $filters turma, curso, disciplina, inicio, fim, status_aluno
     * @return array<int, array> alunos ordenados pelo Índice de Desenvolvimento
     */
    public static function build(array $filters = []): array
    {
        $students = self::candidates($filters);
        $ranking  = [];

        foreach ($students as $student) {
            $summary = AnalyticsService::studentSummary((int) $student['id'], $filters);
            $ranking[] = array_merge($summary, [
                'id'         => (int) $student['id'],
                'full_name'  => $student['full_name'],
                'status'     => $student['status'],
                'class_id'   => $student['class_id'] !== null ? (int) $student['class_id'] : null,
                'class_code' => $student['class_code'],
            ]);
        }

        usort($ranking, static function ($a, $b) {
            // Alunos sem dados suficientes ficam no fim, sem posição.
            if ($a['indice'] === null && $b['indice'] === null) {
                return strcmp($a['full_name'], $b['full_name']);
            }
            if ($a['indice'] === null) { return 1; }
            if ($b['indice'] === null) { return -1; }
            return $b['indice'] <=> $a['indice'];
        });

        $position = 0;
        foreach ($ranking as $index => $row) {
            $ranking[$index]['posicao'] = $row['indice'] === null ? null : ++$position;
        }

        return $ranking;
    }

    /** Alunos elegíveis ao recorte pedido. */
    private static function candidates(array $filters): array
    {
        $where  = ["s.status <> 'inativo'"];
        $params = [];

        if (!empty($filters['turma'])) {
            $where[] = 'c.id = :turma';
            $params['turma'] = (int) $filters['turma'];
        }
        if (!empty($filters['curso'])) {
            $where[] = 'c.course_id = :curso';
            $params['curso'] = (int) $filters['curso'];
        }
        if (!empty($filters['status_aluno'])) {
            $where[0] = 's.status = :status_aluno';
            $params['status_aluno'] = $filters['status_aluno'];
        }
        if (!empty($filters['aluno'])) {
            // Recorte de um único aluno (usado pelos alertas individuais).
            $where[0] = '1 = 1';
            $where[] = 's.id = :aluno_id';
            $params['aluno_id'] = (int) $filters['aluno'];
        }
        if (isset($filters['ofertas'])) {
            // Escopo do professor: só entram alunos das turmas em que ele leciona.
            $ids = array_filter(array_map('intval', (array) $filters['ofertas']));
            $where[] = $ids === []
                ? '1 = 0'
                : 'c.id IN (SELECT class_id FROM class_subjects WHERE id IN (' . implode(',', $ids) . '))';
        }

        return Database::all(
            'SELECT s.id, s.full_name, s.status, c.id AS class_id, c.code AS class_code
               FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.full_name',
            $params
        );
    }

    /** Contagem por classificação, para os cards do dashboard. */
    public static function summarize(array $ranking): array
    {
        $counts = ['evolucao' => 0, 'intermediario' => 0, 'atencao' => 0, 'sem_dados' => 0];
        foreach ($ranking as $row) {
            $counts[$row['classificacao']] = ($counts[$row['classificacao']] ?? 0) + 1;
        }
        return $counts;
    }

    /** Alunos filtrados por classificação. */
    public static function filterByClassification(array $ranking, string $classification): array
    {
        return array_values(array_filter($ranking, static fn ($row) => $row['classificacao'] === $classification));
    }

    /** Maiores ganhos e maiores quedas de desempenho recente. */
    public static function movers(array $ranking, int $limit = 5): array
    {
        $withDelta = array_values(array_filter($ranking, static fn ($r) => $r['evolucao_recente'] !== null));
        usort($withDelta, static fn ($a, $b) => $b['evolucao_recente'] <=> $a['evolucao_recente']);

        $rising  = array_slice($withDelta, 0, $limit);
        $falling = array_slice(array_reverse($withDelta), 0, $limit);
        $falling = array_values(array_filter($falling, static fn ($r) => $r['evolucao_recente'] < 0));

        return ['subiram' => $rising, 'cairam' => $falling];
    }

    /** Posição de um aluno específico dentro do ranking da própria turma. */
    public static function positionInClass(int $studentId, int $classId, array $filters = []): array
    {
        $ranking = self::build(array_merge($filters, ['turma' => $classId]));
        foreach ($ranking as $row) {
            if ($row['id'] === $studentId) {
                return [
                    'posicao' => $row['posicao'],
                    'total'   => count(array_filter($ranking, static fn ($r) => $r['indice'] !== null)),
                    'indice'  => $row['indice'],
                ];
            }
        }
        return ['posicao' => null, 'total' => count($ranking), 'indice' => null];
    }

    /** Descrição legível dos pesos em uso (exibida junto ao ranking). */
    public static function weightsLabel(): string
    {
        $w = Setting::weights();
        return sprintf(
            'Desempenho %d%% · Evolução %d%% · Frequência %d%% · Consistência %d%%',
            round($w['desempenho'] * 100),
            round($w['evolucao'] * 100),
            round($w['frequencia'] * 100),
            round($w['consistencia'] * 100)
        );
    }
}
