<?php

namespace App\Services;

use App\Core\Database;

/**
 * Records and reads administrative actions (who did what to which record).
 * Never put passwords, OTP codes or tokens in $details.
 */
final class AuditService
{
    public function record(?int $actorId, string $action, string $targetType, ?int $targetId = null, string $details = ''): void
    {
        Database::connection()->prepare(
            'INSERT INTO audit_logs (actor_id, action, target_type, target_id, details, created_at)
             VALUES (:actor_id, :action, :target_type, :target_id, :details, NOW())'
        )->execute([
            'actor_id' => $actorId,
            'action' => mb_substr($action, 0, 80),
            'target_type' => mb_substr($targetType, 0, 60),
            'target_id' => $targetId,
            'details' => mb_substr($details, 0, 1000),
        ]);
    }

    /**
     * @return array{rows: array, total: int}
     */
    public function search(string $term, int $limit, int $offset): array
    {
        $where = '';
        $params = [];

        $term = trim($term);
        if ($term !== '') {
            $like = like_pattern($term);
            $where = 'WHERE (a.action LIKE :s_action OR a.target_type LIKE :s_target
                       OR a.details LIKE :s_details OR u.email LIKE :s_actor)';
            $params = ['s_action' => $like, 's_target' => $like, 's_details' => $like, 's_actor' => $like];
        }

        $from = 'FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id ' . $where;

        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT a.id, a.action, a.target_type, a.target_id, a.details, a.created_at, u.email AS actor_email '
            . $from . ' ORDER BY a.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }
}
