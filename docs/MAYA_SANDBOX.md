# Maya Checkout Sandbox

Native PHP/PDO integration using Maya's hosted Sandbox checkout. COD remains independent. Avela never collects card numbers, CVVs or card credentials. This implementation has no production endpoint or production-payment mode.

## Private configuration

Configure a dedicated, non-production application environment. Never copy keys into example files, browser code, commands or reports. For initial provider trials, obtain the official shared Sandbox pair directly from Maya's [credentials page](https://developers.maya.ph/reference/sandbox-credentials-and-cards).

- `MAYA_ENVIRONMENT=sandbox`
- `MAYA_PUBLIC_KEY` / `MAYA_SECRET_KEY`: private Sandbox values. Existing `MAYA_SANDBOX_PUBLIC_KEY` / `MAYA_SANDBOX_SECRET_KEY` names are supported if the preferred names are absent. Conflicting pairs fail closed.
- `MAYA_MERCHANT_ORIGIN`: an HTTPS origin on port 443, without path, credentials, query or fragment.
- `MAYA_SANDBOX_ENABLED=false`, `MAYA_WEBHOOK_READY=false`, `MAYA_RECONCILIATION_READY=false` initially. Enable only in the isolated Sandbox environment after the prerequisites below are established. These are operator declarations, not proof of external verification.

`php scripts/check_maya.php` performs read-only configuration/schema checks and prints booleans, never keys. It sends no provider requests and does not prove that credentials work or that a webhook was delivered. PHP cURL and OpenSSL must be available; TLS certificate validation remains enabled.

## Additive migration

Back up and restore-test the intended non-production database first. `scripts/migrate_maya.php` defaults to dry-run and requires every exact guard:

```text
php scripts/migrate_maya.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=SERVER_HOSTNAME --expect-server-id=SERVER_ID --expect-datadir=DATA_DIRECTORY
```

After reviewing the dry-run, explicitly append `--apply`. Quote paths containing spaces. The only additions are `maya_payments` and `maya_webhook_inbox`; there is no order enum alteration, data backfill or destructive rollback. MySQL DDL may commit implicitly. Repeat dry-run must show zero additions. Existing inconsistent tables are rejected, not repaired automatically.

Maya orders use the existing `manual` order-method value plus a unique `maya_payments` association. The UI identifies that association as **Maya Sandbox**. Older manual/COD orders retain their meaning.

## HTTPS webhook and worker

Register `/payments/maya/webhook` for `PAYMENT_SUCCESS`, `PAYMENT_FAILED`, `PAYMENT_CANCELLED` and `PAYMENT_EXPIRED` through the Sandbox merchant configuration/API, following [Maya's webhook guide](https://developers.maya.ph/reference/configuring-your-webhook-for-maya-checkout). Do not delete or replace another merchant's webhooks. Shared exploration keys may not provide isolated webhook administration; use an independently provisioned Sandbox merchant for controlled acceptance testing if needed.

Maya requires a publicly reachable HTTPS:443 endpoint. This application accepts only the actual server-established TLS connection and Sandbox peers `13.229.160.234` / `3.1.199.75`. It ignores `X-Forwarded-For`, `Forwarded` and `X-Forwarded-Proto`. A generic tunnel that presents a loopback/proxy peer does not satisfy these guards. Use direct HTTPS or a separately reviewed, source-restricted gateway/web-server configuration; do not relax the allowlist or trust arbitrary forwarded headers.

Ingress bounds/parses JSON and durably stores only payment ID, merchant reference association and event status. It returns `200` after queueing/recognizing a duplicate, without a provider API call. Unrecognized shared-Sandbox references are acknowledged without storing data. Invalid sources/payloads are rejected; unavailable durable storage returns `503` so Maya retries. Monitor response latency and availability; do not acknowledge an event that could not be persisted.

Schedule the guarded worker at least once per minute, using the same database guards as the migration:

```text
php scripts/process_maya.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=SERVER_HOSTNAME --expect-server-id=SERVER_ID --expect-datadir=DATA_DIRECTORY --limit=10
```

It verifies events through authenticated Sandbox GET Payment calls, and reconciles pending orders when webhooks are delayed/missing. Exit `2` means verification remains pending; inspect the bounded job error categories and retry. Exhausted inbox attempts require attention; pending-payment reconciliation still operates. No automatic worker/service/scheduler is installed by this change.

## Lifecycle and recovery

- Order creation recalculates items and shipping, reserves stock once, snapshots delivery and creates a random unique merchant reference in the existing transaction. Pending online orders cannot be fulfilled through Administrator status controls.
- The provider create request occurs after commit and is claimed once. A failed/uncertain request is never blindly repeated. The worker can recover the payment ID through authenticated reference lookup. A successful API create response is not proof of payment.
- Return URLs are read-only and owner-protected. Query parameters cannot mark an order paid. The customer can check status or resume the same hosted session with CSRF-protected POST actions.
- Only authenticated provider evidence matching ID/reference/PHP amount changes payment state. Success commits the reservation without another deduction and queues one Sandbox confirmation through the existing mail outbox.
- Definitive failure/cancellation/expiration cancels the pending order and releases stock once, atomically with audit events. Repeated or out-of-order notifications cannot double-release or double-fulfill. A verified success after release, or reversal after payment, requires review; there is no automatic re-reservation, refund or unsafe fulfillment.
- Unknown initiation remains reserved until resolved. An authenticated empty reference lookup (including Maya's documented `404/PY0009`, not other 404s) after a conservative two-hour window expires/releases it. Provider lookup failures never assume expiry or success.
- Retry after a terminal failure starts a fresh shopping checkout with current stock/prices. Existing addresses and profile data are not overwritten. Authorization holds and reversals require reconciliation; this integration does not automate capture, void or refunds.

## Real Sandbox acceptance checklist

1. Keep production payments disabled. Select a dedicated test deployment/database and approved test identities; obtain required owner approval before changing an existing runtime or database.
2. Privately configure the official Sandbox pair and HTTPS origin. Keep CAPTCHA enabled and ensure its allowed domains cover the test hostname; this is separate from Maya configuration. Run the guarded migration and readiness command. Verify source-IP restrictions and a running reconciliation worker before enabling the three readiness flags in that isolated environment.
3. Register the four webhook events without disturbing other registrations. Verify registration from the provider side. A localhost request or a simulated ingress test is not delivery evidence.
4. Sign in through normal CAPTCHA/MFA. Select **Maya Sandbox**. Verify the server total and hosted Sandbox domain. Enter only official Sandbox test-card data on Maya's page, never on Avela or in chat.
5. Complete success, failure, cancellation and expiration cases. Confirm a real webhook reached the HTTPS endpoint, was acknowledged, processed by the worker and matched authenticated payment retrieval.
6. Compare internal order/payment state, one receipt intent and stock/audit changes. Verify return-only navigation cannot create success, repeats do not duplicate changes, unsuccessful reservations release, COD still works, and no secrets/card data were logged.
7. Record externally observed results separately from automated simulations. This source change does not deploy, register webhooks, enable a scheduler, send real email or perform a real Sandbox transaction.

References: [one-time checkout/API/key contracts](https://developers.maya.ph/reference/accept-one-time-payment-using-maya-checkout), [payment states](https://developers.maya.ph/reference/understanding-payment-statuses-in-maya-checkout), [Sandbox validation](https://developers.maya.ph/reference/testing-and-validating-your-maya-checkout-integration).

## Local simulation

MAYA_LOCAL_SIMULATION defaults to false. Local interactive tests require the explicitly guarded private development launcher, loopback PHP development server and disposable test database. The default provider client refuses to run when simulation is selected; no production API or automatic insecure fallback is enabled. The launcher injects an in-process test transport and clearly labels outcomes as simulated. Keep genuine webhook/worker readiness declarations false until separately verified. Simulated records belong only in the disposable test database.


## Accurate order status and local browser reconciliation

Order fulfillment and payment are displayed separately. COD cancellation removes the payment-due claim; a delivered COD order states that collection has not been recorded rather than inventing payment evidence. The current schema has no COD collection ledger, and delivery alone must not mark COD paid. A card paid label requires recorded verification; old simulations remain explicitly distinct.

While an authenticated Customer views an awaiting-payment order, `order-payment.js` submits the existing ownership-checked, CSRF-protected refresh action. It makes up to three checks, at least 30 seconds apart, with loading/failure feedback and the existing manual fallback. Server-side session throttling also bounds repeated checks. The JSON response exposes only the owned order's state, label and verification time. Terminal results reload the order from saved authenticated evidence. GET, including provider return URLs, remains read-only. Successful same-state retrievals also update the verification timestamp.

This browser flow does not replace unattended reconciliation. If the Customer closes the page or a provider result arrives later, the independently guarded `process_maya.php` worker/webhook workflow must run. No public endpoint, proxy exception, production mode or OS scheduled task is enabled by this change; deployment needs reviewed HTTPS ingress and worker scheduling. Targeted receipt delivery occurs after committed reconciliation, never while stock/order locks are held.
