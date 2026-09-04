<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Core\Scope;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Attendance;
use App\Models\ClassGroup;
use App\Models\ClassSubject;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;

class LessonController extends Controller
{
    public function index(): void
    {
        $filters = $this->scopedFilters(['turma', 'disciplina', 'aluno', 'inicio', 'fim']);
        $this->view('lessons/index', [
            'title'       => 'Aulas',
            'aulas'       => Lesson::search($filters, 200),
            'total'       => Lesson::countSearch($filters),
            'filters'     => $filters,
            'turmas'      => $this->turmasVisiveis(),
            'disciplinas' => $this->disciplinasVisiveis(),
            'alunos'      => Scope::students(),
        ]);
    }

    public function create(): void
    {
        $this->view('lessons/form', [
            'title'   => 'Nova aula',
            'aula'    => null,
            'ofertas' => ClassSubject::options(Scope::apply([])),
            'topicos' => Topic::all(),
            'selecionados' => [],
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateLesson();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/aulas/nova');
        }

        $id = Lesson::create($this->request->post, (array) ($this->request->post['topics'] ?? []));
        ActivityLog::record('registrou', 'aula', $id, $this->request->input('title'));
        Flash::success('Aula registrada. Faça a chamada para computar a frequência.');
        $this->redirect('/aulas/' . $id . '/frequencia');
    }

    public function edit(string $id): void
    {
        $aula = Lesson::find((int) $id);
        if (!$aula) {
            $this->notFound('Aula não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $aula['class_subject_id']),
            'Esta aula é de uma turma/disciplina fora do seu escopo.');
        $this->view('lessons/form', [
            'title'   => 'Editar aula',
            'aula'    => $aula,
            'ofertas' => ClassSubject::options(Scope::apply([])),
            'topicos' => Topic::all(),
            'selecionados' => Lesson::topicIds((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $lessonId = (int) $id;
        $existente = Lesson::find($lessonId);
        if (!$existente) {
            $this->notFound('Aula não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $existente['class_subject_id']));
        $validator = $this->validateLesson();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/aulas/' . $lessonId . '/editar');
        }
        Lesson::update($lessonId, $this->request->post, (array) ($this->request->post['topics'] ?? []));
        ActivityLog::record('atualizou', 'aula', $lessonId, $this->request->input('title'));
        Flash::success('Aula atualizada.');
        $this->redirect('/aulas');
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

    private function validateLesson(): Validator
    {
        $validator = Validator::make($this->request->post, [
            'class_subject_id' => 'required|integer|exists:class_subjects',
            'title'            => 'required|max:200',
            'lesson_date'      => 'required|date',
            'duration_minutes' => 'nullable|integer|min_value:0|max_value:1440',
        ], [
            'class_subject_id' => 'turma/disciplina',
            'title'            => 'título',
            'lesson_date'      => 'data da aula',
            'duration_minutes' => 'duração',
        ]);

        $ofertaId = (int) $this->request->input('class_subject_id', 0);
        if ($ofertaId > 0 && !Scope::canAccessClassSubject($ofertaId)) {
            $validator->add('class_subject_id', 'Turma/disciplina fora do seu escopo.');
        }

        // Aviso de coerência: a data deve cair no período da turma.
        $oferta = ClassSubject::find($ofertaId);
        $data   = (string) $this->request->input('lesson_date', '');
        if ($oferta && $data !== '') {
            $turma = ClassGroup::find((int) $oferta['class_id']);
            if ($turma && $turma['start_date'] && $data < $turma['start_date']) {
                Flash::warning('Atenção: a data da aula é anterior ao início da turma.');
            }
            if ($turma && $turma['end_date'] && $data > $turma['end_date']) {
                Flash::warning('Atenção: a data da aula é posterior ao término da turma.');
            }
        }
        return $validator;
    }

    /** Chamada: presença e participação de todos os alunos da turma. */
    public function attendance(string $id): void
    {
        $lessonId = (int) $id;
        $aula = Lesson::find($lessonId);
        if (!$aula) {
            $this->notFound('Aula não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $aula['class_subject_id']));

        $this->view('lessons/attendance', [
            'title'     => 'Chamada — ' . $aula['title'],
            'aula'      => $aula,
            'alunos'    => Student::byClass((int) $aula['class_id']),
            'registros' => Attendance::forLesson($lessonId),
            'topicos'   => Lesson::topics($lessonId),
        ]);
    }

    public function saveAttendance(string $id): void
    {
        $lessonId = (int) $id;
        $aula = Lesson::find($lessonId);
        if (!$aula) {
            $this->notFound('Aula não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $aula['class_subject_id']));

        $entries = [];
        $alunos  = Student::byClass((int) $aula['class_id']);
        $status  = (array) ($this->request->post['status'] ?? []);
        $part    = (array) ($this->request->post['participation'] ?? []);
        $notes   = (array) ($this->request->post['notes'] ?? []);

        foreach ($alunos as $aluno) {
            $studentId = (int) $aluno['id'];
            if (!isset($status[$studentId])) {
                continue;
            }
            $entries[$studentId] = [
                'status'        => (string) $status[$studentId],
                'participation' => $part[$studentId] ?? '',
                'notes'         => $notes[$studentId] ?? '',
            ];
        }

        $saved = Attendance::saveForLesson($lessonId, $entries);
        ActivityLog::record('registrou chamada', 'aula', $lessonId, "{$saved} aluno(s)");
        Flash::success("Chamada registrada para {$saved} aluno(s).");
        $this->redirect('/aulas/' . $lessonId . '/frequencia');
    }

    public function destroy(string $id): void
    {
        $lessonId = (int) $id;
        $aula = Lesson::find($lessonId);
        if (!$aula) {
            $this->notFound('Aula não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $aula['class_subject_id']));
        Lesson::delete($lessonId);
        ActivityLog::record('excluiu', 'aula', $lessonId, $aula['title']);
        Flash::success('Aula excluída (a chamada correspondente também foi removida).');
        $this->redirect('/aulas');
    }
}
