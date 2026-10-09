<?php

namespace App\Services;

use App\Core\Database;

final class AuditService
{
    public function record(?int $actorId, string $action, string $targetType, ?int $targetId = null, string $details = ''): void
    {
        Database::connection()->prepare(
            'INSERT INTO audit_logs (actor_id, action, target_type, target_id, details, created_at)
             VALUES (:a, :act, :tt, :tid, :d, NOW())'
        )->execute(['a'=>$actorId, 'act'=>$action, 'tt'=>$targetType, 'tid'=>$targetId, 'd'=>mb_substr($details, 0, 1000)]);
    }

    public function search(string $q, int $limit, int $offset): array
    {
        $where = ''; $p = [];
        if ($q !== '') {
            $where = 'WHERE a.action LIKE :q1 OR a.details LIKE :q2 OR u.email LIKE :q3';
            $p = ['q1'=>"%$q%", 'q2'=>"%$q%", 'q3'=>"%$q%"];
        }
        $pdo = Database::connection();
        $c = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id $where");
        $c->execute($p);
        $s = $pdo->prepare("SELECT a.*, u.email AS actor_email FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id
                            $where ORDER BY a.id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset);
        $s->execute($p);
        return ['rows'=>$s->fetchAll(), 'total'=>(int)$c->fetchColumn()];
    }
}