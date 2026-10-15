# Checkout and customer orders

Checkpoint 5, 15 October 2026. Customer checkout, order placement, confirmation and owned order history are implemented. Administrator fulfillment/status editing remains separate work. Existing order statuses are preserved.

## Implemented behavior

- GET/POST /checkout requires a currently eligible customer. Guests and staff cannot place orders, including direct POST requests.
- Recipient name, Philippine mobile contact and complete delivery address are validated and snapshotted. Account email comes from the current account. Checkout does not edit the profile.
- Cash on delivery is the only offered method, labeled demonstration. Shipping is explicitly zero for this demonstration. No gateway, online charge, card storage, courier booking or verified-phone claim is made.
- Authoritative quantities and prices come from locked rows. Integer cents avoid floating-point calculation errors. Quantities are 1–99 and order totals respect DECIMAL(10,2). Inactive products/categories and insufficient stock block placement.
- Customer-row locking coordinates cart mutations and checkout. Category/product locks and guarded stock updates prevent overselling. Existing multiple active carts are aggregated and converted together.
- A quote signature binds cart contents, product identity and prices. Changes require review of a fresh summary. A 30-minute intent stores only customer ID, random key, signature and issue time; delivery information is not cached in it.
- Order/items, stock deductions, cart conversion and order.create audit commit together. Failures roll back everything. Only rolled-back InnoDB deadlocks are retried, at most twice.
- The unique random checkout key and customer lock prevent duplicate orders. Replays return the customer's original order and cannot consume a newly filled bag.
- Success redirects with HTTP 303 to /orders/view?id=ID. Details and paginated /orders always enforce ownership. Private pages use no-store; names, addresses, errors and submitted values are encoded.
- Converted cart items remain stored under converted carts. They disappear from the active bag without being deleted.

## Safe schema upgrade

Fresh installations use database/schema.sql. Never rerun the installer over an existing database.

scripts/migrate_checkout.php adds nullable order columns recipient_name_snapshot VARCHAR(120), contact_email_snapshot VARCHAR(190), contact_no_snapshot VARCHAR(25), and checkout_key CHAR(64) with ASCII/binary collation and a unique index. Existing rows are unchanged and receive NULL for the new fields. Older order pages explain missing historical contact snapshots rather than substituting current profile values.

The script defaults to dry-run. It verifies exact host, configured/actual port, database, server hostname, server ID and data directory; checks existing column/index definitions; stops on incompatible definitions; and skips matching additions on repeat execution.

Before a separately approved existing-database update, verify the target, make a private backup, restore-test it into a separate disposable database, review dry-run output and only then add --apply. MySQL DDL auto-commits; the backup is the recovery mechanism. Do not drop populated columns as a rollback shortcut.

Replace all quoted placeholders with independently verified values:

    php scripts/migrate_checkout.php --expect-host="HOST" --expect-port="PORT" --expect-database="DATABASE" --expect-server="HOSTNAME" --expect-server-id="SERVER_ID" --expect-datadir="EXACT_DATA_DIRECTORY"

Source publication does not deploy the running application or migrate the personal database. Coordinate the reviewed additive migration before enabling the new checkout code in any existing runtime.

## Five-step PHP form handling

| Step | Implementation |
|---|---|
| Collection | CustomerController::placeOrder reads POST; CheckoutValidator restricts expected delivery/payment fields. |
| Validation | Scalar/UTF-8, required/length/contact/payment validation; CSRF and submission intent; current account/cart/product/category/stock/price checks. |
| Sanitization / secure output | Trim/normalize text and contact; e() encodes all displayed user input; PDO binds database values. |
| Processing | One InnoDB transaction creates order/items, deducts stock, converts carts and audits the order. |
| Response | Clear 422/409 validation/stale-form errors retain submitted input; failures preserve the bag; success/replay redirects to owned details/history. |

Maps EC08–EC12, EC05/DB05, DB01/DB02/DB06, RB01/RB02, SE01–SE04 and transactional LG02. EC14 administrator fulfillment is separate. Gateway Q-001, encryption Q-007 and other pending course decisions remain unresolved.

## Verification

tests/checkout.php is CLI-only. It refuses normal/personal database ports, non-test database names, mismatched targets, private directories inside the repository and the personal web port. Use a disposable schema with the twenty-product catalog, synthetic image_customer/image_catalog_manager/image_admin accounts and a separate PHP runtime. Do not copy personal credentials.

    php tests/checkout.php --expect-database="TEST_DATABASE" --expect-port="TEST_PORT" --expect-datadir="EXACT_TEST_DATA_DIRECTORY" --base-url="http://127.0.0.1:TEST_HTTP_PORT" --session-directory="PRIVATE_SESSION_DIRECTORY" --scratch-directory="PRIVATE_SCRATCH_DIRECTORY" --output="PRIVATE_RESULT_FILE"

PDO/HTTP/concurrent-process checks are actual disposable runtime operations. Rollback checks deliberately inject failures. HTTP clients use synthetic sessions. Browser tests use a temporary external one-use synthetic authenticated-session fixture, with no application backdoor or authentication change. Three-role authentication-service/OTP tests are distinct from browser CAPTCHA login.

Real desktop/mobile checkout, validation response, placement, confirmation reload and history were exercised. Exact fresh counts are recorded in the checkpoint report. Real gateway/courier/SMTP/CAPTCHA-provider, hosted Apache and physical-device validation are not claimed.

WAR evidence: implemented PHP POST processing and validation/output encoding, prepared relational SQL, session/role/ownership/CSRF guards, exact currency arithmetic, InnoDB transactions/locking/idempotency, safe additive migration, native accessible forms and regression/fault/concurrency tests. Professor WAR format remains pending.
