# Authenticator enrollment

Signed-in Customers, Catalog Managers and Administrators can manage their own authenticator at `/account/mfa`. The owner confirmed on 24 October that Customer enrollment is optional. Enrolled Customers sign in with password → Authenticator or a one-use recovery code; Customer login sends no email OTP. Customers without an active authenticator use password-only sign-in, including older email-only MFA flags. Removing a Customer authenticator disables their optional MFA and preserves session-version revocation. Administrators and Catalog Managers use password → Authenticator or a one-use recovery code once enrolled. Until enrollment, they may sign in with their email/username and password; legacy email-MFA flags do not represent an enrolled authenticator. Staff login sends no email OTP. This owner-approved enrollment transition does not claim mandatory MFA coverage for unenrolled accounts. Registration and sensitive account changes remain separate verified flows. Customers see the optional setup suggestion once on their first signed-in landing page. Staff can open 2-step verification from their own account navigation.

## Setup and recovery

1. Confirm the current password to start setup; wait for the existing email-code cooldown if necessary.
2. Open Google Authenticator or a compatible app on your phone and choose Add account.
3. Copy the full setup key, displayed in groups of four, or open View QR code to scan a locally generated QR in a dialog. Copy removes display spaces; the generated secret is not shortened. Continue opens confirmation on the same page, with Back available. A shared countdown keeps the original five-minute deadline; expiry clears the key, QR and code fields and offers Start again. Without JavaScript, the full key and guarded confirmation form remain usable (Time based). The QR uses issuer Avela and your account username, not the store's Gmail address. Confirm both the email code and an authenticator code within five minutes. No external QR service receives the key; no provisioning URI is put in an image URL, query string, browser storage or diagnostic log. Expired setup is rejected by the server and removed from the page. Each user enrolls their own account; signing into Google Authenticator for optional sync does not need the store's Google account.
4. Save the eight recovery codes privately. They are displayed once. Each replaces one TOTP after password verification.

Codes use the standard six-digit SHA-1/30-second TOTP format with one adjacent time step of tolerance. Consumed steps cannot be reused, including simultaneous requests. Three incorrect authenticator attempts lock the account under the existing policy. Recovery codes cannot bypass inactive/locked status, the password step, or challenge expiry/version checks.

Removing an authenticator requires the current password and a current TOTP or unused recovery code. After confirmed removal, password sign-in is available until reenrollment; email proofs remain required for enrollment and sensitive account changes. Enrollment/removal invalidate older signed-in sessions and pending challenges; removal signs out the current session. Administrators cannot view another user's secret or recovery codes through these pages.

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
