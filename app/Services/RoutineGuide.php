<?php
declare(strict_types=1);

namespace App\Services;

/** Care instructions are separate from product ranking and never change a saved plan. */
final class RoutineGuide
{
    public static function forProfile(array $profile, array $items): array
    {
        $texture = $profile['hair_texture'] ?? 'unsure';
        $scalp = $profile['scalp_type'] ?? 'unsure';
        $condition = $profile['hair_condition'] ?? 'unsure';
        $concerns = array_column(array_filter($profile['tags'] ?? [], static fn($tag) => ($tag['type'] ?? '') === 'concern'), 'code');
        $textured = in_array($texture, ['curly', 'coily'], true);
        $dry = in_array($condition, ['dry', 'mixed'], true) || in_array('dryness', $concerns, true);
        $products = [];
        foreach ($items as $item) {
            $product = $item['product'];
            $role = match ($product['category_name'] ?? '') {
                'Shampoo' => 'cleanse', 'Conditioner' => 'condition', 'Treatment' => 'treat',
                'Serum', 'Hair Oil', 'Styling' => 'finish',
                'Hair Tools' => (($product['sku'] ?? '') === 'AVE-TOL-001' || ($product['accessory_purpose'] ?? '') === 'detangling') ? 'accessory' : null,
                default => null,
            };
            if ($role !== null && !isset($products[$role])) $products[$role] = $product;
        }
        $step = static fn(string $key, string $title, string $when, string $instruction, string $why, ?array $product = null, bool $optional = false): array =>
            compact('key', 'title', 'when', 'instruction', 'why', 'product', 'optional');
        $rhythm = match ($scalp) {
            'oily_scalp' => 'Let your roots set the pace. Wash when they feel oily or there is build-up; keep conditioner on the lengths.',
            'dry_scalp' => 'Use how your scalp feels to guide wash days. Avoid scrubbing, and rinse gently rather than following a fixed daily schedule.',
            default => $textured ? 'Wash when your scalp needs cleansing. Work in sections rather than adding extra wash days for your curls or coils.'
                : 'Wash when your scalp feels oily or there is build-up. There is no fixed number of wash days for everyone.',
        };
        $wash = [$step('cleanse', 'Cleanse your scalp', 'Start on wet hair',
            'Massage shampoo gently into the scalp, then rinse thoroughly. Let the rinse pass through the lengths instead of scrubbing the ends.',
            match ($scalp) {'oily_scalp' => 'You recorded oily roots: focus cleansing there, separately from the needs of your ends.',
                'dry_scalp' => 'You recorded a dry-feeling scalp: keep the handling gentle rather than scrubbing harder.',
                'normal_scalp' => 'Your scalp is recorded as balanced: use how it feels to decide when to wash.',
                default => 'Your scalp type is not confirmed, so this step does not prescribe a wash frequency.'}, $products['cleanse'] ?? null)];
        $treatment = $products['treat'] ?? null;
        if ($treatment) {
            // Only these reviewed catalog identities establish rinse-out purpose; never infer it from a name.
            $sku = $treatment['sku'] ?? '';
            $mask = in_array($sku, ['AVE-TRT-001', 'AVE-TRT-002'], true);
            $cream = $sku === 'AVE-TRT-003';
            $wash[] = $step('treat', $mask ? 'Make room for a mask' : ($cream ? 'Work through knots' : 'Follow your treatment directions'),
                $mask ? 'Occasional wash-day extra' : 'Optional Â· follow the label',
                $mask ? 'Use on a wash day at the frequency on its label. Check whether it replaces your conditioner; do not automatically layer both. Rinse as directed.'
                    : ($cream ? 'Work the rinse-out cream through wet sections. Gently loosen knots from the ends upward, then rinse.'
                        : 'Check the label for where this treatment belongs, how often to use it and whether to rinse. Its application method is not confirmed here.'),
                in_array('damage', $concerns, true) ? 'Rough or damaged-feeling hair is one of your recorded concerns. This is an optional care step, not a repair guarantee.'
                    : 'This extra comes from your supported product matches; it does not need to be used at every wash.', $treatment, true);
        }
        $wash[] = $step('condition', 'Condition your lengths', 'After cleansing',
            $textured ? 'Distribute conditioner through the lengths in sections. Detangle gently before rinsing; follow the label for timing.'
                : 'Smooth conditioner through the mid-lengths and ends, then rinse as directed. Keep it away from roots that already feel oily.',
            $dry ? 'Your lengths or recorded concerns include dryness: give the lengths attention separately from your scalp.'
                : ($textured ? 'Your curls or coils make section-by-section handling useful.' : 'Conditioning follows cleansing; focus it where your lengths need it.'), $products['condition'] ?? null);
        if (in_array('tangling', $concerns, true) || isset($products['accessory'])) {
            $wash[] = $step('detangle', 'Detangle without pulling', $textured ? 'While conditioner is in' : ($texture === 'straight' ? 'After hair has dried a little' : 'Gently, in small sections'),
                $textured ? 'Use fingers or a wide-tooth comb while conditioner is still in the hair. Start at the ends and work upward, then rinse.'
                    : ($texture === 'straight' ? 'Let hair dry a little before combing. Start at the ends with a wide-tooth comb and work upward without tugging.'
                        : 'Start at the ends with fingers or a wide-tooth comb. Work upward in small sections; stop and loosen knots rather than pulling through.'),
                in_array('tangling', $concerns, true) ? 'Tangling is one of your recorded concerns: how you handle knots is part of the routine.'
                    : 'This tool supports gentle handling; it is not an extra treatment.', $products['accessory'] ?? null, true);
            // Straight hair is combed after the gentle-drying step below.
            if ($texture === 'straight') $straightDetangle = array_pop($wash);
        }
        $finish = $products['finish'] ?? null;
        $dampFinish = ($finish['sku'] ?? '') === 'AVE-SER-002';
        if ($finish && $dampFinish) $wash[] = self::finish($finish, $step, $dry, $concerns, true);
        $wash[] = $step('dry', 'Dry gently', 'After rinsing',
            'Blot or loosely wrap with a towel or T-shirt instead of rubbing. If you heat-style, choose a low or medium setting and a product labelled for heat protection.',
            in_array('damage', $concerns, true) ? 'You recorded rough or damaged-feeling hair: reduce friction and unnecessary heat.'
                : (in_array('frizz', $concerns, true) ? 'You recorded frizz: avoid adding friction while drying.' : 'Gentle drying is useful regardless of scalp type.'));
        if (isset($straightDetangle)) $wash[] = $straightDetangle;
        if ($finish && !$dampFinish) $wash[] = self::finish($finish, $step, $dry, $concerns, false);
        $between = [
            $step('check', 'Check roots before refreshing', 'Between wash days',
                'If your scalp feels oily or has build-up, return to the wash-day steps. Adding oil or serum is not a substitute for cleansing.',
                $scalp === 'oily_scalp' ? 'Your roots are recorded as oily, even if your ends need different care.' : 'Your scalp and lengths can need different things on the same day.'),
            $step('handle', 'Keep handling gentle', 'As needed',
                $textured ? 'Loosen knots gently with fingers. Save full comb-throughs for conditioning rather than repeatedly brushing dry curls or coils.'
                    : 'Loosen knots from the ends upward. Avoid repeated tugging or tight styles on the same section.',
                $textured ? 'This approach follows your recorded curly or coily texture.' : 'This keeps the routine focused on handling, without adding another product.'),
        ];
        if (in_array('heat_styled', explode(',', (string) ($profile['treatment_history'] ?? '')), true)) {
            $between[] = $step('heat', 'Pause before heat-styling', 'Only when you style',
                'Use a low or medium heat setting and a product explicitly labelled for heat protection. A serum or oil is not automatically a heat protectant.',
                'Regular heat styling is recorded in your assessment.');
        }
        return ['rhythm' => $rhythm, 'wash' => $wash, 'between' => $between];
    }

    private static function finish(array $product, callable $step, bool $dry, array $concerns, bool $damp): array
    {
        return $step('finish', $damp ? 'Apply your leave-in' : 'Finish the lengths', $damp ? 'On damp lengths Â· optional' : 'After drying Â· optional',
            $damp ? 'Spread a small amount through damp lengths, avoiding the scalp. Leave in and dry or style as usual, following its label.'
                : 'If you want a finishing step, apply only as directed to the lengths or ends. Keep it off roots that feel oily.',
            in_array('frizz', $concerns, true) ? 'Frizz is one of your recorded concerns: this finish is optional, not a reason to add more layers.'
                : ($dry ? 'You recorded dryness in the lengths: keep any finishing product focused there.' : 'Use this extra only when you want a finish.'), $product, true);
    }
}
