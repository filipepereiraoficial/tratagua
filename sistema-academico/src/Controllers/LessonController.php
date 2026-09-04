<?php
namespace App\Controllers;

use App\Core\Controller;
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
        $filters = $this->filters(['turma', 'disciplina', 'aluno', 'inicio', 'fim']);
        $this->view('lessons/index', [
            'title'       => 'Aulas',
            'aulas'       => Lesson::search($filters, 200),
            'total'       => Lesson::countSearch($filters),
            'filters'     => $filters,
            'turmas'      => ClassGroup::options(),
            'disciplinas' => Subject::options(),
            'alunos'      => Student::search(['status' => 'ativo']),
        ]);
    }

    public function create(): void
    {
        $this->view('lessons/form', [
            'title'   => 'Nova aula',
            'aula'    => null,
            'ofertas' => ClassSubject::options(),
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
        Flash::success('Aula registrada. Faça a chamada para computar a frequência.');
        $this->redirect('/aulas/' . $id . '/frequencia');
    }

    public function edit(string $id): void
    {
        $aula = Lesson::find((int) $id);
        if (!$aula) {
            $this->notFound('Aula não encontrada.');
        }
        $this->view('lessons/form', [
            'title'   => 'Editar aula',
            'aula'    => $aula,
            'ofertas' => ClassSubject::options(),
            'topicos' => Topic::all(),
            'selecionados' => Lesson::topicIds((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $lessonId = (int) $id;
        if (!Lesson::find($lessonId)) {
            $this->notFound('Aula não encontrada.');
        }
        $validator = $this->validateLesson();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/aulas/' . $lessonId . '/editar');
        }
        Lesson::update($lessonId, $this->request->post, (array) ($this->request->post['topics'] ?? []));
        Flash::success('Aula atualizada.');
        $this->redirect('/aulas');
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

        // Aviso de coerência: a data deve cair no período da turma.
        $oferta = ClassSubject::find((int) $this->request->input('class_subject_id', 0));
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
        Flash::success("Chamada registrada para {$saved} aluno(s).");
        $this->redirect('/aulas/' . $lessonId . '/frequencia');
    }

    public function destroy(string $id): void
    {
        $lessonId = (int) $id;
        if (!Lesson::find($lessonId)) {
            $this->notFound('Aula não encontrada.');
        }
        Lesson::delete($lessonId);
        Flash::success('Aula excluída (a chamada correspondente também foi removida).');
        $this->redirect('/aulas');
    }
}
