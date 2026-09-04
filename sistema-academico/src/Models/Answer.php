<?php
namespace App\Models;

use App\Core\Database;

class Answer
{
    /** Respostas de um aluno em uma avaliação, indexadas por question_id. */
    public static function forStudentAssessment(int $assessmentId, int $studentId): array
    {
        $rows = Database::all(
            'SELECT sa.* FROM student_answers sa
               JOIN questions q ON q.id = sa.question_id
              WHERE q.assessment_id = ? AND sa.student_id = ?',
            [$assessmentId, $studentId]
        );
        $byQuestion = [];
        foreach ($rows as $row) {
            $byQuestion[(int) $row['question_id']] = $row;
        }
        return $byQuestion;
    }

    /** Matriz aluno × questão de uma avaliação (para a tela de resultados). */
    public static function matrixForAssessment(int $assessmentId): array
    {
        $rows = Database::all(
            'SELECT sa.student_id, sa.question_id, sa.result, sa.given_answer, sa.score_earned
               FROM student_answers sa
               JOIN questions q ON q.id = sa.question_id
              WHERE q.assessment_id = ?',
            [$assessmentId]
        );
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[(int) $row['student_id']][(int) $row['question_id']] = $row;
        }
        return $matrix;
    }

    /**
     * Grava as respostas de um aluno numa avaliação e recalcula a nota.
     * @param array<int, string> $results question_id => correta|incorreta|nao_respondida
     */
    public static function saveForStudent(int $assessmentId, int $studentId, array $results, array $givenAnswers = []): void
    {
        $valid = ['correta', 'incorreta', 'nao_respondida'];
        Database::transaction(static function () use ($assessmentId, $studentId, $results, $givenAnswers, $valid) {
            $questions = Database::all('SELECT id, points FROM questions WHERE assessment_id = ?', [$assessmentId]);
            $points = [];
            foreach ($questions as $question) {
                $points[(int) $question['id']] = (float) $question['points'];
            }

            foreach ($results as $questionId => $result) {
                $questionId = (int) $questionId;
                if (!isset($points[$questionId])) {
                    continue; // questão de outra avaliação: ignora
                }
                $result = in_array($result, $valid, true) ? $result : 'nao_respondida';
                $score  = $result === 'correta' ? $points[$questionId] : 0.0;
                $given  = trim((string) ($givenAnswers[$questionId] ?? '')) ?: null;

                $existing = Database::value(
                    'SELECT id FROM student_answers WHERE question_id = ? AND student_id = ?',
                    [$questionId, $studentId]
                );
                if ($existing) {
                    Database::update('student_answers', [
                        'result'       => $result,
                        'score_earned' => $score,
                        'given_answer' => $given,
                    ], 'id = :id', ['id' => (int) $existing]);
                } else {
                    Database::insert('student_answers', [
                        'question_id'  => $questionId,
                        'student_id'   => $studentId,
                        'result'       => $result,
                        'score_earned' => $score,
                        'given_answer' => $given,
                    ]);
                }
            }
        });

        Grade::recalculate($assessmentId, $studentId);
    }

    /** Remove todas as respostas de um aluno em uma avaliação. */
    public static function clearForStudent(int $assessmentId, int $studentId): void
    {
        Database::run(
            'DELETE FROM student_answers
              WHERE student_id = ?
                AND question_id IN (SELECT id FROM questions WHERE assessment_id = ?)',
            [$studentId, $assessmentId]
        );
        Grade::recalculate($assessmentId, $studentId);
    }

    /** Índice de acerto por questão de uma avaliação. */
    public static function statsByQuestion(int $assessmentId): array
    {
        return Database::all(
            "SELECT q.id, q.number, q.difficulty, q.points, q.statement,
                    t.name AS topic_name,
                    COUNT(sa.id) AS answered,
                    SUM(CASE WHEN sa.result = 'correta' THEN 1 ELSE 0 END) AS correct,
                    SUM(CASE WHEN sa.result = 'incorreta' THEN 1 ELSE 0 END) AS wrong,
                    SUM(CASE WHEN sa.result = 'nao_respondida' THEN 1 ELSE 0 END) AS blank
               FROM questions q
               LEFT JOIN student_answers sa ON sa.question_id = q.id
               LEFT JOIN topics t ON t.id = q.topic_id
              WHERE q.assessment_id = ?
              GROUP BY q.id, q.number, q.difficulty, q.points, q.statement, t.name
              ORDER BY COALESCE(q.number, q.id)",
            [$assessmentId]
        );
    }
}
