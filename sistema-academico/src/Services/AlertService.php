<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Alertas pedagógicos derivados dos dados reais (docs/04, item 4.9).
 * Cada alerta carrega uma chave estável, para que o professor possa marcá-lo
 * como tratado sem perder o histórico.
 */
class AlertService
{
    public const HIGH = 'alta';
    public const MEDIUM = 'media';
    public const POSITIVE = 'positiva';

    /**
     * Gera os alertas do recorte pedido.
     * @param array|null $ranking ranking já calculado (evita recalcular)
     */
    public static function generate(array $filters = [], ?array $ranking = null, bool $includeDismissed = false): array
    {
        $ranking = $ranking ?? RankingService::build($filters);
        $alerts  = [];

        $queda        = Setting::float('queda_alerta');
        $ganho        = Setting::float('evolucao_alerta');
        $freqMinima   = Setting::float('frequencia_minima');
        $mediaAlerta  = Setting::float('media_alerta');
        $limiteDif    = Setting::float('limite_dificuldade');
        $ocorrencias  = Setting::int('ocorrencias_persistente');

        foreach ($ranking as $student) {
            $name = $student['full_name'];
            $id   = $student['id'];

            if ($student['evolucao_recente'] !== null && $student['evolucao_recente'] <= -$queda) {
                $alerts[] = self::make(
                    "queda:{$id}", self::HIGH, 'Queda de desempenho', $id, $name,
                    sprintf('%s teve queda de %s pontos percentuais nas avaliações mais recentes (de %s%% para %s%%).',
                        $name,
                        number_format(abs($student['evolucao_recente']), 1, ',', '.'),
                        number_format(self::previousAverage($student['percentuais']), 1, ',', '.'),
                        number_format(self::recentAverage($student['percentuais']), 1, ',', '.')
                    )
                );
            }

            if ($student['frequencia'] !== null && $student['frequencia'] < $freqMinima && $student['presenca']['aulas'] > 0) {
                $alerts[] = self::make(
                    "frequencia:{$id}", self::HIGH, 'Frequência baixa', $id, $name,
                    sprintf('%s está com %s%% de frequência (mínimo configurado: %s%%), com %d falta(s) em %d aula(s).',
                        $name,
                        number_format($student['frequencia'], 1, ',', '.'),
                        number_format($freqMinima, 0, ',', '.'),
                        $student['presenca']['faltas'],
                        $student['presenca']['aulas']
                    )
                );
            }

            if ($student['media'] !== null && $student['media'] < $mediaAlerta && $student['avaliacoes'] > 0) {
                $alerts[] = self::make(
                    "media:{$id}", self::HIGH, 'Baixo aproveitamento', $id, $name,
                    sprintf('%s está com média de %s%% em %d avaliação(ões), abaixo do mínimo de %s%%.',
                        $name,
                        number_format($student['media'], 1, ',', '.'),
                        $student['avaliacoes'],
                        number_format($mediaAlerta, 0, ',', '.')
                    )
                );
            }

            if ($student['evolucao_recente'] !== null && $student['evolucao_recente'] >= $ganho) {
                $alerts[] = self::make(
                    "evolucao:{$id}", self::POSITIVE, 'Evolução significativa', $id, $name,
                    sprintf('%s evoluiu %s pontos percentuais nas avaliações mais recentes. Vale registrar o reconhecimento.',
                        $name,
                        number_format($student['evolucao_recente'], 1, ',', '.')
                    )
                );
            }

            if ($student['avaliacoes'] === 0 && $student['class_id'] !== null) {
                $alerts[] = self::make(
                    "sem_avaliacao:{$id}", self::MEDIUM, 'Aluno sem resultados', $id, $name,
                    sprintf('%s está vinculado a uma turma mas ainda não possui nenhum resultado registrado.', $name)
                );
            }
        }

        foreach (self::persistentDifficulties($filters, $limiteDif, $ocorrencias) as $row) {
            $alerts[] = self::make(
                "persistente:{$row['student_id']}:{$row['topic_id']}", self::HIGH, 'Dificuldade persistente',
                (int) $row['student_id'], $row['full_name'],
                sprintf('Atenção: %s apresentou aproveitamento inferior a %s%% em %s nas últimas %d avaliações.',
                    $row['full_name'],
                    number_format($limiteDif, 0, ',', '.'),
                    $row['topic_name'],
                    (int) $row['ocorrencias']
                ),
                (int) $row['topic_id']
            );
        }

        foreach (self::criticalClassTopics($filters, $limiteDif) as $row) {
            $alerts[] = self::make(
                "turma_conteudo:{$row['class_id']}:{$row['topic_id']}", self::MEDIUM, 'Conteúdo crítico da turma',
                null, null,
                sprintf('A turma %s obteve apenas %s%% de aproveitamento em %s (%d respostas de %d aluno(s)). Conteúdo candidato a revisão.',
                    $row['class_code'],
                    number_format((float) $row['aproveitamento'], 1, ',', '.'),
                    $row['topic_name'],
                    (int) $row['respondidas'],
                    (int) $row['alunos']
                )
            );
        }

        if (!$includeDismissed) {
            $dismissed = self::dismissedKeys();
            $alerts = array_values(array_filter($alerts, static fn ($a) => !in_array($a['key'], $dismissed, true)));
        }

        $order = [self::HIGH => 0, self::MEDIUM => 1, self::POSITIVE => 2];
        usort($alerts, static fn ($a, $b) => ($order[$a['severity']] ?? 3) <=> ($order[$b['severity']] ?? 3));

        return $alerts;
    }

    private static function make(string $key, string $severity, string $title, ?int $studentId, ?string $studentName, string $message, ?int $topicId = null): array
    {
        return [
            'key'          => $key,
            'severity'     => $severity,
            'title'        => $title,
            'student_id'   => $studentId,
            'student_name' => $studentName,
            'topic_id'     => $topicId,
            'message'      => $message,
        ];
    }

    private static function recentAverage(array $percentages): float
    {
        $window = min(Setting::int('janela_recente'), max(1, (int) floor(count($percentages) / 2)));
        $slice  = array_slice($percentages, -$window);
        return $slice === [] ? 0.0 : array_sum($slice) / count($slice);
    }

    private static function previousAverage(array $percentages): float
    {
        $window = min(Setting::int('janela_recente'), max(1, (int) floor(count($percentages) / 2)));
        $slice  = array_slice($percentages, 0, count($percentages) - $window);
        return $slice === [] ? 0.0 : array_sum($slice) / count($slice);
    }

    /**
     * Aluno com aproveitamento abaixo do limite no mesmo assunto em N avaliações
     * distintas — a assinatura de uma dificuldade que não se resolveu sozinha.
     */
    private static function persistentDifficulties(array $filters, float $limit, int $minOccurrences): array
    {
        $where  = ['q.topic_id IS NOT NULL'];
        $params = ['limite' => $limit];

        if (!empty($filters['turma']))      { $where[] = 'cs.class_id = :turma';        $params['turma'] = (int) $filters['turma']; }
        if (!empty($filters['curso']))      { $where[] = 'cl.course_id = :curso';       $params['curso'] = (int) $filters['curso']; }
        if (!empty($filters['disciplina'])) { $where[] = 'cs.subject_id = :disciplina'; $params['disciplina'] = (int) $filters['disciplina']; }
        if (!empty($filters['aluno']))      { $where[] = 'sa.student_id = :aluno';      $params['aluno'] = (int) $filters['aluno']; }
        if (!empty($filters['inicio']))     { $where[] = 'a.assessment_date >= :inicio'; $params['inicio'] = $filters['inicio']; }
        if (!empty($filters['fim']))        { $where[] = 'a.assessment_date <= :fim';   $params['fim'] = $filters['fim']; }

        // Aproveitamento do aluno, por assunto, em cada avaliação.
        $sql = 'SELECT student_id, topic_id, COUNT(*) AS ocorrencias
                  FROM (
                    SELECT sa.student_id AS student_id,
                           COALESCE(tpp.id, tp.id) AS topic_id,
                           a.id AS assessment_id,
                           100.0 * SUM(sa.score_earned) / NULLIF(SUM(q.points), 0) AS aproveitamento
                      FROM student_answers sa
                      JOIN questions q ON q.id = sa.question_id
                      JOIN assessments a ON a.id = q.assessment_id
                      JOIN class_subjects cs ON cs.id = a.class_subject_id
                      JOIN classes cl ON cl.id = cs.class_id
                      LEFT JOIN topics tp ON tp.id = q.topic_id
                      LEFT JOIN topics tpp ON tpp.id = tp.parent_id
                     WHERE ' . implode(' AND ', $where) . '
                     GROUP BY sa.student_id, COALESCE(tpp.id, tp.id), a.id
                  ) AS por_avaliacao
                 -- CAST obrigatório: o PDO envia todo parâmetro como texto e, no
                 -- SQLite, qualquer número é menor que qualquer texto — sem o cast
                 -- a comparação seria sempre verdadeira e o alerta dispararia para
                 -- todos os assuntos de todos os alunos.
                 WHERE aproveitamento IS NOT NULL AND aproveitamento < CAST(:limite AS REAL)
                 GROUP BY student_id, topic_id
                HAVING COUNT(*) >= ' . (int) $minOccurrences;

        $rows = Database::all($sql, $params);
        if ($rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $info = Database::first(
                'SELECT s.full_name, t.name AS topic_name
                   FROM students s, topics t
                  WHERE s.id = ? AND t.id = ?',
                [(int) $row['student_id'], (int) $row['topic_id']]
            );
            if ($info) {
                $out[] = $row + $info;
            }
        }
        return $out;
    }

    /** Assuntos em que uma turma inteira ficou abaixo do limite. */
    private static function criticalClassTopics(array $filters, float $limit, int $minAnswers = 5): array
    {
        $where  = ['q.topic_id IS NOT NULL'];
        $params = [];
        if (!empty($filters['turma']))      { $where[] = 'cs.class_id = :turma';        $params['turma'] = (int) $filters['turma']; }
        if (!empty($filters['curso']))      { $where[] = 'cl.course_id = :curso';       $params['curso'] = (int) $filters['curso']; }
        if (!empty($filters['disciplina'])) { $where[] = 'cs.subject_id = :disciplina'; $params['disciplina'] = (int) $filters['disciplina']; }
        if (!empty($filters['inicio']))     { $where[] = 'a.assessment_date >= :inicio'; $params['inicio'] = $filters['inicio']; }
        if (!empty($filters['fim']))        { $where[] = 'a.assessment_date <= :fim';   $params['fim'] = $filters['fim']; }

        $rows = Database::all(
            'SELECT cl.id AS class_id, cl.code AS class_code,
                    COALESCE(tpp.id, tp.id) AS topic_id,
                    COALESCE(tpp.name, tp.name) AS topic_name,
                    COUNT(sa.id) AS respondidas,
                    COUNT(DISTINCT sa.student_id) AS alunos,
                    100.0 * SUM(sa.score_earned) / NULLIF(SUM(q.points), 0) AS aproveitamento
               FROM student_answers sa
               JOIN questions q ON q.id = sa.question_id
               JOIN assessments a ON a.id = q.assessment_id
               JOIN class_subjects cs ON cs.id = a.class_subject_id
               JOIN classes cl ON cl.id = cs.class_id
               LEFT JOIN topics tp ON tp.id = q.topic_id
               LEFT JOIN topics tpp ON tpp.id = tp.parent_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY cl.id, cl.code, COALESCE(tpp.id, tp.id), COALESCE(tpp.name, tp.name)
             HAVING COUNT(sa.id) >= ' . (int) $minAnswers,
            $params
        );

        return array_values(array_filter($rows, static fn ($r) => $r['aproveitamento'] !== null && (float) $r['aproveitamento'] < $limit));
    }

    private static function dismissedKeys(): array
    {
        return array_column(Database::all('SELECT alert_key FROM alert_dismissals'), 'alert_key');
    }

    public static function dismiss(string $key, ?int $userId): void
    {
        $exists = (int) Database::value('SELECT COUNT(*) FROM alert_dismissals WHERE alert_key = ?', [$key], 0);
        if ($exists === 0) {
            Database::insert('alert_dismissals', ['alert_key' => $key, 'dismissed_by' => $userId]);
        }
    }

    public static function restore(string $key): void
    {
        Database::delete('alert_dismissals', 'alert_key = ?', [$key]);
    }

    /** Alertas de um aluno específico. */
    public static function forStudent(int $studentId): array
    {
        return self::generate(['aluno' => $studentId]);
    }

    public static function countBySeverity(array $alerts): array
    {
        $counts = [self::HIGH => 0, self::MEDIUM => 0, self::POSITIVE => 0];
        foreach ($alerts as $alert) {
            $counts[$alert['severity']]++;
        }
        return $counts;
    }
}
