<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Lesson
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT l.*, cs.class_id, cs.subject_id, c.code AS class_code, s.name AS subject_name
               FROM lessons l
               JOIN class_subjects cs ON cs.id = l.class_subject_id
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
              WHERE l.id = ?',
            [$id]
        );
    }

    /**
     * Histórico de aulas com filtros por turma, disciplina, período e aluno.
     * O filtro por aluno usa o vínculo corrente com a turma da aula.
     */
    public static function search(array $filters = [], int $limit = 0): array
    {
        [$where, $params] = self::buildWhere($filters);
        $sql = 'SELECT l.*, c.code AS class_code, c.id AS class_id, s.name AS subject_name, s.id AS subject_id,
                       (SELECT COUNT(*) FROM attendances a WHERE a.lesson_id = l.id) AS attendance_count,
                       (SELECT COUNT(*) FROM attendances a WHERE a.lesson_id = l.id AND a.status IN (\'presente\',\'atraso\')) AS present_count
                  FROM lessons l
                  JOIN class_subjects cs ON cs.id = l.class_subject_id
                  JOIN classes c ON c.id = cs.class_id
                  JOIN subjects s ON s.id = cs.subject_id
                 WHERE ' . $where . '
                 ORDER BY l.lesson_date DESC, l.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return Database::all($sql, $params);
    }

    public static function countSearch(array $filters = []): int
    {
        [$where, $params] = self::buildWhere($filters);
        return (int) Database::value(
            'SELECT COUNT(*) FROM lessons l
               JOIN class_subjects cs ON cs.id = l.class_subject_id
               JOIN classes c ON c.id = cs.class_id
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
        if (!empty($filters['inicio'])) {
            $where[] = 'l.lesson_date >= :inicio';
            $params['inicio'] = $filters['inicio'];
        }
        if (!empty($filters['fim'])) {
            $where[] = 'l.lesson_date <= :fim';
            $params['fim'] = $filters['fim'];
        }
        if (!empty($filters['aluno'])) {
            $where[] = 'cs.class_id IN (SELECT e.class_id FROM enrollments e WHERE e.student_id = :aluno)';
            $params['aluno'] = (int) $filters['aluno'];
        }
        if (isset($filters['ofertas'])) {
            // Escopo do professor.
            $ids = array_filter(array_map('intval', (array) $filters['ofertas']));
            $where[] = $ids === [] ? '1 = 0' : 'cs.id IN (' . implode(',', $ids) . ')';
        }
        return [implode(' AND ', $where), $params];
    }

    public static function create(array $data, array $topicIds = []): int
    {
        return Database::transaction(static function () use ($data, $topicIds) {
            $id = Database::insert('lessons', [
                'class_subject_id' => (int) $data['class_subject_id'],
                'title'            => $data['title'],
                'lesson_date'      => $data['lesson_date'],
                'content'          => Input::text($data, 'content'),
                'duration_minutes' => Input::int($data, 'duration_minutes'),
                'materials'        => Input::text($data, 'materials'),
                'notes'            => Input::text($data, 'notes'),
            ]);
            self::syncTopics($id, $topicIds);
            return $id;
        });
    }

    public static function update(int $id, array $data, array $topicIds = []): void
    {
        Database::transaction(static function () use ($id, $data, $topicIds) {
            Database::update('lessons', [
                'class_subject_id' => (int) $data['class_subject_id'],
                'title'            => $data['title'],
                'lesson_date'      => $data['lesson_date'],
                'content'          => Input::text($data, 'content'),
                'duration_minutes' => Input::int($data, 'duration_minutes'),
                'materials'        => Input::text($data, 'materials'),
                'notes'            => Input::text($data, 'notes'),
            ], 'id = :id', ['id' => $id]);
            self::syncTopics($id, $topicIds);
        });
    }

    private static function syncTopics(int $lessonId, array $topicIds): void
    {
        Database::delete('lesson_topics', 'lesson_id = ?', [$lessonId]);
        foreach (array_unique(array_map('intval', $topicIds)) as $topicId) {
            if ($topicId > 0) {
                Database::insert('lesson_topics', ['lesson_id' => $lessonId, 'topic_id' => $topicId]);
            }
        }
    }

    public static function topics(int $lessonId): array
    {
        return Database::all(
            'SELECT t.* FROM lesson_topics lt JOIN topics t ON t.id = lt.topic_id
              WHERE lt.lesson_id = ? ORDER BY t.name',
            [$lessonId]
        );
    }

    public static function topicIds(int $lessonId): array
    {
        return array_map('intval', array_column(
            Database::all('SELECT topic_id FROM lesson_topics WHERE lesson_id = ?', [$lessonId]),
            'topic_id'
        ));
    }

    public static function delete(int $id): void
    {
        Database::delete('lessons', 'id = ?', [$id]);
    }
}
