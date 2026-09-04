<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Intervention;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\AlertService;
use App\Services\AnalyticsService;
use App\Services\RankingService;

/**
 * Painel do administrador: a instituição inteira ou um curso por vez.
 *
 * O dashboard geral responde "como está indo"; este responde "onde está o
 * problema" — compara cursos, turmas e professores entre si e mostra onde a
 * operação está travada (turma sem professor, disciplina sem conteúdo,
 * avaliação sem resultado).
 */
class AdminPanelController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters(['curso', 'ano', 'inicio', 'fim']);
        $cursoId = isset($filters['curso']) ? (int) $filters['curso'] : null;
        $recorte = $cursoId ? ['curso' => $cursoId] : [];
        if (!empty($filters['inicio'])) { $recorte['inicio'] = $filters['inicio']; }
        if (!empty($filters['fim']))    { $recorte['fim'] = $filters['fim']; }

        $ranking = RankingService::build($recorte);

        $this->view('admin/panel', [
            'title'        => 'Painel do administrador',
            'filters'      => $filters,
            'curso'        => $cursoId ? Course::find($cursoId) : null,
            'cursos'       => Course::options(),
            'anos'         => ClassGroup::years(),
            'por_curso'    => $this->porCurso($recorte),
            'por_turma'    => AnalyticsService::classAverages($recorte),
            'por_disciplina' => AnalyticsService::subjectAverages($recorte),
            'por_professor'=> $this->porProfessor($recorte),
            'ranking'      => $ranking,
            'classes'      => RankingService::summarize($ranking),
            'media'        => AnalyticsService::overallAverage($recorte),
            'acertos'      => AnalyticsService::answerTotals($recorte),
            'contadores'   => AnalyticsService::globalCounters([]),
            'assuntos'     => AnalyticsService::topicPerformance($recorte),
            'alertas'      => AlertService::generate($recorte, $ranking),
            'acompanhamentos' => Intervention::countByStatus([]),
            'pendencias'   => $this->pendencias(),
            'atividade'    => ActivityLog::search([], 8),
            'faixas'       => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    /** Um retrato por curso: turmas, alunos, média e distribuição. */
    private function porCurso(array $recorte): array
    {
        $linhas = [];
        foreach (Course::all() as $curso) {
            $filtro = array_merge($recorte, ['curso' => (int) $curso['id']]);
            $ranking = RankingService::build($filtro);
            $classes = RankingService::summarize($ranking);
            $linhas[] = [
                'id'        => (int) $curso['id'],
                'nome'      => $curso['name'],
                'turmas'    => (int) $curso['classes_count'],
                'alunos'    => count($ranking),
                'media'     => AnalyticsService::overallAverage($filtro),
                'acertos'   => AnalyticsService::answerTotals($filtro)['pct_acertos'],
                'evolucao'  => $classes['evolucao'],
                'atencao'   => $classes['atencao'],
            ];
        }
        usort($linhas, static fn ($a, $b) => ($b['media'] ?? -1) <=> ($a['media'] ?? -1));
        return $linhas;
    }

    /** Desempenho médio das turmas de cada professor. */
    private function porProfessor(array $recorte): array
    {
        $linhas = [];
        foreach (Teacher::search(['ativo' => 1]) as $professor) {
            $ofertas = array_map(static fn ($o) => (int) $o['id'], Teacher::offerings((int) $professor['id']));
            if ($ofertas === []) {
                continue;
            }
            $filtro = array_merge($recorte, ['ofertas' => $ofertas]);
            $ranking = RankingService::build($filtro);
            $classes = RankingService::summarize($ranking);
            $linhas[] = [
                'id'          => (int) $professor['id'],
                'nome'        => $professor['name'],
                'ofertas'     => count($ofertas),
                'alunos'      => count($ranking),
                'aulas'       => (int) $professor['aulas'],
                'avaliacoes'  => (int) $professor['avaliacoes'],
                'media'       => AnalyticsService::overallAverage($filtro),
                'atencao'     => $classes['atencao'],
            ];
        }
        usort($linhas, static fn ($a, $b) => ($b['media'] ?? -1) <=> ($a['media'] ?? -1));
        return $linhas;
    }

    /**
     * Pendências operacionais: o que está impedindo o sistema de produzir
     * análise. Cada linha é acionável, com o link para resolver.
     */
    private function pendencias(): array
    {
        $itens = [];

        $semProfessor = Database::all(
            'SELECT cs.id, c.code, c.id AS class_id, s.name AS subject_name
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
              WHERE cs.teacher_user_id IS NULL AND s.teacher_user_id IS NULL'
        );
        foreach ($semProfessor as $linha) {
            $itens[] = ['tipo' => 'Sem professor',
                'texto' => "{$linha['code']} — {$linha['subject_name']} não tem professor responsável.",
                'link' => '/turmas/' . $linha['class_id']];
        }

        $semTurma = (int) Database::value(
            'SELECT COUNT(*) FROM students s
              LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
             WHERE e.id IS NULL AND s.status = \'ativo\'', [], 0);
        if ($semTurma > 0) {
            $itens[] = ['tipo' => 'Aluno sem turma',
                'texto' => "{$semTurma} aluno(s) ativo(s) sem vínculo com turma.",
                'link' => '/alunos?sem_turma=1'];
        }

        $semConteudo = Database::all(
            'SELECT s.id, s.name FROM subjects s
              WHERE NOT EXISTS (SELECT 1 FROM topics t WHERE t.subject_id = s.id)'
        );
        foreach ($semConteudo as $linha) {
            $itens[] = ['tipo' => 'Disciplina sem conteúdo',
                'texto' => "{$linha['name']} não tem assuntos cadastrados — sem eles não há análise por conteúdo.",
                'link' => '/disciplinas/' . $linha['id']];
        }

        $semResultado = Database::all(
            "SELECT a.id, a.name, c.code
               FROM assessments a
               JOIN class_subjects cs ON cs.id = a.class_subject_id
               JOIN classes c ON c.id = cs.class_id
              WHERE a.status <> 'planejada'
                AND NOT EXISTS (SELECT 1 FROM grades g WHERE g.assessment_id = a.id)"
        );
        foreach ($semResultado as $linha) {
            $itens[] = ['tipo' => 'Avaliação sem resultado',
                'texto' => "{$linha['name']} ({$linha['code']}) foi aplicada mas não tem notas lançadas.",
                'link' => '/avaliacoes/' . $linha['id'] . '/resultados'];
        }

        $semChamada = (int) Database::value(
            'SELECT COUNT(*) FROM lessons l
              WHERE NOT EXISTS (SELECT 1 FROM attendances a WHERE a.lesson_id = l.id)', [], 0);
        if ($semChamada > 0) {
            $itens[] = ['tipo' => 'Aula sem chamada',
                'texto' => "{$semChamada} aula(s) sem registro de frequência.",
                'link' => '/aulas'];
        }

        return $itens;
    }
}
