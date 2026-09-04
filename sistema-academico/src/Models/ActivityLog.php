<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Trilha de auditoria: quem fez o quê e quando. Registra apenas operações que
 * alteram estado — leitura não entra, para o log continuar legível.
 */
class ActivityLog
{
    public static function record(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null): void
    {
        try {
            Database::insert('activity_log', [
                'user_id'   => Auth::id(),
                'action'    => mb_substr($action, 0, 64),
                'entity'    => $entity === null ? null : mb_substr($entity, 0, 64),
                'entity_id' => $entityId,
                'details'   => $details === null ? null : mb_substr($details, 0, 255),
            ]);
        } catch (\Throwable) {
            // Auditoria nunca pode derrubar a operação que ela observa.
        }
    }

    public static function search(array $filters = [], int $limit = 200): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (!empty($filters['usuario'])) {
            $where[] = 'l.user_id = :usuario';
            $params['usuario'] = (int) $filters['usuario'];
        }
        if (!empty($filters['entidade'])) {
            $where[] = 'l.entity = :entidade';
            $params['entidade'] = $filters['entidade'];
        }
        if (!empty($filters['acao'])) {
            $where[] = 'l.action = :acao';
            $params['acao'] = $filters['acao'];
        }
        if (!empty($filters['inicio'])) {
            $where[] = 'l.created_at >= :inicio';
            $params['inicio'] = $filters['inicio'] . ' 00:00:00';
        }
        if (!empty($filters['fim'])) {
            $where[] = 'l.created_at <= :fim';
            $params['fim'] = $filters['fim'] . ' 23:59:59';
        }

        return Database::all(
            'SELECT l.*, u.name AS user_name, u.role AS user_role
               FROM activity_log l
               LEFT JOIN users u ON u.id = l.user_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY l.created_at DESC, l.id DESC
              LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function entities(): array
    {
        return array_column(Database::all(
            'SELECT DISTINCT entity FROM activity_log WHERE entity IS NOT NULL ORDER BY entity'
        ), 'entity');
    }

    public static function actions(): array
    {
        return array_column(Database::all(
            'SELECT DISTINCT action FROM activity_log ORDER BY action'
        ), 'action');
    }

    public static function total(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM activity_log', [], 0);
    }
}
