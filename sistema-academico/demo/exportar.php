<?php
/**
 * Gera os dados da demonstração a partir do banco do próprio sistema.
 *
 *   php database/migrate.php --fresh --seed --demo
 *   php demo/exportar.php
 *
 * Produz:
 *   demo/dados.json      — dataset embutido na página da demonstração
 *   demo/referencia.json — valores calculados pelo PHP para cada perfil, usados
 *                          por verificar.js para provar que o porte em
 *                          JavaScript não divergiu — inclusive no escopo.
 */
define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(function ($c) {
    if (str_starts_with($c, 'App\\')) {
        $f = APP_ROOT . '/src/' . str_replace('\\', '/', substr($c, 4)) . '.php';
        if (is_file($f)) { require_once $f; }
    }
});
require APP_ROOT . '/src/helpers.php';
$config = require APP_ROOT . '/config/config.php';
App\Core\Database::connect($config['db']);

use App\Core\Database as DB;
use App\Services\AlertService as AL;
use App\Services\AnalyticsService as A;
use App\Services\RankingService as R;

$dados = [
    'cursos'      => DB::all('SELECT id, name, description FROM courses ORDER BY id'),
    'turmas'      => DB::all('SELECT id, code, name, course_id, year, period, start_date, end_date, status FROM classes ORDER BY id'),
    'disciplinas' => DB::all('SELECT id, name, description, workload_hours, teacher_user_id FROM subjects ORDER BY id'),
    'ofertas'     => DB::all('SELECT id, class_id, subject_id, teacher_user_id FROM class_subjects ORDER BY id'),
    'professores' => DB::all("SELECT id, name, email, role, qualification, phone, is_active
                                FROM users WHERE role IN ('professor','admin') ORDER BY id"),
    'topicos'     => DB::all('SELECT id, parent_id, subject_id, name FROM topics ORDER BY id'),
    'alunos'      => DB::all('SELECT s.id, s.full_name, s.email, s.phone, s.birth_date, s.enrolled_at, s.status, s.notes,
                                     e.class_id
                                FROM students s
                                LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
                               ORDER BY s.full_name'),
    'aulas'       => DB::all('SELECT id, class_subject_id, title, lesson_date, content, duration_minutes FROM lessons ORDER BY lesson_date, id'),
    'aula_topicos'=> DB::all('SELECT lesson_id, topic_id FROM lesson_topics'),
    'presencas'   => DB::all('SELECT lesson_id, student_id, status, participation FROM attendances'),
    'avaliacoes'  => DB::all('SELECT id, class_subject_id, name, type, assessment_date, max_score, weight, description, status
                                FROM assessments ORDER BY assessment_date, id'),
    'questoes'    => DB::all('SELECT id, assessment_id, number, topic_id, difficulty, points, answer_key FROM questions ORDER BY assessment_id, number'),
    'respostas'   => DB::all('SELECT question_id, student_id, result, score_earned FROM student_answers'),
    'notas'       => DB::all('SELECT assessment_id, student_id, score, percentage, correct_count, wrong_count, blank_count FROM grades'),
    'acompanhamentos' => DB::all(
        'SELECT id, student_id, class_subject_id, author_user_id, type, status, title, description,
                action_taken, due_date, result_note, baseline_media, baseline_frequencia, created_at
           FROM interventions ORDER BY id'),
];

/** Um bloco de referência por perfil, para conferir também o recorte. */
$referencia = static function (array $filtros) {
    $ranking = R::build($filtros);
    return [
        'media_geral'    => A::overallAverage($filtros),
        'acertos'        => A::answerTotals($filtros),
        'distribuicao'   => A::performanceDistribution($filtros),
        'por_avaliacao'  => array_map(fn ($r) => ['nome' => $r['assessment_name'], 'media' => $r['media'] === null ? null : round((float) $r['media'], 2)], A::assessmentAverages($filtros)),
        'por_disciplina' => array_map(fn ($r) => ['nome' => $r['subject_name'], 'media' => $r['media'] === null ? null : round((float) $r['media'], 2)], A::subjectAverages($filtros)),
        'por_turma'      => array_map(fn ($r) => ['nome' => $r['class_code'], 'media' => $r['media'] === null ? null : round((float) $r['media'], 2)], A::classAverages($filtros)),
        'por_dificuldade'=> A::difficultyPerformance($filtros),
        'assuntos'       => array_map(fn ($t) => ['nome' => $t['topic_name'], 'aprov' => $t['aproveitamento'], 'resp' => $t['respondidas'], 'acertos' => $t['acertos'], 'classe' => $t['classificacao']], A::topicPerformance($filtros, 'desc')),
        'perda_avaliacao'=> array_map(fn ($p) => ['nome' => $p['assessment_name'], 'perdidos' => $p['pontos_perdidos'], 'aprov' => $p['aproveitamento']], A::assessmentPointLoss($filtros, 50)),
        'perda_aluno'    => array_map(fn ($p) => ['nome' => $p['full_name'], 'perdidos' => $p['perdidos'], 'pior' => $p['pior_avaliacao']], A::studentPointLoss($filtros)),
        'ranking'        => array_map(fn ($r) => [
            'nome' => $r['full_name'], 'posicao' => $r['posicao'], 'avaliacoes' => $r['avaliacoes'],
            'media' => $r['media'], 'freq' => $r['frequencia'], 'evolucao' => $r['evolucao_recente'],
            'slope' => $r['evolucao_slope'], 'desvio' => $r['desvio'],
            'score_ev' => $r['score_evolucao'], 'score_cons' => $r['score_consistencia'],
            'indice' => $r['indice'], 'classe' => $r['classificacao'],
            'dominados' => $r['dominados'], 'intermediarios' => $r['intermediarios'], 'dificuldades' => $r['dificuldades'],
            'pct_acertos' => $r['acertos']['pct_acertos'], 'pct_erros' => $r['acertos']['pct_erros'],
        ], $ranking),
        'alertas'        => array_map(fn ($a) => ['sev' => $a['severity'], 'titulo' => $a['title'], 'msg' => $a['message']], AL::generate($filtros, $ranking)),
    ];
};

$ofertasPorProfessor = [];
foreach ($dados['professores'] as $professor) {
    $ids = array_map('intval', array_column(DB::all(
        'SELECT cs.id FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id
          WHERE cs.teacher_user_id = :u OR (cs.teacher_user_id IS NULL AND s.teacher_user_id = :u)',
        ['u' => (int) $professor['id']]
    ), 'id'));
    $ofertasPorProfessor[(int) $professor['id']] = $ids;
}

$saida = ['admin' => $referencia([])];
foreach ($ofertasPorProfessor as $id => $ids) {
    if ($ids === []) { continue; }
    $saida['professor_' . $id] = $referencia(['ofertas' => $ids]);
}
$saida['_ofertas_por_professor'] = $ofertasPorProfessor;

file_put_contents(APP_ROOT . '/demo/dados.json', json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents(APP_ROOT . '/demo/referencia.json', json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo 'dados.json: ', number_format(strlen(json_encode($dados)) / 1024, 1), " KB\n";
foreach ($saida as $perfil => $bloco) {
    if ($perfil === '_ofertas_por_professor') { continue; }
    printf("  %-14s %d aluno(s), %d alerta(s), %d assunto(s), média %s\n",
        $perfil, count($bloco['ranking']), count($bloco['alertas']),
        count($bloco['assuntos']), $bloco['media_geral'] ?? '—');
}
