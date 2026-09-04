<?php
/**
 * Tabela de rotas. A ordem importa apenas entre padrões que se sobrepõem
 * (rotas literais vêm antes das que têm parâmetros).
 *
 * @var App\Core\Router $router
 */

use App\Controllers\ApiController;
use App\Controllers\AssessmentController;
use App\Controllers\AuthController;
use App\Controllers\ChartController;
use App\Controllers\ClassController;
use App\Controllers\CourseController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\LessonController;
use App\Controllers\QuestionController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\StudentController;
use App\Controllers\SubjectController;

// ------------------------------------------------------------- instalação
$router->get('/instalar',  [InstallController::class, 'show']);
$router->post('/instalar', [InstallController::class, 'run']);

// ---------------------------------------------------------- autenticação
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/logout',  [AuthController::class, 'logout']);

$router->group(['auth'], static function ($r) {
    $r->get('/senha',  [AuthController::class, 'showPassword']);
    $r->post('/senha', [AuthController::class, 'updatePassword']);
});

// ------------------------------------------------- área do professor/admin
$router->group(['auth', 'role:admin|professor'], static function ($r) {

    // Dashboard
    $r->get('/',                 [DashboardController::class, 'index']);
    $r->post('/alertas/tratar',  [DashboardController::class, 'dismissAlert']);
    $r->post('/alertas/restaurar', [DashboardController::class, 'restoreAlert']);

    // Alunos
    $r->get('/alunos',                 [StudentController::class, 'index']);
    $r->get('/alunos/exportar',        [StudentController::class, 'export']);
    $r->get('/alunos/novo',            [StudentController::class, 'create']);
    $r->post('/alunos',                [StudentController::class, 'store']);
    $r->get('/alunos/{id}',            [StudentController::class, 'show']);
    $r->get('/alunos/{id}/editar',     [StudentController::class, 'edit']);
    $r->post('/alunos/{id}',           [StudentController::class, 'update']);
    $r->post('/alunos/{id}/turma',     [StudentController::class, 'changeClass']);
    $r->post('/alunos/{id}/excluir',   [StudentController::class, 'destroy']);

    // Cursos
    $r->get('/cursos',               [CourseController::class, 'index']);
    $r->post('/cursos',              [CourseController::class, 'store']);
    $r->post('/cursos/{id}',         [CourseController::class, 'update']);
    $r->post('/cursos/{id}/excluir', [CourseController::class, 'destroy']);

    // Turmas
    $r->get('/turmas',                        [ClassController::class, 'index']);
    $r->get('/turmas/nova',                   [ClassController::class, 'create']);
    $r->post('/turmas',                       [ClassController::class, 'store']);
    $r->get('/turmas/{id}',                   [ClassController::class, 'show']);
    $r->get('/turmas/{id}/editar',            [ClassController::class, 'edit']);
    $r->post('/turmas/{id}',                  [ClassController::class, 'update']);
    $r->post('/turmas/{id}/disciplinas',      [ClassController::class, 'attachSubject']);
    $r->post('/turmas/{id}/disciplinas/remover', [ClassController::class, 'detachSubject']);
    $r->post('/turmas/{id}/alunos',           [ClassController::class, 'attachStudent']);
    $r->post('/turmas/{id}/alunos/remover',   [ClassController::class, 'detachStudent']);
    $r->post('/turmas/{id}/excluir',          [ClassController::class, 'destroy']);

    // Disciplinas
    $r->get('/disciplinas',                  [SubjectController::class, 'index']);
    $r->get('/disciplinas/nova',             [SubjectController::class, 'create']);
    $r->post('/disciplinas',                 [SubjectController::class, 'store']);
    $r->get('/disciplinas/{id}',             [SubjectController::class, 'show']);
    $r->get('/disciplinas/{id}/editar',      [SubjectController::class, 'edit']);
    $r->post('/disciplinas/{id}',            [SubjectController::class, 'update']);
    $r->post('/disciplinas/{id}/excluir',    [SubjectController::class, 'destroy']);
    $r->post('/disciplinas/{id}/assuntos',   [SubjectController::class, 'storeTopic']);
    $r->post('/assuntos/{id}',               [SubjectController::class, 'updateTopic']);
    $r->post('/assuntos/{id}/excluir',       [SubjectController::class, 'destroyTopic']);

    // Aulas
    $r->get('/aulas',                    [LessonController::class, 'index']);
    $r->get('/aulas/nova',               [LessonController::class, 'create']);
    $r->post('/aulas',                   [LessonController::class, 'store']);
    $r->get('/aulas/{id}/editar',        [LessonController::class, 'edit']);
    $r->post('/aulas/{id}',              [LessonController::class, 'update']);
    $r->get('/aulas/{id}/frequencia',    [LessonController::class, 'attendance']);
    $r->post('/aulas/{id}/frequencia',   [LessonController::class, 'saveAttendance']);
    $r->post('/aulas/{id}/excluir',      [LessonController::class, 'destroy']);

    // Avaliações
    $r->get('/avaliacoes',                   [AssessmentController::class, 'index']);
    $r->get('/avaliacoes/nova',              [AssessmentController::class, 'create']);
    $r->post('/avaliacoes',                  [AssessmentController::class, 'store']);
    $r->get('/avaliacoes/{id}',              [AssessmentController::class, 'show']);
    $r->get('/avaliacoes/{id}/editar',       [AssessmentController::class, 'edit']);
    $r->post('/avaliacoes/{id}',             [AssessmentController::class, 'update']);
    $r->get('/avaliacoes/{id}/questoes',     [AssessmentController::class, 'questions']);
    $r->post('/avaliacoes/{id}/questoes',    [AssessmentController::class, 'storeQuestion']);
    $r->post('/avaliacoes/{id}/questoes/lote', [AssessmentController::class, 'bulkQuestions']);
    $r->post('/avaliacoes/{id}/questoes/salvar', [AssessmentController::class, 'updateQuestions']);
    $r->get('/avaliacoes/{id}/resultados',   [AssessmentController::class, 'results']);
    $r->post('/avaliacoes/{id}/resultados',  [AssessmentController::class, 'saveResults']);
    $r->post('/avaliacoes/{id}/notas',       [AssessmentController::class, 'saveGrades']);
    $r->get('/avaliacoes/{id}/exportar',     [AssessmentController::class, 'export']);
    $r->post('/avaliacoes/{id}/excluir',     [AssessmentController::class, 'destroy']);

    // Questões (banco)
    $r->get('/questoes',              [QuestionController::class, 'index']);
    $r->get('/questoes/nova',         [QuestionController::class, 'create']);
    $r->post('/questoes',             [QuestionController::class, 'store']);
    $r->get('/questoes/{id}/editar',  [QuestionController::class, 'edit']);
    $r->post('/questoes/{id}',        [QuestionController::class, 'update']);
    $r->post('/questoes/{id}/excluir',[QuestionController::class, 'destroy']);

    // Relatórios
    $r->get('/relatorios',           [ReportController::class, 'index']);
    $r->get('/relatorios/exportar',  [ReportController::class, 'export']);
    $r->get('/relatorios/imprimir',  [ReportController::class, 'print']);

    // Gráficos e comparações
    $r->get('/graficos',   [ChartController::class, 'index']);
    $r->get('/comparacao', [ChartController::class, 'compare']);

    // API JSON (séries dos gráficos)
    $r->get('/api/series/{tipo}',   [ApiController::class, 'series']);
    $r->get('/api/assuntos/{id}',   [ApiController::class, 'topicsBySubject']);
    $r->get('/api/ofertas',         [ApiController::class, 'classSubjects']);
});

// ---------------------------------------------------- configurações (admin)
$router->group(['auth', 'role:admin'], static function ($r) {
    $r->get('/configuracoes',                 [SettingsController::class, 'index']);
    $r->post('/configuracoes',                [SettingsController::class, 'update']);
    $r->post('/configuracoes/restaurar',      [SettingsController::class, 'reset']);
    $r->get('/configuracoes/usuarios',        [SettingsController::class, 'users']);
    $r->post('/configuracoes/usuarios',       [SettingsController::class, 'storeUser']);
    $r->post('/configuracoes/usuarios/{id}',  [SettingsController::class, 'updateUser']);
    $r->post('/configuracoes/usuarios/{id}/senha',   [SettingsController::class, 'resetUserPassword']);
    $r->post('/configuracoes/usuarios/{id}/excluir', [SettingsController::class, 'destroyUser']);
});
