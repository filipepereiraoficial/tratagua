<?php
namespace App\Core;

/**
 * Escopo de acesso por perfil.
 *
 *  · admin     — sem restrição: enxerga todos os cursos, turmas e disciplinas.
 *  · professor — apenas as ofertas (turma × disciplina) sob sua responsabilidade,
 *                seja como responsável da oferta, seja como responsável da
 *                disciplina quando a oferta não tem professor próprio.
 *  · aluno     — apenas os próprios dados.
 *
 * A restrição é aplicada uma única vez, aqui, e viaja pelos filtros como
 * `ofertas`. Os serviços analíticos sabem respeitá-la, então nenhuma consulta
 * precisa repetir a regra — e nenhuma esquece dela.
 */
class Scope
{
    private static ?array $cacheOfertas = null;

    /** @return array<int,int>|null null = sem restrição */
    public static function classSubjectIds(): ?array
    {
        if (self::$cacheOfertas !== null) {
            return self::$cacheOfertas === ['*'] ? null : self::$cacheOfertas;
        }

        $user = Auth::user();
        if (!$user || $user['role'] === 'admin') {
            self::$cacheOfertas = ['*'];
            return null;
        }

        if ($user['role'] === 'professor') {
            $ids = array_map('intval', array_column(Database::all(
                'SELECT cs.id
                   FROM class_subjects cs
                   JOIN subjects s ON s.id = cs.subject_id
                  WHERE cs.teacher_user_id = :uid
                     OR (cs.teacher_user_id IS NULL AND s.teacher_user_id = :uid)',
                ['uid' => (int) $user['id']]
            ), 'id'));
            return self::$cacheOfertas = $ids;
        }

        // Aluno: as ofertas da turma em que está matriculado.
        if ($user['role'] === 'aluno' && $user['student_id']) {
            $ids = array_map('intval', array_column(Database::all(
                'SELECT cs.id
                   FROM class_subjects cs
                   JOIN enrollments e ON e.class_id = cs.class_id AND e.is_current = 1
                  WHERE e.student_id = ?',
                [(int) $user['student_id']]
            ), 'id'));
            return self::$cacheOfertas = $ids;
        }

        return self::$cacheOfertas = [];
    }

    public static function isRestricted(): bool
    {
        return self::classSubjectIds() !== null;
    }

    /** Acrescenta a restrição de ofertas ao conjunto de filtros. */
    public static function apply(array $filters): array
    {
        $ofertas = self::classSubjectIds();
        if ($ofertas !== null) {
            $filters['ofertas'] = $ofertas;
        }
        if (self::isStudent()) {
            $filters['aluno'] = self::studentId();
        }
        return $filters;
    }

    public static function canAccessClassSubject(?int $id): bool
    {
        if ($id === null) {
            return false;
        }
        $ofertas = self::classSubjectIds();
        return $ofertas === null || in_array($id, $ofertas, true);
    }

    /** O aluno pertence a alguma turma das ofertas acessíveis? */
    public static function canAccessStudent(int $studentId): bool
    {
        $ofertas = self::classSubjectIds();
        if ($ofertas === null) {
            return true;
        }
        if (self::isStudent()) {
            return $studentId === self::studentId();
        }
        if ($ofertas === []) {
            return false;
        }
        $lista = implode(',', array_map('intval', $ofertas));
        return (int) Database::value(
            "SELECT COUNT(*)
               FROM enrollments e
               JOIN class_subjects cs ON cs.class_id = e.class_id
              WHERE e.student_id = ? AND e.is_current = 1 AND cs.id IN ({$lista})",
            [$studentId], 0
        ) > 0;
    }

    /** Turmas acessíveis (derivadas das ofertas). */
    public static function classIds(): ?array
    {
        $ofertas = self::classSubjectIds();
        if ($ofertas === null) {
            return null;
        }
        if ($ofertas === []) {
            return [];
        }
        $lista = implode(',', array_map('intval', $ofertas));
        return array_map('intval', array_column(
            Database::all("SELECT DISTINCT class_id FROM class_subjects WHERE id IN ({$lista})"), 'class_id'
        ));
    }

    /** Disciplinas acessíveis (derivadas das ofertas). */
    public static function subjectIds(): ?array
    {
        $ofertas = self::classSubjectIds();
        if ($ofertas === null) {
            return null;
        }
        if ($ofertas === []) {
            return [];
        }
        $lista = implode(',', array_map('intval', $ofertas));
        return array_map('intval', array_column(
            Database::all("SELECT DISTINCT subject_id FROM class_subjects WHERE id IN ({$lista})"), 'subject_id'
        ));
    }

    /** Ofertas do escopo com os nomes prontos para exibição. */
    public static function offerings(): array
    {
        $ofertas = self::classSubjectIds();
        $where = '1 = 1';
        if ($ofertas !== null) {
            if ($ofertas === []) {
                return [];
            }
            $where = 'cs.id IN (' . implode(',', array_map('intval', $ofertas)) . ')';
        }
        return Database::all(
            "SELECT cs.id, cs.class_id, cs.subject_id, c.code AS class_code, c.year,
                    s.name AS subject_name, co.name AS course_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.is_current = 1) AS students_count,
                    (SELECT COUNT(*) FROM lessons l WHERE l.class_subject_id = cs.id) AS lessons_count,
                    (SELECT COUNT(*) FROM assessments a WHERE a.class_subject_id = cs.id) AS assessments_count
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN courses co ON co.id = c.course_id
              WHERE {$where}
              ORDER BY c.year DESC, c.code, s.name"
        );
    }

    /** Alunos alcançados pelo escopo. */
    public static function students(): array
    {
        $turmas = self::classIds();
        $where = "s.status <> 'inativo'";
        if ($turmas !== null) {
            if ($turmas === []) {
                return [];
            }
            $where .= ' AND c.id IN (' . implode(',', array_map('intval', $turmas)) . ')';
        }
        if (self::isStudent()) {
            $where .= ' AND s.id = ' . (int) self::studentId();
        }
        return Database::all(
            "SELECT s.*, c.id AS class_id, c.code AS class_code
               FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.is_current = 1
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE {$where}
              ORDER BY s.full_name"
        );
    }

    public static function isStudent(): bool
    {
        return Auth::hasRole('aluno');
    }

    public static function studentId(): ?int
    {
        $user = Auth::user();
        return $user && $user['student_id'] ? (int) $user['student_id'] : null;
    }

    /** Limpa o cache — necessário após alterar vínculos do próprio usuário. */
    public static function reset(): void
    {
        self::$cacheOfertas = null;
    }
}
