<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Assessment
{
    public const TYPES = ['prova', 'simulado', 'atividade', 'exercicio', 'diagnostica', 'revisao'];

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT a.*, cs.class_id, cs.subject_id, c.code AS class_code, c.name AS class_name,
                    s.name AS subject_name, co.name AS course_name
               FROM assessments a
               JOIN class_subjects cs ON cs.id = a.class_subject_id
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN courses co ON co.id = c.course_id
              WHERE a.id = ?',
            [$id]
        );
    }

    public static function search(array $filters = [], int $limit = 0): array
    {
        [$where, $params] = self::buildWhere($filters);
        $sql = 'SELECT a.*, c.code AS class_code, c.id AS class_id, s.name AS subject_name, s.id AS subject_id,
                       (SELECT COUNT(*) FROM questions q WHERE q.assessment_id = a.id) AS questions_count,
                       (SELECT COUNT(*) FROM grades g WHERE g.assessment_id = a.id) AS graded_count,
                       (SELECT AVG(g.percentage) FROM grades g WHERE g.assessment_id = a.id) AS avg_percentage
                  FROM assessments a
                  JOIN class_subjects cs ON cs.id = a.class_subject_id
                  JOIN classes c ON c.id = cs.class_id
                  JOIN subjects s ON s.id = cs.subject_id
                 WHERE ' . $where . '
                 ORDER BY a.assessment_date DESC, a.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return Database::all($sql, $params);
    }

    public static function countSearch(array $filters = []): int
    {
        [$where, $params] = self::buildWhere($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM assessments a
               JOIN class_subjects cs ON cs.id = a.class_subject_id
              WHERE ' . $where,
            $params, 0
        );
    }

    private static function buildWhere(array $filters): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!empty($filters['turma'])) {
            $where[] = 'cs.class_id = :turma';
            $params['turma'] = (int) $filters['turma'];
        }
        if (!empty($filters['disciplina'])) {
            $where[] = 'cs.subject_id = :disciplina';
            $params['disciplina'] = (int) $filters['disciplina'];
        }
        if (!empty($filters['tipo'])) {
            $where[] = 'a.type = :tipo';
            $params['tipo'] = $filters['tipo'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['inicio'])) {
            $where[] = 'a.assessment_date >= :inicio';
            $params['inicio'] = $filters['inicio'];
        }
        if (!empty($filters['fim'])) {
            $where[] = 'a.assessment_date <= :fim';
            $params['fim'] = $filters['fim'];
        }
        if (!empty($filters['aluno'])) {
            $where[] = 'cs.class_id IN (SELECT e.class_id FROM enrollments e WHERE e.student_id = :aluno)';
            $params['aluno'] = (int) $filters['aluno'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function create(array $data): int
    {
        return Database::insert('assessments', [
            'class_subject_id' => (int) $data['class_subject_id'],
            'name'             => $data['name'],
            'type'             => in_array($data['type'] ?? '', self::TYPES, true) ? $data['type'] : 'prova',
            'assessment_date'  => $data['assessment_date'],
            'max_score'        => (float) $data['max_score'],
            'weight'           => Input::float($data, 'weight', 1.0),
            'description'      => Input::text($data, 'description'),
            'status'           => $data['status'] ?? 'planejada',
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('assessments', [
            'class_subject_id' => (int) $data['class_subject_id'],
            'name'             => $data['name'],
            'type'             => in_array($data['type'] ?? '', self::TYPES, true) ? $data['type'] : 'prova',
            'assessment_date'  => $data['assessment_date'],
            'max_score'        => (float) $data['max_score'],
            'weight'           => Input::float($data, 'weight', 1.0),
            'description'      => Input::text($data, 'description'),
            'status'           => $data['status'] ?? 'planejada',
        ], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('assessments', 'id = ?', [$id]);
    }

    /** Soma dos pontos das questões cadastradas — usada para alertar divergência com o valor máximo. */
    public static function questionPoints(int $assessmentId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(points), 0) FROM questions WHERE assessment_id = ?',
            [$assessmentId], 0
        );
    }

    /** Tópicos efetivamente avaliados (derivados das questões). */
    public static function topics(int $assessmentId): array
    {
        return Database::all(
            'SELECT t.id, t.name, COUNT(q.id) AS questions
               FROM questions q JOIN topics t ON t.id = q.topic_id
              WHERE q.assessment_id = ?
              GROUP BY t.id, t.name ORDER BY t.name',
            [$assessmentId]
        );
    }
}
