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
}
