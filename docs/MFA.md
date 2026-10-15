# Authenticator enrollment

Signed-in Customers, Catalog Managers and Administrators can manage their own authenticator at `/account/mfa`. Enrollment is optional while mandatory course role coverage is pending. Existing staff email OTP remains required. Enrolled accounts sign in with password → email OTP → TOTP or one-use recovery code. Activation and profile-email verification remain separate existing flows.

## Setup and recovery

1. Confirm the current password to start setup; wait for the existing email-code cooldown if necessary.
2. In Google Authenticator or a compatible app, add a **time-based** account using the private setup key and Avela username. Manual key entry is supported; no external QR service receives the key.
3. Confirm both the email code and an authenticator code within five minutes.
4. Save the eight recovery codes privately. They are displayed once. Each replaces one TOTP after password and email verification.

Codes use the standard six-digit SHA-1/30-second TOTP format with one adjacent time step of tolerance. Consumed steps cannot be reused, including simultaneous requests. Three incorrect authenticator attempts lock the account under the existing policy. Recovery codes cannot bypass inactive/locked status or email verification.

Removing an authenticator requires the current password and a current TOTP or unused recovery code. Email MFA stays enabled. Enrollment/removal invalidate older signed-in sessions and pending challenges; removal signs out the current session. Administrators cannot view another user's secret or recovery codes through these pages.

## Deployment prerequisites

- PHP 8.2+, PDO MySQL and OpenSSL; synchronize the host clock.
- Restore locked Composer dependencies using `composer install --no-dev --no-plugins --no-scripts`. OTPHP and its small clock/encoding/deprecation support libraries are functional libraries; Avela keeps its custom PHP architecture. No framework is introduced.
- Back up and restore-test the target database. Apply `migrate_security.php` first, then run the following guarded dry-run against a disposable restored copy:

```text
php scripts/migrate_mfa.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=SERVER --expect-server-id=ID --expect-datadir=ABSOLUTE_DATA_DIRECTORY
```

The additive migration creates only `user_authenticators`. It does not enroll users or change existing accounts. After reviewing the backup and target, add `--apply`; incompatible definitions stop. DDL implicitly commits. Retain the added table during an application rollback; do not discard enrollment data.

Generate the encryption key **privately**, outside the checkout, without printing it:

```text
php scripts/create_mfa_key.php --output=PRIVATE_KEY_FILE
```

Use a private directory with appropriate OS permissions. The script refuses overwrite and paths inside the checkout. Privately put the generated `MFA_ENCRYPTION_KEY` value in the intended environment before enrollment. Do not copy it into examples, commits, screenshots or messages. Keep a secure backup with database recovery material. Existing environments without a key retain their current unenrolled login flow; enrollment is unavailable.

Secrets use OpenSSL authenticated AES-256-GCM with random nonces and account-bound authentication data. Recovery codes contain 128 random bits and are stored as keyed hashes. Lost or casually rotated keys make enrolled factors/recovery hashes unusable: key rotation and account recovery require a separate reviewed operation. Restoring old source without MFA support after users enroll would remove TOTP enforcement; do not use that as an unattended rollback. Preserve the MFA-capable source or place the site in maintenance while restoring a coherent source/database/key backup.

## Verification

`tests/mfa.php` uses the same disposable-only flags as `tests/security.php`, with the MFA preview URL. It creates synthetic accounts/enrollments, tests encryption integrity, the public RFC vector, replay/concurrency, lockout, recovery, HTTP verification, RBAC and session invalidation. Scratch files contain synthetic credentials/recovery material and must remain private outside Git. Run security, Checkout and catalog regressions afterward.

Service-generated codes and browser recovery-code tests demonstrate the implemented flow. They do not demonstrate a physical Google Authenticator device, real Google CAPTCHA, SMTP delivery, HTTPS or production hosting. Complete those checks privately on the approved deployment.
