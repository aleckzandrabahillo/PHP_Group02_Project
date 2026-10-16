<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\HairProfile;
use App\Models\User;
use InvalidArgumentException;
use PDO;

final class RoutineBuilder
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function save(int $userId, string $expected): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $expected)) throw new InvalidArgumentException('Refresh your matches before saving.');
        $pdo = $this->connection ?? Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!User::canAuthenticate($user) || $user['role'] !== 'customer') throw new InvalidArgumentException('Use an active customer account.');
            $preview = (new RecommendationService($pdo))->forUser($userId, true);
            if (!hash_equals($preview['fingerprint'], $expected)) throw new InvalidArgumentException('Your profile, products or availability changed. Review the updated matches before saving.');
            if (!$preview['routine']['items']) throw new InvalidArgumentException('There are not enough reviewed, available products to save a routine.');
            $stmt = $pdo->prepare('SELECT r.id FROM routines r JOIN routine_contexts rc ON rc.routine_id=r.id
                WHERE r.user_id=? AND r.hair_profile_id=? AND rc.plan_fingerprint=? ORDER BY r.id DESC LIMIT 1');
            $stmt->execute([$userId, $preview['profile']['id'], $expected]);
            $existing = $stmt->fetchColumn();
            if ($existing) { $pdo->commit(); return (int) $existing; }
            $pdo->prepare('INSERT INTO routines (user_id,hair_profile_id) VALUES (?,?)')->execute([$userId, $preview['profile']['id']]);
            $id = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare('INSERT INTO routine_items (routine_id,product_id,step_order,routine_step) VALUES (?,?,?,?)');
            $explanations = [];
            foreach ($preview['routine']['items'] as $index => $item) {
                $product = $item['product'];
                $insert->execute([$id, $product['id'], $index + 1, $product['role'] === 'accessory' ? 'finish' : $product['role']]);
                $explanations[(int) $product['id']] = ['title' => $item['title'], 'optional' => $item['optional'],
                    'reasons' => $product['reasons'], 'metadata_signature' => $product['metadata_signature'], 'evidence' => $product['evidence']];
            }
            $pdo->prepare('INSERT INTO routine_contexts (routine_id,profile_version,plan_fingerprint,rules_version,explanation_json) VALUES (?,?,?,?,?)')
                ->execute([$id, HairProfile::version($preview['profile']), $expected, ProductMatcher::VERSION,
                    json_encode($explanations, JSON_THROW_ON_ERROR)]);
            $pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (?,?,?,?,?)')
                ->execute([$userId, 'routine.create', 'routine', $id, 'Saved explainable routine; items ' . count($explanations) . '; rules ' . ProductMatcher::VERSION]);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
