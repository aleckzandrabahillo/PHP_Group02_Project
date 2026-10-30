<?php

namespace App\Models;

use App\Core\Database;
use App\Models\HairProfile;
use App\Services\SuitabilityRepository;

final class Routine
{
    /** Current derived plan: a read never creates or overwrites a saved historical routine. */
    public function currentForUser(int $userId): array
    {
        $result=(new \App\Services\RecommendationService())->forUser($userId);
        if(!$result['profile'])return ['state'=>'assessment_needed','profile'=>null,'routine'=>null,'items'=>[],'stale'=>false];
        $items=[];
        foreach($result['routine']['items'] as $index=>$item){
            $product=$item['product'];
            $items[]=[...$product,'product_id'=>(int)$product['id'],'step_order'=>$index+1,'routine_step'=>$product['role'],
                'explanation'=>['title'=>$item['title'],'optional'=>$item['optional'],'reasons'=>$product['reasons']]];
        }
        return ['state'=>'ready','profile'=>$result['profile'],'routine'=>null,'items'=>$items,'stale'=>false,
            'warnings'=>$result['routine']['warnings'],'derived'=>true];
    }

    public function stateForUser(int $userId): array
    {
        $pdo = Database::connection();
        $profile = (new HairProfile($pdo))->forUser($userId);

        if (!$profile) {
            return ['state' => 'assessment_needed', 'profile' => null, 'routine' => null, 'items' => []];
        }

        $routineStmt = $pdo->prepare(
            'SELECT r.id,r.hair_profile_id,r.created_at,r.updated_at,rc.profile_version,rc.explanation_json
             FROM routines r LEFT JOIN routine_contexts rc ON rc.routine_id=r.id
             WHERE r.user_id = :user_id AND r.hair_profile_id = :profile_id
             ORDER BY r.updated_at DESC,r.id DESC LIMIT 1'
        );
        $routineStmt->execute(['user_id' => $userId, 'profile_id' => (int) $profile['id']]);
        $routine = $routineStmt->fetch();

        if (!$routine) {
            return ['state' => 'profile_ready', 'profile' => $profile, 'routine' => null, 'items' => []];
        }

        $itemsStmt = $pdo->prepare(
            'SELECT ri.step_order, ri.routine_step,
                    p.id AS product_id,p.sku,p.name,p.price,p.stock_qty,p.image_path,p.status,
                    c.name AS category_name,c.status AS category_status
             FROM routine_items ri
             INNER JOIN products p ON p.id = ri.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE ri.routine_id = :routine_id
             ORDER BY ri.step_order ASC, ri.id ASC'
        );
        $itemsStmt->execute(['routine_id' => (int) $routine['id']]);
        $items = $itemsStmt->fetchAll();
        $context = $routine['explanation_json'] === null ? [] : json_decode($routine['explanation_json'], true, 32, JSON_THROW_ON_ERROR);
        $stale = $routine['profile_version'] === null || !hash_equals($routine['profile_version'], HairProfile::version($profile));
        $current = [];
        try {
            foreach ((new SuitabilityRepository())->candidates($pdo) as $candidate) $current[(int) $candidate['id']] = $candidate;
        } catch (\Throwable) {
            $stale = true; // Preserve the saved routine if current suitability sources need review.
        }
        foreach ($items as &$item) {
            $item['explanation'] = $context[(int) $item['product_id']] ?? null;
            $candidate = $current[(int) $item['product_id']] ?? null;
            $item['available'] = $item['status'] === 'active' && $item['category_status'] === 'active' && (int) $item['stock_qty'] > 0;
            if (!$candidate || !$item['explanation']
                || !hash_equals($item['explanation']['metadata_signature'], $candidate['metadata_signature'])) $stale = true;
        }
        unset($item);

        return [
            'state' => $items === [] ? 'profile_ready' : 'ready',
            'profile' => $profile,
            'routine' => $routine,
            'items' => $items,
            'stale' => $stale,
        ];
    }
}
