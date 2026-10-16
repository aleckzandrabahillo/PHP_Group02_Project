<?php
declare(strict_types=1);

namespace App\Core;

final class HairProfileValidator
{
    public const CHOICES = [
        'hair_texture' => ['straight' => 'Straight', 'wavy' => 'Wavy', 'curly' => 'Curly', 'coily' => 'Coily', 'unsure' => 'Not sure'],
        'scalp_type' => ['normal_scalp' => 'Balanced', 'oily_scalp' => 'Oily roots / scalp', 'dry_scalp' => 'Dry-feeling scalp', 'unsure' => 'Not sure'],
        'hair_condition' => ['balanced' => 'Balanced', 'dry' => 'Dry lengths or ends', 'oily' => 'Oily lengths', 'mixed' => 'Both dry and oily areas', 'unsure' => 'Not sure'],
        'concerns' => ['dryness' => 'Dryness', 'frizz' => 'Frizz', 'tangling' => 'Tangling', 'damage' => 'Rough or damaged-feeling hair', 'oiliness' => 'Oiliness'],
        'treatments' => ['colored' => 'Colored', 'bleached' => 'Bleached', 'heat_styled' => 'Regular heat styling', 'permed' => 'Permed', 'relaxed' => 'Chemically straightened'],
    ];

    public static function defaults(): array
    {
        return ['hair_texture' => '', 'scalp_type' => '', 'hair_condition' => '',
            'concerns' => [], 'treatments' => [], 'concern_text' => ''];
    }

    public static function validate(array $input): array
    {
        $values = self::defaults();
        $errors = [];
        foreach (['hair_texture', 'scalp_type', 'hair_condition'] as $field) {
            $values[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            if (!isset(self::CHOICES[$field][$values[$field]])) $errors[$field] = 'Choose one of the listed options.';
        }
        foreach (['concerns', 'treatments'] as $field) {
            $raw = $input[$field] ?? [];
            if (!is_array($raw) || count($raw) > count(self::CHOICES[$field])) {
                $errors[$field] = 'Choose only the listed options.';
                continue;
            }
            foreach ($raw as $code) {
                if (!is_string($code) || !isset(self::CHOICES[$field][$code])) {
                    $errors[$field] = 'Choose only the listed options.';
                    continue;
                }
                $values[$field][] = $code;
            }
            $values[$field] = array_values(array_unique($values[$field]));
            sort($values[$field]);
        }
        $text = $input['concern_text'] ?? '';
        if (!is_string($text) || !mb_check_encoding($text, 'UTF-8')) {
            $errors['concern_text'] = 'Enter a short description using valid text.';
        } else {
            $values['concern_text'] = trim($text);
            if (mb_strlen($text, 'UTF-8') > 500 || strlen($text) > 2000
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $text)) {
                $errors['concern_text'] = 'Keep your description to 500 characters without control characters.';
            }
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public static function assessmentTags(array $values): array
    {
        $tags = array_map(static fn($code) => ['type' => 'concern', 'code' => $code], $values['concerns']);
        if ($values['hair_texture'] !== 'unsure') $tags[] = ['type' => 'texture', 'code' => $values['hair_texture']];
        if ($values['scalp_type'] !== 'unsure') $tags[] = ['type' => 'scalp', 'code' => $values['scalp_type']];
        if (in_array($values['hair_condition'], ['dry', 'mixed'], true)) $tags[] = ['type' => 'concern', 'code' => 'dryness'];
        if (in_array($values['hair_condition'], ['oily', 'mixed'], true)) $tags[] = ['type' => 'concern', 'code' => 'oiliness'];
        return $tags;
    }
}
