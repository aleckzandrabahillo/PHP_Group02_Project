<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Services\RecommendationService;
use App\Services\RoutineBuilder;

final class RecommendationController
{
    public function matches(): void
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
        try {
            (new RoutineBuilder())->save((int) $user['id'], is_string($_POST['plan_fingerprint'] ?? null) ? $_POST['plan_fingerprint'] : '');
            Session::flash('success', 'Your routine has been saved. Add only the products you need.');
            http_response_code(303);
            Response::redirect('/routine');
        } catch (\InvalidArgumentException $e) {
            http_response_code(409);
            $this->render((int) $user['id'], $e->getMessage());
        } catch (\Throwable) {
            http_response_code(503);
            $this->render((int) $user['id'], 'Your routine was not changed. Please try again later.');
        }
    }

    private function render(int $userId, ?string $error = null): void
    {
        try { $result = (new RecommendationService())->forUser($userId); }
        catch (\Throwable) {
            http_response_code(503);
            $error = 'Matches are temporarily unavailable. Your saved profile and routine are preserved.';
            $result = ['profile' => null, 'ranked' => [], 'routine' => ['items' => [], 'warnings' => []], 'fingerprint' => ''];
        }
        $options = (new \App\Models\Product())->filterOptions();
        View::render('customer/recommendations', ['pageTitle' => 'Your Hair-Care Matches', 'result' => $result, 'error' => $error,
            'storeNav' => ['categories' => $options['categories'], 'concerns' => $options['concern']],
            'favoriteIds' => (new \App\Models\Favorite())->idsForUser($userId),
            'favoriteCount' => (new \App\Models\Favorite())->countForUser($userId),
            'cartCount' => (new \App\Models\Cart())->countForUser($userId)]);
    }
}
