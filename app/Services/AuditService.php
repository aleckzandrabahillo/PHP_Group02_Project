<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Sql;
use PDO;

final class AuditService
{
    /** Default admin-page size for audit/order pagination. */
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 100;

    public static function record(PDO $pdo, ?int $actor, string $action, string $type, ?int $target, array $details=[]): void
    {
        if(!$pdo->inTransaction())throw new \LogicException('Audit requires the business transaction.');
        if(!preg_match('/^[a-z][a-z0-9_.]{0,79}$/D',$action)||!preg_match('/^[a-z][a-z0-9_]{0,59}$/D',$type))throw new \InvalidArgumentException('Invalid audit event.');
        $json=json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(strlen($json)>1000)throw new \InvalidArgumentException('Audit details exceed the storage limit.');
        $pdo->prepare('INSERT INTO audit_logs(actor_id,action,target_type,target_id,details) VALUES (?,?,?,?,?)')->execute([$actor,$action,$type,$target,$json]);
    }

    /**
     * Paginated audit log. $term matches action, target type, details or actor email.
     *
     * @return array{rows: array, total: int}
     */
    public function search(string $term, int $limit, int $offset): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $offset = max(0, $offset);

        $where = [];
        $params = [];

        $term = mb_substr(trim($term), 0, 100);
        if ($term !== '') {
            $like = Sql::contains($term);
            $where[] = '(a.action LIKE :s_action OR a.target_type LIKE :s_target OR a.details LIKE :s_details OR u.email LIKE :s_actor)';
            $params['s_action'] = $like;
            $params['s_target'] = $like;
            $params['s_details'] = $like;
            $params['s_actor'] = $like;
        }

        $from = 'FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id'
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT a.id, a.action, a.target_type, a.target_id, a.details, a.created_at, u.email AS actor_email '
            . $from . ' ORDER BY a.id DESC' . Sql::limitOffset($limit, $offset)
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** Audit history for a single record (e.g. one order), most recent first. */
    public function forTarget(string $targetType, int $targetId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.action, a.details, a.created_at, u.email AS actor_email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.target_type = :target_type AND a.target_id = :target_id
             ORDER BY a.id DESC' . Sql::limitOffset($limit, 0)
        );
        $stmt->execute(['target_type' => $targetType, 'target_id' => $targetId]);
        return $stmt->fetchAll();
    }
}
