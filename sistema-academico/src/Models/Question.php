<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Question
{
    public const DIFFICULTIES = ['facil', 'medio', 'dificil'];

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT q.*, s.name AS subject_name, t.name AS topic_name, p.name AS parent_topic_name,
                    a.name AS assessment_name
               FROM questions q
               JOIN subjects s ON s.id = q.subject_id
               LEFT JOIN topics t ON t.id = q.topic_id
               LEFT JOIN topics p ON p.id = t.parent_id
               LEFT JOIN assessments a ON a.id = q.assessment_id
              WHERE q.id = ?',
            [$id]
        );
    }

    public static function forAssessment(int $assessmentId): array
    {
        return Database::all(
            'SELECT q.*, t.name AS topic_name, p.name AS parent_topic_name
               FROM questions q
               LEFT JOIN topics t ON t.id = q.topic_id
               LEFT JOIN topics p ON p.id = t.parent_id
              WHERE q.assessment_id = ?
              ORDER BY COALESCE(q.number, q.id), q.id',
            [$assessmentId]
        );
    }

    /** Banco de questões com estatística real de acerto. */
    public static function search(array $filters = [], int $limit = 200): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!empty($filters['disciplina'])) {
            $where[] = 'q.subject_id = :disciplina';
            $params['disciplina'] = (int) $filters['disciplina'];
        }
        if (!empty($filters['assunto'])) {
            $where[] = '(q.topic_id = :assunto OR t.parent_id = :assunto)';
            $params['assunto'] = (int) $filters['assunto'];
        }
        if (!empty($filters['dificuldade'])) {
            $where[] = 'q.difficulty = :dificuldade';
            $params['dificuldade'] = $filters['dificuldade'];
        }
        if (!empty($filters['avaliacao'])) {
            $where[] = 'q.assessment_id = :avaliacao';
            $params['avaliacao'] = (int) $filters['avaliacao'];
        }
        if (!empty($filters['busca'])) {
            $where[] = 'LOWER(q.statement) LIKE :busca';
            $params['busca'] = '%' . mb_strtolower($filters['busca']) . '%';
        }
        if (($filters['origem'] ?? '') === 'banco') {
            $where[] = 'q.assessment_id IS NULL';
        } elseif (($filters['origem'] ?? '') === 'avaliacao') {
            $where[] = 'q.assessment_id IS NOT NULL';
        }

        return Database::all(
            "SELECT q.*, s.name AS subject_name, t.name AS topic_name, p.name AS parent_topic_name,
                    a.name AS assessment_name, a.assessment_date,
                    (SELECT COUNT(*) FROM student_answers sa WHERE sa.question_id = q.id) AS answers_count,
                    (SELECT COUNT(*) FROM student_answers sa WHERE sa.question_id = q.id AND sa.result = 'correta') AS correct_count
               FROM questions q
               JOIN subjects s ON s.id = q.subject_id
               LEFT JOIN topics t ON t.id = q.topic_id
               LEFT JOIN topics p ON p.id = t.parent_id
               LEFT JOIN assessments a ON a.id = q.assessment_id
              WHERE " . implode(' AND ', $where) . '
              ORDER BY s.name, a.assessment_date DESC, COALESCE(q.number, q.id)
              LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('questions', [
            'assessment_id' => !empty($data['assessment_id']) ? (int) $data['assessment_id'] : null,
            'subject_id'    => (int) $data['subject_id'],
            'topic_id'      => !empty($data['topic_id']) ? (int) $data['topic_id'] : null,
            'number'        => Input::int($data, 'number'),
            'statement'     => Input::text($data, 'statement'),
            'type'          => ($data['type'] ?? 'objetiva') === 'discursiva' ? 'discursiva' : 'objetiva',
            'difficulty'    => in_array($data['difficulty'] ?? '', self::DIFFICULTIES, true) ? $data['difficulty'] : 'medio',
            'points'        => (float) ($data['points'] !== '' ? $data['points'] : 1),
            'answer_key'    => Input::text($data, 'answer_key'),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('questions', [
            'subject_id' => (int) $data['subject_id'],
            'topic_id'   => !empty($data['topic_id']) ? (int) $data['topic_id'] : null,
            'number'     => Input::int($data, 'number'),
            'statement'  => Input::text($data, 'statement'),
            'type'       => ($data['type'] ?? 'objetiva') === 'discursiva' ? 'discursiva' : 'objetiva',
            'difficulty' => in_array($data['difficulty'] ?? '', self::DIFFICULTIES, true) ? $data['difficulty'] : 'medio',
            'points'     => (float) ($data['points'] !== '' ? $data['points'] : 1),
            'answer_key' => Input::text($data, 'answer_key'),
        ], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('questions', 'id = ?', [$id]);
    }

    /**
     * Cria rapidamente N questões numeradas em uma avaliação — o caminho mais
     * comum: o professor já tem a prova pronta e só precisa mapear tópicos.
     */
    public static function bulkCreate(int $assessmentId, int $subjectId, int $quantity, float $points, string $difficulty, ?int $topicId = null): int
    {
        $start = (int) Database::value(
            'SELECT COALESCE(MAX(number), 0) FROM questions WHERE assessment_id = ?',
            [$assessmentId], 0
        );
        $created = 0;
        Database::transaction(static function () use ($assessmentId, $subjectId, $quantity, $points, $difficulty, $topicId, $start, &$created) {
            for ($i = 1; $i <= $quantity; $i++) {
                Database::insert('questions', [
                    'assessment_id' => $assessmentId,
                    'subject_id'    => $subjectId,
                    'topic_id'      => $topicId,
                    'number'        => $start + $i,
                    'type'          => 'objetiva',
                    'difficulty'    => in_array($difficulty, self::DIFFICULTIES, true) ? $difficulty : 'medio',
                    'points'        => $points,
                ]);
                $created++;
            }
        });
        return $created;
    }

    public static function options(int $questionId): array
    {
        return Database::all('SELECT * FROM question_options WHERE question_id = ? ORDER BY letter', [$questionId]);
    }

    public static function syncOptions(int $questionId, array $options): void
    {
        Database::transaction(static function () use ($questionId, $options) {
            Database::delete('question_options', 'question_id = ?', [$questionId]);
            foreach ($options as $letter => $option) {
                $content = trim((string) ($option['content'] ?? ''));
                if ($content === '') {
                    continue;
                }
                Database::insert('question_options', [
                    'question_id' => $questionId,
                    'letter'      => (string) $letter,
                    'content'     => $content,
                    'is_correct'  => !empty($option['is_correct']) ? 1 : 0,
                ]);
            }
        });
    }
}
