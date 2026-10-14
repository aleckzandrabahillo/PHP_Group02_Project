# Avela

**A Hair Care E-Commerce Application with Hair Profile Assessment and Routine Building**

Avela is a PHP and MySQL web application for browsing hair-care products, managing customer accounts, and building a more personalized shopping experience through a hair profile and routine feature.

This repository is the Group 02 project for **Open-Source Programming**.

## Group Information

- **Group:** Group 02
- **Project:** Avela
- **Course:** Open-Source Programming

### Group Members

1. Aleckzandra Bahillo - @aleckzandrabahillo
2. Jasmine Iris Eva - @EvaIris5892
3. Alexzis Mae Tutor - @alexzismaetutor
4. Angela Nicole Zurbano - @zurbanoangelanicole

## Technology Stack

- **PHP 8.2 with `pdo_mysql`** - server-side logic and routing
- **MySQL / MariaDB** - database
- **HTML5** - page structure
- **CSS3** - layout and responsive design
- **Vanilla JavaScript** - client-side interactions

No PHP or front-end framework is used in the project.

A small Python component for NLP-based analysis of free-text hair concerns is planned for a later development phase. It is not included in the current build yet.

## Current Features

- Public home, shop, search, about, and product pages
- Customer registration and login
- CAPTCHA validation
- Email OTP account activation
- Staff OTP authentication
- Password hashing and password rules
- Failed-login tracking and temporary account lockout
- Session timeout and session ID regeneration
- CSRF protection for state-changing forms
- Role-based access for Customer, Catalog Manager, and Administrator
- Product filtering and sorting
- Favorites
- Shopping cart and checkout interface
- Product reviews and catalog-based recommendations
- Customer, Catalog Manager, and Administrator dashboards
- Authentication and audit-related database tables

Some modules are still under development, including full Catalog Manager CRUD, order completion/payment processing, and the NLP component.

## Project Structure

```text
PHP_Group02_Project/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   ├── Support/
│   └── Views/
├── database/
│   └── schema.sql
├── public/
│   ├── assets/
│   ├── uploads/
│   └── index.php
├── scripts/
│   ├── create_staff.php
│   ├── healthcheck.php
│   └── install.php
├── storage/
│   └── logs/
├── .env.example
├── .gitignore
├── bootstrap.php
├── router.php
└── README.md
```

## Database

The default database name is:

```text
avela
```

The complete database structure and initial local testing records are in:

```text
database/schema.sql
```

## Local Setup

### Requirements

- PHP 8.2 with `pdo_mysql` 
- MySQL or MariaDB
- VS Code or another code editor
- Web browser

### Steps

1. Clone or copy the project to your local machine.
2. Copy `.env.example` and rename the copy to `.env`.
3. Update the database settings in `.env` if needed.
4. Start MySQL or MariaDB.
5. From the project folder, run:

```bash
php scripts/install.php
```

6. Start the local PHP server:

```bash
php -S 127.0.0.1:8000 -t public router.php
```

7. Open the site in a browser:

```text
http://127.0.0.1:8000
```

For XAMPP on Windows, `C:\xampp\php\php.exe` can be used instead of `php` if PHP is not added to the system PATH.

## Staff Accounts

Administrator and Catalog Manager accounts can be created from the command line using:

```bash
php scripts/create_staff.php
```

The script will ask for the needed account information.

## Security Notes

- Passwords are stored using PHP password hashing.
- Sensitive local settings are kept in `.env` and should not be committed.
- `.env.example` contains only sample configuration values.
- Uploaded files and local log files are excluded from Git except for their empty placeholder folders.

## Development Status

Avela is still being developed. The repository will be updated as the group completes the remaining e-commerce, catalog management, NLP, and deployment requirements.


## Demonstration catalog (Checkpoint 4A)

The canonical [20-product manifest](database/demo_catalog.json) preserves the six seed entries and adds fourteen fictional Avela products. It contains names, stable SKUs, existing categories/tag codes, descriptions, PHP prices, opening stock and suggested image filenames. Descriptions and suitability tags are demonstration shopping metadata, not verified ingredient/formulation or medical claims.

The importer is CLI-only and **dry-run by default**:

~~~sh
php scripts/import_demo_catalog.php
php scripts/import_demo_catalog.php --dry-run
php scripts/import_demo_catalog.php --help
~~~

Dry-run reports the connected host, server hostname, port/database and proposed additions, using a read-only transaction. It does not create products, update accounts or write audit records. Categories and tags resolve by name/code, not installation-specific IDs.

After review, separate authorization and a recoverable database backup, apply with every target value copied from the reviewed dry-run:

~~~sh
php scripts/import_demo_catalog.php --apply --actor=YOUR_STAFF_USERNAME --expect-host=YOUR_DB_HOST --expect-port=YOUR_DB_PORT --expect-database=YOUR_DB_NAME
~~~

The actor must be an existing active, unlocked Catalog Manager or Administrator. This identifies the authorized operator in audit history; it is not a new web login or an OTP/MFA bypass. Run only from a trusted local shell with access to the private environment configuration.

The importer reuses ProductValidator and the current account-eligibility helper. The existing single-product save method starts its own transaction, so this bulk operation uses prepared inserts in one outer transaction for all products, tag links and product-create audits. An advisory lock serializes concurrent imports. No existing record is updated or deleted.

Matching SKUs are skipped without resetting inventory or timestamps. Any differing field/tag, missing seed SKU, inactive category, unknown category/tag, or unexpected extra product stops the whole operation. After later stock, price, tag or image changes, a conflict is expected: inspect it instead of restoring manifest values over live data. This tool is for the expected six-to-twenty demonstration catalog, not arbitrary catalog synchronization. Do not rerun scripts/install.php on an existing database.

Suggested image names are `avela-<lowercase-sku>.webp`, such as `avela-ave-smp-002.webp`. They are planning identifiers only; image_path remains NULL and current storefront fallbacks remain intact. Image installation is a separate checkpoint.

### Regression tests

Requirements: PHP 8.2 with pdo_mysql and mbstring; a verified disposable MariaDB containing the unchanged six-product schema/seed baseline, plus synthetic active Customer and Catalog Manager fixture accounts. Configure only a separate worktree's ignored .env for this database and serve its public directory on a separate loopback port.

~~~sh
php tests/demo_catalog.php --expect-database=avela_demo_test_UNIQUE --expect-port=DISPOSABLE_PORT --expect-datadir=DISPOSABLE_DATA_DIRECTORY --actor=SYNTHETIC_STAFF_USERNAME --customer=SYNTHETIC_CUSTOMER_USERNAME --base-url=http://127.0.0.1:DISPOSABLE_HTTP_PORT --output=EXTERNAL_RESULTS_FILE
~~~

The harness requires development mode, an avela_demo_test_ database name, exact port/data-directory confirmation and a loopback host. It refuses ports 3306 and 13307. It creates the fourteen demonstration records only in that disposable database; never point it at personal/shared data. Tests cover negative/boundary inputs, real transactional rollback after simulated failure, concurrent and sequential repeat safety, record preservation, exact field/tag relationships and public HTTP rendering. These are automated synthetic-data tests, not browser visual testing or Apache deployment certification.

For a later personal-database import: verify server identity, take and verify a private recoverable backup outside Git, run dry-run, review every conflict and the proposed final count, then obtain approval before --apply. Keep personal .env files, backups, logs and test credentials out of Git.

## Approved demonstration image assignment (Checkpoint 4B.3)

The separate `database/demo_images.json` manifest binds reviewed SKUs and exact product names to approved WebP paths, SHA-256 checksums and dimensions. It initially contains only AVE-SMP-002 and AVE-TOL-001. Add further reviewed assets to this manifest later; do not alter `database/demo_catalog.json` or use the catalog importer to attach images.

`scripts/assign_demo_images.php` is CLI-only and defaults to dry-run. Both modes require an active, unlocked Catalog Manager/Administrator username and exact configured host, actual port/database, server hostname, server ID and data-directory confirmation. CLI attribution is a trusted operator action, not a web login or an MFA bypass.

Inspect the intended private environment and actual server identity read-only first. Use a clean checkout configured for that target, or a reviewed process-local environment loader; never silently use another worktree's test configuration. The script does not accept credentials on its command line.

~~~sh
php scripts/assign_demo_images.php --help
php scripts/assign_demo_images.php --actor=STAFF_USERNAME --expect-host=127.0.0.1 --expect-port=DB_PORT --expect-database=DB_NAME --expect-server=SERVER_HOSTNAME --expect-server-id=SERVER_ID --expect-datadir="SERVER_DATA_DIRECTORY"
~~~

Review the dry-run's SKU/product-ID mapping and the exact target. After separate authorization and a verified recoverable backup, repeat the same guards with `--apply` and one confirmed ID for every manifest entry:

~~~sh
php scripts/assign_demo_images.php --apply --actor=STAFF_USERNAME --expect-host=127.0.0.1 --expect-port=DB_PORT --expect-database=DB_NAME --expect-server=SERVER_HOSTNAME --expect-server-id=SERVER_ID --expect-datadir="SERVER_DATA_DIRECTORY" --expect-product=AVE-SMP-002:CONFIRMED_ID --expect-product=AVE-TOL-001:CONFIRMED_ID
~~~

- The actual application serving the target database must already have these files in its public root. A different worktree containing the files does not make them available to the running application.
- Only NULL image paths are assigned. An already matching path is skipped; any other existing path, including an empty string, stops the entire batch.
- Product SKU, exact name and confirmed installation-specific ID must agree. All files are checked even on a repeat run: approved checksum, canonical SKU filename, real contained path, no symlink escape, bounded size/dimensions, actual MIME and valid permitted WebP container chunks.
- PHP GD is not required. Runtime checks bind bytes to reviewed checksums and inspect MIME, dimensions and container structure; they do not claim to decode arbitrary new compressed WebP data. Every newly approved image must also pass full decoding/browser inspection before its checksum enters the manifest.
- One transaction contains image updates and per-product `product.image.assign` audits. Current actor eligibility and product rows are locked; an advisory lock serializes assignment runs. A stale NULL check, validation failure or audit failure rolls the transaction back. Only image_path and its normal update timestamp change; prices, stock, descriptions, categories and tag links are preserved.
- Re-run dry-run after applying: expect zero planned assignments and no new audits. Existing assigned files cannot be overwritten by this tool. Secure the deployment asset directory against concurrent edits while applying.
- The original catalog importer will intentionally report conflicts after product images change; do not rerun it to reset live product records.

### Focused disposable tests

Use a fresh synthetic twenty-product database, all image paths NULL, with active synthetic users named image_catalog_manager, image_admin and image_customer. Use the existing schema and approved catalog importer only in that disposable environment. Never use personal/shared data or the main installer against an existing database.

The harness requires a development environment, an `avela_demo_images_test_` database, exact port/data-directory confirmation, a loopback test HTTP server and a scratch directory outside the checkout. It refuses database ports 3306/13307 and HTTP ports 80/18080. Negative asset fixtures are private copies; approved repository assets are never modified.

~~~sh
php tests/demo_images.php --expect-database=avela_demo_images_test_UNIQUE --expect-port=TEST_DB_PORT --expect-datadir="TEST_DATA_DIRECTORY" --base-url=http://127.0.0.1:TEST_HTTP_PORT --scratch-directory="EXTERNAL_SCRATCH_DIRECTORY" --output="EXTERNAL_RESULTS_JSON"
~~~

The tests execute real PDO writes, concurrent CLI processes and HTTP requests. A deliberately injected second-audit failure verifies rollback of both image updates and the earlier audit. Role/identity/path/file/ID conflicts, repeat safety, unrelated-record preservation, two real storefront images and eighteen unchanged fallbacks are checked. Browser/mobile and Apache deployment evidence must be reported separately.
