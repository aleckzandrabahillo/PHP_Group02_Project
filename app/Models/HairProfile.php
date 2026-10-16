<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HairProfileValidator;
use InvalidArgumentException;
use PDO;

final class HairProfile
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function forUser(int $userId, bool $lock = false): ?array
    {
        $pdo = $this->connection ?? Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM hair_profiles WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        if (!$profile) return null;
        $stmt = $pdo->prepare('SELECT t.tag_type AS type,t.code,t.display_name,hpt.source FROM hair_profile_tags hpt
            JOIN tags t ON t.id=hpt.tag_id WHERE hpt.hair_profile_id=? ORDER BY t.tag_type,t.code,hpt.source');
        $stmt->execute([$profile['id']]);
        $profile['tags'] = $stmt->fetchAll();
        return $profile;
    }

    public static function version(?array $profile): string
    {
        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR));
    }

    public static function values(?array $profile): array
    {
        if (!$profile) return HairProfileValidator::defaults();
        $values = array_intersect_key($profile, HairProfileValidator::defaults());
        $values['concerns'] = array_values(array_map(static fn($tag) => $tag['code'],
            array_filter($profile['tags'], static fn($tag) => $tag['type'] === 'concern' && $tag['source'] === 'assessment')));
        $values['treatments'] = array_values(array_intersect(explode(',', (string) $profile['treatment_history']),
            array_keys(HairProfileValidator::CHOICES['treatments'])));
        return array_replace(HairProfileValidator::defaults(), $values, ['concern_text' => $profile['concern_text'] ?? '']);
    }

    public function save(int $userId, array $input, array $interpretedTags, string $version): int
    {
        $checked = HairProfileValidator::validate($input);
        if ($checked['errors']) throw new InvalidArgumentException('Review the highlighted profile fields.');
        $values = $checked['values'];
        $pdo = $this->connection ?? Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!User::canAuthenticate($user) || $user['role'] !== 'customer') throw new InvalidArgumentException('Please sign in with an active customer account.');
            $current = $this->forUser($userId, true);
            if (!hash_equals(self::version($current), $version)) throw new InvalidArgumentException('Your profile changed in another window. Reload before saving again.');
            $args = [$values['hair_texture'], $values['scalp_type'], $values['hair_condition'],
                implode(',', $values['treatments']), $values['concern_text'] ?: null];
            if ($current) {
                $id = (int) $current['id'];
                $stmt = $pdo->prepare('UPDATE hair_profiles SET hair_texture=?,scalp_type=?,hair_condition=?,treatment_history=?,concern_text=?,updated_at=NOW() WHERE id=? AND user_id=?');
                $stmt->execute([...$args, $id, $userId]);
                $pdo->prepare('DELETE FROM hair_profile_tags WHERE hair_profile_id=?')->execute([$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO hair_profiles (hair_texture,scalp_type,hair_condition,treatment_history,concern_text,user_id) VALUES (?,?,?,?,?,?)');
                $stmt->execute([...$args, $userId]);
                $id = (int) $pdo->lastInsertId();
            }
            $lookup = $pdo->prepare('SELECT id FROM tags WHERE tag_type=? AND code=?');
            $insert = $pdo->prepare('INSERT IGNORE INTO hair_profile_tags (hair_profile_id,tag_id,source) VALUES (?,?,?)');
            foreach (['assessment' => HairProfileValidator::assessmentTags($values), 'nlp' => $interpretedTags] as $source => $tags) {
                foreach ($tags as $tag) {
                    // Structured choices take precedence over a conflicting text guess.
                    if ($source === 'nlp' && (($tag['type'] === 'scalp' && $values['scalp_type'] !== 'unsure' && $tag['code'] !== $values['scalp_type'])
                        || ($tag['type'] === 'texture' && $values['hair_texture'] !== 'unsure' && $tag['code'] !== $values['hair_texture']))) continue;
                    if (!in_array($tag['type'] ?? '', ['concern', 'scalp', 'texture'], true)) continue;
                    $lookup->execute([$tag['type'], $tag['code']]);
                    $tagId = $lookup->fetchColumn();
                    if (!$tagId) throw new \RuntimeException('Required hair-tag metadata is unavailable.');
                    $insert->execute([$id, $tagId, $source]);
                }
            }
            $pdo->prepare('INSERT INTO audit_logs (actor_id,action,target_type,target_id,details) VALUES (?,?,?,?,?)')
                ->execute([$userId, $current ? 'hair_profile.update' : 'hair_profile.create', 'hair_profile', $id,
                    'Structured hair profile and optional rule-based interpretation saved. No concern text recorded in this audit.']);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
