<?php
namespace App\Models;

use App\Core\Database;

class Attendance
{
    public static function forLesson(int $lessonId): array
    {
        $rows = Database::all('SELECT * FROM attendances WHERE lesson_id = ?', [$lessonId]);
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[(int) $row['student_id']] = $row;
        }
        return $byStudent;
    }

    /**
     * Grava a chamada inteira de uma aula.
     * @param array<int, array{status:string, participation?:string, notes?:string}> $entries indexado por student_id
     */
    public static function saveForLesson(int $lessonId, array $entries): int
    {
        $valid = ['presente', 'falta', 'falta_justificada', 'atraso'];
        return Database::transaction(static function () use ($lessonId, $entries, $valid) {
            $saved = 0;
            foreach ($entries as $studentId => $entry) {
                $studentId = (int) $studentId;
                $status    = in_array($entry['status'] ?? '', $valid, true) ? $entry['status'] : 'presente';
                $part      = ($entry['participation'] ?? '') === '' ? null : max(0, min(5, (int) $entry['participation']));
                $notes     = trim((string) ($entry['notes'] ?? '')) ?: null;

                $existing = Database::value(
                    'SELECT id FROM attendances WHERE lesson_id = ? AND student_id = ?',
                    [$lessonId, $studentId]
                );
                if ($existing) {
                    Database::update('attendances', [
                        'status'        => $status,
                        'participation' => $part,
                        'notes'         => $notes,
                    ], 'id = :id', ['id' => (int) $existing]);
                } else {
                    Database::insert('attendances', [
                        'lesson_id'     => $lessonId,
                        'student_id'    => $studentId,
                        'status'        => $status,
                        'participation' => $part,
                        'notes'         => $notes,
                    ]);
                }
                $saved++;
            }
            return $saved;
        });
    }

    /** Histórico de presença de um aluno (opcionalmente filtrado). */
    public static function historyForStudent(int $studentId, array $filters = []): array
    {
        $where  = ['a.student_id = :sid'];
        $params = ['sid' => $studentId];
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
        return Database::all(
            'SELECT a.*, l.title, l.lesson_date, s.name AS subject_name, c.code AS class_code
               FROM attendances a
               JOIN lessons l ON l.id = a.lesson_id
               JOIN class_subjects cs ON cs.id = l.class_subject_id
               JOIN subjects s ON s.id = cs.subject_id
               JOIN classes c ON c.id = cs.class_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY l.lesson_date DESC',
            $params
        );
    }
}
