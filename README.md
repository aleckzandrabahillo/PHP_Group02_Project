# Avela

A student hair-care demonstration store for Group 02's Open Source Programming and Information Assurance and Security project. Built with native PHP, MySQL/MariaDB, HTML, CSS and vanilla JavaScript; no application framework.

## Supported features

- Catalog, filters, sorting, pagination, product details and persistent favorites.
- Customer profiles, structured saved delivery addresses and independently editable Checkout recipients.
- Customer registration with reCAPTCHA v2 Checkbox, single-use email activation links and numeric verification fallback.
- Transactional Cash on Delivery Checkout, shipping quotes, guarded discounts, public order references, private history and fulfillment timelines.
- Maya-hosted **Sandbox-only** card checkout with authenticated payment reconciliation, idempotency and stock reservations. Avela does not collect or store card details; browser redirects do not prove payment.
- Catalog Manager/Administrator Category and Product CRUD, image uploads, archive/reactivation and guarded inventory updates.
- Staff email OTP, optional authenticator enrollment/recovery, three-attempt account locking, Administrator recovery, password changes/expiry and automatic session-expiry navigation.
- Administrator users/staff management, actor-attributed audits, filtered activity lists and transactional order cancellation/stock restoration.
- Responsive customer/staff pages, dashboard reports, native address dialogs, consistent interaction motion and staff list filtering/sorting/paging.
- Private, editable hair profiles with separate scalp/length characteristics and bounded English/Taglish concern interpretation using Python.
- Explainable profile-based product matches and saved routines, using reviewed demonstration suitability metadata and existing cart/Checkout paths.

External SMTP delivery, public HTTPS, genuine Sandbox webhook delivery and worker scheduling require operator configuration and separate acceptance evidence. Sensitive-field encryption remains a separate planned workstream. The Privacy Policy retains its review notice until a monitored contact channel is configured and verified. Source tests do not establish production or legal compliance.

## Local setup

Concern interpretation requires Python **3.10+** (standard library only) and PHP proc_open. Configure a working NLP_PYTHON_BINARY in private .env; no Python service, packages or external API is required. See [hair-profile setup and limitations](docs/HAIR_PROFILE.md).

Requires PHP **8.2+**, Composer, MySQL/MariaDB with InnoDB, and PHP `pdo_mysql`, `mbstring`, `fileinfo` and `openssl`; Maya additionally requires `curl`. PHP must support HTTPS requests for Google verification. Enable required extensions in your development environment; do not replace locked dependencies with an update.

1. Clone the repository and run `composer install`.
2. Copy `.env.example` to private `.env`. Set your own database target, `APP_ENV=development`, `APP_URL=http://localhost:8000`, and reCAPTCHA v2 Checkbox keys registered for **localhost**. Never commit credentials. Missing CAPTCHA configuration fails closed.
3. Use `MAIL_DRIVER=log` only for local development. Your own email/OTP entry appears in private `storage/logs/dev_mail.log`; production requires working SMTP. Do not share codes or commit logs.
4. For a **new, empty database only**, review the configured target, then run `php scripts/install.php`. This executes the schema and seed data. **Do not use it to upgrade an existing database.**
5. Generate a private, backed-up authenticator encryption key using [MFA setup](docs/MFA.md). Keep the key outside the public root and Git, and configure `MFA_ENCRYPTION_KEY` privately.
6. Start the local server from the repository root:

```sh
php -S localhost:8000 -t public router.php
```

Open **http://localhost:8000** consistently. `127.0.0.1` is a different CAPTCHA hostname and browser cookie origin. The PHP development server is not a production server.

For an existing installation, back up and restore-test the database first. Review the guarded upgrades documented in [Checkout](docs/CHECKOUT.md), [security](docs/SECURITY.md), [authenticator](docs/MFA.md), [passwords](docs/PASSWORDS.md), [email activation](docs/EMAIL.md), [matching/routines](docs/RECOMMENDATIONS.md), [account/address experience](docs/ACCOUNT_EXPERIENCE.md) and [Maya Sandbox](docs/MAYA_SANDBOX.md). Dry-run the exact database/server identity guards and apply only missing, separately approved additions. MySQL DDL may commit implicitly; a code rollback does not undo a schema migration. Never copy another member's environment file, encryption key, sessions or database dumps.

COD works independently of Maya. Keep the Maya readiness flags false until their documented requirements are satisfied. Local simulation and the owner's loopback hosted-Sandbox experiment require separate guarded private launchers; setting an environment flag alone does not enable them. This repository does not expose a local site, register webhooks or install a scheduler.

## Staff setup and roles

On Windows, use PowerShell 7's secure prompt (set `-PhpBinary` if PHP is not on PATH):

```powershell
pwsh -File scripts/create_staff.ps1 -Role catalog_manager -Email catalog@example.test -Username local_catalog -FullName "Local Catalog Manager"
```

Choose `admin` for an Administrator. This creates an active account and its staff profile through the existing model; staff email OTP remains required. Passwords must satisfy the configured policy (at least 12 characters with upper/lowercase, number and special character; at most 72 bytes). They are hashed with PHP `password_hash()`.

The underlying PHP helper accepts `<role> <email> <username> <full-name> --password-stdin` for trusted automation with securely redirected input. It refuses interactive plaintext input and the old password argument format. Never put a password in shell history, arguments or a source file. The wrapper cannot guarantee removal of all temporary managed-memory copies; use a trusted local terminal.

- Customers: `/home`, `/profile`, `/orders`; only their own records.
- Catalog Managers: `/catalog`, `/catalog/products`, `/catalog/categories`, `/catalog/inventory`.
- Administrators: `/admin`, `/admin/security`, plus catalog access.
- Own-account authenticator settings: `/account/mfa`.

## Demonstration data and assets

The canonical [catalog manifest](database/demo_catalog.json) contains twenty fictional products. The [separate image manifest](database/demo_images.json) contains fifteen approved photographs; five products retain placeholders. Source assets alone do not assign database image paths.

- [Repeat-safe catalog import and disposable tests](docs/DEMO_CATALOG.md).
- [Guarded image assignment and disposable tests](docs/DEMO_IMAGES.md).
- [Five-megabyte image upload policy](docs/UPLOADS.md).
- Product-image review/provenance records remain alongside the approved assets in `public/assets/images/products/demo/`.
- Inter is served locally, unmodified, under its [SIL Open Font License](public/assets/fonts/inter/LICENSE.txt); [source revision/checksum](public/assets/fonts/inter/SOURCE.txt).

## Verification and deployment

For profile-based matching and saved routines, see [matching rules, evidence and the additive routine migration](docs/RECOMMENDATIONS.md). Existing installations need the guarded routine_contexts addition before deploying those routes. tests/product_matching.php covers the pure rules; tests/matching_integration.php requires a disposable database.

`tests/checkout.php`, `tests/security.php`, `tests/mfa.php` and `tests/create_staff.php` require a verified disposable database and explicit port/data-directory guards. Use the reproduction commands in the focused documents; never point mutating tests at personal/shared data. HTTP/service fixtures, actual browser interaction and real provider/device verification are separate evidence.

Keep `.env`, logs, backups, sessions, recovery codes and user uploads private. Serve only `public/`; configure HTTPS, SMTP, upload execution restrictions, filesystem permissions and secure backups before hosting. Real malware scanning depends on host support. Local HTTP and MIME checks do not prove production compliance.

## Group members

Aleckzandra Bahillo (@aleckzandrabahillo), Jasmine Iris Eva (@EvaIris5892), Alexzis Mae Tutor (@alexzismaetutor), Angela Nicole Zurbano (@zurbanoangelanicole).
