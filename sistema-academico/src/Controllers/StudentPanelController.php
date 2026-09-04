<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Scope;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Student;
use App\Services\AnalyticsService;
use App\Services\RankingService;

/**
 * Painel do aluno — leitura da própria evolução.
 *
 * Mostra desempenho, conteúdos dominados e com dificuldade, frequência e a
 * posição relativa. Não expõe o ranking nominal da turma nem os alertas
 * internos: o aluno vê a si mesmo, não os colegas.
 */
class StudentPanelController extends Controller
{
    public function index(): void
    {
        $studentId = Scope::studentId();
        if (!$studentId) {
            $this->view('student/sem-vinculo', ['title' => 'Painel do aluno']);
            return;
        }

        $aluno = Student::find($studentId);
        if (!$aluno) {
            $this->notFound('Cadastro de aluno não encontrado.');
        }

        $filters = $this->filters(['disciplina', 'inicio', 'fim']);
        $filters['aluno'] = $studentId;
        $resumo = AnalyticsService::studentSummary($studentId, $filters);

        $daTurma = $filters;
        unset($daTurma['aluno']);
        if ($aluno['class_id']) {
            $daTurma['turma'] = (int) $aluno['class_id'];
        }

        $posicao = $aluno['class_id']
            ? RankingService::positionInClass($studentId, (int) $aluno['class_id'], $filters)
            : ['posicao' => null, 'total' => 0, 'indice' => null];

        $this->view('student/panel', [
            'title'          => 'Minha evolução',
            'aluno'          => $aluno,
            'resumo'         => $resumo,
            'filters'        => $filters,
            'usuario'        => Auth::user(),
            'media_turma'    => AnalyticsService::overallAverage($daTurma),
            'serie_turma'    => AnalyticsService::assessmentAverages($daTurma),
            'por_disciplina' => AnalyticsService::studentSubjectPerformance($studentId, $filters),
            'por_dificuldade'=> AnalyticsService::difficultyPerformance($filters),
            'posicao'        => $posicao,
            'presencas'      => Attendance::historyForStudent($studentId, $filters),
            'faixas'         => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }
}
