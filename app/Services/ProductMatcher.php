<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HairProfileValidator;

final class ProductMatcher
{
    public const VERSION = '1';
    private const ROLES = ['Shampoo' => 'cleanse', 'Conditioner' => 'condition', 'Treatment' => 'treat',
        'Serum' => 'finish', 'Hair Oil' => 'finish', 'Styling' => 'finish', 'Hair Tools' => 'accessory'];

    public function rank(array $profile, array $products, array $textTreatments = []): array
    {
        $signals = [];
        foreach ($profile['tags'] as $tag) $signals[$tag['type'] . ':' . $tag['code']] = true;
        $chemical = array_values(array_intersect(array_unique([...explode(',', (string) $profile['treatment_history']), ...$textTreatments]),
            ['colored', 'bleached', 'permed', 'relaxed']));
        $texture = $profile['hair_texture'];
        $scalp = $profile['scalp_type'];
        if ($texture === 'unsure') foreach (array_keys(HairProfileValidator::CHOICES['hair_texture']) as $code) {
            if (isset($signals['texture:' . $code])) { $texture = $code; break; }
        }
        if ($scalp === 'unsure') foreach (array_keys(HairProfileValidator::CHOICES['scalp_type']) as $code) {
            if (isset($signals['scalp:' . $code])) { $scalp = $code; break; }
        }
        $ranked = [];
        foreach ($products as $product) {
            $role = self::ROLES[$product['category_name']] ?? null;
            if ($product['status'] !== 'active' || $product['category_status'] !== 'active' || !$role
                || ($product['evidence']['status'] ?? '') !== 'reviewed_demo' || !($product['verified_tags'] ?? [])
                || ($role !== 'accessory' && $product['routine_step'] !== $role)) continue;
            $tags = array_fill_keys($product['verified_tags'], true);
            $textures = array_filter($product['verified_tags'], static fn($tag) => str_starts_with($tag, 'texture:'));
            $scalps = array_filter($product['verified_tags'], static fn($tag) => str_starts_with($tag, 'scalp:'));
            if ($textures && $texture !== 'unsure' && !isset($tags['texture:' . $texture])) continue;
            if ($role === 'cleanse' && $scalps && $scalp !== 'unsure' && !isset($tags['scalp:' . $scalp])) continue;
            // Never imply color/chemical-treatment suitability without explicit supporting metadata.
            if ($chemical && in_array($role, ['cleanse', 'treat'], true)
                && array_diff($chemical, $product['treatment_support'] ?? [])) continue;
            $score = 0;
            $reasons = [];
            if (isset($tags['texture:' . $texture])) {
                $score += 2;
                $reasons[] = ['code' => 'texture.' . $texture, 'points' => 2,
                    'text' => 'Catalog texture tag: ' . HairProfileValidator::CHOICES['hair_texture'][$texture] . '.'];
            }
            if ($role === 'cleanse' && isset($tags['scalp:' . $scalp])) {
                $score += 4;
                $reasons[] = ['code' => 'scalp.' . $scalp, 'points' => 4,
                    'text' => 'Cleansing suitability tag: ' . HairProfileValidator::CHOICES['scalp_type'][$scalp] . '.'];
            }
            $concernMatches = 0;
            foreach (HairProfileValidator::CHOICES['concerns'] as $code => $label) {
                if ($role === 'cleanse' && $code !== 'oiliness') continue;
                if (isset($signals['concern:' . $code], $tags['concern:' . $code])) {
                    $score += 4; $concernMatches++;
                    $reasons[] = ['code' => 'concern.' . $code, 'points' => 4, 'text' => 'Catalog concern tag: ' . $label . '.'];
                }
            }
            if ($score === 0 || (in_array($role, ['treat', 'finish', 'accessory'], true) && !$concernMatches)) continue;
            usort($reasons, static fn($a, $b) => ($b['points'] <=> $a['points']) ?: strcmp($a['code'], $b['code']));
            $ranked[] = [...$product, 'score' => $score, 'reasons' => $reasons, 'role' => $role,
                'concern_matches' => $concernMatches, 'available' => (int) $product['stock_qty'] > 0];
        }
        usort($ranked, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['sku'], $b['sku']) ?: ((int) $a['id'] <=> (int) $b['id']));
        return $ranked;
    }

    public function routine(array $profile, array $ranked): array
    {
        $items = [];
        $warnings = [];
        $concerns = array_column(array_filter($profile['tags'], static fn($tag) => $tag['type'] === 'concern'), 'code');
        $pick = static function (string $role, ?string $purpose = null) use ($ranked): ?array {
            foreach ($ranked as $product) {
                if ($product['role'] === $role && $product['available']
                    && ($purpose === null || $product['accessory_purpose'] === $purpose)) return $product;
            }
            return null;
        };
        foreach (['cleanse' => 'Cleanse', 'condition' => 'Condition'] as $role => $title) {
            $product = $pick($role);
            if ($product) $items[] = ['title' => $title, 'optional' => false, 'product' => $product];
            else $warnings[] = 'No reviewed, available ' . ($role === 'cleanse' ? 'cleansing' : 'conditioning') . ' product matches this profile.';
        }
        // Limit extras: one optional care product, plus a detangling tool only when relevant.
        $extra = in_array('damage', $concerns, true) ? 'treat' : (in_array('frizz', $concerns, true) ? 'finish' : null);
        if ($extra && ($product = $pick($extra))) $items[] = ['title' => $extra === 'treat' ? 'Optional treatment' : 'Optional finish', 'optional' => true, 'product' => $product];
        if (in_array('tangling', $concerns, true) && ($product = $pick('accessory', 'detangling'))) {
            $items[] = ['title' => 'Optional detangling tool', 'optional' => true, 'product' => $product];
        }
        return ['items' => $items, 'warnings' => $warnings];
    }
}
