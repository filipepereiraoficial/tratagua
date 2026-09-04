<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Scope;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\ClassGroup;
use App\Models\Intervention;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AlertService;
use App\Services\AnalyticsService;
use App\Services\RankingService;

/**
 * Painel do professor — recortado nas ofertas sob sua responsabilidade.
 *
 * Responde as três perguntas que o professor faz sobre a própria disciplina:
 * quem está evoluindo, quem está ficando para trás, e onde a turma mais perde
 * pontuação. O administrador também acessa, e nesse caso vê tudo.
 */
class TeacherPanelController extends Controller
{
    public function index(): void
    {
        $filters = $this->scopedFilters(['turma', 'disciplina', 'tipo', 'inicio', 'fim']);
        $ofertas = Scope::offerings();

        $ranking = RankingService::build($filters);
        $movers  = RankingService::movers($ranking, 5);
        $atencao = RankingService::filterByClassification($ranking, 'atencao');

        $this->view('teacher/panel', [
            'title'          => 'Meu painel',
            'filters'        => $filters,
            'ofertas'        => $ofertas,
            'turmas'         => $this->turmasDoEscopo(),
            'disciplinas'    => $this->disciplinasDoEscopo(),
            'ensino'         => AnalyticsService::teachingCounters($filters),
            'media'          => AnalyticsService::overallAverage($filters),
            'acertos'        => AnalyticsService::answerTotals($filters),
            'ranking'        => $ranking,
            'classes'        => RankingService::summarize($ranking),
            'evoluindo'      => $movers['subiram'],
            'caindo'         => $movers['cairam'],
            'atencao'        => $atencao,
            'perda_avaliacao'=> AnalyticsService::assessmentPointLoss($filters, 8),
            'perda_aluno'    => array_slice(AnalyticsService::studentPointLoss($filters), 0, 8),
            'assuntos'       => AnalyticsService::topicPerformance($filters),
            'serie'          => AnalyticsService::assessmentAverages($filters),
            'distribuicao'   => AnalyticsService::performanceDistribution($filters),
            'alertas'        => AlertService::generate($filters, $ranking),
            'acompanhamentos'=> Intervention::search(array_merge($filters, ['abertas' => 1]), 8),
            'proximas_aulas' => Lesson::search($this->paraListas($filters), 5),
            'avaliacoes'     => Assessment::search($this->paraListas($filters), 5),
            'faixas'         => $this->faixas(),
            'pesos'          => RankingService::weightsLabel(),
        ]);
    }

    /** Painel individual de um aluno, recortado na disciplina do professor. */
    public function student(string $id): void
    {
        $studentId = (int) $id;
        $this->denyUnless(Scope::canAccessStudent($studentId),
            'Este aluno não está em nenhuma turma sob sua responsabilidade.');

        $aluno = Student::find($studentId);
        if (!$aluno) {
            $this->notFound('Aluno não encontrado.');
        }

        $filters = $this->scopedFilters(['disciplina', 'tipo', 'inicio', 'fim']);
        $filters['aluno'] = $studentId;
        $resumo = AnalyticsService::studentSummary($studentId, $filters);

        // Referência da turma dentro do mesmo escopo, para o "acima/abaixo da média".
        $daTurma = $filters;
        unset($daTurma['aluno']);
        if ($aluno['class_id']) {
            $daTurma['turma'] = (int) $aluno['class_id'];
        }

        $this->view('teacher/student', [
            'title'         => $aluno['full_name'],
            'aluno'         => $aluno,
            'resumo'        => $resumo,
            'filters'       => $filters,
            'disciplinas'   => $this->disciplinasDoEscopo(),
            'media_turma'   => AnalyticsService::overallAverage($daTurma),
            'serie_turma'   => AnalyticsService::assessmentAverages($daTurma),
            'por_disciplina'=> AnalyticsService::studentSubjectPerformance($studentId, $filters),
            'por_dificuldade'=> AnalyticsService::difficultyPerformance($filters),
            'perda'         => AnalyticsService::studentPointLoss($filters),
            'posicao'       => $aluno['class_id']
                ? RankingService::positionInClass($studentId, (int) $aluno['class_id'], $filters)
                : ['posicao' => null, 'total' => 0, 'indice' => null],
            'alertas'       => AlertService::generate($filters),
            'acompanhamentos' => Intervention::forStudent($studentId),
            'presencas'     => Attendance::historyForStudent($studentId, $filters),
            'ofertas'       => Scope::offerings(),
            'faixas'        => $this->faixas(),
        ]);
    }

    /** Lista das ofertas do professor, com atalhos para aula, avaliação e chamada. */
    public function offerings(): void
    {
        $ofertas = Scope::offerings();
        $desempenho = [];
        foreach ($ofertas as $oferta) {
            $filtro = ['ofertas' => [(int) $oferta['id']]];
            $desempenho[(int) $oferta['id']] = [
                'media'   => AnalyticsService::overallAverage($filtro),
                'acertos' => AnalyticsService::answerTotals($filtro)['pct_acertos'],
            ];
        }

        $this->view('teacher/offerings', [
            'title'      => 'Minhas turmas e disciplinas',
            'ofertas'    => $ofertas,
            'desempenho' => $desempenho,
            'faixas'     => $this->faixas(),
        ]);
    }

    /** Turmas visíveis ao perfil, para os selects de filtro. */
    private function turmasDoEscopo(): array
    {
        $ids = Scope::classIds();
        if ($ids === null) {
            return ClassGroup::options();
        }
        return array_values(array_filter(ClassGroup::options(),
            static fn ($t) => in_array((int) $t['id'], $ids, true)));
    }

    private function disciplinasDoEscopo(): array
    {
        $ids = Scope::subjectIds();
        if ($ids === null) {
            return Subject::options();
        }
        return array_values(array_filter(Subject::options(),
            static fn ($d) => in_array((int) $d['id'], $ids, true)));
    }

    /** Aulas e avaliações usam `turma`/`disciplina`, não a lista de ofertas. */
    private function paraListas(array $filters): array
    {
        $lista = array_intersect_key($filters, array_flip(['turma', 'disciplina', 'inicio', 'fim']));
        $ids = Scope::classSubjectIds();
        if ($ids !== null && empty($lista['turma']) && empty($lista['disciplina'])) {
            $lista['ofertas'] = $ids;
        }
        return $lista;
    }

    private function faixas(): array
    {
        return [
            'dominio'       => Setting::float('faixa_dominio'),
            'intermediario' => Setting::float('faixa_intermediario'),
        ];
    }
}
