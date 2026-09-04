<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Scope;
use App\Models\Assessment;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Question;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\ReportService;

class ReportController extends Controller
{
    public function index(): void
    {
        $tipo    = (string) $this->request->query('relatorio', 'turma');
        $filters = $this->scopedFilters();
        $relatorio = ReportService::build($tipo, $filters);

        $this->view('reports/index', array_merge($this->formData($tipo, $filters), [
            'title'     => 'Relatórios',
            'relatorio' => $relatorio,
        ]));
    }

    /** Versão para impressão — o navegador gera o PDF. */
    public function print(): void
    {
        $tipo    = (string) $this->request->query('relatorio', 'turma');
        $filters = $this->scopedFilters();

        $this->view('reports/print', [
            'title'     => 'Relatório',
            'relatorio' => ReportService::build($tipo, $filters),
            'filtros_descricao' => $this->describeFilters($filters),
        ], 'layouts/print');
    }

    public function export(): void
    {
        $tipo    = (string) $this->request->query('relatorio', 'turma');
        $filters = $this->scopedFilters();
        $relatorio = ReportService::build($tipo, $filters);

        $rows = [];
        foreach ($relatorio['linhas'] as $linha) {
            $rows[] = array_map(
                static fn ($v) => is_float($v) ? round($v, 2) : $v,
                array_values($linha)
            );
        }

        $this->csv('relatorio-' . $tipo . '-' . date('Y-m-d'), $relatorio['colunas'], $rows);
    }

    private function formData(string $tipo, array $filters): array
    {
        return [
            'tipo'        => $tipo,
            'tipos'       => ReportService::TYPES,
            'filters'     => $filters,
            'cursos'      => Course::options(),
            'turmas'      => $this->turmasVisiveis(),
            'disciplinas' => $this->disciplinasVisiveis(),
            'alunos'      => Scope::students(),
            'avaliacoes'  => Assessment::search([], 100),
            'assuntos'    => !empty($filters['disciplina']) ? Topic::optionsBySubject((int) $filters['disciplina']) : [],
            'tipos_aval'  => Assessment::TYPES,
            'dificuldades'=> Question::DIFFICULTIES,
            'filtros_descricao' => $this->describeFilters($filters),
        ];
    }

    private function turmasVisiveis(): array
    {
        $ids = Scope::classIds();
        return $ids === null ? ClassGroup::options()
            : array_values(array_filter(ClassGroup::options(), static fn ($t) => in_array((int) $t['id'], $ids, true)));
    }

    private function disciplinasVisiveis(): array
    {
        $ids = Scope::subjectIds();
        return $ids === null ? Subject::options()
            : array_values(array_filter(Subject::options(), static fn ($d) => in_array((int) $d['id'], $ids, true)));
    }

    /** Descreve o recorte em texto — vai no cabeçalho do relatório impresso. */
    private function describeFilters(array $filters): string
    {
        $partes = [];
        if (!empty($filters['curso']) && ($c = \App\Models\Course::find((int) $filters['curso']))) {
            $partes[] = 'Curso: ' . $c['name'];
        }
        if (!empty($filters['turma']) && ($t = ClassGroup::find((int) $filters['turma']))) {
            $partes[] = 'Turma: ' . $t['code'];
        }
        if (!empty($filters['disciplina']) && ($d = Subject::find((int) $filters['disciplina']))) {
            $partes[] = 'Disciplina: ' . $d['name'];
        }
        if (!empty($filters['aluno']) && ($a = Student::find((int) $filters['aluno']))) {
            $partes[] = 'Aluno: ' . $a['full_name'];
        }
        if (!empty($filters['tipo'])) {
            $partes[] = 'Tipo: ' . rotulo('tipo_avaliacao', $filters['tipo']);
        }
        if (!empty($filters['dificuldade'])) {
            $partes[] = 'Dificuldade: ' . rotulo('dificuldade', $filters['dificuldade']);
        }
        if (!empty($filters['inicio']) || !empty($filters['fim'])) {
            $partes[] = 'Período: ' . data_br($filters['inicio'] ?? null, 'início') . ' a ' . data_br($filters['fim'] ?? null, 'hoje');
        }
        return $partes === [] ? 'Todos os registros' : implode(' · ', $partes);
    }
}
