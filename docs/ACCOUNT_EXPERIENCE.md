# Customer account and operations experience

The customer profile has a structured default delivery address. `/profile/address` accepts only authenticated, currently eligible customers with CSRF protection. Address fields use the same Philippine delivery validator as Checkout. The customer row serializes writes; a random revision detects stale forms. The address and legacy formatted profile address commit with an actor-attributed audit record. Audit details contain field names, not the address. Duplicate unchanged saves create no additional changes. Checkout snapshots may use another recipient without changing the saved address.

Ordinary name/contact edits save directly. Email changes still require a single-use code sent to the current email. Confirmation rechecks account eligibility and preserves ordinary fields/address changed after that verification began. Password and authenticator flows are unchanged.

## Additive schema upgrade

Before using this revision with an existing database, make and restore-test a private consistent backup, verify the server identity and review the dry-run:

```text
php scripts/migrate_account_experience.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=HOSTNAME --expect-server-id=SERVER_ID --expect-datadir=EXACT_DATA_DIRECTORY
```

Only after approval and matching guards, repeat with `--apply`. The migration adds `customer_delivery_addresses`, nullable `orders.public_reference` and its unique index. No existing rows are backfilled. Repeating it plans zero additions. MySQL DDL implicitly commits: rollback must not drop populated columns/tables or restore stale records over newer activity. Fresh installations include this schema; never rerun the installer over an existing database.

New order references use `AVE-YYYYMMDD-` plus cryptographically random hexadecimal text, assigned inside the order transaction and enforced unique by MySQL. Legacy rows have a stable date/base-36 display fallback. Numeric links, foreign keys, checkout idempotency keys and Maya merchant/provider references remain compatible. Public-reference lookups still enforce customer ownership. Emails use the same display reference.

## Dashboards and payment evidence

Administrator reporting uses bounded 7/30/90-day aggregates, an accessible daily-count chart/table, fulfillment distribution and recent audit activity. Counts include actual recorded statuses. The preparation count excludes unverified card orders. Verified card order value requires paid, stock-committed, authenticated-provider evidence and excludes cancelled orders and deterministic local simulations. It is Sandbox order value, not bank settlement or COD cash collection.

Catalog reporting exposes stock, categories, attention items and delivered-product unit totals; it does not expose customer records, security activity or payment value. Fulfilled-product totals exclude cancelled, unpaid card and simulated payments. There is no inventory-cost valuation without cost data.

Card details remain exclusively on Maya's hosted Sandbox page. The expandable Avela section is information only; browser redirects never prove payment. Existing reconciliation, amount/currency/reference checks, CSRF, idempotency and stock protections remain in force.

Focused regression: `tests/account_experience.php`, using the established disposable-only database/server/session guards. No automated mutations should target a personal runtime or database.
