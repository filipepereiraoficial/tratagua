<?php
namespace App\Models;

use App\Core\Database;

/** Oferta = disciplina dentro de uma turma. Ponto de ancoragem de aulas e avaliações. */
class ClassSubject
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT cs.*, c.code AS class_code, c.name AS class_name, c.id AS class_id,
                    s.name AS subject_name, s.id AS subject_id, co.name AS course_name
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN courses co ON co.id = c.course_id
              WHERE cs.id = ?',
            [$id]
        );
    }

    /** Todas as ofertas, para popular selects de aula/avaliação. */
    public static function options(array $filters = []): array
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
        if (isset($filters['ofertas'])) {
            $ids = array_filter(array_map('intval', (array) $filters['ofertas']));
            $where[] = $ids === [] ? '1 = 0' : 'cs.id IN (' . implode(',', $ids) . ')';
        }
        return Database::all(
            'SELECT cs.id, cs.class_id, cs.subject_id, c.code AS class_code, s.name AS subject_name
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.code, s.name',
            $params
        );
    }

    public static function updateTeacher(int $id, ?int $teacherId): void
    {
        Database::update('class_subjects', ['teacher_user_id' => $teacherId], 'id = :id', ['id' => $id]);
    }
}
