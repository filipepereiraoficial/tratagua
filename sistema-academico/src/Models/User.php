<?php
namespace App\Models;

use App\Core\Database;

class User
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::first('SELECT * FROM users WHERE LOWER(email) = ?', [mb_strtolower($email)]);
    }

    public static function all(): array
    {
        return Database::all('SELECT * FROM users ORDER BY name');
    }

    public static function teachers(): array
    {
        return Database::all("SELECT id, name FROM users WHERE role IN ('admin','professor') AND is_active = 1 ORDER BY name");
    }

    public static function create(array $data): int
    {
        return Database::insert('users', [
            'name'                 => $data['name'],
            'email'                => mb_strtolower($data['email']),
            'password_hash'        => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'                 => $data['role'] ?? 'professor',
            'student_id'           => $data['student_id'] ?? null,
            'is_active'            => (int) ($data['is_active'] ?? 1),
            'must_change_password' => (int) ($data['must_change_password'] ?? 0),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        foreach (['name', 'email', 'role', 'is_active', 'student_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $field === 'email' ? mb_strtolower($data[$field]) : $data[$field];
            }
        }
        if ($fields !== []) {
            Database::update('users', $fields, 'id = :id', ['id' => $id]);
        }
    }

    public static function updatePassword(int $id, string $password, bool $mustChange = false): void
    {
        Database::update('users', [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => $mustChange ? 1 : 0,
        ], 'id = :id', ['id' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('users', 'id = ?', [$id]);
    }
}
