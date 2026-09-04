<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AlertService;
use App\Services\AnalyticsService;
use App\Services\RankingService;

class ClassController extends Controller
{
    public function index(): void
    {
        $filters = [
            'curso'  => $this->request->query('curso'),
            'ano'    => $this->request->query('ano'),
            'status' => $this->request->query('status'),
            'busca'  => $this->request->query('busca'),
        ];
        $this->view('classes/index', [
            'title'   => 'Turmas',
            'turmas'  => ClassGroup::search($filters),
            'filters' => $filters,
            'cursos'  => Course::options(),
            'anos'    => ClassGroup::years(),
        ]);
    }

    public function create(): void
    {
        $this->view('classes/form', [
            'title'  => 'Nova turma',
            'turma'  => null,
            'cursos' => Course::options(),
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateClass();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/turmas/nova');
        }
        $id = ClassGroup::create($this->request->post);
        Flash::success('Turma criada. Vincule as disciplinas e os alunos abaixo.');
        $this->redirect('/turmas/' . $id);
    }

    public function edit(string $id): void
    {
        $turma = ClassGroup::find((int) $id);
        if (!$turma) {
            $this->notFound('Turma não encontrada.');
        }
        $this->view('classes/form', [
            'title'  => 'Editar turma',
            'turma'  => $turma,
            'cursos' => Course::options(),
        ]);
    }

    public function update(string $id): void
    {
        $classId = (int) $id;
        if (!ClassGroup::find($classId)) {
            $this->notFound('Turma não encontrada.');
        }
        $validator = $this->validateClass($classId);
        if ($validator->fails()) {
            $this->rejectWith($validator, '/turmas/' . $classId . '/editar');
        }
        ClassGroup::update($classId, $this->request->post);
        Flash::success('Turma atualizada.');
        $this->redirect('/turmas/' . $classId);
    }

    private function validateClass(?int $ignoreId = null): Validator
    {
        $validator = Validator::make($this->request->post, [
            'code'       => 'required|max:32|unique:classes,code' . ($ignoreId ? ',' . $ignoreId : ''),
            'name'       => 'nullable|max:150',
            'course_id'  => 'required|integer|exists:courses',
            'year'       => 'required|integer|min_value:2000|max_value:2100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'status'     => 'required|in:planejada,em_andamento,concluida,cancelada',
        ], [
            'code' => 'código da turma', 'name' => 'nome', 'course_id' => 'curso', 'year' => 'ano',
            'start_date' => 'data de início', 'end_date' => 'data de término', 'status' => 'situação',
        ]);

        $start = $this->request->input('start_date');
        $end   = $this->request->input('end_date');
        if ($start && $end && $end < $start) {
            $validator->add('end_date', 'A data de término não pode ser anterior à data de início.');
        }
        return $validator;
    }

    /** Painel da turma: desempenho, ranking interno e conteúdos críticos. */
    public function show(string $id): void
    {
        $classId = (int) $id;
        $turma = ClassGroup::find($classId);
        if (!$turma) {
            $this->notFound('Turma não encontrada.');
        }

        $filters = array_merge($this->filters(['disciplina', 'inicio', 'fim']), ['turma' => $classId]);
        $ranking = RankingService::build($filters);

        $this->view('classes/show', [
            'title'          => 'Turma ' . $turma['code'],
            'turma'          => $turma,
            'filters'        => $filters,
            'alunos'         => Student::byClass($classId, false),
            'disciplinas'    => ClassGroup::subjects($classId),
            'disponiveis'    => Subject::options(),
            'professores'    => User::teachers(),
            'ranking'        => $ranking,
            'classificacao'  => RankingService::summarize($ranking),
            'media'          => AnalyticsService::overallAverage($filters),
            'acertos'        => AnalyticsService::answerTotals($filters),
            'serie'          => AnalyticsService::assessmentAverages($filters),
            'por_disciplina' => AnalyticsService::subjectAverages($filters),
            'assuntos'       => AnalyticsService::topicPerformance($filters),
            'frequencia'     => AnalyticsService::attendanceByClass($filters),
            'distribuicao'   => AnalyticsService::performanceDistribution($filters),
            'alertas'        => AlertService::generate($filters, $ranking),
            'sem_turma'      => Student::search(['sem_turma' => 1]),
            'blockers'       => ClassGroup::blockers($classId),
            'faixas'         => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    public function attachSubject(string $id): void
    {
        $classId  = (int) $id;
        $subjectId = (int) $this->request->input('subject_id', 0);
        $teacherId = (int) $this->request->input('teacher_user_id', 0) ?: null;

        if (!ClassGroup::find($classId) || !Subject::find($subjectId)) {
            Flash::error('Turma ou disciplina inválida.');
            $this->redirect('/turmas/' . $classId);
        }

        if (ClassGroup::attachSubject($classId, $subjectId, $teacherId)) {
            Flash::success('Disciplina vinculada à turma.');
        } else {
            Flash::warning('Esta disciplina já está vinculada à turma.');
        }
        $this->redirect('/turmas/' . $classId);
    }

    public function detachSubject(string $id): void
    {
        $classId = (int) $id;
        $result  = ClassGroup::detachSubject((int) $this->request->input('class_subject_id', 0));
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);
        $this->redirect('/turmas/' . $classId);
    }

    public function attachStudent(string $id): void
    {
        $classId   = (int) $id;
        $studentId = (int) $this->request->input('student_id', 0);

        if (!ClassGroup::find($classId) || !Student::find($studentId)) {
            Flash::error('Turma ou aluno inválido.');
            $this->redirect('/turmas/' . $classId);
        }

        Student::assignToClass($studentId, $classId);
        Flash::success('Aluno vinculado à turma.');
        $this->redirect('/turmas/' . $classId);
    }

    public function detachStudent(string $id): void
    {
        $classId   = (int) $id;
        $studentId = (int) $this->request->input('student_id', 0);
        Student::removeFromClass($studentId);
        Flash::info('Aluno removido da turma. Notas e frequência foram preservadas no histórico.');
        $this->redirect('/turmas/' . $classId);
    }

    public function destroy(string $id): void
    {
        $classId = (int) $id;
        $blockers = ClassGroup::blockers($classId);
        if ($blockers !== []) {
            Flash::error('Não é possível excluir a turma: ' . implode(', ', $blockers) . '.');
            $this->redirect('/turmas/' . $classId);
        }
        ClassGroup::delete($classId);
        Flash::success('Turma excluída.');
        $this->redirect('/turmas');
    }
}
