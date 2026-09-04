<?php
namespace App\Models;

use App\Core\Database;

/**
 * Notas consolidadas. A nota é sempre derivada das respostas quando elas
 * existem (regra 6 do escopo: cálculo automático); lançamentos diretos ficam
 * marcados como manuais e não são sobrescritos.
 */
class Grade
{
    public static function forAssessment(int $assessmentId): array
    {
        $rows = Database::all('SELECT * FROM grades WHERE assessment_id = ?', [$assessmentId]);
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[(int) $row['student_id']] = $row;
        }
        return $byStudent;
    }

    public static function forStudent(int $studentId, array $filters = []): array
    {
        $where  = ['g.student_id = :sid'];
        $params = ['sid' => $studentId];
        if (!empty($filters['disciplina'])) {
            $where[] = 'cs.subject_id = :disciplina';
            $params['disciplina'] = (int) $filters['disciplina'];
        }
        if (!empty($filters['turma'])) {
            $where[] = 'cs.class_id = :turma';
            $params['turma'] = (int) $filters['turma'];
        }
        if (!empty($filters['tipo'])) {
            $where[] = 'a.type = :tipo';
            $params['tipo'] = $filters['tipo'];
        }
        if (!empty($filters['inicio'])) {
            $where[] = 'a.assessment_date >= :inicio';
            $params['inicio'] = $filters['inicio'];
        }
        if (!empty($filters['fim'])) {
            $where[] = 'a.assessment_date <= :fim';
            $params['fim'] = $filters['fim'];
        }
        return Database::all(
            'SELECT g.*, a.name AS assessment_name, a.assessment_date, a.type, a.max_score, a.weight,
                    s.name AS subject_name, s.id AS subject_id, c.code AS class_code, c.id AS class_id
               FROM grades g
               JOIN assessments a ON a.id = g.assessment_id
               JOIN class_subjects cs ON cs.id = a.class_subject_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN classes c ON c.id = cs.class_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY a.assessment_date, a.id',
            $params
        );
    }

    /** Lançamento manual de nota (sem detalhamento por questão). */
    public static function saveManual(int $assessmentId, int $studentId, ?float $score, float $maxScore, ?string $notes = null): void
    {
        if ($score === null) {
            Database::delete('grades', 'assessment_id = ? AND student_id = ? AND is_manual = 1', [$assessmentId, $studentId]);
            return;
        }
        $score      = max(0.0, min($score, $maxScore));
        $percentage = $maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0;
        self::upsert($assessmentId, $studentId, [
            'score'      => round($score, 2),
            'percentage' => $percentage,
            'is_manual'  => 1,
            'notes'      => $notes,
        ]);
    }

    /**
     * Recalcula a nota de um aluno a partir das respostas registradas.
     * O denominador é o valor máximo da avaliação; se a soma dos pontos das
     * questões diferir dele, a proporção é preservada.
     */
    public static function recalculate(int $assessmentId, int $studentId): ?array
    {
        $assessment = Database::first('SELECT max_score FROM assessments WHERE id = ?', [$assessmentId]);
        if (!$assessment) {
            return null;
        }

        $summary = Database::first(
            "SELECT COUNT(*) AS answered,
                    COALESCE(SUM(sa.score_earned), 0) AS earned,
                    COALESCE(SUM(q.points), 0) AS possible,
                    SUM(CASE WHEN sa.result = 'correta' THEN 1 ELSE 0 END) AS correct,
                    SUM(CASE WHEN sa.result = 'incorreta' THEN 1 ELSE 0 END) AS wrong,
                    SUM(CASE WHEN sa.result = 'nao_respondida' THEN 1 ELSE 0 END) AS blank
               FROM student_answers sa
               JOIN questions q ON q.id = sa.question_id
              WHERE q.assessment_id = ? AND sa.student_id = ?",
            [$assessmentId, $studentId]
        );

        if (!$summary || (int) $summary['answered'] === 0) {
            // Sem respostas: remove apenas notas calculadas, preserva as manuais.
            Database::delete('grades', 'assessment_id = ? AND student_id = ? AND is_manual = 0', [$assessmentId, $studentId]);
            return null;
        }

        $possible   = (float) $summary['possible'];
        $earned     = (float) $summary['earned'];
        $maxScore   = (float) $assessment['max_score'];
        $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : 0.0;
        $score      = round($percentage / 100 * $maxScore, 2);

        self::upsert($assessmentId, $studentId, [
            'score'         => $score,
            'percentage'    => $percentage,
            'correct_count' => (int) $summary['correct'],
            'wrong_count'   => (int) $summary['wrong'],
            'blank_count'   => (int) $summary['blank'],
            'is_manual'     => 0,
        ]);

        return ['score' => $score, 'percentage' => $percentage];
    }

    /** Recalcula todos os alunos de uma avaliação (após edição de questões/pontos). */
    public static function recalculateAssessment(int $assessmentId): int
    {
        $studentIds = array_column(Database::all(
            'SELECT DISTINCT sa.student_id
               FROM student_answers sa JOIN questions q ON q.id = sa.question_id
              WHERE q.assessment_id = ?',
            [$assessmentId]
        ), 'student_id');

        foreach ($studentIds as $studentId) {
            self::recalculate($assessmentId, (int) $studentId);
        }
        return count($studentIds);
    }

    private static function upsert(int $assessmentId, int $studentId, array $data): void
    {
        $existing = Database::value(
            'SELECT id FROM grades WHERE assessment_id = ? AND student_id = ?',
            [$assessmentId, $studentId]
        );
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            Database::update('grades', $data, 'id = :id', ['id' => (int) $existing]);
        } else {
            Database::insert('grades', array_merge([
                'assessment_id' => $assessmentId,
                'student_id'    => $studentId,
                'correct_count' => 0,
                'wrong_count'   => 0,
                'blank_count'   => 0,
            ], $data));
        }
    }
}
