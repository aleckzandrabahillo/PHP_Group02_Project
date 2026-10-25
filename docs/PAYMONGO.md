# PayMongo online payment

Customers can choose **Pay online (GCash, Maya or card)** at checkout. They are sent to PayMongo's hosted
page, pay there, and come back to their order page. Cash on delivery still works exactly as before.
Avela never sees or stores card or wallet details.

## How it works

1. Checkout saves the order (`payment_method = paymongo`, `payment_status = pending`) and reserves stock.
2. Avela creates a PayMongo checkout session and redirects the customer to it.
3. The customer pays. Payment is recorded when **either** of these happens first:
   - PayMongo calls our webhook (`POST /webhooks/paymongo`, event `checkout_session.payment.paid`), or
   - the customer returns to Avela, which asks PayMongo directly whether the session was paid.
     This second path is what makes it work on localhost, where PayMongo cannot reach a webhook.
4. The redirect itself is **never** treated as proof of payment. The paid amount must equal the order total.
5. Unpaid orders are cancelled and their stock returned by `scripts/expire_unpaid_orders.php`
   (PayMongo sessions never expire on their own). Customers can also cancel an unpaid order themselves.
6. Administrators cannot move an unpaid online order to processing/shipped/delivered.

## Setup

### 1. Apply the database migration (once)

Back up first, then:

```
mysqldump -u root -p avela > avela_before_paymongo.sql
mysql -u root -p avela < database/paymongo_migration.sql
```

It adds the same columns `scripts/migrate_paymongo.php` checks for, so that script's dry run should then
report no pending changes. Fresh installs get the columns from `database/schema.sql`.

### 2. Configure `.env`

```
APP_URL=http://127.0.0.1:8000
PAYMONGO_ENABLED=true
PAYMONGO_SECRET_KEY=sk_test_...        # Dashboard > Developers > API Keys (test mode)
PAYMONGO_WEBHOOK_SECRET=whsk_...       # step 3
```

- `APP_URL` is where PayMongo sends customers back. It must be the public address of the site and include
  any sub-folder (for XAMPP: `http://localhost/PHP_Group02_Project/public`), with no trailing slash.
- Leave `PAYMONGO_ENABLED=false` (or unset) and the PayMongo option simply does not appear at checkout.
- Never commit `.env`. It holds live secrets.

### 3. Register the webhook (needed for automatic confirmation)

PayMongo must be able to reach your server over HTTPS, so on a laptop use a tunnel such as ngrok or
cloudflared. In the PayMongo dashboard (test mode) go to Developers > Webhooks and create one with:

- URL: `https://<your-tunnel-host>/webhooks/paymongo` (add your sub-folder if you have one)
- Event: `checkout_session.payment.paid`

Copy the signing secret (`whsk_...`) into `PAYMONGO_WEBHOOK_SECRET`. Test and live modes need separate
webhooks and separate secrets. Without a webhook, payments are still confirmed when the customer returns
to the site or opens the order, just not if they close the tab right after paying.

### 4. Schedule the cleanup

Every 10-15 minutes (Windows Task Scheduler, or cron):

```
php scripts/expire_unpaid_orders.php --minutes=60
```

It checks PayMongo first (so a late payment is still recorded), then expires the session, cancels the
order and returns the stock. Logs go to `storage/logs/payments.log`.

## Testing (test mode)

1. Add a product to the bag, go to checkout, pick **Pay online**, place the order.
2. On PayMongo's page use card `4343 4343 4343 4345`, any future expiry, any 3-digit CVC.
   For GCash/Maya, the test page lets you choose Authorize or Fail.
3. You should land on the order page showing **Paid**, and the admin order page shows the PayMongo
   session id.
4. Also try: closing the PayMongo tab (order stays "Awaiting payment", **Pay now** resumes it),
   **Cancel order** (stock comes back), and the cleanup script.
5. `php tests/paymongo_webhook.php` runs offline checks of signature verification and redirect safety.

## Going live

- Activate your PayMongo account, then switch `PAYMONGO_SECRET_KEY` to the `sk_live_...` key.
- Create a **new** webhook with the live key and use its secret. Disable the test webhook.
- Serve the site over HTTPS (PayMongo only calls HTTPS webhooks in live mode) and set `APP_URL` to it.
- Set `APP_ENV=production` and `SESSION_SECURE_COOKIE=true`.
- Confirm which payment methods your account may use. The session asks for GCash, Maya and card
  (`app/Services/PayMongoService.php`); remove any your account is not approved for.

## Good to know

- **Refunds** are done in the PayMongo dashboard. If a customer pays an order that was already cancelled,
  the admin order page shows a "paid but cancelled, refund it" notice and the audit log records
  `payment.paid_after_cancel`.
- Administrator cancellation does not return stock (existing behaviour); automatic and customer
  cancellation of unpaid online orders does.
- PayMongo has a minimum charge per payment method (around PHP 20). Smaller orders fail at the
  "start payment" step; the order is cancelled and the bag restored.
- If a customer takes long on PayMongo's page and their Avela session times out, they are asked to sign in
  again on return. The payment is still recorded by the webhook or the cleanup script.
