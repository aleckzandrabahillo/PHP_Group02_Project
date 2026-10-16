<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\HairProfile;
use PDO;

final class RecommendationService
{
    public function __construct(private readonly ?PDO $connection = null) {}

    public function forUser(int $userId, bool $lock = false): array
    {
        $pdo = $this->connection ?? Database::connection();
        $profile = (new HairProfile($pdo))->forUser($userId, $lock);
        if (!$profile) return ['profile' => null, 'ranked' => [], 'routine' => ['items' => [], 'warnings' => []], 'fingerprint' => ''];
        $analysis = (new ConcernInterpreter())->interpret((string) $profile['concern_text']);
        $textTreatments = array_column(array_filter($analysis['tags'], static fn($tag) => $tag['type'] === 'treatment'), 'code');
        $matcher = new ProductMatcher();
        $ranked = $matcher->rank($profile, (new SuitabilityRepository())->candidates($pdo, $lock), $textTreatments);
        $routine = $matcher->routine($profile, $ranked);
        $fingerprint = hash('sha256', json_encode([ProductMatcher::VERSION, HairProfile::version($profile), $ranked, $routine], JSON_THROW_ON_ERROR));
        return ['profile' => $profile, 'ranked' => $ranked, 'routine' => $routine, 'fingerprint' => $fingerprint];
    }
}
