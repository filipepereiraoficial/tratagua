<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Course
{
    public static function all(): array
    {
        return Database::all(
            'SELECT c.*, (SELECT COUNT(*) FROM classes t WHERE t.course_id = c.id) AS classes_count
               FROM courses c ORDER BY c.name'
        );
    }

    public static function options(): array
    {
        return Database::all('SELECT id, name FROM courses ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM courses WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        return Database::insert('courses', [
            'name'           => $data['name'],
            'description'    => Input::text($data, 'description'),
            'workload_hours' => Input::int($data, 'workload_hours'),
            'status'         => $data['status'] ?? 'ativo',
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('courses', [
            'name'           => $data['name'],
            'description'    => Input::text($data, 'description'),
            'workload_hours' => Input::int($data, 'workload_hours'),
            'status'         => $data['status'] ?? 'ativo',
        ], 'id = :id', ['id' => $id]);
    }

    /** Impede exclusão que deixaria turmas órfãs (regra 4 do escopo). */
    public static function canDelete(int $id): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM classes WHERE course_id = ?', [$id], 0) === 0;
    }

    public static function delete(int $id): void
    {
        Database::delete('courses', 'id = ?', [$id]);
    }
}
