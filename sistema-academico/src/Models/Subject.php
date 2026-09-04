<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Subject
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT s.*, u.name AS teacher_name
               FROM subjects s LEFT JOIN users u ON u.id = s.teacher_user_id
              WHERE s.id = ?',
            [$id]
        );
    }

    public static function search(array $filters = []): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!empty($filters['busca'])) {
            $where[] = 'LOWER(s.name) LIKE :busca';
            $params['busca'] = '%' . mb_strtolower($filters['busca']) . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        return Database::all(
            'SELECT s.*, u.name AS teacher_name,
                    (SELECT COUNT(*) FROM class_subjects cs WHERE cs.subject_id = s.id) AS classes_count,
                    (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) AS topics_count,
                    (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS questions_count
               FROM subjects s
               LEFT JOIN users u ON u.id = s.teacher_user_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name',
            $params
        );
    }

    public static function options(): array
    {
        return Database::all("SELECT id, name FROM subjects WHERE status = 'ativa' ORDER BY name");
    }

    public static function create(array $data): int
    {
        return Database::insert('subjects', [
            'name'            => $data['name'],
            'description'     => Input::text($data, 'description'),
            'teacher_user_id' => Input::id($data, 'teacher_user_id'),
            'workload_hours'  => Input::int($data, 'workload_hours'),
            'status'          => $data['status'] ?? 'ativa',
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('subjects', [
            'name'            => $data['name'],
            'description'     => Input::text($data, 'description'),
            'teacher_user_id' => Input::id($data, 'teacher_user_id'),
            'workload_hours'  => Input::int($data, 'workload_hours'),
            'status'          => $data['status'] ?? 'ativa',
        ], 'id = :id', ['id' => $id]);
    }

    public static function blockers(int $id): array
    {
        $blockers = [];
        $classes = (int) Database::value('SELECT COUNT(*) FROM class_subjects WHERE subject_id = ?', [$id], 0);
        if ($classes > 0) {
            $blockers[] = "{$classes} turma(s) vinculada(s)";
        }
        $questions = (int) Database::value('SELECT COUNT(*) FROM questions WHERE subject_id = ?', [$id], 0);
        if ($questions > 0) {
            $blockers[] = "{$questions} questão(ões) cadastrada(s)";
        }
        return $blockers;
    }

    public static function delete(int $id): void
    {
        Database::delete('subjects', 'id = ?', [$id]);
    }

    /** Turmas em que a disciplina é ofertada. */
    public static function classes(int $subjectId): array
    {
        return Database::all(
            'SELECT cs.id AS class_subject_id, c.*, co.name AS course_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.is_current = 1) AS students_count
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN courses co ON co.id = c.course_id
              WHERE cs.subject_id = ?
              ORDER BY c.year DESC, c.code',
            [$subjectId]
        );
    }
}
