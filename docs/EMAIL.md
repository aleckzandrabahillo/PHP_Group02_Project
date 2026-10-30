# Email verification

Avela uses the existing locked PHPMailer package. SMTP requires authenticated STARTTLS (`MAIL_ENCRYPTION=tls`) or implicit TLS (`ssl`), a single valid hostname, a valid sender address and certificate/hostname verification. Debug output is disabled. Configure credentials only in the private environment, never in commands, source or issue/PR text. The server must have a maintained CA trust store; repair trust configuration rather than disabling validation.

Typical Gmail settings: `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`; use an eligible account with 2-Step Verification and a privately generated app password. See [Google's app-password guidance](https://support.google.com/accounts/answer/185833) and [SMTP settings](https://support.google.com/a/answer/176600). Brevo is another option with a verified sender, its account-specific SMTP login/key, `smtp-relay.brevo.com` and TLS; see [official SMTP setup](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP). Provider eligibility, quotas, sender verification and actual receipt require owner verification.

## Activation

The latest instructor clarification, relayed by the owner on 24 October, requires two sequential registration steps. Registration first saves the pending Customer/profile and sends only a hashed, expiring email OTP. Correct OTP verification consumes the code and sends a separate activation email; it does not activate or sign in the account. Only the later link activates it. Initial delivery failure rolls registration back. Failure to send the second email rolls back its token and OTP consumption so the code can be retried. An already sent message cannot be recalled after a later database commit failure; an uncommitted link remains invalid.

The 256-bit random activation link expires after 30 minutes. It places the proof in a URL fragment, removes it from the address and requires an explicit CSRF-protected POST to `/activate`; a mail scanner GET does not activate. No external scripts load on that page. Successful activation consumes all pending activation links/codes. In the same browser, a still-valid registration session that already verified the password and email OTP signs the Customer in through the normal session finalizer and redirects home. It rechecks password version, email, account identity, eligibility and absence of an enrolled authenticator; session ID rotates. A different browser or expired registration session returns to sign-in. An existing authenticated identity stays unchanged and returns home. Activation never bypasses an enrolled authenticator. Invalid, expired, used or locked-account links receive the same response.

OTP expiration, attempt limits and resend cooldown remain enforced. A separate waiting screen appears after OTP verification and offers link resend without another OTP while the verified session remains valid for 30 minutes. Resend rechecks current pending status, email, password version and cooldown. New links replace old ones; failed delivery preserves previous proof. Versioned token digests reject older combined code/link messages without rewriting existing rows, so old pending links require a fresh OTP. No schema migration or active-account backfill is needed. Staff login email OTP is unchanged; Customer login now uses optional Authenticator without email OTP.

Production `APP_URL` must be the trusted HTTPS origin/base path; do not derive email links from request Host headers. HTTP is accepted only for development localhost/loopback.

## Upgrade and local testing

Back up and restore-test before separately authorized deployment. Run `scripts/migrate_activation.php` dry-run with all exact host/port/database/server/server-ID/data-directory guards, then approved `--apply`. It adds only `activation_tokens`. Repeat runs validate the existing table. DDL implicitly commits; preserve additive schema rather than dropping it for rollback. Run prior security/lock migrations first and never rerun the installer over an existing database.

`MAIL_DRIVER=log` is development-only. Existing login/profile OTPs remain in the private development mail log. Activation messages go to an existing, protected `MAIL_DEV_OUTBOX_DIRECTORY` outside the project and public root. General logs contain delivery metadata, not activation links. Windows operators must verify effective owner/service/SYSTEM-only permissions; Unix directories must exclude group/other access. These messages are private credentials: never commit, share, serve, screenshot or paste their contents. Remove obsolete disposable messages securely after testing.

`tests/email_activation.php` uses the same disposable identity/session/scratch/HTTP guards as the security suite. It tests digest-only storage, expiry, reuse, malformed proof, locked accounts, concurrent consumption, OTP-first/link-second sequencing, CSRF, audit and delivery rollback. A one-connection loopback SMTP fixture tests successful verified TLS, rejection of an untrusted certificate and refusal of plaintext. This proves transport behavior only, not provider delivery.

For real evidence, the owner must configure an authorized provider privately, receive a real registration activation email, activate once, confirm reuse is denied, and receive a real staff sign-in OTP. No real-provider delivery is claimed by automated fixtures.

## Guarded operator transport check

`scripts/check_mail.php` is CLI-only and dry-run by default. Supply `--actor=ADMINISTRATOR_USERNAME` and the same exact `--expect-host`, `--expect-port`, `--expect-database`, `--expect-server`, `--expect-server-id`, `--expect-datadir` guards used by the migrations. It requires an active, unlocked Administrator, the reviewed persistent-lock schema and `MAIL_DRIVER=smtp`. It validates strict transport configuration without contacting a provider or modifying records.

After separately authorizing transmission and configuring the provider privately, add `--send`. It sends a non-secret diagnostic message only to that Administrator's existing account address and records a secret-free audit. There is no arbitrary recipient option or credential argument. It neither issues/consumes OTPs nor activates accounts or creates a sign-in session. Account eligibility is rechecked under a row lock before sending.

Provider acceptance is not mailbox receipt; the owner must check the authorized inbox. A sent message cannot be recalled if a later audit/commit fails. The command reports that possibility safely rather than claiming no email was sent. Real activation and staff-OTP verification still require the normal application flow. The tool is not a bulk-mail interface and has no public route.

For Internet delivery, use an explicitly authorized disposable Administrator with a real mailbox address. Local `@avela.test` fixtures cannot receive Internet mail. Do not edit personal accounts merely to run the check; use the supported private staff-creation procedure in the approved test environment.


## Order confirmations and Sandbox receipts

COD checkout commits the order, stock, audit and unique email intent first. The controller then calls `OrderNotification::deliver` for that order only. SMTP failure never rolls back the order or makes a duplicate submission send another email. The order detail page reports the saved delivery status and lets its authenticated owner send an existing pending confirmation with a CSRF-protected POST. Sent, failed/uncertain and already-claimed jobs are never blindly resent. SMTP acceptance is not proof of Inbox receipt.

Maya receipts require a paid state, recorded authenticated verification and a non-simulated payment reference. Customer verification, processed webhook jobs and scheduled reconciliation attempt the targeted confirmation after the payment transaction commits. Old local simulations and cancelled/unverified orders cannot issue paid receipts. Browser return parameters remain no proof of payment.

`OrderReceipt` renders self-contained, responsive, escaped HTML plus a plain-text alternative: order reference/date, quantity, unit and line prices, discount, charged shipping and exact total. It validates the arithmetic before sending. COD messages state that payment is due on delivery; Sandbox receipts explicitly state that no real money was charged and neither message claims to be a tax invoice. No customer address, card information, remote image or tracker is embedded.

`process_order_emails.php` remains the guarded operator fallback. It is dry-run by default and sends at most one claimed job with `--send`; deployment scheduling is a separate operational step. Existing queued mail is not bulk-dispatched by this update. Interrupted `sending` or `failed` jobs need delivery review before any deliberate resend. No automatic retries may duplicate a message after uncertain SMTP acceptance.

Regression evidence: `tests/order_delivery.php` uses disposable data, private log mail, concurrent senders and an explicitly injected HTTP payment fixture. `tests/mail_readiness.php` independently exercises a loopback SMTP/TLS fixture. These tests do not establish new Gmail Inbox delivery or a new genuine Maya transaction.
