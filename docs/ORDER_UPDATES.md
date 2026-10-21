# Order updates and confirmation email

Customers see their own order details, public reference, delivery snapshot and internal fulfillment timeline. This is an Avela status history, not live courier tracking. Administrator updates follow pending → processing → shipped → delivered. Unpaid card orders cannot advance to fulfillment.

Eligible Administrators can cancel pending/processing COD orders only when the original stock deduction is evidenced. Status checks, stock restoration, discount release, timeline and actor-attributed audits share one transaction. Replays/stale forms cannot restore stock twice. Shipped/delivered orders and unsupported legacy deductions require separate review. Maya payment outcomes use authenticated provider reconciliation instead of this COD cancellation path.

Saved profile addresses are independent of delivery recipients. Checkout snapshots the edited recipient/address without overwriting the saved address. See [account/address setup](ACCOUNT_EXPERIENCE.md).

## Existing-database upgrade

Make a consistent private backup and restore-test it separately. Review the dry-run against independently verified identity values:

```text
php scripts/migrate_order_experience.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=HOSTNAME --expect-server-id=SERVER_ID --expect-datadir=EXACT_DATA_DIRECTORY
```

Only after approval and matching guards, append `--apply`. The migration adds `order_status_events` and `order_email_outbox`; it validates existing definitions and performs no row backfill. Repeat execution must report zero pending additions. MySQL DDL implicitly commits. Preserve populated additions on rollback and never restore stale records over newer orders. Fresh installations include the schema; do not rerun the installer for upgrades.

Checkout queues one order-confirmation intent atomically. Provider-verified successful card payments queue confirmation through their payment lifecycle. `scripts/process_order_emails.php` is the guarded operator worker; scheduling and real SMTP require separate configuration and acceptance testing. A queued/sent status or local mail log is not proof of mailbox delivery. No email is sent merely by publishing this source.

The receipt includes recorded items, totals, shipping, discount and payment status. Delivery details and inbox contents remain private. See [email setup](EMAIL.md) and [Checkout](CHECKOUT.md).
