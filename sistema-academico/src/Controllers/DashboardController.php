<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Subject;
use App\Services\AlertService;
use App\Services\AnalyticsService;
use App\Services\RankingService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters(['curso', 'turma', 'disciplina', 'inicio', 'fim']);

        $ranking      = RankingService::build($filters);
        $counts       = RankingService::summarize($ranking);
        $alerts       = AlertService::generate($filters, $ranking);
        $counters     = AnalyticsService::globalCounters($filters);
        $answers      = AnalyticsService::answerTotals($filters);
        $movers       = RankingService::movers($ranking);

        $this->view('dashboard/index', [
            'title'        => 'Dashboard',
            'filters'      => $filters,
            'counters'     => $counters,
            'media_geral'  => AnalyticsService::overallAverage($filters),
            'acertos'      => $answers,
            'classificacao'=> $counts,
            'ranking'      => array_slice($ranking, 0, 10),
            'ranking_total'=> count($ranking),
            'alertas'      => array_slice($alerts, 0, 8),
            'alertas_total'=> count($alerts),
            'alertas_sev'  => AlertService::countBySeverity($alerts),
            'movers'       => $movers,
            'serie'          => AnalyticsService::assessmentAverages($filters),
            'por_disciplina' => AnalyticsService::subjectAverages($filters),
            'por_turma'      => AnalyticsService::classAverages($filters),
            'distribuicao'   => AnalyticsService::performanceDistribution($filters),
            'frequencia'     => AnalyticsService::attendanceByClass($filters),
            'assuntos'     => array_slice(AnalyticsService::topicPerformance($filters), 0, 8),
            'pesos'        => RankingService::weightsLabel(),
            'cursos'       => Course::options(),
            'turmas'       => ClassGroup::options(),
            'disciplinas'  => Subject::options(),
            'faixas'       => [
                'dominio'       => Setting::float('faixa_dominio'),
                'intermediario' => Setting::float('faixa_intermediario'),
            ],
        ]);
    }

    public function dismissAlert(): void
    {
        $key = (string) $this->request->input('alert_key', '');
        if ($key !== '') {
            AlertService::dismiss($key, Auth::id());
            Flash::success('Alerta marcado como tratado. Ele reaparece se a condição voltar a ocorrer com dados novos.');
        }
        $this->back();
    }

    public function restoreAlert(): void
    {
        $key = (string) $this->request->input('alert_key', '');
        if ($key !== '') {
            AlertService::restore($key);
            Flash::info('Alerta reativado.');
        }
        $this->back();
    }
}
