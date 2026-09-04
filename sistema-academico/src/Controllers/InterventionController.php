<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Scope;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\ClassSubject;
use App\Models\Intervention;
use App\Models\Student;
use App\Services\AnalyticsService;

/**
 * Acompanhamento pedagógico. Fecha o ciclo do sistema: o alerta aponta quem
 * precisa de atenção, aqui se registra o que foi feito, e a comparação com a
 * linha de base mostra se funcionou.
 */
class InterventionController extends Controller
{
    public function index(): void
    {
        $filters = Scope::apply([
            'status' => $this->request->query('status'),
            'tipo'   => $this->request->query('tipo'),
            'aluno'  => $this->request->query('aluno'),
        ]);
        $registros = Intervention::search($filters);

        // Efeito medido de cada acompanhamento, contra a linha de base.
        foreach ($registros as &$registro) {
            $resumo = AnalyticsService::studentSummary((int) $registro['student_id']);
            $registro['efeito'] = Intervention::effect($registro, $resumo['media'], $resumo['frequencia']);
            $registro['media_atual'] = $resumo['media'];
            $registro['atrasado'] = in_array($registro['status'], ['aberta', 'em_andamento'], true)
                && $registro['due_date'] !== null && $registro['due_date'] < date('Y-m-d');
        }
        unset($registro);

        $this->view('interventions/index', [
            'title'     => 'Acompanhamento pedagógico',
            'registros' => $registros,
            'contagem'  => Intervention::countByStatus(Scope::apply([])),
            'filters'   => $filters,
            'alunos'    => Scope::students(),
            'tipos'     => Intervention::TYPES,
            'situacoes' => Intervention::STATUSES,
        ]);
    }

    public function create(): void
    {
        $studentId = (int) $this->request->query('aluno', 0);
        if ($studentId > 0) {
            $this->denyUnless(Scope::canAccessStudent($studentId));
        }
        $this->view('interventions/form', [
            'title'         => 'Novo acompanhamento',
            'registro'      => null,
            'alunos'        => Scope::students(),
            'ofertas'       => Scope::offerings(),
            'aluno_pre'     => $studentId ?: null,
            'alerta_pre'    => $this->request->query('alerta'),
            'titulo_pre'    => $this->request->query('titulo'),
            'tipos'         => Intervention::TYPES,
            'situacoes'     => Intervention::STATUSES,
        ]);
    }

    public function store(): void
    {
        $validator = Validator::make($this->request->post, [
            'student_id' => 'required|integer|exists:students',
            'title'      => 'required|max:200',
            'type'       => 'required|in:' . implode(',', array_keys(Intervention::TYPES)),
            'status'     => 'required|in:' . implode(',', array_keys(Intervention::STATUSES)),
            'due_date'   => 'nullable|date',
        ], [
            'student_id' => 'aluno', 'title' => 'título', 'type' => 'tipo',
            'status' => 'situação', 'due_date' => 'prazo',
        ]);

        $studentId = (int) $this->request->input('student_id', 0);
        if (!Scope::canAccessStudent($studentId)) {
            $validator->add('student_id', 'Este aluno não está em uma turma sob sua responsabilidade.');
        }
        $ofertaId = (int) $this->request->input('class_subject_id', 0);
        if ($ofertaId > 0 && !Scope::canAccessClassSubject($ofertaId)) {
            $validator->add('class_subject_id', 'Turma/disciplina fora do seu escopo.');
        }

        if ($validator->fails()) {
            $this->rejectWith($validator, '/acompanhamento/novo');
        }

        // Congela a situação do aluno agora: é contra ela que o efeito é medido.
        $resumo = AnalyticsService::studentSummary($studentId);

        $id = Intervention::create(array_merge($this->request->post, [
            'author_user_id'      => Auth::id(),
            'baseline_media'      => $resumo['media'],
            'baseline_frequencia' => $resumo['frequencia'],
        ]));
        ActivityLog::record('abriu', 'acompanhamento', $id, $this->request->input('title'));

        Flash::success('Acompanhamento registrado. A média e a frequência atuais ficaram guardadas como linha de base.');
        $this->redirect('/acompanhamento');
    }

    public function edit(string $id): void
    {
        $registro = Intervention::find((int) $id);
        if (!$registro) {
            $this->notFound('Acompanhamento não encontrado.');
        }
        $this->denyUnless(Scope::canAccessStudent((int) $registro['student_id']));

        $resumo = AnalyticsService::studentSummary((int) $registro['student_id']);

        $this->view('interventions/form', [
            'title'     => 'Editar acompanhamento',
            'registro'  => $registro,
            'alunos'    => Scope::students(),
            'ofertas'   => Scope::offerings(),
            'tipos'     => Intervention::TYPES,
            'situacoes' => Intervention::STATUSES,
            'efeito'    => Intervention::effect($registro, $resumo['media'], $resumo['frequencia']),
            'resumo'    => $resumo,
        ]);
    }

    public function update(string $id): void
    {
        $registro = Intervention::find((int) $id);
        if (!$registro) {
            $this->notFound('Acompanhamento não encontrado.');
        }
        $this->denyUnless(Scope::canAccessStudent((int) $registro['student_id']));

        $validator = Validator::make($this->request->post, [
            'title'    => 'required|max:200',
            'type'     => 'required|in:' . implode(',', array_keys(Intervention::TYPES)),
            'status'   => 'required|in:' . implode(',', array_keys(Intervention::STATUSES)),
            'due_date' => 'nullable|date',
        ], ['title' => 'título', 'type' => 'tipo', 'status' => 'situação', 'due_date' => 'prazo']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/acompanhamento/' . $id . '/editar');
        }

        Intervention::update((int) $id, $this->request->post);
        ActivityLog::record('atualizou', 'acompanhamento', (int) $id, $this->request->input('status'));

        Flash::success('Acompanhamento atualizado.');
        $this->redirect('/acompanhamento');
    }

    public function destroy(string $id): void
    {
        $registro = Intervention::find((int) $id);
        if (!$registro) {
            $this->notFound('Acompanhamento não encontrado.');
        }
        $this->denyUnless(Scope::canAccessStudent((int) $registro['student_id']));

        Intervention::delete((int) $id);
        ActivityLog::record('excluiu', 'acompanhamento', (int) $id);
        Flash::success('Acompanhamento excluído.');
        $this->redirect('/acompanhamento');
    }
}
