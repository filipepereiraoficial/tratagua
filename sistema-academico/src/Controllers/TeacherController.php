<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\ClassSubject;
use App\Models\Teacher;
use App\Services\AnalyticsService;
use App\Services\RankingService;

/** Cadastro de professores e vínculo com turma × disciplina (somente admin). */
class TeacherController extends Controller
{
    public function index(): void
    {
        $filters = [
            'busca' => $this->request->query('busca'),
            'ativo' => $this->request->query('ativo'),
        ];
        $this->view('teachers/index', [
            'title'       => 'Professores',
            'professores' => Teacher::search($filters),
            'filters'     => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('teachers/form', ['title' => 'Novo professor', 'professor' => null]);
    }

    public function store(): void
    {
        $validator = Validator::make($this->request->post, [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|min:8',
            'role'     => 'required|in:professor,admin',
            'document' => 'nullable|max:32',
            'phone'    => 'nullable|max:32',
        ], [
            'name' => 'nome', 'email' => 'e-mail', 'password' => 'senha inicial',
            'role' => 'perfil', 'document' => 'CPF', 'phone' => 'telefone',
        ]);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/professores/novo');
        }

        $id = Teacher::create($this->request->post);
        ActivityLog::record('criou', 'professor', $id, $this->request->input('name'));

        Flash::success('Professor cadastrado. Ele definirá a própria senha no primeiro acesso.');
        $this->redirect('/professores/' . $id);
    }

    public function edit(string $id): void
    {
        $professor = Teacher::find((int) $id);
        if (!$professor) {
            $this->notFound('Professor não encontrado.');
        }
        $this->view('teachers/form', ['title' => 'Editar professor', 'professor' => $professor]);
    }

    public function update(string $id): void
    {
        $teacherId = (int) $id;
        $professor = Teacher::find($teacherId);
        if (!$professor) {
            $this->notFound('Professor não encontrado.');
        }

        $validator = Validator::make($this->request->post, [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:150|unique:users,email,' . $teacherId,
            'role'     => 'required|in:professor,admin',
            'document' => 'nullable|max:32',
            'phone'    => 'nullable|max:32',
        ], ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil', 'document' => 'CPF', 'phone' => 'telefone']);

        $ativo = !empty($this->request->input('is_active'));
        if ($teacherId === Auth::id() && !$ativo) {
            $validator->add('is_active', 'Você não pode desativar o próprio usuário.');
        }
        if (!$ativo && ($blockers = Teacher::blockers($teacherId)) !== []) {
            $validator->add('is_active', 'Transfira antes: ' . implode(', ', $blockers) . '.');
        }

        if ($validator->fails()) {
            $this->rejectWith($validator, '/professores/' . $teacherId . '/editar');
        }

        Teacher::update($teacherId, $this->request->post);
        ActivityLog::record('atualizou', 'professor', $teacherId, $this->request->input('name'));

        Flash::success('Dados do professor atualizados.');
        $this->redirect('/professores/' . $teacherId);
    }

    /** Ficha do professor: vínculos e desempenho das turmas que ele atende. */
    public function show(string $id): void
    {
        $teacherId = (int) $id;
        $professor = Teacher::find($teacherId);
        if (!$professor) {
            $this->notFound('Professor não encontrado.');
        }

        $ofertas = Teacher::offerings($teacherId);
        $ids     = array_map(static fn ($o) => (int) $o['id'], $ofertas);
        $filtros = $ids === [] ? ['ofertas' => []] : ['ofertas' => $ids];
        $ranking = RankingService::build($filtros);

        $this->view('teachers/show', [
            'title'       => $professor['name'],
            'professor'   => $professor,
            'ofertas'     => $ofertas,
            'disponiveis' => Teacher::assignableOfferings($teacherId),
            'media'       => AnalyticsService::overallAverage($filtros),
            'acertos'     => AnalyticsService::answerTotals($filtros),
            'ensino'      => AnalyticsService::teachingCounters($filtros),
            'ranking'     => $ranking,
            'classes'     => RankingService::summarize($ranking),
            'assuntos'    => AnalyticsService::topicPerformance($filtros),
            'blockers'    => Teacher::blockers($teacherId),
        ]);
    }

    /** Vincula uma oferta (turma × disciplina) ao professor. */
    public function assign(string $id): void
    {
        $teacherId = (int) $id;
        if (!Teacher::find($teacherId)) {
            $this->notFound('Professor não encontrado.');
        }

        $classSubjectId = (int) $this->request->input('class_subject_id', 0);
        $oferta = ClassSubject::find($classSubjectId);
        if (!$oferta) {
            Flash::error('Turma/disciplina inválida.');
            $this->redirect('/professores/' . $teacherId);
        }

        Teacher::assign($classSubjectId, $teacherId);
        ActivityLog::record('vinculou', 'professor', $teacherId,
            $oferta['class_code'] . ' — ' . $oferta['subject_name']);

        Flash::success("Professor vinculado a {$oferta['class_code']} — {$oferta['subject_name']}.");
        $this->redirect('/professores/' . $teacherId);
    }

    public function unassign(string $id): void
    {
        $teacherId = (int) $id;
        $classSubjectId = (int) $this->request->input('class_subject_id', 0);
        $oferta = ClassSubject::find($classSubjectId);

        if ($oferta && (int) $oferta['teacher_user_id'] === $teacherId) {
            Teacher::assign($classSubjectId, null);
            ActivityLog::record('desvinculou', 'professor', $teacherId,
                $oferta['class_code'] . ' — ' . $oferta['subject_name']);
            Flash::info('Vínculo removido. A oferta ficou sem professor responsável.');
        } else {
            Flash::error('Vínculo não encontrado.');
        }
        $this->redirect('/professores/' . $teacherId);
    }

    public function resetPassword(string $id): void
    {
        $teacherId = (int) $id;
        if (!Teacher::find($teacherId)) {
            $this->notFound('Professor não encontrado.');
        }
        $senha = (string) $this->request->input('password', '');
        if (mb_strlen($senha) < 8) {
            Flash::error('A nova senha deve ter ao menos 8 caracteres.');
            $this->redirect('/professores/' . $teacherId);
        }
        \App\Models\User::updatePassword($teacherId, $senha, true);
        ActivityLog::record('redefiniu senha', 'professor', $teacherId);
        Flash::success('Senha redefinida. O professor deverá trocá-la no próximo acesso.');
        $this->redirect('/professores/' . $teacherId);
    }

    public function destroy(string $id): void
    {
        $teacherId = (int) $id;
        if ($teacherId === Auth::id()) {
            Flash::error('Você não pode excluir o próprio usuário.');
            $this->redirect('/professores/' . $teacherId);
        }
        $blockers = Teacher::blockers($teacherId);
        if ($blockers !== []) {
            Flash::error('Não é possível excluir: ' . implode(', ', $blockers) . '. Transfira os vínculos antes.');
            $this->redirect('/professores/' . $teacherId);
        }
        Teacher::delete($teacherId);
        ActivityLog::record('excluiu', 'professor', $teacherId);
        Flash::success('Professor excluído.');
        $this->redirect('/professores');
    }
}
