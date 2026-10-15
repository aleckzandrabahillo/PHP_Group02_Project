<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthGuard;
use App\Services\SecurityManagement;
use App\Services\SecurityPolicy;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class SecurityController
{
    public function __construct(private readonly SecurityManagement $security) {}

    public function page(): void
    {
        AuthGuard::roles(['admin']);
        $before = filter_var($_GET['before'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $history = $this->security->history($before === false ? 0 : $before);
        $next = count($history) > 20 ? (int) $history[19]['id'] : null;
        View::render('admin/security', [
            'pageTitle' => 'Security', 'policy' => SecurityPolicy::values(),
            'lockedAccounts' => $this->security->lockedAccounts(), 'history' => array_slice($history, 0, 20), 'next' => $next,
        ]);
    }

    public function save(): void
    {
        $actor = AuthGuard::roles(['admin']);
        Csrf::enforce(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null);
        try {
            if (($_POST['action'] ?? '') === 'save_policy') {
                $values = array_intersect_key($_POST, SecurityPolicy::RULES);
                $this->security->savePolicy((int) $actor['id'], $values);
                Session::flash('success', 'Security settings saved. Active sessions use the updated timeout on their next request.');
            } elseif (($_POST['action'] ?? '') === 'unlock') {
                $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $lock = $_POST['expected_lock'] ?? null;
                if (!$id || !is_string($lock)) throw new InvalidArgumentException('Choose a valid locked account.');
                $this->security->unlock((int) $actor['id'], $id, $lock);
                Session::flash('success', 'Account unlocked. Its status, password and MFA requirements are unchanged.');
            } else {
                throw new InvalidArgumentException('Choose a valid security action.');
            }
        } catch (DomainException | InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'The security change could not be saved. No partial change was committed.');
        }
        Response::redirect('/admin/security');
    }
}
