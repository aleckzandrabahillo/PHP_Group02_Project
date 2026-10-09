<?php

namespace App\Services;

use App\Core\Database;

final class LogService
{
    public function auth(?int $userId, string $event, string $result, array $metadata = []): void
    {
        try {
            $stmt = Database::connection()->prepare('INSERT INTO auth_logs (user_id, event, result, ip_address, user_agent, metadata_json, created_at) VALUES (:user_id, :event, :result, :ip, :ua, :metadata, NOW())');
            $stmt->execute([
                'user_id' => $userId,
                'event' => $event,
                'result' => $result,
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45),
                'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255),
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // Continue the authentication flow if a log write fails.
            @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/app.log', '[' . date('c') . '] auth log failure: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    public function search(string $q, string $event, string $result, int $limit, int $offset): array
    {
        $w = []; $p = [];
        if ($q !== '')  { $w[] = '(u.email LIKE :q1 OR l.ip_address LIKE :q2)'; $p += ['q1'=>"%$q%", 'q2'=>"%$q%"]; }
        if ($event !== '')  { $w[] = 'l.event = :ev';  $p['ev'] = $event; }
        if (in_array($result, ['success','failure'], true)) { $w[] = 'l.result = :res'; $p['res'] = $result; }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $pdo = Database::connection();
        $c = $pdo->prepare("SELECT COUNT(*) FROM auth_logs l LEFT JOIN users u ON u.id=l.user_id $where");
        $c->execute($p);
        $s = $pdo->prepare("SELECT l.*, u.email FROM auth_logs l LEFT JOIN users u ON u.id=l.user_id
                            $where ORDER BY l.id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset);
        $s->execute($p);
        return ['rows'=>$s->fetchAll(), 'total'=>(int)$c->fetchColumn()];
    }
    public function recent(int $n = 5): array
    {
        return Database::connection()->query('SELECT l.*, u.email FROM auth_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT ' . (int)$n)->fetchAll();
    }
}
