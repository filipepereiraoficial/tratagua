<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

class Student
{
    /** Colunas liberadas para ordenação (whitelist contra injeção em ORDER BY). */
    private const SORTABLE = [
        'nome'   => 's.full_name',
        'status' => 's.status',
        'turma'  => 'c.code',
        'criado' => 's.created_at',
    ];

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT s.*, c.id AS class_id, c.code AS class_code, c.name AS class_name,
                    co.name AS course_name, co.id AS course_id
               FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
               LEFT JOIN classes c ON c.id = e.class_id
               LEFT JOIN courses co ON co.id = c.course_id
              WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * Lista com filtros combináveis.
     * @param array{busca?:string,turma?:int,curso?:int,status?:string,sort?:string,dir?:string} $filters
     */
    public static function search(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        [$where, $params] = self::buildWhere($filters);

        $sort = self::SORTABLE[$filters['sort'] ?? 'nome'] ?? self::SORTABLE['nome'];
        $dir  = strtolower($filters['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $sql = "SELECT s.*, c.id AS class_id, c.code AS class_code, co.name AS course_name
                  FROM students s
                  LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
                  LEFT JOIN classes c ON c.id = e.class_id
                  LEFT JOIN courses co ON co.id = c.course_id
                 WHERE {$where}
                 ORDER BY {$sort} {$dir}";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        return Database::all($sql, $params);
    }

    public static function countSearch(array $filters = []): int
    {
        [$where, $params] = self::buildWhere($filters);
        return (int) Database::value(
            "SELECT COUNT(*) FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE {$where}",
            $params,
            0
        );
    }

    private static function buildWhere(array $filters): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filters['busca'])) {
            $where[] = '(LOWER(s.full_name) LIKE :busca OR LOWER(s.email) LIKE :busca OR s.document LIKE :busca)';
            $params['busca'] = '%' . mb_strtolower($filters['busca']) . '%';
        }
        if (!empty($filters['turma'])) {
            $where[] = 'c.id = :turma';
            $params['turma'] = (int) $filters['turma'];
        }
        if (!empty($filters['curso'])) {
            $where[] = 'c.course_id = :curso';
            $params['curso'] = (int) $filters['curso'];
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['sem_turma'])) {
            $where[] = 'c.id IS NULL';
        }
        if (isset($filters['turmas_permitidas'])) {
            // Escopo do professor: só alunos das turmas em que ele leciona.
            $ids = array_filter(array_map('intval', (array) $filters['turmas_permitidas']));
            $where[] = $ids === [] ? '1 = 0' : 'c.id IN (' . implode(',', $ids) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /** Alunos com vínculo corrente em uma turma. */
    public static function byClass(int $classId, bool $onlyActive = true): array
    {
        $sql = 'SELECT s.* FROM students s
                  JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
                 WHERE e.class_id = ?';
        if ($onlyActive) {
            $sql .= " AND s.status <> 'inativo'";
        }
        return Database::all($sql . ' ORDER BY s.full_name', [$classId]);
    }

    public static function create(array $data): int
    {
        return Database::insert('students', [
            'full_name'   => $data['full_name'],
            'document'    => Input::text($data, 'document'),
            'email'       => Input::text($data, 'email'),
            'phone'       => Input::text($data, 'phone'),
            'birth_date'  => Input::text($data, 'birth_date'),
            'enrolled_at' => Input::text($data, 'enrolled_at', date('Y-m-d')),
            'status'      => $data['status'] ?? 'ativo',
            'notes'       => Input::text($data, 'notes'),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('students', [
            'full_name'   => $data['full_name'],
            'document'    => Input::text($data, 'document'),
            'email'       => Input::text($data, 'email'),
            'phone'       => Input::text($data, 'phone'),
            'birth_date'  => Input::text($data, 'birth_date'),
            'enrolled_at' => Input::text($data, 'enrolled_at'),
            'status'      => $data['status'] ?? 'ativo',
            'notes'       => Input::text($data, 'notes'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('students', 'id = ?', [$id]);
    }

    /** Vínculos (turmas) do aluno, do mais recente para o mais antigo. */
    public static function enrollments(int $studentId): array
    {
        return Database::all(
            'SELECT e.*, c.code, c.name AS class_name, c.year, co.name AS course_name
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               JOIN courses co ON co.id = c.course_id
              WHERE e.student_id = ?
              ORDER BY e.is_current DESC, e.id DESC',
            [$studentId]
        );
    }

    /**
     * Vincula o aluno a uma turma. Um vínculo corrente anterior é encerrado e
     * preservado no histórico (regra 2 e 5 do escopo).
     */
    public static function assignToClass(int $studentId, int $classId, ?string $startedAt = null): void
    {
        Database::transaction(static function () use ($studentId, $classId, $startedAt) {
            $current = Database::first(
                'SELECT * FROM enrollments WHERE student_id = ? AND is_current = 1',
                [$studentId]
            );

            if ($current && (int) $current['class_id'] === $classId) {
                return; // já está na turma: nada a fazer
            }
            if ($current) {
                Database::update('enrollments', [
                    'is_current' => 0,
                    'ended_at'   => date('Y-m-d'),
                    'status'     => 'transferido',
                ], 'id = :id', ['id' => $current['id']]);
            }

            Database::insert('enrollments', [
                'student_id' => $studentId,
                'class_id'   => $classId,
                'started_at' => $startedAt ?: date('Y-m-d'),
                'is_current' => 1,
                'status'     => 'ativo',
            ]);
        });
    }

    /** Remove o aluno da turma corrente, mantendo o histórico. */
    public static function removeFromClass(int $studentId, string $status = 'transferido'): void
    {
        Database::update('enrollments', [
            'is_current' => 0,
            'ended_at'   => date('Y-m-d'),
            'status'     => $status,
        ], 'student_id = :sid AND is_current = 1', ['sid' => $studentId]);
    }

    public static function currentClassId(int $studentId): ?int
    {
        $id = Database::value(
            'SELECT class_id FROM enrollments WHERE student_id = ? AND is_current = 1',
            [$studentId]
        );
        return $id === null ? null : (int) $id;
    }

    public static function countByStatus(): array
    {
        $rows = Database::all('SELECT status, COUNT(*) AS total FROM students GROUP BY status');
        $out = ['ativo' => 0, 'inativo' => 0, 'concluido' => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['total'];
        }
        return $out;
    }
}
