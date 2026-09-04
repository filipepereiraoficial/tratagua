<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * Monta os conjuntos de dados dos relatórios. Cada método devolve
 * ['titulo', 'colunas', 'linhas'] — a mesma estrutura alimenta a tabela na tela,
 * a exportação CSV e a versão para impressão/PDF.
 */
class ReportService
{
    public const TYPES = [
        'aluno'       => 'Relatório individual do aluno',
        'turma'       => 'Relatório de desempenho da turma',
        'disciplina'  => 'Relatório por disciplina',
        'avaliacao'   => 'Relatório por avaliação',
        'assunto'     => 'Relatório por assunto',
        'dificuldade' => 'Relatório de dificuldades',
        'evolucao'    => 'Relatório de evolução',
        'frequencia'  => 'Relatório de frequência',
    ];

    public static function build(string $type, array $filters): array
    {
        return match ($type) {
            'aluno'       => self::student($filters),
            'turma'       => self::classReport($filters),
            'disciplina'  => self::subject($filters),
            'avaliacao'   => self::assessment($filters),
            'assunto'     => self::topic($filters),
            'dificuldade' => self::difficulties($filters),
            'evolucao'    => self::evolution($filters),
            'frequencia'  => self::attendance($filters),
            default       => ['titulo' => 'Relatório', 'colunas' => [], 'linhas' => [], 'aviso' => 'Tipo de relatório desconhecido.'],
        };
    }

    // ---------------------------------------------------------------- aluno
    private static function student(array $filters): array
    {
        $studentId = (int) ($filters['aluno'] ?? 0);
        if ($studentId <= 0) {
            return self::empty('Relatório individual do aluno', 'Selecione um aluno para gerar este relatório.');
        }
        $student = \App\Models\Student::find($studentId);
        if (!$student) {
            return self::empty('Relatório individual do aluno', 'Aluno não encontrado.');
        }

        $summary = AnalyticsService::studentSummary($studentId, $filters);
        $linhas  = [];
        foreach ($summary['notas'] as $grade) {
            $linhas[] = [
                'Data'          => data_br($grade['assessment_date']),
                'Disciplina'    => $grade['subject_name'],
                'Avaliação'     => $grade['assessment_name'],
                'Tipo'          => rotulo('tipo_avaliacao', $grade['type']),
                'Nota'          => (float) $grade['score'],
                'Valor máximo'  => (float) $grade['max_score'],
                'Aproveitamento'=> (float) $grade['percentage'],
                'Acertos'       => (int) $grade['correct_count'],
                'Erros'         => (int) $grade['wrong_count'],
                'Em branco'     => (int) $grade['blank_count'],
            ];
        }

        return [
            'titulo'  => 'Relatório individual — ' . $student['full_name'],
            'colunas' => ['Data', 'Disciplina', 'Avaliação', 'Tipo', 'Nota', 'Valor máximo', 'Aproveitamento', 'Acertos', 'Erros', 'Em branco'],
            'linhas'  => $linhas,
            'resumo'  => [
                'Turma'                    => $student['class_code'] ?? 'Sem turma',
                'Avaliações realizadas'    => $summary['avaliacoes'],
                'Média geral'              => $summary['media'] !== null ? pct($summary['media']) : '—',
                'Percentual de acertos'    => $summary['acertos']['pct_acertos'] !== null ? pct($summary['acertos']['pct_acertos']) : '—',
                'Frequência'               => $summary['frequencia'] !== null ? pct($summary['frequencia']) : '—',
                'Evolução recente'         => $summary['evolucao_recente'] !== null ? num($summary['evolucao_recente']) . ' p.p.' : '—',
                'Índice de Desenvolvimento'=> $summary['indice'] !== null ? num($summary['indice']) : '—',
                'Classificação'            => rotulo('classificacao', $summary['classificacao']),
                'Conteúdos dominados'      => $summary['dominados'],
                'Conteúdos com dificuldade'=> $summary['dificuldades'],
            ],
            'detalhe' => $summary,
        ];
    }

    // ---------------------------------------------------------------- turma
    private static function classReport(array $filters): array
    {
        $ranking = RankingService::build($filters);
        $linhas  = [];
        foreach ($ranking as $row) {
            $linhas[] = [
                'Posição'        => $row['posicao'] ?? '—',
                'Aluno'          => $row['full_name'],
                'Turma'          => $row['class_code'] ?? '—',
                'Avaliações'     => $row['avaliacoes'],
                'Média'          => $row['media'],
                '% acertos'      => $row['acertos']['pct_acertos'],
                'Frequência'     => $row['frequencia'],
                'Evolução (p.p.)'=> $row['evolucao_recente'],
                'Índice'         => $row['indice'],
                'Classificação'  => rotulo('classificacao', $row['classificacao']),
            ];
        }
        return [
            'titulo'  => 'Relatório de desempenho da turma',
            'colunas' => ['Posição', 'Aluno', 'Turma', 'Avaliações', 'Média', '% acertos', 'Frequência', 'Evolução (p.p.)', 'Índice', 'Classificação'],
            'linhas'  => $linhas,
            'resumo'  => self::classSummaryBox($filters, $ranking),
        ];
    }

    private static function classSummaryBox(array $filters, array $ranking): array
    {
        $counts = RankingService::summarize($ranking);
        return [
            'Alunos no recorte'    => count($ranking),
            'Média geral'          => ($m = AnalyticsService::overallAverage($filters)) !== null ? pct($m) : '—',
            'Em evolução'          => $counts['evolucao'],
            'Intermediários'       => $counts['intermediario'],
            'Precisam de atenção'  => $counts['atencao'],
            'Sem dados suficientes'=> $counts['sem_dados'],
        ];
    }

    // ----------------------------------------------------------- disciplina
    private static function subject(array $filters): array
    {
        $rows   = AnalyticsService::subjectAverages($filters);
        $linhas = [];
        foreach ($rows as $row) {
            $subjectFilters = array_merge($filters, ['disciplina' => (int) $row['subject_id']]);
            $totals = AnalyticsService::answerTotals($subjectFilters);
            $linhas[] = [
                'Disciplina'  => $row['subject_name'],
                'Alunos'      => (int) $row['alunos'],
                'Avaliações'  => (int) $row['avaliacoes'],
                'Lançamentos' => (int) $row['lancamentos'],
                'Média'       => $row['media'] !== null ? round((float) $row['media'], 2) : null,
                '% acertos'   => $totals['pct_acertos'],
                '% erros'     => $totals['pct_erros'],
            ];
        }
        return [
            'titulo'  => 'Relatório por disciplina',
            'colunas' => ['Disciplina', 'Alunos', 'Avaliações', 'Lançamentos', 'Média', '% acertos', '% erros'],
            'linhas'  => $linhas,
        ];
    }

    // ------------------------------------------------------------ avaliação
    private static function assessment(array $filters): array
    {
        $rows   = AnalyticsService::assessmentAverages($filters, 200);
        $linhas = [];
        foreach ($rows as $row) {
            $linhas[] = [
                'Data'       => data_br($row['assessment_date']),
                'Avaliação'  => $row['assessment_name'],
                'Turma'      => $row['class_code'],
                'Disciplina' => $row['subject_name'],
                'Tipo'       => rotulo('tipo_avaliacao', $row['type']),
                'Alunos'     => (int) $row['alunos'],
                'Média'      => $row['media'] !== null ? round((float) $row['media'], 2) : null,
                'Menor'      => $row['minima'] !== null ? round((float) $row['minima'], 2) : null,
                'Maior'      => $row['maxima'] !== null ? round((float) $row['maxima'], 2) : null,
            ];
        }
        return [
            'titulo'  => 'Relatório por avaliação',
            'colunas' => ['Data', 'Avaliação', 'Turma', 'Disciplina', 'Tipo', 'Alunos', 'Média', 'Menor', 'Maior'],
            'linhas'  => $linhas,
        ];
    }

    // -------------------------------------------------------------- assunto
    private static function topic(array $filters): array
    {
        $rows   = AnalyticsService::topicPerformance($filters, 'desc');
        $linhas = [];
        foreach ($rows as $row) {
            $linhas[] = [
                'Assunto'        => $row['topic_name'],
                'Disciplina'     => $row['subject_name'],
                'Alunos'         => $row['alunos'],
                'Questões resp.' => $row['respondidas'],
                'Acertos'        => $row['acertos'],
                'Erros'          => $row['erros'],
                'Aproveitamento' => $row['aproveitamento'],
                'Situação'       => self::masteryLabel($row['classificacao']),
            ];
        }
        return [
            'titulo'  => 'Relatório por assunto',
            'colunas' => ['Assunto', 'Disciplina', 'Alunos', 'Questões resp.', 'Acertos', 'Erros', 'Aproveitamento', 'Situação'],
            'linhas'  => $linhas,
        ];
    }

    // ---------------------------------------------------------- dificuldades
    private static function difficulties(array $filters): array
    {
        $limite = Setting::float('limite_dificuldade');
        $rows   = AnalyticsService::topicPerformance($filters, 'asc');
        $linhas = [];
        foreach ($rows as $row) {
            if ($row['aproveitamento'] === null || $row['aproveitamento'] >= $limite) {
                continue;
            }
            $linhas[] = [
                'Assunto'        => $row['topic_name'],
                'Disciplina'     => $row['subject_name'],
                'Aproveitamento' => $row['aproveitamento'],
                'Alunos'         => $row['alunos'],
                'Questões resp.' => $row['respondidas'],
                'Erros'          => $row['erros'],
                'Amostra'        => $row['amostra_suficiente'] ? 'Suficiente' : 'Insuficiente',
            ];
        }
        return [
            'titulo'  => 'Relatório de dificuldades (aproveitamento abaixo de ' . num($limite, 0) . '%)',
            'colunas' => ['Assunto', 'Disciplina', 'Aproveitamento', 'Alunos', 'Questões resp.', 'Erros', 'Amostra'],
            'linhas'  => $linhas,
            'aviso'   => $linhas === [] ? 'Nenhum assunto ficou abaixo do limite configurado neste recorte.' : null,
        ];
    }

    // ------------------------------------------------------------- evolução
    private static function evolution(array $filters): array
    {
        $ranking = RankingService::build($filters);
        $linhas  = [];
        foreach ($ranking as $row) {
            if ($row['avaliacoes'] === 0) {
                continue;
            }
            $linhas[] = [
                'Aluno'            => $row['full_name'],
                'Turma'            => $row['class_code'] ?? '—',
                'Avaliações'       => $row['avaliacoes'],
                'Primeira nota'    => $row['percentuais'] ? round($row['percentuais'][0], 2) : null,
                'Última nota'      => $row['percentuais'] ? round(end($row['percentuais']), 2) : null,
                'Tendência (p.p./aval.)' => $row['evolucao_slope'],
                'Evolução recente' => $row['evolucao_recente'],
                'Consistência'     => $row['score_consistencia'],
                'Classificação'    => rotulo('classificacao', $row['classificacao']),
            ];
        }
        usort($linhas, static fn ($a, $b) => ($b['Evolução recente'] ?? -999) <=> ($a['Evolução recente'] ?? -999));
        return [
            'titulo'  => 'Relatório de evolução',
            'colunas' => ['Aluno', 'Turma', 'Avaliações', 'Primeira nota', 'Última nota', 'Tendência (p.p./aval.)', 'Evolução recente', 'Consistência', 'Classificação'],
            'linhas'  => $linhas,
        ];
    }

    // ----------------------------------------------------------- frequência
    private static function attendance(array $filters): array
    {
        $where  = ["s.status <> 'inativo'"];
        $params = [];
        if (!empty($filters['turma'])) { $where[] = 'c.id = :turma'; $params['turma'] = (int) $filters['turma']; }
        if (!empty($filters['curso'])) { $where[] = 'c.course_id = :curso'; $params['curso'] = (int) $filters['curso']; }
        if (!empty($filters['aluno'])) { $where[] = 's.id = :aluno'; $params['aluno'] = (int) $filters['aluno']; }

        $students = Database::all(
            'SELECT s.id, s.full_name, c.code AS class_code
               FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.full_name',
            $params
        );

        $minima = Setting::float('frequencia_minima');
        $linhas = [];
        foreach ($students as $student) {
            $att = AnalyticsService::studentAttendance((int) $student['id'], $filters);
            $linhas[] = [
                'Aluno'        => $student['full_name'],
                'Turma'        => $student['class_code'] ?? '—',
                'Aulas'        => $att['aulas'],
                'Presenças'    => $att['presentes'],
                'Atrasos'      => $att['atrasos'],
                'Faltas'       => $att['faltas'],
                'Justificadas' => $att['justificadas'],
                'Frequência'   => $att['frequencia'],
                'Participação' => $att['participacao'],
                'Situação'     => $att['frequencia'] === null ? 'Sem aulas' : ($att['frequencia'] < $minima ? 'Abaixo do mínimo' : 'Regular'),
            ];
        }
        return [
            'titulo'  => 'Relatório de frequência',
            'colunas' => ['Aluno', 'Turma', 'Aulas', 'Presenças', 'Atrasos', 'Faltas', 'Justificadas', 'Frequência', 'Participação', 'Situação'],
            'linhas'  => $linhas,
        ];
    }

    private static function masteryLabel(string $classification): string
    {
        return match ($classification) {
            'dominio'       => 'Domínio',
            'intermediario' => 'Intermediário',
            'dificuldade'   => 'Dificuldade',
            default         => 'Amostra insuficiente',
        };
    }

    private static function empty(string $titulo, string $aviso): array
    {
        return ['titulo' => $titulo, 'colunas' => [], 'linhas' => [], 'aviso' => $aviso];
    }
}
