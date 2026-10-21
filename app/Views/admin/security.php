<section class="page-head">
  <div><span class="eyebrow">ADMINISTRATION</span><h1>Security</h1>
    <p>Manage sign-in limits and review account locks. CAPTCHA and staff MFA remain required.</p></div>
</section>

<section class="panel security-panel" aria-labelledby="policy-title">
  <h2 id="policy-title">Account and session policy</h2>
  <form class="form-stack" method="post" action="<?= e(url('/admin/security')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_policy">
    <div class="field-grid two">
      <?php foreach (['max_login_attempts'=>'Failed sign-in threshold', 'lockout_minutes'=>'Incident review interval (minutes)',
        'session_timeout_minutes'=>'Inactive session timeout (minutes)', 'password_min_length'=>'Minimum password length',
        'password_min_uppercase'=>'Required uppercase letters', 'password_min_lowercase'=>'Required lowercase letters',
        'password_min_numbers'=>'Required numbers', 'password_min_special'=>'Required special characters'] as $key=>$label): ?>
        <?php [$min, $max] = \App\Services\SecurityPolicy::RULES[$key]; ?>
        <label class="field"><span><?= e($label) ?></span>
          <input type="number" name="<?= e($key) ?>" value="<?= (int) $policy[$key] ?>" min="<?= $min ?>" max="<?= $max ?>" required <?= $key==='max_login_attempts'?'readonly aria-readonly="true"':'' ?>>
          <small class="muted">Allowed range: <?= $min ?>–<?= $max ?>.</small>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="muted small">New passwords always require uppercase, lowercase, a number and a special character. Changes do not invalidate existing passwords. Password expiration and mandatory TOTP role coverage remain pending project decisions.</p>
    <div><button class="btn btn-primary" type="submit">Save security settings</button></div>
  </form>
</section>

<section class="panel security-panel" aria-labelledby="locks-title">
  <h2 id="locks-title">Currently locked accounts</h2>
  <p class="muted small">Unlocking clears the failed sign-in counter. It does not reactivate an inactive account or change its password or MFA. Locks require Administrator release; the review interval never unlocks an account. Up to 100 current locks are shown.</p>
  <?php if (!$lockedAccounts): ?><p class="security-empty">No accounts are currently locked.</p>
  <?php else: ?>
    <div class="security-table-wrap" tabindex="0" role="region" aria-label="Currently locked accounts table">
      <table class="security-table"><thead><tr><th scope="col">Username</th><th scope="col">Role / status</th><th scope="col">Attempts</th><th scope="col">Review after</th><th scope="col">Action</th></tr></thead><tbody>
        <?php foreach ($lockedAccounts as $account): ?><tr>
          <td><?= e($account['username']) ?></td><td><?= e($account['role']) ?><br><small><?= e($account['status']) ?></small></td>
          <td><?= (int) $account['failed_attempts'] ?></td><td><?= e($account['locked_until']) ?></td>
          <td><form method="post" action="<?= e(url('/admin/security')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="unlock">
            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
            <input type="hidden" name="expected_lock" value="<?= e($account['locked_until']) ?>">
            <button type="submit" class="btn btn-secondary btn-sm" aria-label="Unlock <?= e($account['username']) ?>">Unlock account</button>
          </form></td>
        </tr><?php endforeach; ?>
      </tbody></table>
    </div>
  <?php endif; ?>
</section>

<section class="panel security-panel" aria-labelledby="history-title">
  <h2 id="history-title">Lock history</h2>
  <p class="muted small">Recorded from the security migration onward. Earlier incidents are not reconstructed.</p>
  <?php if (!$history): ?><p class="security-empty">No recorded lock incidents.</p>
  <?php else: ?>
    <div class="security-table-wrap" tabindex="0" role="region" aria-label="Lock history table">
      <table class="security-table"><thead><tr><th scope="col">Username at lock</th><th scope="col">Attempts</th><th scope="col">Locked at</th><th scope="col">Review after</th><th scope="col">Administrator unlock</th></tr></thead><tbody>
        <?php foreach ($history as $entry): ?><tr>
          <td><?= e($entry['username_snapshot']) ?></td><td><?= (int) $entry['failed_attempts'] ?></td>
          <td><?= e($entry['locked_at']) ?></td><td><?= e($entry['locked_until']) ?></td>
          <td><?= $entry['unlocked_at'] ? e($entry['unlocked_at'] . ' · ' . ($entry['unlocked_by_name'] ?? 'Former administrator')) : 'No manual unlock' ?></td>
        </tr><?php endforeach; ?>
      </tbody></table>
    </div>
  <?php endif; ?>
  <nav class="security-history-nav" aria-label="Lock history pages">
    <?php if (isset($_GET['before'])): ?><a href="<?= e(url('/admin/security')) ?>">Newest incidents</a><?php endif; ?>
    <?php if ($next): ?><a href="<?= e(url('/admin/security?before=' . $next)) ?>">Older incidents</a><?php endif; ?>
  </nav>
</section>
