# Account security

The custom PHP application uses prepared statements, encoded output, CSRF tokens, password hashing and live account/role checks. Staff still complete email OTP. [Optional authenticator enrollment](MFA.md) adds TOTP and one-use recovery codes; mandatory role coverage remains pending. Password expiration, PII encryption scope and cookie-consent policy also remain pending decisions.

Administrators manage `/admin/security`. Catalog Managers and Customers cannot read or change these controls.

| Control | Allowed values | Default |
| --- | --- | --- |
| Failed-login threshold | 1–3 | 3 |
| Incident review interval (does not unlock) | 15–120 minutes | 15 |
| Inactivity timeout | 1–30 minutes | 30 |
| New-password minimum | 12–64 characters | 12 |
| Each required uppercase/lowercase/number/special count | 1–4 | 1 |

Uppercase, lowercase, a digit and a non-whitespace special character always remain required. Administrators can strengthen each required count without disabling a class. Passwords are limited to 72 bytes to avoid bcrypt truncation. Existing passwords are not invalidated by a stronger minimum. Stored database settings take precedence over environment fallbacks; out-of-range inherited settings are bounded securely. [Password changes and proposed expiry](PASSWORDS.md) explain credential revocation, the guarded migration and default-disabled expiration.

Account locks are serialized on the user row and persist until an authorized Administrator releases them. The recorded review interval is informational, never an automatic unlock. The default/hard cap is three; administrators may select a stricter one or two. IAS C9 says maximum three, while C10 offers customization. If the instructor requires exactly three in every configuration, keep the setting at three. Login requests are additionally bounded per network address after CSRF validation; CAPTCHA remains required.

Account locks are serialized on the user row. Each incident records the username, attempts and timestamps in `locked_accounts`. Unlocking resets the counter without changing status, password, MFA or last-login time. Stale unlock requests are rejected. Unlock and policy audits commit with the change; required-history/audit failure rolls back the operation. History starts at deployment and does not reconstruct older incidents.

## Upgrade an existing database

Back up and restore-test the intended database first. Test the new source and migration on a restored disposable copy. The migration creates **only** the `locked_accounts` InnoDB table and its foreign keys/indexes. It never rewrites existing account records or settings. DDL implicitly commits: do not assume a transaction can undo it.

Use the intended private environment, verify server identity independently, then run:

```text
php scripts/migrate_security.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=SERVER --expect-server-id=ID --expect-datadir=ABSOLUTE_DATA_DIRECTORY
```

Dry-run is the default. After reviewing the exact target and backup, add `--apply`. Repeat runs validate the table and do nothing; incompatible existing definitions stop for review. Run the migration before deploying this source. Do not run the fresh installer against an existing database.

After the original security migration, run `scripts/migrate_locks.php` with the same exact identity guards, first dry-run and then explicitly approved apply. This adds `users.lock_requires_admin` and `security_rate_limits`; it preserves currently active legacy locks without relocking already expired ones. Repeat runs validate definitions. Personal deployment requires separate approval and a fresh restore-tested backup.

Do not roll back to code that ignores persisted locks while they exist. Preserve additive schema and all history. Database restoration requires explicit approval and a plan for newer records.

## Verification

`tests/security.php` requires a disposable database with a name matching `avela_*test_*`, a separate port/data directory, an isolated PHP server, and private scratch/session folders outside the checkout:

```text
php tests/security.php --expect-database=TEST_DATABASE --expect-port=TEST_PORT --expect-datadir=ABSOLUTE_TEST_DATA_DIRECTORY --base-url=http://127.0.0.1:TEST_HTTP_PORT --session-directory=PRIVATE_SESSIONS --scratch-directory=PRIVATE_SCRATCH --output=PRIVATE_RESULTS_JSON
```

It refuses personal ports 13307/18080 and normal database port 3306. It creates synthetic accounts, lock history, settings and audit/OTP records in the disposable database. Password/MFA checks use service calls with synthetic credentials; HTTP checks verify real routes, CSRF and RBAC. This does not prove real Google CAPTCHA, SMTP delivery or an authenticator device works. Keep all scratch files private. Run the existing Checkout and image suites separately.

## Deployment controls

Staff setup on Windows uses `pwsh -File scripts/create_staff.ps1` (PowerShell 7) with hidden password confirmation. The PHP helper accepts securely redirected stdin only; password arguments are no longer supported. It validates role/email/username/name and the shared password policy, creates the existing active staff/profile records, and preserves mandatory staff email OTP. Passwords and raw database exceptions are not printed.

Reproduce its positive/negative and record-preservation checks only in a disposable development database:

```sh
php tests/create_staff.php --expect-database=TEST_DATABASE --expect-port=TEST_PORT --expect-datadir=ABSOLUTE_TEST_DATA_DIRECTORY --output=PRIVATE_RESULTS_JSON
```

The test refuses database ports 3306/13307, generates synthetic credentials in memory, passes them only through child stdin, and leaves two synthetic staff accounts in the disposable database. The PowerShell prompt still depends on a trusted local terminal; never paste personal passwords into a conversation or command.

- Serve only `public/`; keep `.env`, sessions, logs, backups and credentials outside public access and Git.
- Use HTTPS with secure session cookies and a trusted host configuration in production.
- `MAIL_DRIVER=log` is accepted only with `APP_ENV=development`; configure working SMTP for production. Mail/log write failures must not be presented as delivered OTPs.
- Configure actual reCAPTCHA v2 keys for the intended hostname. Missing keys fail closed and are shown as a configuration problem.
- File signature/size validation is not malware scanning. Add real scanning only where the host supports it.
- COD remains a demonstration/manual payment method; gateway and fulfillment integration are not claimed.

IAS evidence: workbook C9/C10 (bounded lockout), C11/C12 (password complexity/length), C13 (unlock), C14 (timeout), C15–C19 (preserved access/input/session protections), C24 (lock history), C27 (transactional security audits). Expiration in C11 and remaining production/MFA/privacy items need separate evidence.

## Private-console Administrator recovery

If all Administrators become locked, a trusted host operator can run `scripts/recover_admin.ps1` locally with `-Actor`, `-Target`, `-Php` and the complete `-DatabaseGuards` array used by migrations. Dry-run the PHP script first. An active Administrator must prove their current password and authenticator/unused recovery code, or existing email OTP when unenrolled. Use `-SendCode` for the latter, then run the hidden prompts. Never put proof values in arguments, history, chat or reports.

This operation unlocks the chosen record atomically with incident/audit updates; it does not log the operator in, alter passwords, reactivate users or remove MFA. Proof attempts are persisted and limited to three per fifteen minutes. Host access alone is insufficient without Administrator proof. Keep the private MFA key and a usable staff email channel/recovery codes secured. The recovery script has no HTTP route.

`tests/persistent_locks.php` adds elapsed-time, all-admin recovery, real CLI stdin, history preservation, proof/audit rollback, throttle and malformed-CSRF checks under the same disposable-only guards as `tests/security.php`.
