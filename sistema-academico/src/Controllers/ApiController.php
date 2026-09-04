<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ClassSubject;
use App\Models\Topic;
use App\Services\AnalyticsService;
use App\Services\RankingService;

/** Endpoints JSON que alimentam os gráficos e os selects dependentes. */
class ApiController extends Controller
{
    public function series(string $tipo): void
    {
        $filters = $this->filters();

        $data = match ($tipo) {
            'evolucao-turma' => self::labelled(
                AnalyticsService::assessmentAverages($filters),
                'assessment_name', 'media'
            ),
            'media-disciplina' => self::labelled(
                AnalyticsService::subjectAverages($filters),
                'subject_name', 'media'
            ),
            'media-turma' => self::labelled(
                AnalyticsService::classAverages($filters),
                'class_code', 'media'
            ),
            'assuntos' => self::labelled(
                array_slice(AnalyticsService::topicPerformance($filters, 'desc'), 0, 15),
                'topic_name', 'aproveitamento'
            ),
            'dificuldade' => self::labelled(
                AnalyticsService::difficultyPerformance($filters),
                'dificuldade', 'aproveitamento'
            ),
            'distribuicao' => (function () use ($filters) {
                $buckets = AnalyticsService::performanceDistribution($filters);
                return ['labels' => array_keys($buckets), 'valores' => array_values($buckets)];
            })(),
            'frequencia' => self::labelled(
                AnalyticsService::attendanceByClass($filters),
                'class_code', 'frequencia'
            ),
            'evolucao-alunos' => (function () use ($filters) {
                $movers = RankingService::movers(RankingService::build($filters), 8);
                $rows = array_merge($movers['subiram'], array_reverse($movers['cairam']));
                return [
                    'labels'  => array_column($rows, 'full_name'),
                    'valores' => array_map(static fn ($r) => $r['evolucao_recente'], $rows),
                ];
            })(),
            default => ['labels' => [], 'valores' => []],
        };

        $this->json($data);
    }

    private static function labelled(array $rows, string $labelKey, string $valueKey): array
    {
        return [
            'labels'  => array_map(static fn ($r) => (string) ($r[$labelKey] ?? ''), $rows),
            'valores' => array_map(static fn ($r) => $r[$valueKey] === null ? null : round((float) $r[$valueKey], 2), $rows),
        ];
    }

    /** Assuntos/tópicos de uma disciplina (selects dependentes). */
    public function topicsBySubject(string $id): void
    {
        $this->json(Topic::optionsBySubject((int) $id));
    }

    /** Ofertas (turma × disciplina) filtráveis por turma. */
    public function classSubjects(): void
    {
        $this->json(ClassSubject::options($this->filters(['turma', 'disciplina'])));
    }
}
