# Demonstration image assignment


The separate `database/demo_images.json` manifest binds reviewed SKUs and exact product names to approved WebP paths, SHA-256 checksums and dimensions. It contains fifteen approved assets; five products retain placeholders. Add further individually reviewed assets to this manifest later; do not alter `database/demo_catalog.json` or use the catalog importer to attach images.

`scripts/assign_demo_images.php` is CLI-only and defaults to dry-run. Both modes require an active, unlocked Catalog Manager/Administrator username and exact configured host, actual port/database, server hostname, server ID and data-directory confirmation. CLI attribution is a trusted operator action, not a web login or an MFA bypass.

Inspect the intended private environment and actual server identity read-only first. Use a clean checkout configured for that target, or a reviewed process-local environment loader; never silently use another worktree's test configuration. The script does not accept credentials on its command line.

~~~sh
php scripts/assign_demo_images.php --help
php scripts/assign_demo_images.php --actor=STAFF_USERNAME --expect-host=127.0.0.1 --expect-port=DB_PORT --expect-database=DB_NAME --expect-server=SERVER_HOSTNAME --expect-server-id=SERVER_ID --expect-datadir="SERVER_DATA_DIRECTORY"
~~~

Review the dry-run's SKU/product-ID mapping and the exact target. After separate authorization and a verified recoverable backup, repeat the same guards with `--apply` and one confirmed ID for every manifest entry. The example below shows two guards; include all fifteen SKU/ID pairs from the reviewed dry-run:

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

The tests execute real PDO writes, concurrent CLI processes and HTTP requests. A deliberately injected second-audit failure verifies rollback of both image updates and the earlier audit. Role/identity/path/file/ID conflicts, repeat safety, unrelated-record preservation, the approved storefront images and unchanged fallbacks are checked. Browser/mobile and Apache deployment evidence must be reported separately.
