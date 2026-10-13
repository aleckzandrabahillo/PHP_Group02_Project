<?php

namespace App\Services;

use App\Core\Database;

/**
 * Records and reads administrative actions (who did what to which record).
 * Never put passwords, OTP codes or tokens in $details.
 */
final class AuditService
{
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 100;
    public const MAX_TERM_LENGTH = 100;

    /** Clamp a requested page size to 1..MAX_LIMIT (falls back to the default). */
    public static function normalizeLimit(mixed $limit): int
    {
        $limit = filter_var($limit, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) {
            return self::DEFAULT_LIMIT;
        }
        return min($limit, self::MAX_LIMIT);
    }

    /** Offsets can never be negative or non-numeric. */
    public static function normalizeOffset(mixed $offset): int
    {
        $offset = filter_var($offset, FILTER_VALIDATE_INT);
        return ($offset === false || $offset < 0) ? 0 : $offset;
    }

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
        $limit = self::normalizeLimit($limit);
        $offset = self::normalizeOffset($offset);

        $where = '';
        $params = [];

        $term = mb_substr(trim($term), 0, self::MAX_TERM_LENGTH);
        if ($term !== '') {
            $like = like_pattern($term);
            $where = "WHERE (a.action LIKE :s_action ESCAPE '!' OR a.target_type LIKE :s_target ESCAPE '!'
                       OR a.details LIKE :s_details ESCAPE '!' OR u.email LIKE :s_actor ESCAPE '!')";
            $params = ['s_action' => $like, 's_target' => $like, 's_details' => $like, 's_actor' => $like];
        }

        $from = 'FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id ' . $where;

        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT a.id, a.action, a.target_type, a.target_id, a.details, a.created_at, u.email AS actor_email '
            . $from . ' ORDER BY a.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }
}
