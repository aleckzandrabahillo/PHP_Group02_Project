<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\HairProfileValidator;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Models\HairProfile;
use App\Services\ConcernInterpreter;

final class HairProfileController
{
    public function __construct(private readonly HairProfile $profiles, private readonly ConcernInterpreter $interpreter) {}

    public function assessment(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        $this->render((int) $user['id']);
    }

    public function save(): void
    {
        header('Cache-Control: no-store, private');
        $user = AuthGuard::roles(['customer']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        $checked = HairProfileValidator::validate($_POST);
        $version = is_string($_POST['profile_version'] ?? null) ? $_POST['profile_version'] : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $version)) $checked['errors']['form'] = 'Refresh this page before saving your profile.';
        if ($checked['errors']) {
            http_response_code(422);
            $this->render((int) $user['id'], $checked['values'], $checked['errors'], $version);
            return;
        }
        try {
            $analysis = $this->interpreter->interpret($checked['values']['concern_text']);
            $this->profiles->save((int) $user['id'], $checked['values'], $analysis['tags'], $version);
            Session::flash('success', 'Your hair profile has been saved.');
            http_response_code(303);
            Response::redirect('/assessment');
        } catch (\InvalidArgumentException $e) {
            http_response_code(409);
            $this->render((int) $user['id'], $checked['values'], ['form' => $e->getMessage()], $version);
        } catch (\Throwable) {
            http_response_code(503);
            $this->render((int) $user['id'], $checked['values'], ['form' => 'Your profile was not changed. Try again, or leave the optional description blank if text interpretation is unavailable.'], $version);
        }
    }

    private function render(int $userId, ?array $values = null, array $errors = [], ?string $version = null): void
    {
        $profile = $this->profiles->forUser($userId);
        $analysis = null;
        if ($profile && $values === null) {
            try { $analysis = $this->interpreter->interpret((string) $profile['concern_text']); }
            catch (\Throwable) { $analysis = ['tags' => [], 'matches' => [], 'warnings' => ['Text interpretation is temporarily unavailable. Your saved profile remains intact.']]; }
        }
        $options = (new \App\Models\Product())->filterOptions();
        View::render('customer/assessment', ['pageTitle' => 'Your Hair Profile', 'hairProfile' => $profile,
            'values' => $values ?? HairProfile::values($profile), 'errors' => $errors,
            'profileVersion' => $version ?? HairProfile::version($profile), 'analysis' => $analysis,
            'choices' => HairProfileValidator::CHOICES,
            'storeNav' => ['categories' => $options['categories'], 'concerns' => $options['concern']],
            'favoriteIds' => (new \App\Models\Favorite())->idsForUser($userId),
            'favoriteCount' => (new \App\Models\Favorite())->countForUser($userId),
            'cartCount' => (new \App\Models\Cart())->countForUser($userId)]);
    }
}
