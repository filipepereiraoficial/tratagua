<?php
namespace App\Models;

use App\Core\Database;

/** Assuntos (parent_id nulo) e tópicos (filhos de um assunto). */
class Topic
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT t.*, s.name AS subject_name, p.name AS parent_name
               FROM topics t
               JOIN subjects s ON s.id = t.subject_id
               LEFT JOIN topics p ON p.id = t.parent_id
              WHERE t.id = ?',
            [$id]
        );
    }

    public static function bySubject(int $subjectId): array
    {
        return Database::all(
            'SELECT t.*, p.name AS parent_name
               FROM topics t LEFT JOIN topics p ON p.id = t.parent_id
              WHERE t.subject_id = ?
              ORDER BY COALESCE(p.sort_order, t.sort_order), COALESCE(p.name, t.name), t.parent_id IS NOT NULL, t.sort_order, t.name',
            [$subjectId]
        );
    }

    /** Árvore assunto → tópicos, pronta para exibição. */
    public static function treeBySubject(int $subjectId): array
    {
        $rows = self::bySubject($subjectId);
        $tree = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $tree[(int) $row['id']] = $row + ['children' => []];
            }
        }
        foreach ($rows as $row) {
            if ($row['parent_id'] !== null) {
                $parent = (int) $row['parent_id'];
                if (isset($tree[$parent])) {
                    $tree[$parent]['children'][] = $row;
                } else {
                    $tree[(int) $row['id']] = $row + ['children' => []];
                }
            }
        }
        return array_values($tree);
    }

    /** Opções achatadas com indentação para <select>. */
    public static function optionsBySubject(int $subjectId): array
    {
        $options = [];
        foreach (self::treeBySubject($subjectId) as $parent) {
            $options[] = ['id' => $parent['id'], 'label' => $parent['name'], 'is_parent' => true];
            foreach ($parent['children'] as $child) {
                $options[] = ['id' => $child['id'], 'label' => '— ' . $child['name'], 'is_parent' => false];
            }
        }
        return $options;
    }

    public static function all(): array
    {
        return Database::all(
            'SELECT t.id, t.name, t.parent_id, t.subject_id, s.name AS subject_name
               FROM topics t JOIN subjects s ON s.id = t.subject_id
              ORDER BY s.name, t.name'
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('topics', [
            'subject_id'  => (int) $data['subject_id'],
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null ?: null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('topics', [
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null ?: null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('topics', 'id = ?', [$id]);
    }

    public static function questionCount(int $topicId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM questions WHERE topic_id = ?', [$topicId], 0);
    }
}
