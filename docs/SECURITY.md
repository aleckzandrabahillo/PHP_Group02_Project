# Account security

The custom PHP application uses prepared statements, encoded output, CSRF tokens, password hashing and live account/role checks. Staff still complete email OTP. TOTP enrollment and its mandatory role coverage are separate work; password expiration, PII encryption scope and cookie-consent policy remain pending decisions.

Administrators manage `/admin/security`. Catalog Managers and Customers cannot read or change these controls.

| Control | Allowed values | Default |
| --- | --- | --- |
| Failed-login threshold | 1–3 | 3 |
| Lock duration | 15–120 minutes | 15 |
| Inactivity timeout | 1–30 minutes | 30 |
| New-password minimum | 12–64 characters | 12 |

Uppercase, lowercase, a digit and a non-whitespace special character always remain required. Passwords are limited to 72 bytes to avoid bcrypt truncation. Existing passwords are not invalidated by a stronger minimum. Stored database settings take precedence over environment fallbacks; out-of-range inherited settings are bounded securely.

Account locks are serialized on the user row. Each incident records the username, attempts and timestamps in `locked_accounts`. Unlocking resets the counter without changing status, password, MFA or last-login time. Stale unlock requests are rejected. Unlock and policy audits commit with the change; required-history/audit failure rolls back the operation. History starts at deployment and does not reconstruct older incidents.

## Upgrade an existing database

Back up and restore-test the intended database first. Test the new source and migration on a restored disposable copy. The migration creates **only** the `locked_accounts` InnoDB table and its foreign keys/indexes. It never rewrites existing account records or settings. DDL implicitly commits: do not assume a transaction can undo it.

Use the intended private environment, verify server identity independently, then run:

```text
php scripts/migrate_security.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=SERVER --expect-server-id=ID --expect-datadir=ABSOLUTE_DATA_DIRECTORY
```

Dry-run is the default. After reviewing the exact target and backup, add `--apply`. Repeat runs validate the table and do nothing; incompatible existing definitions stop for review. Run the migration before deploying this source. Do not run the fresh installer against an existing database.

For rollback, stop the new runtime and restore the previous source. Preserve the additive history table and its records. Restore a database backup only with explicit approval and a reviewed plan for any data added afterward.

## Verification

`tests/security.php` requires a disposable database with a name matching `avela_*test_*`, a separate port/data directory, an isolated PHP server, and private scratch/session folders outside the checkout:

```text
php tests/security.php --expect-database=TEST_DATABASE --expect-port=TEST_PORT --expect-datadir=ABSOLUTE_TEST_DATA_DIRECTORY --base-url=http://127.0.0.1:TEST_HTTP_PORT --session-directory=PRIVATE_SESSIONS --scratch-directory=PRIVATE_SCRATCH --output=PRIVATE_RESULTS_JSON
```

It refuses personal ports 13307/18080 and normal database port 3306. It creates synthetic accounts, lock history, settings and audit/OTP records in the disposable database. Password/MFA checks use service calls with synthetic credentials; HTTP checks verify real routes, CSRF and RBAC. This does not prove real Google CAPTCHA, SMTP delivery or an authenticator device works. Keep all scratch files private. Run the existing Checkout and image suites separately.

## Deployment controls

- Serve only `public/`; keep `.env`, sessions, logs, backups and credentials outside public access and Git.
- Use HTTPS with secure session cookies and a trusted host configuration in production.
- `MAIL_DRIVER=log` is accepted only with `APP_ENV=development`; configure working SMTP for production. Mail/log write failures must not be presented as delivered OTPs.
- Configure actual reCAPTCHA v2 keys for the intended hostname. Missing keys fail closed and are shown as a configuration problem.
- File signature/size validation is not malware scanning. Add real scanning only where the host supports it.
- COD remains a demonstration/manual payment method; gateway and fulfillment integration are not claimed.

IAS evidence: workbook C9/C10 (bounded lockout), C11/C12 (password complexity/length), C13 (unlock), C14 (timeout), C15–C19 (preserved access/input/session protections), C24 (lock history), C27 (transactional security audits). Expiration in C11 and remaining production/MFA/privacy items need separate evidence.
