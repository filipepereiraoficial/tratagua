<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

/** Turma (a palavra "class" é reservada em PHP). */
class ClassGroup
{
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT c.*, co.name AS course_name
               FROM classes c JOIN courses co ON co.id = c.course_id
              WHERE c.id = ?',
            [$id]
        );
    }

    public static function search(array $filters = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filters['curso'])) {
            $where[] = 'c.course_id = :curso';
            $params['curso'] = (int) $filters['curso'];
        }
        if (!empty($filters['ano'])) {
            $where[] = 'c.year = :ano';
            $params['ano'] = (int) $filters['ano'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['busca'])) {
            $where[] = '(LOWER(c.code) LIKE :busca OR LOWER(c.name) LIKE :busca)';
            $params['busca'] = '%' . mb_strtolower($filters['busca']) . '%';
        }

        return Database::all(
            'SELECT c.*, co.name AS course_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.is_current = 1) AS students_count,
                    (SELECT COUNT(*) FROM class_subjects cs WHERE cs.class_id = c.id) AS subjects_count
               FROM classes c
               JOIN courses co ON co.id = c.course_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.year DESC, c.code',
            $params
        );
    }

    public static function options(): array
    {
        return Database::all(
            'SELECT c.id, c.code, c.name, c.year, co.name AS course_name
               FROM classes c JOIN courses co ON co.id = c.course_id
              ORDER BY c.year DESC, c.code'
        );
    }

    public static function years(): array
    {
        return array_column(Database::all('SELECT DISTINCT year FROM classes ORDER BY year DESC'), 'year');
    }

    public static function create(array $data): int
    {
        return Database::insert('classes', [
            'code'       => $data['code'],
            'name'       => Input::text($data, 'name'),
            'course_id'  => (int) $data['course_id'],
            'year'       => (int) $data['year'],
            'period'     => Input::text($data, 'period'),
            'start_date' => Input::text($data, 'start_date'),
            'end_date'   => Input::text($data, 'end_date'),
            'status'     => $data['status'] ?? 'em_andamento',
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('classes', [
            'code'       => $data['code'],
            'name'       => Input::text($data, 'name'),
            'course_id'  => (int) $data['course_id'],
            'year'       => (int) $data['year'],
            'period'     => Input::text($data, 'period'),
            'start_date' => Input::text($data, 'start_date'),
            'end_date'   => Input::text($data, 'end_date'),
            'status'     => $data['status'] ?? 'em_andamento',
        ], 'id = :id', ['id' => $id]);
    }

    /** Só permite excluir turma sem alunos vinculados e sem aulas/avaliações. */
    public static function blockers(int $id): array
    {
        $blockers = [];
        $students = (int) Database::value('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND is_current = 1', [$id], 0);
        if ($students > 0) {
            $blockers[] = "{$students} aluno(s) vinculado(s)";
        }
        $lessons = (int) Database::value(
            'SELECT COUNT(*) FROM lessons l JOIN class_subjects cs ON cs.id = l.class_subject_id WHERE cs.class_id = ?',
            [$id], 0
        );
        if ($lessons > 0) {
            $blockers[] = "{$lessons} aula(s) registrada(s)";
        }
        $assessments = (int) Database::value(
            'SELECT COUNT(*) FROM assessments a JOIN class_subjects cs ON cs.id = a.class_subject_id WHERE cs.class_id = ?',
            [$id], 0
        );
        if ($assessments > 0) {
            $blockers[] = "{$assessments} avaliação(ões) registrada(s)";
        }
        return $blockers;
    }

    public static function delete(int $id): void
    {
        Database::transaction(static function () use ($id) {
            Database::delete('enrollments', 'class_id = ?', [$id]);
            Database::delete('classes', 'id = ?', [$id]);
        });
    }

    /** Disciplinas ofertadas na turma. */
    public static function subjects(int $classId): array
    {
        return Database::all(
            'SELECT cs.id AS class_subject_id, s.*, u.name AS teacher_name,
                    (SELECT COUNT(*) FROM lessons l WHERE l.class_subject_id = cs.id) AS lessons_count,
                    (SELECT COUNT(*) FROM assessments a WHERE a.class_subject_id = cs.id) AS assessments_count
               FROM class_subjects cs
               JOIN subjects s ON s.id = cs.subject_id
               LEFT JOIN users u ON u.id = COALESCE(cs.teacher_user_id, s.teacher_user_id)
              WHERE cs.class_id = ?
              ORDER BY s.name',
            [$classId]
        );
    }

    public static function attachSubject(int $classId, int $subjectId, ?int $teacherId = null): bool
    {
        $exists = (int) Database::value(
            'SELECT COUNT(*) FROM class_subjects WHERE class_id = ? AND subject_id = ?',
            [$classId, $subjectId], 0
        );
        if ($exists > 0) {
            return false;
        }
        Database::insert('class_subjects', [
            'class_id'        => $classId,
            'subject_id'      => $subjectId,
            'teacher_user_id' => $teacherId,
        ]);
        return true;
    }

    /** Remove a oferta apenas se ela não tiver aulas ou avaliações. */
    public static function detachSubject(int $classSubjectId): array
    {
        $lessons = (int) Database::value('SELECT COUNT(*) FROM lessons WHERE class_subject_id = ?', [$classSubjectId], 0);
        $assessments = (int) Database::value('SELECT COUNT(*) FROM assessments WHERE class_subject_id = ?', [$classSubjectId], 0);
        if ($lessons > 0 || $assessments > 0) {
            return ['ok' => false, 'message' => "Não é possível desvincular: existem {$lessons} aula(s) e {$assessments} avaliação(ões) nesta disciplina para a turma."];
        }
        Database::delete('class_subjects', 'id = ?', [$classSubjectId]);
        return ['ok' => true, 'message' => 'Disciplina desvinculada da turma.'];
    }
}
