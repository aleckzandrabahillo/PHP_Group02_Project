# Avela

A student hair-care demonstration store for Group 02's Open Source Programming and Information Assurance and Security project. Built with native PHP, MySQL/MariaDB, HTML, CSS and vanilla JavaScript; no application framework.

## Supported features

- Catalog, filters, sorting, pagination, product details and persistent favorites.
- Customer profiles, structured saved delivery addresses and independently editable Checkout recipients.
- Customer registration with reCAPTCHA v2 Checkbox, email-code verification followed by a separate, single-use activation link.
- Transactional Cash on Delivery Checkout, shipping quotes, guarded discounts, public order references, private history and fulfillment timelines.
- Maya-hosted **Sandbox-only** card checkout with authenticated payment reconciliation, idempotency and stock reservations. Avela does not collect or store card details; browser redirects do not prove payment.
- Catalog Manager/Administrator Category and Product CRUD, image uploads, archive/reactivation and guarded inventory updates.
- Staff email OTP, optional authenticator enrollment/recovery, configurable account locking (default three attempts), Administrator recovery, password changes/expiry and automatic session-expiry navigation.
- Administrator users/staff management, actor-attributed audits, filtered activity lists and transactional order cancellation/stock restoration.
- Responsive customer/staff pages, dashboard reports, native address dialogs, consistent interaction motion and staff list filtering/sorting/paging.
- Private, editable hair profiles with separate scalp/length characteristics and bounded English/Taglish concern interpretation using Python.
- Explainable profile-based product matches and saved routines, using reviewed demonstration suitability metadata and existing cart/Checkout paths.

External SMTP delivery, public HTTPS, genuine Sandbox webhook delivery and worker scheduling require operator configuration and separate acceptance evidence. Selected PII encryption and real upload scanning are opt-in implementations requiring separately verified schema/key/scanner rollout. The Privacy Policy retains its review notice until a monitored contact channel is configured and verified. Source tests do not establish production or legal compliance.

## Local setup

Concern interpretation requires Python **3.10+** (standard library only) and PHP proc_open. Configure a working NLP_PYTHON_BINARY in private .env; no Python service, packages or external API is required. See [hair-profile setup and limitations](docs/HAIR_PROFILE.md).

Requires PHP **8.2+**, Composer, MySQL/MariaDB with InnoDB, and PHP `pdo_mysql`, `mbstring`, `fileinfo`, `gd` and `openssl`; Maya additionally requires `curl`. PHP must support HTTPS requests for Google verification. Enable required extensions in your development environment; do not replace locked dependencies with an update.

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

The current owner-approved [fictional catalog](database/avela_catalog_20261030.json) contains 100 products, with 95 supplied images and five honest placeholders. Its [reviewed suitability metadata](database/avela_suitability_20261030.json) supports 76 cosmetic demonstration entries; 24 remain outside recommendations pending review. See the [catalog refresh guide](docs/CATALOG_REFRESH_20261030.md) and [team setup guide](docs/TEAM_SETUP_20261030.md). Git includes the catalog source and images, not anyone's database, accounts, orders or private settings. Catalog installation requires a separate, guarded local import.

The earlier [twenty-product manifest](database/demo_catalog.json) and [image manifest](database/demo_images.json) remain for historical compatibility and regression fixtures. Source assets alone do not assign database image paths. The current refresh archives older demonstration products rather than deleting their history or relationships.

- [Repeat-safe catalog import and disposable tests](docs/DEMO_CATALOG.md).
- [Guarded image assignment and disposable tests](docs/DEMO_IMAGES.md).
- [Shared 2.5 MB image upload policy and PHP GD requirement](docs/UPLOADS.md).
- Product-image review/provenance records remain alongside the approved assets in `public/assets/images/products/demo/`.
- Inter is served locally, unmodified, under its [SIL Open Font License](public/assets/fonts/inter/LICENSE.txt); [source revision/checksum](public/assets/fonts/inter/SOURCE.txt).

## Verification and deployment

For existing installations adopting the latest customer/staff refinements, back up and restore-test the database first. Review `scripts/migrate_delivery_locations.php` (nullable address province/area fields) and `scripts/migrate_staff_profiles.php` (nullable staff contact/photo fields), using each script's complete database identity guards and default dry-run before an authorized apply. Do not rerun the installer on existing data. These updates do not include private configuration, uploaded photos, accounts or database contents.

For profile-based matching and saved routines, see [matching rules, evidence and the additive routine migration](docs/RECOMMENDATIONS.md). Existing installations need the guarded routine_contexts addition before deploying those routes. tests/product_matching.php covers the pure rules; tests/matching_integration.php requires a disposable database.

`tests/checkout.php`, `tests/security.php`, `tests/mfa.php` and `tests/create_staff.php` require a verified disposable database and explicit port/data-directory guards. Use the reproduction commands in the focused documents; never point mutating tests at personal/shared data. HTTP/service fixtures, actual browser interaction and real provider/device verification are separate evidence.

Keep `.env`, logs, backups, sessions, recovery codes and user uploads private. Serve only `public/`; configure HTTPS, SMTP, upload execution restrictions, filesystem permissions and secure backups before hosting. Real malware scanning depends on host support. Local HTTP and MIME checks do not prove production compliance.

## Group members

Aleckzandra Bahillo (@aleckzandrabahillo), Jasmine Iris Eva (@EvaIris5892), Alexzis Mae Tutor (@alexzismaetutor), Angela Nicole Zurbano (@zurbanoangelanicole).

## Approved interface direction

The owner-approved 28 October 2026 Site v8 handoff supplies the editorial presentation. The working PHP application remains the source of truth for accounts, security, catalog, checkout and hair personalization. See [feature parity](UI_PARITY_AUDIT.md), [29 October repair-brief results](UI_REVIEW_REPAIR_20261029.md). Earlier [handoff correction notes](UI_HANDOFF_CORRECTION_20261029.md) and [regression totals](UI_HANDOFF_CORRECTION_20261029.json) are historical evidence. The static preview is a design reference, not a replacement deployment.
