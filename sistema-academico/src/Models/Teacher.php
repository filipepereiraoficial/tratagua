<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

/**
 * Professor. Não é uma tabela própria: é o usuário com perfil `professor`
 * (ou `admin`, que também leciona), acrescido dos dados de contato e formação.
 * Manter uma identidade só evita o clássico "cadastro de professor sem login".
 */
class Teacher
{
    public static function find(int $id): ?array
    {
        return Database::first(
            "SELECT * FROM users WHERE id = ? AND role IN ('professor','admin')",
            [$id]
        );
    }

    /** Lista com a carga de trabalho de cada professor. */
    public static function search(array $filters = []): array
    {
        $where  = ["u.role IN ('professor','admin')"];
        $params = [];
        if (!empty($filters['busca'])) {
            $where[] = '(LOWER(u.name) LIKE :busca OR LOWER(u.email) LIKE :busca)';
            $params['busca'] = '%' . mb_strtolower($filters['busca']) . '%';
        }
        if (isset($filters['ativo']) && $filters['ativo'] !== '') {
            $where[] = 'u.is_active = :ativo';
            $params['ativo'] = (int) $filters['ativo'];
        }

        return Database::all(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM class_subjects cs WHERE cs.teacher_user_id = u.id) AS ofertas,
                    (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.teacher_user_id = u.id) AS turmas,
                    (SELECT COUNT(DISTINCT cs.subject_id) FROM class_subjects cs WHERE cs.teacher_user_id = u.id) AS disciplinas,
                    (SELECT COUNT(*) FROM lessons l
                       JOIN class_subjects cs ON cs.id = l.class_subject_id
                      WHERE cs.teacher_user_id = u.id) AS aulas,
                    (SELECT COUNT(*) FROM assessments a
                       JOIN class_subjects cs ON cs.id = a.class_subject_id
                      WHERE cs.teacher_user_id = u.id) AS avaliacoes
               FROM users u
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY u.is_active DESC, u.name',
            $params
        );
    }

    public static function options(): array
    {
        return Database::all(
            "SELECT id, name FROM users
              WHERE role IN ('professor','admin') AND is_active = 1
              ORDER BY name"
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('users', [
            'name'                 => $data['name'],
            'email'                => mb_strtolower($data['email']),
            'password_hash'        => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'                 => ($data['role'] ?? 'professor') === 'admin' ? 'admin' : 'professor',
            'is_active'            => 1,
            'must_change_password' => 1,
            'document'             => Input::text($data, 'document'),
            'phone'                => Input::text($data, 'phone'),
            'qualification'        => Input::text($data, 'qualification'),
            'notes'                => Input::text($data, 'notes'),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('users', [
            'name'          => $data['name'],
            'email'         => mb_strtolower($data['email']),
            'role'          => ($data['role'] ?? 'professor') === 'admin' ? 'admin' : 'professor',
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
            'document'      => Input::text($data, 'document'),
            'phone'         => Input::text($data, 'phone'),
            'qualification' => Input::text($data, 'qualification'),
            'notes'         => Input::text($data, 'notes'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /** Ofertas (turma × disciplina) sob responsabilidade do professor. */
    public static function offerings(int $teacherId): array
    {
        return Database::all(
            'SELECT cs.id, cs.class_id, cs.subject_id,
                    c.code AS class_code, c.year, s.name AS subject_name, co.name AS course_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.is_current = 1) AS alunos,
                    (SELECT COUNT(*) FROM lessons l WHERE l.class_subject_id = cs.id) AS aulas,
                    (SELECT COUNT(*) FROM assessments a WHERE a.class_subject_id = cs.id) AS avaliacoes
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN courses co ON co.id = c.course_id
              WHERE cs.teacher_user_id = ?
              ORDER BY c.year DESC, c.code, s.name',
            [$teacherId]
        );
    }

    /** Ofertas ainda sem este professor, para o formulário de vínculo. */
    public static function assignableOfferings(int $teacherId): array
    {
        return Database::all(
            'SELECT cs.id, c.code AS class_code, c.year, s.name AS subject_name,
                    u.name AS teacher_name
               FROM class_subjects cs
               JOIN classes c ON c.id = cs.class_id
               JOIN subjects s ON s.id = cs.subject_id
               LEFT JOIN users u ON u.id = cs.teacher_user_id
              WHERE cs.teacher_user_id IS NULL OR cs.teacher_user_id <> ?
              ORDER BY c.year DESC, c.code, s.name',
            [$teacherId]
        );
    }

    public static function assign(int $classSubjectId, ?int $teacherId): void
    {
        Database::update('class_subjects', ['teacher_user_id' => $teacherId], 'id = :id', ['id' => $classSubjectId]);
    }

    /**
     * Impede desativar/excluir quem ainda responde por ofertas — o vínculo
     * precisa ser transferido antes, senão as aulas ficam sem responsável.
     */
    public static function blockers(int $id): array
    {
        $ofertas = (int) Database::value('SELECT COUNT(*) FROM class_subjects WHERE teacher_user_id = ?', [$id], 0);
        $disciplinas = (int) Database::value('SELECT COUNT(*) FROM subjects WHERE teacher_user_id = ?', [$id], 0);
        $blockers = [];
        if ($ofertas > 0)     { $blockers[] = "{$ofertas} oferta(s) de turma/disciplina"; }
        if ($disciplinas > 0) { $blockers[] = "{$disciplinas} disciplina(s) como responsável"; }
        return $blockers;
    }

    public static function delete(int $id): void
    {
        Database::delete('users', 'id = ?', [$id]);
    }
}
