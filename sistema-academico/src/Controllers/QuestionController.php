<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Scope;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;

/** Banco de questões: reaproveitamento e estatística real de acerto. */
class QuestionController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters(['disciplina', 'assunto', 'dificuldade', 'avaliacao', 'busca', 'origem']);
        // O professor vê o banco das disciplinas que leciona.
        $permitidas = Scope::subjectIds();
        if ($permitidas !== null) {
            $filters['disciplinas_permitidas'] = $permitidas;
        }
        $questoes = Question::search($filters, 300);

        foreach ($questoes as &$questao) {
            $respostas = (int) $questao['answers_count'];
            $questao['indice_acerto'] = $respostas > 0
                ? round((int) $questao['correct_count'] / $respostas * 100, 2)
                : null;
        }
        unset($questao);

        $assuntos = !empty($filters['disciplina'])
            ? Topic::optionsBySubject((int) $filters['disciplina'])
            : [];

        $this->view('questions/index', [
            'title'       => 'Banco de questões',
            'questoes'    => $questoes,
            'filters'     => $filters,
            'disciplinas' => $this->disciplinasVisiveis(),
            'assuntos'    => $assuntos,
            'dificuldades'=> Question::DIFFICULTIES,
        ]);
    }

    public function create(): void
    {
        $subjectId = (int) $this->request->query('disciplina', 0);
        $this->view('questions/form', [
            'title'       => 'Nova questão',
            'questao'     => null,
            'disciplinas' => Subject::options(),
            'assuntos'    => $subjectId > 0 ? Topic::optionsBySubject($subjectId) : [],
            'subject_id'  => $subjectId,
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateQuestion();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/questoes/nova');
        }
        Question::create($this->request->post);
        Flash::success('Questão cadastrada no banco.');
        $this->redirect('/questoes');
    }

    public function edit(string $id): void
    {
        $questao = Question::find((int) $id);
        if (!$questao) {
            $this->notFound('Questão não encontrada.');
        }
        $this->view('questions/form', [
            'title'       => 'Editar questão',
            'questao'     => $questao,
            'disciplinas' => Subject::options(),
            'assuntos'    => Topic::optionsBySubject((int) $questao['subject_id']),
            'subject_id'  => (int) $questao['subject_id'],
            'alternativas'=> Question::options((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $questionId = (int) $id;
        $questao = Question::find($questionId);
        if (!$questao) {
            $this->notFound('Questão não encontrada.');
        }
        $validator = $this->validateQuestion();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/questoes/' . $questionId . '/editar');
        }

        Question::update($questionId, $this->request->post);

        $alternativas = (array) ($this->request->post['options'] ?? []);
        if ($alternativas !== []) {
            Question::syncOptions($questionId, $alternativas);
        }

        // Mudar os pontos altera a nota derivada de quem respondeu.
        if ($questao['assessment_id'] !== null) {
            Grade::recalculateAssessment((int) $questao['assessment_id']);
        }

        Flash::success('Questão atualizada.');
        $this->redirect('/questoes');
    }

    private function disciplinasVisiveis(): array
    {
        $ids = Scope::subjectIds();
        return $ids === null ? Subject::options()
            : array_values(array_filter(Subject::options(), static fn ($d) => in_array((int) $d['id'], $ids, true)));
    }

    private function validateQuestion(): Validator
    {
        $permitidas = Scope::subjectIds();
        $disciplina = (int) $this->request->input('subject_id', 0);
        $validator = Validator::make($this->request->post, [
            'subject_id' => 'required|integer|exists:subjects',
            'difficulty' => 'required|in:' . implode(',', Question::DIFFICULTIES),
            'points'     => 'required|numeric|min_value:0.01|max_value:1000',
            'type'       => 'required|in:objetiva,discursiva',
        ], [
            'subject_id' => 'disciplina',
            'difficulty' => 'dificuldade',
            'points'     => 'valor da questão',
            'type'       => 'tipo',
        ]);
        if ($permitidas !== null && $disciplina > 0 && !in_array($disciplina, $permitidas, true)) {
            $validator->add('subject_id', 'Você não leciona esta disciplina.');
        }
        return $validator;
    }

    public function destroy(string $id): void
    {
        $questionId = (int) $id;
        $questao = Question::find($questionId);
        if (!$questao) {
            $this->notFound('Questão não encontrada.');
        }
        $assessmentId = $questao['assessment_id'] !== null ? (int) $questao['assessment_id'] : null;
        Question::delete($questionId);
        if ($assessmentId !== null) {
            Grade::recalculateAssessment($assessmentId);
        }
        Flash::success('Questão excluída.');
        $this->back();
    }
}
