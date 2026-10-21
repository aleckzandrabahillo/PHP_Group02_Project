# Checkout and customer orders

Customer checkout, order placement, confirmation and owned order history are implemented. See [Order updates](ORDER_UPDATES.md) for the separate guarded additive migration, internal Administrator status controls, saved-versus-delivery address handling and queued confirmation email. Existing order statuses and snapshots are preserved; real courier and SMTP configuration remain separate.

## Implemented behavior

- GET/POST /checkout requires a currently eligible customer. Guests and staff cannot place orders, including direct POST requests.
- Recipient name, Philippine mobile contact and complete delivery address are validated and snapshotted. Account email comes from the current account. Checkout does not edit the profile.
- Cash on delivery and provider-hosted Sandbox card checkout are available. The customer option reads Debit/Credit Card, with a truthful test-payment disclosure. Shipping and eligible merchandise discounts are calculated server-side. No raw card storage, courier booking or verified-phone claim is made.
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

## Structured delivery and shipping

The customer form prefills only recorded account values. Names, phone and the saved address remain editable; missing city, barangay, postal code and region stay blank rather than being guessed. Checkout does not overwrite the permanent profile. Country is Philippines only; the eighteen-region list follows PSA PSGC, including Negros Island Region.

`scripts/migrate_delivery.php` is a separate guarded additive migration for `order_delivery_details`. Back up and restore-test first. Supply the exact host, port, database, hostname, server ID and data directory; dry-run is default and apply is explicit. MySQL DDL implicitly commits. No existing orders or addresses are backfilled or rewritten. Fresh schema includes the same table. Do not run the installer for an upgrade.

The centralized `config/shipping.php` uses Pasig City as origin and configured area tiers: Pasig PHP 80; nearby Metro Manila cities PHP 90; remaining Metro Manila PHP 100; Central Luzon/CALABARZON PHP 120; other Luzon PHP 150; Visayas PHP 180; Mindanao PHP 200. Priority adds PHP 60. City names are normalized, NCR destinations are checked against a supported city list, and incomplete/unsupported destinations are rejected. Outside NCR the selected region supplies a regional fallback, not a verified city/postal match. This is an owner-review rate card, not road-distance measurement, courier pricing or a delivery-time guarantee. The server calculates and snapshots the fee and total in the existing order transaction. Submitted fee/total values are ignored. Shipping method and fee appear in order details and confirmation mail.

## Discounts

Eligible Catalog Managers and Administrators manage `/catalog/discounts`: unique codes, percent/fixed values, status, dates in Philippine local time, minimum merchandise subtotal and optional total/per-customer limits. One code applies across eligible merchandise before shipping; no stacking. Fixed discounts are capped at merchandise subtotal and percentages round down to cents.

The server rechecks cart identity, code revision, eligibility and current usage inside the stock/order transaction. COD commits a use when ordered; verified cancellation releases it. Card checkout reserves a use until authenticated provider evidence commits or releases it. Failed/cancelled/expired payments release stock and redemption together. Uncertain payments retain reservations until reconciliation establishes their outcome; unattended abandoned-checkout cleanup therefore requires the guarded payment worker. A timeout alone is not evidence of payment failure.

Order-linked code and amount snapshots preserve historical totals, details and confirmation messages. Referenced codes archive; only unused codes can be deleted. Staff mutations and redemption lifecycle events are audited.

`scripts/migrate_discounts.php` is dry-run by default and uses the same exact host, port, database, hostname, server ID and data-directory guards as the payment migration. It adds only InnoDB `discount_codes` and `order_discounts`; it verifies compatible existing definitions and requires zero additions on repeat runs. Back up and restore-test before an authorized existing-database application. DDL implicitly commits; do not drop populated discount tables as rollback. Rollback source only if no discounted orders have been accepted, otherwise retain snapshot-aware reading and reconcile forward.

`tests/discounts.php` covers validation, staff/CSRF controls, audit attribution, stale revisions/carts, exact totals, limits, concurrent redemption, safe archival, cancellation, payment lifecycle and rollback using disposable fixtures. Provider responses in automated tests are simulated.

## Maya Sandbox

Maya Checkout is hosted by Maya. The server creates a session at the fixed Sandbox API using its stored order total, PHP currency and unique merchant reference. Card details are entered only on Maya's returned, allowlisted HTTPS checkout page. Avela never accepts or stores them. Browser returns are read-only and never establish payment success.

Authenticated API retrieval verifies payment ID, amount, currency, merchant reference and status before committing stock or releasing a failed reservation. The existing guarded reconciliation worker and source-restricted webhook inbox share the same idempotent transaction path. Uncertain creation is not retried blindly. An earlier local outcome simulation is labeled separately and cannot be resumed as a genuine hosted payment.

Private settings are `MAYA_PUBLIC_KEY` / `MAYA_SECRET_KEY` (or matching `MAYA_SANDBOX_PUBLIC_KEY` / `MAYA_SANDBOX_SECRET_KEY` aliases), `MAYA_ENVIRONMENT=sandbox` and `MAYA_MERCHANT_ORIGIN`. Keys must belong to the same Sandbox CHECKOUT application. Never put keys in examples, source, browser code, commands, reports or chat.

Normal hosted enablement requires `MAYA_SANDBOX_ENABLED=true`, verified `MAYA_WEBHOOK_READY=true` and `MAYA_RECONCILIATION_READY=true`. Leave readiness false until actually configured and verified. `scripts/check_maya.php` performs no provider request and is not proof of real payment acceptance.

An explicit private local launcher can select `MAYA_LOCAL_HOSTED_SANDBOX=true` for loopback-only, development-only testing on a disposable database. It requires `APP_URL` and `MAYA_MERCHANT_ORIGIN` to be exactly `http://localhost:18080`, `MAYA_LOCAL_SIMULATION=false`, a launcher marker and Sandbox enablement. This limited browser-return exception does not allow HTTP provider transport, HTTP webhooks, arbitrary forwarded headers, production environments or personal database ports. Webhook and scheduled-worker readiness remain honestly false. After paying on Maya, use the owned order's CSRF-protected **Check payment status** action for authenticated reconciliation. Pending reservations need later checks if the browser closes; unattended operation still requires the worker.

The local simulator is an explicitly selected test transport, never a fallback for failed real requests. Keep it confined to disposable tests. Do not expose its routes or test fixtures publicly.

Webhooks currently require direct, server-established HTTPS:443 and the documented Sandbox socket source IP. Ngrok's loopback connection does not satisfy these checks. Do not change `REMOTE_ADDR` / `HTTPS` from arbitrary forwarded headers. A future separately reviewed webhook-only HTTPS ingress must verify the edge connection and original Maya source, block every other app route, and protect the local forwarder. No provider payload alone may mark an order paid; authenticated API evidence remains mandatory.

`scripts/process_maya.php` performs reconciliation, not migrations. Run only with independently verified exact host, port, database, hostname, server ID and data-directory guards. Do not run the installer against existing data. Back up/restore-test before a separately approved additive migration.

Official references: [Create Checkout](https://developers.maya.ph/reference/createv1checkout), [one-time Checkout flow and retrieval fallback](https://developers.maya.ph/reference/accept-one-time-payment-using-maya-checkout), [webhooks](https://developers.maya.ph/reference/configuring-your-webhook-for-maya-checkout), [payment verification](https://developers.maya.ph/reference/getpaymentviapaymentid-1), [Sandbox testing](https://developers.maya.ph/reference/testing-and-validating-your-maya-checkout-integration).