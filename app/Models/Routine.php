<?php

namespace App\Models;

use App\Core\Database;

final class Routine
{
    public function stateForUser(int $userId): array
    {
        $pdo = Database::connection();
        $profileStmt = $pdo->prepare(
            'SELECT id, hair_texture, scalp_type, hair_condition, concern_text, created_at, updated_at
             FROM hair_profiles WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC LIMIT 1'
        );
        $profileStmt->execute(['user_id' => $userId]);
        $profile = $profileStmt->fetch();

        if (!$profile) {
            return ['state' => 'assessment_needed', 'profile' => null, 'routine' => null, 'items' => []];
        }

        $routineStmt = $pdo->prepare(
            'SELECT id, hair_profile_id, created_at, updated_at
             FROM routines
             WHERE user_id = :user_id AND hair_profile_id = :profile_id
             ORDER BY updated_at DESC, id DESC LIMIT 1'
        );
        $routineStmt->execute(['user_id' => $userId, 'profile_id' => (int) $profile['id']]);
        $routine = $routineStmt->fetch();

        if (!$routine) {
            return ['state' => 'profile_ready', 'profile' => $profile, 'routine' => null, 'items' => []];
        }

        $itemsStmt = $pdo->prepare(
            'SELECT ri.step_order, ri.routine_step,
                    p.id AS product_id, p.name, p.price, p.stock_qty, p.image_path,
                    c.name AS category_name
             FROM routine_items ri
             INNER JOIN products p ON p.id = ri.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE ri.routine_id = :routine_id
             ORDER BY ri.step_order ASC, ri.id ASC'
        );
        $itemsStmt->execute(['routine_id' => (int) $routine['id']]);
        $items = $itemsStmt->fetchAll();

        return [
            'state' => $items === [] ? 'profile_ready' : 'ready',
            'profile' => $profile,
            'routine' => $routine,
            'items' => $items,
        ];
    }
}
