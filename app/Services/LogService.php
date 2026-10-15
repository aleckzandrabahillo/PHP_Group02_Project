<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Sql;

final class LogService
{
    public const RESULTS = ['success', 'failure'];

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

    /**
     * Paginated authentication log. $term matches user email or IP address.
     * $event is an exact event name; $result is 'success' or 'failure'. Unknown values are ignored.
     *
     * @return array{rows: array, total: int}
     */
    public function search(string $term, string $event, string $result, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        $term = mb_substr(trim($term), 0, 100);
        if ($term !== '') {
            $like = Sql::contains($term);
            $where[] = '(u.email LIKE :s_email OR l.ip_address LIKE :s_ip)';
            $params['s_email'] = $like;
            $params['s_ip'] = $like;
        }
        if ($event !== '') {
            $where[] = 'l.event = :event';
            $params['event'] = mb_substr($event, 0, 60);
        }
        if (in_array($result, self::RESULTS, true)) {
            $where[] = 'l.result = :result';
            $params['result'] = $result;
        }

        $from = 'FROM auth_logs l LEFT JOIN users u ON u.id = l.user_id'
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);

        $rows = $pdo->prepare(
            'SELECT l.id, l.event, l.result, l.ip_address, l.metadata_json, l.created_at, u.email '
            . $from . ' ORDER BY l.id DESC' . Sql::limitOffset($limit, $offset)
        );
        $rows->execute($params);

        return ['rows' => $rows->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return string[] Event names present in the log, for the filter dropdown. */
    public function events(): array
    {
        return Database::connection()
            ->query('SELECT DISTINCT event FROM auth_logs ORDER BY event')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
