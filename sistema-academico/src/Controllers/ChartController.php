<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Assessment;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AnalyticsService;
use App\Services\RankingService;

class ChartController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters();
        $ranking = RankingService::build($filters);

        $this->view('charts/index', [
            'title'         => 'Gráficos',
            'filters'       => $filters,
            'cursos'        => Course::options(),
            'turmas'        => ClassGroup::options(),
            'disciplinas'   => Subject::options(),
            'serie'         => AnalyticsService::assessmentAverages($filters),
            'por_disciplina'=> AnalyticsService::subjectAverages($filters),
            'por_turma'     => AnalyticsService::classAverages($filters),
            'assuntos'      => AnalyticsService::topicPerformance($filters, 'desc'),
            'dificuldade'   => AnalyticsService::difficultyPerformance($filters),
            'distribuicao'  => AnalyticsService::performanceDistribution($filters),
            'frequencia'    => AnalyticsService::attendanceByClass($filters),
            'movers'        => RankingService::movers($ranking, 8),
        ]);
    }

    /**
     * Comparações: aluno×aluno, aluno×turma, aluno×disciplina, turma×turma,
     * disciplina×disciplina e avaliação×avaliação.
     */
    public function compare(): void
    {
        $modo = (string) $this->request->query('modo', 'aluno_aluno');
        $a    = (int) $this->request->query('a', 0);
        $b    = (int) $this->request->query('b', 0);
        $filters = $this->filters(['disciplina', 'turma', 'inicio', 'fim']);

        $resultado = ['modo' => $modo, 'series' => [], 'aviso' => null];

        switch ($modo) {
            case 'aluno_aluno':
                if ($a > 0 && $b > 0) {
                    $resultado['series'] = [
                        self::studentSeries($a, $filters),
                        self::studentSeries($b, $filters),
                    ];
                } else {
                    $resultado['aviso'] = 'Selecione dois alunos para comparar.';
                }
                break;

            case 'aluno_turma':
                if ($a > 0) {
                    $aluno = Student::find($a);
                    $classId = $b > 0 ? $b : (int) ($aluno['class_id'] ?? 0);
                    if ($classId > 0) {
                        $resultado['series'] = [
                            self::studentSeries($a, $filters),
                            self::classSeries($classId, $filters),
                        ];
                    } else {
                        $resultado['aviso'] = 'O aluno selecionado não está vinculado a uma turma.';
                    }
                } else {
                    $resultado['aviso'] = 'Selecione um aluno.';
                }
                break;

            case 'aluno_disciplina':
                if ($a > 0 && $b > 0) {
                    $resultado['series'] = [
                        self::studentSeries($a, array_merge($filters, ['disciplina' => $b])),
                        self::subjectSeries($b, $filters),
                    ];
                } else {
                    $resultado['aviso'] = 'Selecione um aluno e uma disciplina.';
                }
                break;

            case 'turma_turma':
                if ($a > 0 && $b > 0) {
                    $resultado['series'] = [self::classSeries($a, $filters), self::classSeries($b, $filters)];
                } else {
                    $resultado['aviso'] = 'Selecione duas turmas.';
                }
                break;

            case 'disciplina_disciplina':
                if ($a > 0 && $b > 0) {
                    $resultado['series'] = [self::subjectSeries($a, $filters), self::subjectSeries($b, $filters)];
                } else {
                    $resultado['aviso'] = 'Selecione duas disciplinas.';
                }
                break;

            case 'avaliacao_avaliacao':
                if ($a > 0 && $b > 0) {
                    $resultado['series'] = [self::assessmentSnapshot($a), self::assessmentSnapshot($b)];
                    $resultado['formato'] = 'barras';
                } else {
                    $resultado['aviso'] = 'Selecione duas avaliações.';
                }
                break;
        }

        $this->view('charts/compare', [
            'title'       => 'Comparação de desempenho',
            'modo'        => $modo,
            'a'           => $a,
            'b'           => $b,
            'filters'     => $filters,
            'resultado'   => $resultado,
            'alunos'      => Student::search([]),
            'turmas'      => ClassGroup::options(),
            'disciplinas' => Subject::options(),
            'avaliacoes'  => Assessment::search([], 100),
        ]);
    }

    private static function studentSeries(int $studentId, array $filters): array
    {
        $aluno  = Student::find($studentId);
        $resumo = AnalyticsService::studentSummary($studentId, $filters);
        return [
            'nome'   => $aluno['full_name'] ?? 'Aluno',
            'labels' => array_map(static fn ($g) => $g['assessment_name'], $resumo['notas']),
            'dados'  => $resumo['percentuais'],
            'media'  => $resumo['media'],
            'extra'  => [
                'Avaliações' => $resumo['avaliacoes'],
                'Frequência' => $resumo['frequencia'] !== null ? pct($resumo['frequencia']) : '—',
                'Evolução'   => $resumo['evolucao_recente'] !== null ? num($resumo['evolucao_recente']) . ' p.p.' : '—',
                'Índice'     => $resumo['indice'] !== null ? num($resumo['indice']) : '—',
            ],
        ];
    }

    private static function classSeries(int $classId, array $filters): array
    {
        $turma = ClassGroup::find($classId);
        $rows  = AnalyticsService::assessmentAverages(array_merge($filters, ['turma' => $classId]));
        return [
            'nome'   => 'Turma ' . ($turma['code'] ?? $classId),
            'labels' => array_column($rows, 'assessment_name'),
            'dados'  => array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $rows),
            'media'  => AnalyticsService::overallAverage(array_merge($filters, ['turma' => $classId])),
            'extra'  => ['Avaliações' => count($rows)],
        ];
    }

    private static function subjectSeries(int $subjectId, array $filters): array
    {
        $disciplina = Subject::find($subjectId);
        $rows = AnalyticsService::assessmentAverages(array_merge($filters, ['disciplina' => $subjectId]));
        return [
            'nome'   => $disciplina['name'] ?? 'Disciplina',
            'labels' => array_column($rows, 'assessment_name'),
            'dados'  => array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $rows),
            'media'  => AnalyticsService::overallAverage(array_merge($filters, ['disciplina' => $subjectId])),
            'extra'  => ['Avaliações' => count($rows)],
        ];
    }

    private static function assessmentSnapshot(int $assessmentId): array
    {
        $avaliacao = Assessment::find($assessmentId);
        $totais    = AnalyticsService::answerTotals(['avaliacao' => $assessmentId]);
        $media     = AnalyticsService::overallAverage(['avaliacao' => $assessmentId]);
        $questoes  = AnalyticsService::assessmentQuestionAnalysis($assessmentId);

        return [
            'nome'   => $avaliacao['name'] ?? 'Avaliação',
            'labels' => array_map(static fn ($q) => 'Q' . ($q['number'] ?? $q['id']), $questoes),
            'dados'  => array_map(static fn ($q) => $q['indice_acerto'], $questoes),
            'media'  => $media,
            'extra'  => [
                'Data'        => data_br($avaliacao['assessment_date'] ?? null),
                'Turma'       => $avaliacao['class_code'] ?? '—',
                '% acertos'   => $totais['pct_acertos'] !== null ? pct($totais['pct_acertos']) : '—',
                'Questões'    => count($questoes),
            ],
        ];
    }
}
