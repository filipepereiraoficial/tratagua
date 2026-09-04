<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AlertService;
use App\Services\AnalyticsService;
use App\Services\RankingService;

class StudentController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = [
            'busca'  => $this->request->query('busca'),
            'turma'  => $this->request->query('turma'),
            'curso'  => $this->request->query('curso'),
            'status' => $this->request->query('status'),
            'sem_turma' => $this->request->query('sem_turma'),
            'sort'   => $this->request->query('sort', 'nome'),
            'dir'    => $this->request->query('dir', 'asc'),
        ];

        $page   = max(1, (int) $this->request->query('pagina', 1));
        $total  = Student::countSearch($filters);
        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = min($page, $pages);
        $alunos = Student::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->view('students/index', [
            'title'   => 'Alunos',
            'alunos'  => $alunos,
            'filters' => $filters,
            'total'   => $total,
            'pagina'  => $page,
            'paginas' => $pages,
            'turmas'  => ClassGroup::options(),
            'cursos'  => Course::options(),
        ]);
    }

    public function create(): void
    {
        $this->view('students/form', [
            'title'  => 'Novo aluno',
            'aluno'  => null,
            'turmas' => ClassGroup::options(),
            'turma_pre' => $this->request->query('turma'),
        ]);
    }

    public function store(): void
    {
        $validator = $this->validateStudent();
        if ($validator->fails()) {
            $this->rejectWith($validator, '/alunos/novo');
        }

        $id = Student::create($this->request->post);

        $classId = (int) $this->request->input('class_id', 0);
        if ($classId > 0) {
            Student::assignToClass($id, $classId);
        }

        Flash::success('Aluno cadastrado com sucesso.');
        $this->redirect('/alunos/' . $id);
    }

    public function edit(string $id): void
    {
        $aluno = Student::find((int) $id);
        if (!$aluno) {
            $this->notFound('Aluno não encontrado.');
        }
        $this->view('students/form', [
            'title'  => 'Editar aluno',
            'aluno'  => $aluno,
            'turmas' => ClassGroup::options(),
        ]);
    }

    public function update(string $id): void
    {
        $studentId = (int) $id;
        $aluno = Student::find($studentId);
        if (!$aluno) {
            $this->notFound('Aluno não encontrado.');
        }

        $validator = $this->validateStudent($studentId);
        if ($validator->fails()) {
            $this->rejectWith($validator, '/alunos/' . $studentId . '/editar');
        }

        Student::update($studentId, $this->request->post);

        $classId = (int) $this->request->input('class_id', 0);
        $current = Student::currentClassId($studentId);
        if ($classId > 0 && $classId !== $current) {
            Student::assignToClass($studentId, $classId);
            Flash::info('Aluno movido de turma. O histórico anterior foi preservado.');
        } elseif ($classId === 0 && $current !== null) {
            Student::removeFromClass($studentId);
        }

        Flash::success('Dados do aluno atualizados.');
        $this->redirect('/alunos/' . $studentId);
    }

    private function validateStudent(?int $ignoreId = null): Validator
    {
        $validator = Validator::make($this->request->post, [
            'full_name'   => 'required|max:150',
            'email'       => 'nullable|email|max:150',
            'phone'       => 'nullable|max:32',
            'document'    => 'nullable|max:32|unique:students,document' . ($ignoreId ? ',' . $ignoreId : ''),
            'birth_date'  => 'nullable|date',
            'enrolled_at' => 'nullable|date',
            'status'      => 'required|in:ativo,inativo,concluido',
        ], [
            'full_name'   => 'nome completo',
            'email'       => 'e-mail',
            'phone'       => 'telefone',
            'document'    => 'CPF/identificador',
            'birth_date'  => 'data de nascimento',
            'enrolled_at' => 'data de cadastro',
            'status'      => 'situação',
        ]);

        $classId = (int) $this->request->input('class_id', 0);
        if ($classId > 0 && !ClassGroup::find($classId)) {
            $validator->add('class_id', 'A turma selecionada não existe.');
        }
        return $validator;
    }

    /** Dashboard individual de aprendizagem. */
    public function show(string $id): void
    {
        $studentId = (int) $id;
        $aluno = Student::find($studentId);
        if (!$aluno) {
            $this->notFound('Aluno não encontrado.');
        }

        $filters = $this->filters(['disciplina', 'turma', 'tipo', 'inicio', 'fim']);
        $resumo  = AnalyticsService::studentSummary($studentId, $filters);

        $posicao = ['posicao' => null, 'total' => 0, 'indice' => null];
        $mediaTurma = null;
        $serieTurma = [];
        if ($aluno['class_id'] !== null) {
            $classId = (int) $aluno['class_id'];
            $posicao = RankingService::positionInClass($studentId, $classId, $filters);
            $mediaTurma = AnalyticsService::overallAverage(array_merge($filters, ['turma' => $classId]));
            $serieTurma = AnalyticsService::assessmentAverages(array_merge($filters, ['turma' => $classId]));
        }

        $assuntos = $resumo['assuntos'];
        $classificar = static fn (string $classe) => array_values(array_filter(
            $assuntos,
            static fn ($topico) => $topico['classificacao'] === $classe
        ));

        $this->view('students/show', [
            'title'        => $aluno['full_name'],
            'aluno'        => $aluno,
            'resumo'       => $resumo,
            'filters'      => $filters,
            'disciplinas'  => Subject::options(),
            'posicao'      => $posicao,
            'media_turma'  => $mediaTurma,
            'serie_turma'  => $serieTurma,
            'por_disciplina' => AnalyticsService::studentSubjectPerformance($studentId, $filters),
            'por_dificuldade' => AnalyticsService::difficultyPerformance(array_merge($filters, ['aluno' => $studentId])),
            'dominados'    => $classificar('dominio'),
            'intermediarios' => $classificar('intermediario'),
            'dificuldades' => $classificar('dificuldade'),
            'alertas'      => AlertService::forStudent($studentId),
            'presencas'    => \App\Models\Attendance::historyForStudent($studentId, $filters),
            'vinculos'     => Student::enrollments($studentId),
            'turmas'       => ClassGroup::options(),
            'faixas'       => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    public function changeClass(string $id): void
    {
        $studentId = (int) $id;
        if (!Student::find($studentId)) {
            $this->notFound('Aluno não encontrado.');
        }

        $classId = (int) $this->request->input('class_id', 0);
        if ($classId === 0) {
            Student::removeFromClass($studentId);
            Flash::info('Aluno desvinculado da turma. O histórico foi preservado.');
        } elseif (ClassGroup::find($classId)) {
            Student::assignToClass($studentId, $classId);
            Flash::success('Aluno vinculado à turma.');
        } else {
            Flash::error('Turma inválida.');
        }
        $this->redirect('/alunos/' . $studentId);
    }

    public function destroy(string $id): void
    {
        $studentId = (int) $id;
        $aluno = Student::find($studentId);
        if (!$aluno) {
            $this->notFound('Aluno não encontrado.');
        }

        $notas = (int) Database::value('SELECT COUNT(*) FROM grades WHERE student_id = ?', [$studentId], 0);
        if ($notas > 0 && !$this->request->input('confirmar_historico')) {
            Flash::error("Este aluno possui {$notas} resultado(s) registrado(s). Para preservar o histórico, prefira mudar a situação para Inativo.");
            $this->redirect('/alunos/' . $studentId);
        }

        Student::delete($studentId);
        Flash::success('Aluno excluído.');
        $this->redirect('/alunos');
    }

    public function export(): void
    {
        $filters = [
            'busca'  => $this->request->query('busca'),
            'turma'  => $this->request->query('turma'),
            'curso'  => $this->request->query('curso'),
            'status' => $this->request->query('status'),
        ];
        $rows = [];
        foreach (Student::search($filters) as $aluno) {
            $resumo = AnalyticsService::studentSummary((int) $aluno['id']);
            $rows[] = [
                $aluno['full_name'],
                $aluno['document'],
                $aluno['email'],
                $aluno['phone'],
                data_br($aluno['birth_date'], ''),
                $aluno['class_code'] ?? '',
                $aluno['course_name'] ?? '',
                rotulo('status_aluno', $aluno['status']),
                $resumo['avaliacoes'],
                $resumo['media'],
                $resumo['frequencia'],
                $resumo['indice'],
                rotulo('classificacao', $resumo['classificacao']),
            ];
        }
        $this->csv('alunos-' . date('Y-m-d'), [
            'Nome', 'CPF/ID', 'E-mail', 'Telefone', 'Nascimento', 'Turma', 'Curso', 'Situação',
            'Avaliações', 'Média (%)', 'Frequência (%)', 'Índice', 'Classificação',
        ], $rows);
    }
}
