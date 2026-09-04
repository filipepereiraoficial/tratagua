<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\AnalyticsService;

class SubjectController extends Controller
{
    public function index(): void
    {
        $filters = [
            'busca'  => $this->request->query('busca'),
            'status' => $this->request->query('status'),
        ];
        $this->view('subjects/index', [
            'title'       => 'Disciplinas',
            'disciplinas' => Subject::search($filters),
            'filters'     => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('subjects/form', [
            'title'       => 'Nova disciplina',
            'disciplina'  => null,
            'professores' => User::teachers(),
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateSubject();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/disciplinas/nova');
        }
        $id = Subject::create($this->request->post);
        Flash::success('Disciplina cadastrada. Cadastre agora os assuntos e tópicos.');
        $this->redirect('/disciplinas/' . $id);
    }

    public function edit(string $id): void
    {
        $disciplina = Subject::find((int) $id);
        if (!$disciplina) {
            $this->notFound('Disciplina não encontrada.');
        }
        $this->view('subjects/form', [
            'title'       => 'Editar disciplina',
            'disciplina'  => $disciplina,
            'professores' => User::teachers(),
        ]);
    }

    public function update(string $id): void
    {
        $subjectId = (int) $id;
        if (!Subject::find($subjectId)) {
            $this->notFound('Disciplina não encontrada.');
        }
        $validator = $this->validateSubject($subjectId);
        if ($validator->fails()) {
            $this->rejectWith($validator, '/disciplinas/' . $subjectId . '/editar');
        }
        Subject::update($subjectId, $this->request->post);
        Flash::success('Disciplina atualizada.');
        $this->redirect('/disciplinas/' . $subjectId);
    }

    private function validateSubject(?int $ignoreId = null): Validator
    {
        return Validator::make($this->request->post, [
            'name'           => 'required|max:150|unique:subjects,name' . ($ignoreId ? ',' . $ignoreId : ''),
            'workload_hours' => 'nullable|integer|min_value:0',
            'status'         => 'required|in:ativa,inativa',
        ], [
            'name' => 'nome da disciplina',
            'workload_hours' => 'carga horária',
            'status' => 'situação',
        ]);
    }

    /** Detalhe da disciplina: árvore de assuntos, turmas, aulas, avaliações e desempenho. */
    public function show(string $id): void
    {
        $subjectId = (int) $id;
        $disciplina = Subject::find($subjectId);
        if (!$disciplina) {
            $this->notFound('Disciplina não encontrada.');
        }

        $filters = array_merge($this->filters(['turma', 'inicio', 'fim']), ['disciplina' => $subjectId]);

        $this->view('subjects/show', [
            'title'      => $disciplina['name'],
            'disciplina' => $disciplina,
            'filters'    => $filters,
            'arvore'     => Topic::treeBySubject($subjectId),
            'turmas'     => Subject::classes($subjectId),
            'aulas'      => Lesson::search(['disciplina' => $subjectId], 10),
            'avaliacoes' => Assessment::search(['disciplina' => $subjectId], 10),
            'media'      => AnalyticsService::overallAverage($filters),
            'acertos'    => AnalyticsService::answerTotals($filters),
            'assuntos'   => AnalyticsService::topicPerformance($filters),
            'por_turma'  => AnalyticsService::classAverages($filters),
            'dificuldade'=> AnalyticsService::difficultyPerformance($filters),
            'blockers'   => Subject::blockers($subjectId),
            'faixas'     => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    public function destroy(string $id): void
    {
        $subjectId = (int) $id;
        $blockers  = Subject::blockers($subjectId);
        if ($blockers !== []) {
            Flash::error('Não é possível excluir a disciplina: ' . implode(', ', $blockers) . '.');
            $this->redirect('/disciplinas/' . $subjectId);
        }
        Subject::delete($subjectId);
        Flash::success('Disciplina excluída.');
        $this->redirect('/disciplinas');
    }

    // ------------------------------------------------- assuntos e tópicos

    public function storeTopic(string $id): void
    {
        $subjectId = (int) $id;
        if (!Subject::find($subjectId)) {
            $this->notFound('Disciplina não encontrada.');
        }

        $validator = Validator::make($this->request->post, [
            'name' => 'required|max:150',
        ], ['name' => 'nome do assunto/tópico']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/disciplinas/' . $subjectId);
        }

        Topic::create(array_merge($this->request->post, ['subject_id' => $subjectId]));
        Flash::success('Conteúdo cadastrado.');
        $this->redirect('/disciplinas/' . $subjectId);
    }

    public function updateTopic(string $id): void
    {
        $topic = Topic::find((int) $id);
        if (!$topic) {
            $this->notFound('Conteúdo não encontrado.');
        }
        $validator = Validator::make($this->request->post, ['name' => 'required|max:150'], ['name' => 'nome']);
        if ($validator->fails()) {
            $this->rejectWith($validator, '/disciplinas/' . $topic['subject_id']);
        }
        Topic::update((int) $id, $this->request->post);
        Flash::success('Conteúdo atualizado.');
        $this->redirect('/disciplinas/' . $topic['subject_id']);
    }

    public function destroyTopic(string $id): void
    {
        $topic = Topic::find((int) $id);
        if (!$topic) {
            $this->notFound('Conteúdo não encontrado.');
        }
        $questions = Topic::questionCount((int) $id);
        if ($questions > 0) {
            Flash::error("Não é possível excluir: {$questions} questão(ões) estão classificadas neste conteúdo. Reclassifique-as antes.");
            $this->redirect('/disciplinas/' . $topic['subject_id']);
        }
        Topic::delete((int) $id);
        Flash::success('Conteúdo excluído.');
        $this->redirect('/disciplinas/' . $topic['subject_id']);
    }
}
