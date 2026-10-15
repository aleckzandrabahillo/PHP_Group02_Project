# Demonstration catalog


The canonical [20-product manifest](../database/demo_catalog.json) preserves the six seed entries and adds fourteen fictional Avela products. It contains names, stable SKUs, existing categories/tag codes, descriptions, PHP prices, opening stock and suggested image filenames. Descriptions and suitability tags are demonstration shopping metadata, not verified ingredient/formulation or medical claims.

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
