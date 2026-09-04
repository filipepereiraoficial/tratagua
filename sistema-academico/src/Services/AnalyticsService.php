<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Motor analítico do sistema. Implementa, em um único lugar, todas as fórmulas
 * descritas em docs/04-REGRAS-DE-CALCULO.md — telas, relatórios e exportações
 * consomem daqui, então os números nunca divergem entre si.
 *
 * Convenção dos filtros aceitos por quase todos os métodos:
 *   turma, curso, disciplina, assunto, aluno, avaliacao, tipo,
 *   dificuldade, inicio, fim, status_aluno
 */
class AnalyticsService
{
    // ------------------------------------------------------------------
    // Construção de filtros
    // ------------------------------------------------------------------

    /** WHERE aplicado sobre grades g + assessments a + class_subjects cs. */
    private static function gradeWhere(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($f['turma']))      { $where[] = 'cs.class_id = :turma';        $params['turma'] = (int) $f['turma']; }
        if (!empty($f['disciplina'])) { $where[] = 'cs.subject_id = :disciplina'; $params['disciplina'] = (int) $f['disciplina']; }
        if (!empty($f['curso']))      { $where[] = 'cl.course_id = :curso';       $params['curso'] = (int) $f['curso']; }
        if (!empty($f['aluno']))      { $where[] = 'g.student_id = :aluno';       $params['aluno'] = (int) $f['aluno']; }
        if (!empty($f['avaliacao']))  { $where[] = 'a.id = :avaliacao';           $params['avaliacao'] = (int) $f['avaliacao']; }
        if (!empty($f['tipo']))       { $where[] = 'a.type = :tipo';              $params['tipo'] = $f['tipo']; }
        if (!empty($f['inicio']))     { $where[] = 'a.assessment_date >= :inicio'; $params['inicio'] = $f['inicio']; }
        if (!empty($f['fim']))        { $where[] = 'a.assessment_date <= :fim';   $params['fim'] = $f['fim']; }
        if (!empty($f['status_aluno'])) { $where[] = 'st.status = :status_aluno'; $params['status_aluno'] = $f['status_aluno']; }

        // Restrição de escopo (professor vê só as suas ofertas). Chega como lista
        // de ids já validados; vai inline porque IN (?) não aceita array no PDO.
        if (isset($f['ofertas'])) {
            $ids = array_filter(array_map('intval', (array) $f['ofertas']));
            $where[] = $ids === [] ? '1 = 0' : 'cs.id IN (' . implode(',', $ids) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    private const GRADE_FROM = '
        FROM grades g
        JOIN assessments a ON a.id = g.assessment_id
        JOIN class_subjects cs ON cs.id = a.class_subject_id
        JOIN classes cl ON cl.id = cs.class_id
        JOIN subjects sj ON sj.id = cs.subject_id
        JOIN students st ON st.id = g.student_id';

    /** WHERE aplicado sobre student_answers sa + questions q + assessments a. */
    private static function answerWhere(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($f['turma']))      { $where[] = 'cs.class_id = :turma';        $params['turma'] = (int) $f['turma']; }
        if (!empty($f['disciplina'])) { $where[] = 'cs.subject_id = :disciplina'; $params['disciplina'] = (int) $f['disciplina']; }
        if (!empty($f['curso']))      { $where[] = 'cl.course_id = :curso';       $params['curso'] = (int) $f['curso']; }
        if (!empty($f['aluno']))      { $where[] = 'sa.student_id = :aluno';      $params['aluno'] = (int) $f['aluno']; }
        if (!empty($f['avaliacao']))  { $where[] = 'a.id = :avaliacao';           $params['avaliacao'] = (int) $f['avaliacao']; }
        if (!empty($f['tipo']))       { $where[] = 'a.type = :tipo';              $params['tipo'] = $f['tipo']; }
        if (!empty($f['dificuldade'])){ $where[] = 'q.difficulty = :dificuldade'; $params['dificuldade'] = $f['dificuldade']; }
        if (!empty($f['assunto']))    { $where[] = '(q.topic_id = :assunto OR tp.parent_id = :assunto)'; $params['assunto'] = (int) $f['assunto']; }
        if (!empty($f['inicio']))     { $where[] = 'a.assessment_date >= :inicio'; $params['inicio'] = $f['inicio']; }
        if (!empty($f['fim']))        { $where[] = 'a.assessment_date <= :fim';   $params['fim'] = $f['fim']; }

        // Restrição de escopo (professor vê só as suas ofertas). Chega como lista
        // de ids já validados; vai inline porque IN (?) não aceita array no PDO.
        if (isset($f['ofertas'])) {
            $ids = array_filter(array_map('intval', (array) $f['ofertas']));
            $where[] = $ids === [] ? '1 = 0' : 'cs.id IN (' . implode(',', $ids) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    private const ANSWER_FROM = '
        FROM student_answers sa
        JOIN questions q ON q.id = sa.question_id
        JOIN assessments a ON a.id = q.assessment_id
        JOIN class_subjects cs ON cs.id = a.class_subject_id
        JOIN classes cl ON cl.id = cs.class_id
        JOIN subjects sj ON sj.id = cs.subject_id
        LEFT JOIN topics tp ON tp.id = q.topic_id
        LEFT JOIN topics tpp ON tpp.id = tp.parent_id';

    // ------------------------------------------------------------------
    // Estatística básica
    // ------------------------------------------------------------------

    /** Média ponderada dos percentuais (peso = assessments.weight). */
    public static function weightedAverage(array $grades): ?float
    {
        $sum = 0.0;
        $weights = 0.0;
        foreach ($grades as $grade) {
            $weight = (float) ($grade['weight'] ?? 1);
            $sum += ((float) $grade['percentage']) * $weight;
            $weights += $weight;
        }
        return $weights > 0 ? round($sum / $weights, 2) : null;
    }

    /** Coeficiente angular da reta de tendência: pontos percentuais por avaliação. */
    public static function trendSlope(array $percentages): ?float
    {
        $n = count($percentages);
        if ($n < 2) {
            return null;
        }
        $meanX = ($n + 1) / 2;
        $meanY = array_sum($percentages) / $n;
        $num = 0.0;
        $den = 0.0;
        foreach (array_values($percentages) as $i => $y) {
            $dx = ($i + 1) - $meanX;
            $num += $dx * ($y - $meanY);
            $den += $dx * $dx;
        }
        return $den > 0 ? round($num / $den, 3) : null;
    }

    public static function stdDev(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        return round(sqrt($variance / $n), 2);
    }

    /** Diferença entre a média das últimas N avaliações e a das anteriores. */
    public static function recentDelta(array $percentages, ?int $window = null): ?float
    {
        $window = $window ?? Setting::int('janela_recente');
        $n = count($percentages);
        if ($n < 2) {
            return null;
        }
        $window = min($window, (int) floor($n / 2)) ?: 1;
        $recent   = array_slice($percentages, -$window);
        $previous = array_slice($percentages, 0, $n - $window);
        if ($previous === []) {
            return null;
        }
        return round(array_sum($recent) / count($recent) - array_sum($previous) / count($previous), 2);
    }

    private static function clamp(float $value, float $min = 0.0, float $max = 100.0): float
    {
        return max($min, min($max, $value));
    }

    /** Classifica um aproveitamento nas faixas configuradas. */
    public static function classifyMastery(?float $percentage): string
    {
        if ($percentage === null) {
            return 'sem_dados';
        }
        if ($percentage >= Setting::float('faixa_dominio')) {
            return 'dominio';
        }
        if ($percentage >= Setting::float('faixa_intermediario')) {
            return 'intermediario';
        }
        return 'dificuldade';
    }

    // ------------------------------------------------------------------
    // Aluno
    // ------------------------------------------------------------------

    /** Notas do aluno em ordem cronológica (base de quase todos os indicadores). */
    public static function studentGrades(int $studentId, array $filters = []): array
    {
        $filters['aluno'] = $studentId;
        [$where, $params] = self::gradeWhere($filters);
        return Database::all(
            'SELECT g.percentage, g.score, g.correct_count, g.wrong_count, g.blank_count,
                    a.id AS assessment_id, a.name AS assessment_name, a.assessment_date,
                    a.type, a.max_score, a.weight,
                    sj.id AS subject_id, sj.name AS subject_name,
                    cl.id AS class_id, cl.code AS class_code'
            . self::GRADE_FROM . '
             WHERE ' . $where . '
             ORDER BY a.assessment_date, a.id',
            $params
        );
    }

    /** Frequência do aluno: % de presença e média de participação. */
    public static function studentAttendance(int $studentId, array $filters = []): array
    {
        $where  = ['a.student_id = :sid'];
        $params = ['sid' => $studentId];
        if (!empty($filters['disciplina'])) { $where[] = 'cs.subject_id = :disciplina'; $params['disciplina'] = (int) $filters['disciplina']; }
        if (!empty($filters['turma']))      { $where[] = 'cs.class_id = :turma';        $params['turma'] = (int) $filters['turma']; }
        if (!empty($filters['inicio']))     { $where[] = 'l.lesson_date >= :inicio';    $params['inicio'] = $filters['inicio']; }
        if (!empty($filters['fim']))        { $where[] = 'l.lesson_date <= :fim';       $params['fim'] = $filters['fim']; }

        $row = Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN a.status = 'presente' THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN a.status = 'atraso' THEN 1 ELSE 0 END) AS atrasos,
                    SUM(CASE WHEN a.status = 'falta' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN a.status = 'falta_justificada' THEN 1 ELSE 0 END) AS justificadas,
                    AVG(a.participation) AS participacao
               FROM attendances a
               JOIN lessons l ON l.id = a.lesson_id
               JOIN class_subjects cs ON cs.id = l.class_subject_id
              WHERE " . implode(' AND ', $where),
            $params
        ) ?? [];

        $total        = (int) ($row['total'] ?? 0);
        $justificadas = (int) ($row['justificadas'] ?? 0);
        $presentes    = (int) ($row['presentes'] ?? 0);
        $atrasos      = (int) ($row['atrasos'] ?? 0);

        // Faltas justificadas saem do denominador quando assim configurado.
        $base = Setting::bool('justificada_conta') ? $total : $total - $justificadas;
        $rate = $base > 0 ? round(($presentes + $atrasos * 0.5) / $base * 100, 2) : null;

        return [
            'aulas'        => $total,
            'presentes'    => $presentes,
            'atrasos'      => $atrasos,
            'faltas'       => (int) ($row['faltas'] ?? 0),
            'justificadas' => $justificadas,
            'frequencia'   => $rate,
            'participacao' => $row['participacao'] !== null ? round((float) $row['participacao'], 2) : null,
        ];
    }

    /** Aproveitamento do aluno por assunto/tópico, já classificado. */
    public static function studentTopicMastery(int $studentId, array $filters = []): array
    {
        $filters['aluno'] = $studentId;
        return self::topicPerformance($filters);
    }

    /** Aproveitamento por disciplina. */
    public static function studentSubjectPerformance(int $studentId, array $filters = []): array
    {
        $filters['aluno'] = $studentId;
        [$where, $params] = self::gradeWhere($filters);
        return Database::all(
            'SELECT sj.id AS subject_id, sj.name AS subject_name,
                    COUNT(g.id) AS avaliacoes,
                    AVG(g.percentage) AS media,
                    MIN(g.percentage) AS minima,
                    MAX(g.percentage) AS maxima'
            . self::GRADE_FROM . '
             WHERE ' . $where . '
             GROUP BY sj.id, sj.name
             ORDER BY media DESC',
            $params
        );
    }

    /** Aproveitamento por nível de dificuldade das questões. */
    public static function difficultyPerformance(array $filters = []): array
    {
        [$where, $params] = self::answerWhere($filters);
        $rows = Database::all(
            "SELECT q.difficulty,
                    COUNT(sa.id) AS respondidas,
                    SUM(CASE WHEN sa.result = 'correta' THEN 1 ELSE 0 END) AS acertos,
                    COALESCE(SUM(sa.score_earned), 0) AS pontos_obtidos,
                    COALESCE(SUM(q.points), 0) AS pontos_possiveis"
            . self::ANSWER_FROM . '
             WHERE ' . $where . '
             GROUP BY q.difficulty',
            $params
        );

        $ordered = ['facil' => null, 'medio' => null, 'dificil' => null];
        foreach ($rows as $row) {
            $possible = (float) $row['pontos_possiveis'];
            $ordered[$row['difficulty']] = [
                'dificuldade'    => $row['difficulty'],
                'respondidas'    => (int) $row['respondidas'],
                'acertos'        => (int) $row['acertos'],
                'aproveitamento' => $possible > 0 ? round((float) $row['pontos_obtidos'] / $possible * 100, 2) : null,
            ];
        }
        return array_values(array_filter($ordered));
    }

    /** Contagem de acertos/erros/branco (aluno, turma ou geral, conforme filtros). */
    public static function answerTotals(array $filters = []): array
    {
        [$where, $params] = self::answerWhere($filters);
        $row = Database::first(
            "SELECT COUNT(sa.id) AS total,
                    SUM(CASE WHEN sa.result = 'correta' THEN 1 ELSE 0 END) AS acertos,
                    SUM(CASE WHEN sa.result = 'incorreta' THEN 1 ELSE 0 END) AS erros,
                    SUM(CASE WHEN sa.result = 'nao_respondida' THEN 1 ELSE 0 END) AS branco"
            . self::ANSWER_FROM . '
             WHERE ' . $where,
            $params
        ) ?? [];

        $total = (int) ($row['total'] ?? 0);
        return [
            'total'       => $total,
            'acertos'     => (int) ($row['acertos'] ?? 0),
            'erros'       => (int) ($row['erros'] ?? 0),
            'branco'      => (int) ($row['branco'] ?? 0),
            'pct_acertos' => $total > 0 ? round((int) $row['acertos'] / $total * 100, 2) : null,
            'pct_erros'   => $total > 0 ? round((int) $row['erros'] / $total * 100, 2) : null,
            'pct_branco'  => $total > 0 ? round((int) $row['branco'] / $total * 100, 2) : null,
        ];
    }

    /**
     * Painel completo de um aluno: indicadores, séries e classificação.
     * É a fonte única do dashboard individual, dos relatórios e do ranking.
     */
    public static function studentSummary(int $studentId, array $filters = []): array
    {
        $grades      = self::studentGrades($studentId, $filters);
        $percentages = array_map(static fn ($g) => (float) $g['percentage'], $grades);

        $average     = self::weightedAverage($grades);
        $slope       = self::trendSlope($percentages);
        $delta       = self::recentDelta($percentages);
        $deviation   = self::stdDev($percentages);
        $attendance  = self::studentAttendance($studentId, $filters);
        $answers     = self::answerTotals(array_merge($filters, ['aluno' => $studentId]));
        $topics      = self::studentTopicMastery($studentId, $filters);

        $mastered     = 0;
        $intermediate = 0;
        $struggling   = 0;
        foreach ($topics as $topic) {
            match ($topic['classificacao']) {
                'dominio'       => $mastered++,
                'intermediario' => $intermediate++,
                'dificuldade'   => $struggling++,
                default         => null,
            };
        }

        $minEvolution = Setting::int('min_avaliacoes_evolucao');
        $evolutionScore = ($slope !== null && count($grades) >= $minEvolution)
            ? self::clamp(50 + $slope * Setting::float('fator_evolucao'))
            : 50.0;
        $consistencyScore = $deviation !== null
            ? self::clamp(100 - $deviation * Setting::float('fator_consistencia'))
            : 50.0;

        $weights = Setting::weights();
        $index = null;
        $reliable = count($grades) >= Setting::int('min_avaliacoes_indice');
        if ($reliable) {
            $index = round(
                $weights['desempenho']   * (float) $average
                + $weights['evolucao']     * $evolutionScore
                + $weights['frequencia']   * (float) ($attendance['frequencia'] ?? 50)
                + $weights['consistencia'] * $consistencyScore,
                2
            );
        }

        [$classification, $reasons] = self::classifyStudent($index, $delta, $attendance['frequencia'], $reliable);

        return [
            'student_id'          => $studentId,
            'avaliacoes'          => count($grades),
            'media'               => $average,
            'notas'               => $grades,
            'percentuais'         => $percentages,
            'evolucao_slope'      => $slope,
            'evolucao_total'      => $slope !== null ? round($slope * (count($grades) - 1), 2) : null,
            'evolucao_recente'    => $delta,
            'desvio'              => $deviation,
            'score_evolucao'      => round($evolutionScore, 2),
            'score_consistencia'  => round($consistencyScore, 2),
            'frequencia'          => $attendance['frequencia'],
            'participacao'        => $attendance['participacao'],
            'presenca'            => $attendance,
            'acertos'             => $answers,
            'assuntos'            => $topics,
            'dominados'           => $mastered,
            'intermediarios'      => $intermediate,
            'dificuldades'        => $struggling,
            'indice'              => $index,
            'indice_confiavel'    => $reliable,
            'classificacao'       => $classification,
            'motivos'             => $reasons,
        ];
    }

    /**
     * Classificação do aluno com justificativa explícita — o professor sempre vê
     * por que o aluno caiu naquele grupo.
     * @return array{0:string, 1:array<int,string>}
     */
    public static function classifyStudent(?float $index, ?float $delta, ?float $attendance, bool $reliable): array
    {
        if (!$reliable || $index === null) {
            return ['sem_dados', ['Menos de ' . Setting::int('min_avaliacoes_indice') . ' avaliação(ões) registrada(s).']];
        }

        $reasons = [];
        $queda      = Setting::float('queda_alerta');
        $freqMinima = Setting::float('frequencia_minima');

        $needsAttention = false;
        if ($delta !== null && $delta <= -$queda) {
            $needsAttention = true;
            $reasons[] = 'Queda de ' . number_format(abs($delta), 1, ',', '.') . ' p.p. no desempenho recente.';
        }
        if ($attendance !== null && $attendance < $freqMinima) {
            $needsAttention = true;
            $reasons[] = 'Frequência de ' . number_format($attendance, 1, ',', '.') . '%, abaixo do mínimo de ' . number_format($freqMinima, 0, ',', '.') . '%.';
        }

        if ($needsAttention || $index < Setting::float('id_atencao')) {
            if ($index < Setting::float('id_atencao')) {
                $reasons[] = 'Índice de Desenvolvimento em ' . number_format($index, 1, ',', '.') . '.';
            }
            return ['atencao', $reasons];
        }
        if ($index >= Setting::float('id_evolucao')) {
            $reasons[] = 'Índice de Desenvolvimento em ' . number_format($index, 1, ',', '.') . '.';
            if ($delta !== null && $delta > 0) {
                $reasons[] = 'Ganho de ' . number_format($delta, 1, ',', '.') . ' p.p. nas avaliações recentes.';
            }
            return ['evolucao', $reasons];
        }
        $reasons[] = 'Índice de Desenvolvimento em ' . number_format($index, 1, ',', '.') . '.';
        return ['intermediario', $reasons];
    }

    // ------------------------------------------------------------------
    // Agregações coletivas
    // ------------------------------------------------------------------

    /** Aproveitamento por assunto/tópico com classificação e amostra mínima. */
    public static function topicPerformance(array $filters = [], string $order = 'asc'): array
    {
        [$where, $params] = self::answerWhere($filters);
        $rows = Database::all(
            "SELECT COALESCE(tpp.id, tp.id) AS topic_id,
                    COALESCE(tpp.name, tp.name) AS topic_name,
                    CASE WHEN tpp.id IS NULL THEN NULL ELSE tp.name END AS child_name,
                    sj.id AS subject_id, sj.name AS subject_name,
                    COUNT(sa.id) AS respondidas,
                    COUNT(DISTINCT sa.student_id) AS alunos,
                    SUM(CASE WHEN sa.result = 'correta' THEN 1 ELSE 0 END) AS acertos,
                    SUM(CASE WHEN sa.result = 'incorreta' THEN 1 ELSE 0 END) AS erros,
                    COALESCE(SUM(sa.score_earned), 0) AS pontos_obtidos,
                    COALESCE(SUM(q.points), 0) AS pontos_possiveis"
            . self::ANSWER_FROM . '
             WHERE ' . $where . ' AND q.topic_id IS NOT NULL
             GROUP BY COALESCE(tpp.id, tp.id), COALESCE(tpp.name, tp.name),
                      CASE WHEN tpp.id IS NULL THEN NULL ELSE tp.name END,
                      sj.id, sj.name',
            $params
        );

        // Consolida tópicos filhos sob o assunto pai.
        $grouped = [];
        foreach ($rows as $row) {
            $key = (int) $row['topic_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'topic_id'        => $key,
                    'topic_name'      => $row['topic_name'],
                    'subject_id'      => (int) $row['subject_id'],
                    'subject_name'    => $row['subject_name'],
                    'respondidas'     => 0,
                    'alunos'          => 0,
                    'acertos'         => 0,
                    'erros'           => 0,
                    'pontos_obtidos'  => 0.0,
                    'pontos_possiveis'=> 0.0,
                ];
            }
            $grouped[$key]['respondidas']      += (int) $row['respondidas'];
            $grouped[$key]['alunos']            = max($grouped[$key]['alunos'], (int) $row['alunos']);
            $grouped[$key]['acertos']          += (int) $row['acertos'];
            $grouped[$key]['erros']            += (int) $row['erros'];
            $grouped[$key]['pontos_obtidos']   += (float) $row['pontos_obtidos'];
            $grouped[$key]['pontos_possiveis'] += (float) $row['pontos_possiveis'];
        }

        $minSample = Setting::int('min_questoes_assunto');
        $result = [];
        foreach ($grouped as $item) {
            $possible = $item['pontos_possiveis'];
            $item['aproveitamento'] = $possible > 0 ? round($item['pontos_obtidos'] / $possible * 100, 2) : null;
            $item['amostra_suficiente'] = $item['respondidas'] >= $minSample;
            $item['classificacao'] = $item['amostra_suficiente']
                ? self::classifyMastery($item['aproveitamento'])
                : 'sem_dados';
            $result[] = $item;
        }

        usort($result, static function ($a, $b) use ($order) {
            $x = $a['aproveitamento'] ?? 999;
            $y = $b['aproveitamento'] ?? 999;
            return $order === 'desc' ? $y <=> $x : $x <=> $y;
        });

        return $result;
    }

    /** Média por disciplina (turma, curso ou global, conforme filtros). */
    public static function subjectAverages(array $filters = []): array
    {
        [$where, $params] = self::gradeWhere($filters);
        return Database::all(
            'SELECT sj.id AS subject_id, sj.name AS subject_name,
                    COUNT(g.id) AS lancamentos,
                    COUNT(DISTINCT g.student_id) AS alunos,
                    COUNT(DISTINCT a.id) AS avaliacoes,
                    AVG(g.percentage) AS media'
            . self::GRADE_FROM . '
             WHERE ' . $where . '
             GROUP BY sj.id, sj.name
             ORDER BY media DESC',
            $params
        );
    }

    /** Média por avaliação, em ordem cronológica (evolução da turma/curso). */
    public static function assessmentAverages(array $filters = [], int $limit = 40): array
    {
        [$where, $params] = self::gradeWhere($filters);
        return Database::all(
            'SELECT a.id AS assessment_id, a.name AS assessment_name, a.assessment_date, a.type,
                    sj.name AS subject_name, cl.code AS class_code,
                    COUNT(g.id) AS alunos, AVG(g.percentage) AS media,
                    MIN(g.percentage) AS minima, MAX(g.percentage) AS maxima'
            . self::GRADE_FROM . '
             WHERE ' . $where . '
             GROUP BY a.id, a.name, a.assessment_date, a.type, sj.name, cl.code
             ORDER BY a.assessment_date, a.id
             LIMIT ' . (int) $limit,
            $params
        );
    }

    /** Média por turma. */
    public static function classAverages(array $filters = []): array
    {
        [$where, $params] = self::gradeWhere($filters);
        return Database::all(
            'SELECT cl.id AS class_id, cl.code AS class_code, cl.year, co2.name AS course_name,
                    COUNT(DISTINCT g.student_id) AS alunos,
                    COUNT(DISTINCT a.id) AS avaliacoes,
                    AVG(g.percentage) AS media'
            . self::GRADE_FROM . '
             JOIN courses co2 ON co2.id = cl.course_id
             WHERE ' . $where . '
             GROUP BY cl.id, cl.code, cl.year, co2.name
             ORDER BY media DESC',
            $params
        );
    }

    /** Distribuição dos alunos por faixa de desempenho. */
    public static function performanceDistribution(array $filters = []): array
    {
        [$where, $params] = self::gradeWhere($filters);
        $rows = Database::all(
            'SELECT g.student_id, AVG(g.percentage) AS media'
            . self::GRADE_FROM . '
             WHERE ' . $where . '
             GROUP BY g.student_id',
            $params
        );

        $buckets = [
            '0 a 39%'    => 0,
            '40 a 59%'   => 0,
            '60 a 79%'   => 0,
            '80 a 100%'  => 0,
        ];
        foreach ($rows as $row) {
            $media = (float) $row['media'];
            if ($media < 40)      { $buckets['0 a 39%']++; }
            elseif ($media < 60)  { $buckets['40 a 59%']++; }
            elseif ($media < 80)  { $buckets['60 a 79%']++; }
            else                  { $buckets['80 a 100%']++; }
        }
        return $buckets;
    }

    /** Frequência média por turma. */
    public static function attendanceByClass(array $filters = []): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!empty($filters['turma']))  { $where[] = 'cs.class_id = :turma'; $params['turma'] = (int) $filters['turma']; }
        if (!empty($filters['curso']))  { $where[] = 'cl.course_id = :curso'; $params['curso'] = (int) $filters['curso']; }
        if (!empty($filters['inicio'])) { $where[] = 'l.lesson_date >= :inicio'; $params['inicio'] = $filters['inicio']; }
        if (!empty($filters['fim']))    { $where[] = 'l.lesson_date <= :fim'; $params['fim'] = $filters['fim']; }

        return Database::all(
            "SELECT cl.id AS class_id, cl.code AS class_code,
                    COUNT(at.id) AS registros,
                    SUM(CASE WHEN at.status IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas,
                    ROUND(100.0 * SUM(CASE WHEN at.status = 'presente' THEN 1 WHEN at.status = 'atraso' THEN 0.5 ELSE 0 END)
                          / NULLIF(SUM(CASE WHEN at.status = 'falta_justificada' THEN 0 ELSE 1 END), 0), 2) AS frequencia
               FROM attendances at
               JOIN lessons l ON l.id = at.lesson_id
               JOIN class_subjects cs ON cs.id = l.class_subject_id
               JOIN classes cl ON cl.id = cs.class_id
              WHERE " . implode(' AND ', $where) . '
              GROUP BY cl.id, cl.code
              ORDER BY cl.code',
            $params
        );
    }

    /** Totais do sistema para os cards do dashboard geral. */
    public static function globalCounters(array $filters = []): array
    {
        $classFilter   = !empty($filters['turma']) ? ' WHERE id = ' . (int) $filters['turma'] : '';
        $studentWhere  = !empty($filters['turma'])
            ? 'JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1 AND e.class_id = ' . (int) $filters['turma']
            : '';

        return [
            'alunos'      => (int) Database::value("SELECT COUNT(*) FROM students s {$studentWhere}", [], 0),
            'turmas'      => (int) Database::value("SELECT COUNT(*) FROM classes {$classFilter}", [], 0),
            'disciplinas' => (int) Database::value('SELECT COUNT(*) FROM subjects', [], 0),
            'cursos'      => (int) Database::value('SELECT COUNT(*) FROM courses', [], 0),
            'aulas'       => (int) Database::value(
                'SELECT COUNT(*) FROM lessons l JOIN class_subjects cs ON cs.id = l.class_subject_id'
                . (!empty($filters['turma']) ? ' WHERE cs.class_id = ' . (int) $filters['turma'] : ''), [], 0),
            'avaliacoes'  => (int) Database::value(
                'SELECT COUNT(*) FROM assessments a JOIN class_subjects cs ON cs.id = a.class_subject_id'
                . (!empty($filters['turma']) ? ' WHERE cs.class_id = ' . (int) $filters['turma'] : ''), [], 0),
            'questoes'    => (int) Database::value('SELECT COUNT(*) FROM questions', [], 0),
        ];
    }

    /** Média geral do recorte (todas as notas que passam pelos filtros). */
    public static function overallAverage(array $filters = []): ?float
    {
        [$where, $params] = self::gradeWhere($filters);
        $value = Database::value('SELECT AVG(g.percentage)' . self::GRADE_FROM . ' WHERE ' . $where, $params);
        return $value === null ? null : round((float) $value, 2);
    }


    /**
     * Onde a turma mais perde pontos: por avaliação, quanto do total possível
     * ficou pelo caminho. Responde "em que prova meus alunos mais deixaram
     * pontuação na mesa" — e por isso ordena por pontos perdidos, não por média.
     */
    public static function assessmentPointLoss(array $filters = [], int $limit = 20): array
    {
        [$where, $params] = self::answerWhere($filters);
        $rows = Database::all(
            "SELECT a.id AS assessment_id, a.name AS assessment_name, a.assessment_date, a.type,
                    sj.name AS subject_name, cl.code AS class_code,
                    COUNT(DISTINCT sa.student_id) AS alunos,
                    COUNT(sa.id) AS respostas,
                    COALESCE(SUM(q.points), 0) AS pontos_possiveis,
                    COALESCE(SUM(sa.score_earned), 0) AS pontos_obtidos,
                    SUM(CASE WHEN sa.result = 'incorreta' THEN 1 ELSE 0 END) AS erros,
                    SUM(CASE WHEN sa.result = 'nao_respondida' THEN 1 ELSE 0 END) AS branco"
            . self::ANSWER_FROM . ' WHERE ' . $where . '
             GROUP BY a.id, a.name, a.assessment_date, a.type, sj.name, cl.code',
            $params
        );

        foreach ($rows as &$row) {
            $possiveis = (float) $row['pontos_possiveis'];
            $row['pontos_perdidos'] = round($possiveis - (float) $row['pontos_obtidos'], 2);
            $row['pct_perdido']     = $possiveis > 0 ? round($row['pontos_perdidos'] / $possiveis * 100, 2) : null;
            $row['aproveitamento']  = $possiveis > 0 ? round((float) $row['pontos_obtidos'] / $possiveis * 100, 2) : null;
        }
        unset($row);

        usort($rows, static fn ($a, $b) => $b['pontos_perdidos'] <=> $a['pontos_perdidos']);
        return array_slice($rows, 0, $limit);
    }

    /**
     * Quanto cada aluno deixou de pontuar no recorte, e em qual avaliação a
     * perda foi maior. É a leitura individual do mesmo dado acima.
     */
    public static function studentPointLoss(array $filters = []): array
    {
        [$where, $params] = self::answerWhere($filters);
        $rows = Database::all(
            'SELECT sa.student_id, st2.full_name,
                    a.id AS assessment_id, a.name AS assessment_name,
                    COALESCE(SUM(q.points), 0) AS possiveis,
                    COALESCE(SUM(sa.score_earned), 0) AS obtidos'
            . self::ANSWER_FROM . '
             JOIN students st2 ON st2.id = sa.student_id
             WHERE ' . $where . '
             GROUP BY sa.student_id, st2.full_name, a.id, a.name',
            $params
        );

        $porAluno = [];
        foreach ($rows as $row) {
            $id = (int) $row['student_id'];
            $perdido = round((float) $row['possiveis'] - (float) $row['obtidos'], 2);
            if (!isset($porAluno[$id])) {
                $porAluno[$id] = [
                    'student_id' => $id, 'full_name' => $row['full_name'],
                    'possiveis' => 0.0, 'obtidos' => 0.0, 'perdidos' => 0.0,
                    'pior_avaliacao' => null, 'pior_perda' => 0.0,
                ];
            }
            $porAluno[$id]['possiveis'] += (float) $row['possiveis'];
            $porAluno[$id]['obtidos']   += (float) $row['obtidos'];
            $porAluno[$id]['perdidos']  += $perdido;
            if ($perdido > $porAluno[$id]['pior_perda']) {
                $porAluno[$id]['pior_perda']     = $perdido;
                $porAluno[$id]['pior_avaliacao'] = $row['assessment_name'];
            }
        }

        $resultado = array_values($porAluno);
        foreach ($resultado as &$aluno) {
            $aluno['possiveis'] = round($aluno['possiveis'], 2);
            $aluno['obtidos']   = round($aluno['obtidos'], 2);
            $aluno['perdidos']  = round($aluno['perdidos'], 2);
            $aluno['aproveitamento'] = $aluno['possiveis'] > 0
                ? round($aluno['obtidos'] / $aluno['possiveis'] * 100, 2) : null;
        }
        unset($aluno);

        usort($resultado, static fn ($a, $b) => $b['perdidos'] <=> $a['perdidos']);
        return $resultado;
    }

    /** Contagem de aulas e avaliações de um recorte de ofertas. */
    public static function teachingCounters(array $filters = []): array
    {
        $ofertas = isset($filters['ofertas'])
            ? array_filter(array_map('intval', (array) $filters['ofertas'])) : null;
        $filtro = $ofertas === null ? '' : ' WHERE class_subject_id IN (' . ($ofertas === [] ? '0' : implode(',', $ofertas)) . ')';
        return [
            'aulas'      => (int) Database::value('SELECT COUNT(*) FROM lessons' . $filtro, [], 0),
            'avaliacoes' => (int) Database::value('SELECT COUNT(*) FROM assessments' . $filtro, [], 0),
        ];
    }

    /** Análise questão a questão de uma avaliação. */
    public static function assessmentQuestionAnalysis(int $assessmentId): array
    {
        $rows = \App\Models\Answer::statsByQuestion($assessmentId);
        foreach ($rows as &$row) {
            $answered = (int) $row['answered'];
            $row['indice_acerto'] = $answered > 0 ? round((int) $row['correct'] / $answered * 100, 2) : null;
            $row['classificacao'] = self::classifyMastery($row['indice_acerto']);
        }
        return $rows;
    }
}
