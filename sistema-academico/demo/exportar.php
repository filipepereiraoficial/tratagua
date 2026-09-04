<?php
/**
 * Gera os dados da demonstração a partir do banco do próprio sistema.
 *
 *   php database/migrate.php --fresh --seed --demo
 *   php demo/exportar.php
 *
 * Produz:
 *   demo/dados.json      — dataset embutido na página da demonstração
 *   demo/referencia.json — valores calculados pelo PHP, usados por verificar.js
 *                          para provar que o porte em JavaScript não divergiu
 */
define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(function($c){ if(str_starts_with($c,'App\\')) { $f=APP_ROOT.'/src/'.str_replace('\\','/',substr($c,4)).'.php'; if(is_file($f)) require_once $f; }});
require APP_ROOT.'/src/helpers.php';
$config = require APP_ROOT.'/config/config.php';
App\Core\Database::connect($config['db']);
use App\Core\Database as DB;
use App\Services\AnalyticsService as A;
use App\Services\RankingService as R;
use App\Services\AlertService as AL;

$dados = [
  'curso'        => DB::first('SELECT id,name FROM courses'),
  'turma'        => DB::first('SELECT id,code,name,year,period,start_date,end_date,status FROM classes'),
  'disciplina'   => DB::first('SELECT id,name,description,workload_hours FROM subjects'),
  'topicos'      => DB::all('SELECT id,parent_id,subject_id,name FROM topics ORDER BY id'),
  'alunos'       => DB::all('SELECT id,full_name,email,phone,birth_date,enrolled_at,status,notes FROM students ORDER BY full_name'),
  'aulas'        => DB::all('SELECT id,title,lesson_date,content,duration_minutes FROM lessons ORDER BY lesson_date'),
  'aula_topicos' => DB::all('SELECT lesson_id,topic_id FROM lesson_topics'),
  'presencas'    => DB::all('SELECT lesson_id,student_id,status,participation FROM attendances'),
  'avaliacoes'   => DB::all('SELECT id,name,type,assessment_date,max_score,weight,description,status FROM assessments ORDER BY assessment_date'),
  'questoes'     => DB::all('SELECT id,assessment_id,number,topic_id,difficulty,points,answer_key FROM questions ORDER BY assessment_id,number'),
  'respostas'    => DB::all('SELECT question_id,student_id,result,score_earned FROM student_answers'),
  'notas'        => DB::all('SELECT assessment_id,student_id,score,percentage,correct_count,wrong_count,blank_count FROM grades'),
];

// Valores de referência calculados pelo PHP — servem para conferir a versão JS.
$ranking = R::build([]);
$referencia = [
  'media_geral'   => A::overallAverage([]),
  'acertos'       => A::answerTotals([]),
  'distribuicao'  => A::performanceDistribution([]),
  'por_avaliacao' => array_map(fn($r)=>['nome'=>$r['assessment_name'],'media'=>round((float)$r['media'],2)], A::assessmentAverages([])),
  'por_dificuldade'=> A::difficultyPerformance([]),
  'assuntos'      => array_map(fn($t)=>['nome'=>$t['topic_name'],'aprov'=>$t['aproveitamento'],'resp'=>$t['respondidas'],'acertos'=>$t['acertos'],'erros'=>$t['erros'],'alunos'=>$t['alunos'],'classe'=>$t['classificacao']], A::topicPerformance([],'desc')),
  'ranking'       => array_map(fn($r)=>[
      'nome'=>$r['full_name'],'posicao'=>$r['posicao'],'avaliacoes'=>$r['avaliacoes'],
      'media'=>$r['media'],'freq'=>$r['frequencia'],'evolucao'=>$r['evolucao_recente'],
      'slope'=>$r['evolucao_slope'],'desvio'=>$r['desvio'],
      'score_ev'=>$r['score_evolucao'],'score_cons'=>$r['score_consistencia'],
      'indice'=>$r['indice'],'classe'=>$r['classificacao'],
      'dominados'=>$r['dominados'],'intermediarios'=>$r['intermediarios'],'dificuldades'=>$r['dificuldades'],
      'pct_acertos'=>$r['acertos']['pct_acertos'],'pct_erros'=>$r['acertos']['pct_erros'],
    ], $ranking),
  'alertas'       => array_map(fn($a)=>['sev'=>$a['severity'],'titulo'=>$a['title'],'msg'=>$a['message']], AL::generate([], $ranking)),
];

file_put_contents(APP_ROOT.'/demo/dados.json', json_encode($dados, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
file_put_contents(APP_ROOT.'/demo/referencia.json', json_encode($referencia, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "dados.json: ", strlen(json_encode($dados)), " bytes\n";
echo "referência: ", count($referencia['ranking']), " alunos, ", count($referencia['alertas']), " alertas, ", count($referencia['assuntos']), " assuntos\n";
