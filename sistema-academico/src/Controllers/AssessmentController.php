<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Core\Scope;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ClassGroup;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\AnalyticsService;

class AssessmentController extends Controller
{
    public function index(): void
    {
        $filters = $this->scopedFilters(['turma', 'disciplina', 'tipo', 'status', 'inicio', 'fim']);
        $this->view('assessments/index', [
            'title'       => 'Avaliações',
            'avaliacoes'  => Assessment::search($filters, 200),
            'total'       => Assessment::countSearch($filters),
            'filters'     => $filters,
            'turmas'      => $this->turmasVisiveis(),
            'disciplinas' => $this->disciplinasVisiveis(),
            'tipos'       => Assessment::TYPES,
        ]);
    }

    public function create(): void
    {
        $this->view('assessments/form', [
            'title'     => 'Nova avaliação',
            'avaliacao' => null,
            'ofertas'   => ClassSubject::options(Scope::apply([])),
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateAssessment();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/avaliacoes/nova');
        }
        $id = Assessment::create($this->request->post);
        ActivityLog::record('criou', 'avaliacao', $id, $this->request->input('name'));

        // Atalho: já cria as questões numeradas se o professor informou a quantidade.
        $quantidade = (int) $this->request->input('question_count', 0);
        if ($quantidade > 0) {
            $oferta = ClassSubject::find((int) $this->request->input('class_subject_id'));
            $pontos = (float) $this->request->input('max_score', 10) / $quantidade;
            Question::bulkCreate($id, (int) $oferta['subject_id'], min($quantidade, 200), round($pontos, 2), 'medio');
            Flash::info("{$quantidade} questão(ões) criada(s). Classifique cada uma por assunto e dificuldade.");
        }

        Flash::success('Avaliação criada.');
        $this->redirect('/avaliacoes/' . $id . '/questoes');
    }

    public function edit(string $id): void
    {
        $avaliacao = Assessment::find((int) $id);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));
        $this->view('assessments/form', [
            'title'     => 'Editar avaliação',
            'avaliacao' => $avaliacao,
            'ofertas'   => ClassSubject::options(Scope::apply([])),
        ]);
    }

    public function update(string $id): void
    {
        $assessmentId = (int) $id;
        $atual = Assessment::find($assessmentId);
        if (!$atual) {
            $this->notFound('Avaliação não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $atual['class_subject_id']));
        $validator = $this->validateAssessment();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/avaliacoes/' . $assessmentId . '/editar');
        }
        Assessment::update($assessmentId, $this->request->post);
        // O valor máximo pode ter mudado: as notas derivadas precisam acompanhar.
        Grade::recalculateAssessment($assessmentId);
        ActivityLog::record('atualizou', 'avaliacao', $assessmentId, $this->request->input('name'));
        Flash::success('Avaliação atualizada e notas recalculadas.');
        $this->redirect('/avaliacoes/' . $assessmentId);
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

    private function validateAssessment(): Validator
    {
        $validator = Validator::make($this->request->post, [
            'class_subject_id' => 'required|integer|exists:class_subjects',
            'name'             => 'required|max:200',
            'type'             => 'required|in:' . implode(',', Assessment::TYPES),
            'assessment_date'  => 'required|date',
            'max_score'        => 'required|numeric|min_value:0.1',
            'weight'           => 'nullable|numeric|min_value:0.1|max_value:100',
            'status'           => 'required|in:planejada,aplicada,corrigida',
        ], [
            'class_subject_id' => 'turma/disciplina',
            'name'             => 'nome',
            'type'             => 'tipo',
            'assessment_date'  => 'data',
            'max_score'        => 'valor máximo',
            'weight'           => 'peso',
            'status'           => 'situação',
        ]);

        $ofertaId = (int) $this->request->input('class_subject_id', 0);
        if ($ofertaId > 0 && !Scope::canAccessClassSubject($ofertaId)) {
            $validator->add('class_subject_id', 'Turma/disciplina fora do seu escopo.');
        }

        $oferta = ClassSubject::find($ofertaId);
        $data   = (string) $this->request->input('assessment_date', '');
        if ($oferta && $data !== '') {
            $turma = ClassGroup::find((int) $oferta['class_id']);
            if ($turma && $turma['start_date'] && $data < $turma['start_date']) {
                Flash::warning('Atenção: a data da avaliação é anterior ao início da turma.');
            }
        }
        return $validator;
    }

    /** Análise da avaliação: índice de acerto por questão, tópico e dificuldade. */
    public function show(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));

        $filters = ['avaliacao' => $assessmentId];
        $notas   = Grade::forAssessment($assessmentId);
        $percentuais = array_map(static fn ($g) => (float) $g['percentage'], array_values($notas));

        $this->view('assessments/show', [
            'title'       => $avaliacao['name'],
            'avaliacao'   => $avaliacao,
            'questoes'    => AnalyticsService::assessmentQuestionAnalysis($assessmentId),
            'assuntos'    => AnalyticsService::topicPerformance($filters),
            'dificuldade' => AnalyticsService::difficultyPerformance($filters),
            'acertos'     => AnalyticsService::answerTotals($filters),
            'notas'       => $notas,
            'alunos'      => Student::byClass((int) $avaliacao['class_id'], false),
            'media'       => $percentuais ? round(array_sum($percentuais) / count($percentuais), 2) : null,
            'minima'      => $percentuais ? min($percentuais) : null,
            'maxima'      => $percentuais ? max($percentuais) : null,
            'desvio'      => AnalyticsService::stdDev($percentuais),
            'pontos_questoes' => Assessment::questionPoints($assessmentId),
            'faixas'      => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    // ------------------------------------------------------------ questões

    public function questions(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));

        $this->view('assessments/questions', [
            'title'      => 'Questões — ' . $avaliacao['name'],
            'avaliacao'  => $avaliacao,
            'questoes'   => Question::forAssessment($assessmentId),
            'topicos'    => Topic::optionsBySubject((int) $avaliacao['subject_id']),
            'pontos'     => Assessment::questionPoints($assessmentId),
        ]);
    }

    public function storeQuestion(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $validator = Validator::make($this->request->post, [
            'points'     => 'required|numeric|min_value:0.01',
            'difficulty' => 'required|in:' . implode(',', Question::DIFFICULTIES),
        ], ['points' => 'valor da questão', 'difficulty' => 'dificuldade']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/avaliacoes/' . $assessmentId . '/questoes');
        }

        Question::create(array_merge($this->request->post, [
            'assessment_id' => $assessmentId,
            'subject_id'    => (int) $avaliacao['subject_id'],
        ]));
        Grade::recalculateAssessment($assessmentId);

        Flash::success('Questão adicionada.');
        $this->redirect('/avaliacoes/' . $assessmentId . '/questoes');
    }

    public function bulkQuestions(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $quantidade = max(1, min(200, (int) $this->request->input('quantity', 0)));
        $pontos     = (float) ($this->request->input('points') ?: 1);
        $criadas    = Question::bulkCreate(
            $assessmentId,
            (int) $avaliacao['subject_id'],
            $quantidade,
            $pontos,
            (string) $this->request->input('difficulty', 'medio'),
            (int) $this->request->input('topic_id', 0) ?: null
        );
        Grade::recalculateAssessment($assessmentId);

        Flash::success("{$criadas} questão(ões) criada(s).");
        $this->redirect('/avaliacoes/' . $assessmentId . '/questoes');
    }

    /** Salva a grade inteira de questões (tópico, dificuldade, pontos, gabarito). */
    public function updateQuestions(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $topicos     = (array) ($this->request->post['topic_id'] ?? []);
        $dificuldades= (array) ($this->request->post['difficulty'] ?? []);
        $pontos      = (array) ($this->request->post['points'] ?? []);
        $enunciados  = (array) ($this->request->post['statement'] ?? []);
        $gabaritos   = (array) ($this->request->post['answer_key'] ?? []);
        $excluir     = array_map('intval', (array) ($this->request->post['delete'] ?? []));

        foreach (Question::forAssessment($assessmentId) as $questao) {
            $questionId = (int) $questao['id'];
            if (in_array($questionId, $excluir, true)) {
                Question::delete($questionId);
                continue;
            }
            Question::update($questionId, [
                'subject_id' => (int) $avaliacao['subject_id'],
                'topic_id'   => $topicos[$questionId] ?? null,
                'number'     => (string) $questao['number'],
                'statement'  => $enunciados[$questionId] ?? null,
                'type'       => $questao['type'],
                'difficulty' => $dificuldades[$questionId] ?? 'medio',
                'points'     => $pontos[$questionId] ?? 1,
                'answer_key' => $gabaritos[$questionId] ?? null,
            ]);
        }

        Grade::recalculateAssessment($assessmentId);
        Flash::success('Questões salvas e notas recalculadas.');
        $this->redirect('/avaliacoes/' . $assessmentId . '/questoes');
    }

    // ---------------------------------------------------------- resultados

    public function results(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $questoes = Question::forAssessment($assessmentId);
        $alunos   = Student::byClass((int) $avaliacao['class_id'], false);
        $foco     = (int) $this->request->query('aluno', 0);

        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));

        $this->view('assessments/results', [
            'title'     => 'Resultados — ' . $avaliacao['name'],
            'avaliacao' => $avaliacao,
            'questoes'  => $questoes,
            'alunos'    => $alunos,
            'matriz'    => Answer::matrixForAssessment($assessmentId),
            'notas'     => Grade::forAssessment($assessmentId),
            'foco'      => $foco,
            'pontos'    => Assessment::questionPoints($assessmentId),
        ]);
    }

    /** Lançamento por questão (gera nota, percentual e análise por assunto). */
    public function saveResults(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));

        $respostas = (array) ($this->request->post['result'] ?? []);
        $marcadas  = (array) ($this->request->post['given'] ?? []);
        $alunos    = 0;

        foreach ($respostas as $studentId => $porQuestao) {
            if (!is_array($porQuestao)) {
                continue;
            }
            Answer::saveForStudent($assessmentId, (int) $studentId, $porQuestao, (array) ($marcadas[$studentId] ?? []));
            $alunos++;
        }

        if ($avaliacao['status'] !== 'corrigida' && $alunos > 0) {
            Assessment::update($assessmentId, array_merge($avaliacao, ['status' => 'corrigida']));
        }

        ActivityLog::record('lançou resultados', 'avaliacao', $assessmentId, "{$alunos} aluno(s)");
        Flash::success("Resultados registrados para {$alunos} aluno(s). Notas, percentuais e gráficos foram atualizados.");
        $this->redirect('/avaliacoes/' . $assessmentId . '/resultados');
    }

    /** Lançamento apenas da nota final, sem detalhamento por questão. */
    public function saveGrades(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));

        $notas = (array) ($this->request->post['score'] ?? []);
        $maxScore = (float) $avaliacao['max_score'];
        $lancadas = 0;
        $ajustadas = 0;

        foreach ($notas as $studentId => $valor) {
            $valor = trim((string) $valor);
            if ($valor === '') {
                Grade::saveManual($assessmentId, (int) $studentId, null, $maxScore);
                continue;
            }
            $numero = (float) str_replace(',', '.', $valor);
            if ($numero > $maxScore) {
                $ajustadas++;
            }
            Grade::saveManual($assessmentId, (int) $studentId, $numero, $maxScore);
            $lancadas++;
        }

        if ($ajustadas > 0) {
            Flash::warning("{$ajustadas} nota(s) excediam o valor máximo ({$maxScore}) e foram limitadas.");
        }
        ActivityLog::record('lançou notas', 'avaliacao', $assessmentId, "{$lancadas} nota(s)");
        Flash::success("{$lancadas} nota(s) lançada(s).");
        $this->redirect('/avaliacoes/' . $assessmentId . '/resultados');
    }

    public function export(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }

        $notas  = Grade::forAssessment($assessmentId);
        $linhas = [];
        foreach (Student::byClass((int) $avaliacao['class_id'], false) as $aluno) {
            $nota = $notas[(int) $aluno['id']] ?? null;
            $linhas[] = [
                $aluno['full_name'],
                $nota ? (float) $nota['score'] : null,
                (float) $avaliacao['max_score'],
                $nota ? (float) $nota['percentage'] : null,
                $nota ? (int) $nota['correct_count'] : null,
                $nota ? (int) $nota['wrong_count'] : null,
                $nota ? (int) $nota['blank_count'] : null,
                $nota ? ($nota['is_manual'] ? 'Nota direta' : 'Por questão') : 'Sem lançamento',
            ];
        }

        $this->csv(
            'avaliacao-' . $assessmentId . '-' . date('Y-m-d'),
            ['Aluno', 'Nota', 'Valor máximo', 'Aproveitamento (%)', 'Acertos', 'Erros', 'Em branco', 'Origem'],
            $linhas
        );
    }

    public function destroy(string $id): void
    {
        $assessmentId = (int) $id;
        $avaliacao = Assessment::find($assessmentId);
        if (!$avaliacao) {
            $this->notFound('Avaliação não encontrada.');
        }
        $this->denyUnless(Scope::canAccessClassSubject((int) $avaliacao['class_subject_id']));
        Assessment::delete($assessmentId);
        ActivityLog::record('excluiu', 'avaliacao', $assessmentId, $avaliacao['name']);
        Flash::success('Avaliação excluída, junto com suas questões e resultados.');
        $this->redirect('/avaliacoes');
    }
}
