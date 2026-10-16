# Email verification

Avela uses the existing locked PHPMailer package. SMTP requires authenticated STARTTLS (`MAIL_ENCRYPTION=tls`) or implicit TLS (`ssl`), a single valid hostname, a valid sender address and certificate/hostname verification. Debug output is disabled. Configure credentials only in the private environment, never in commands, source or issue/PR text. The server must have a maintained CA trust store; repair trust configuration rather than disabling validation.

Typical Gmail settings: `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`; use an eligible account with 2-Step Verification and a privately generated app password. See [Google's app-password guidance](https://support.google.com/accounts/answer/185833) and [SMTP settings](https://support.google.com/a/answer/176600). Brevo is another option with a verified sender, its account-specific SMTP login/key, `smtp-relay.brevo.com` and TLS; see [official SMTP setup](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP). Provider eligibility, quotas, sender verification and actual receipt require owner verification.

## Activation

Registration saves the customer/profile, hashed OTP and activation-token digest atomically. Delivery failure rolls registration back and gives a safe retry response. A delivered message cannot be recalled if a later database commit fails; an uncommitted link remains invalid.

The 256-bit random activation link expires after 30 minutes. It places the proof in a URL fragment, removes it from the address and requires an explicit CSRF-protected POST to `/activate`; a mail scanner GET does not activate. No external scripts load on that page. Successful activation consumes all pending activation links/codes and redirects to `/login` without creating a signed-in session. An existing authenticated session stays unchanged. Invalid, expired, used or locked-account links receive the same response.

Numeric activation remains available in the pending verification session. Its existing expiration, attempt limit and resend cooldown remain enforced. Numeric activation also consumes outstanding links. A new successful resend replaces prior activation proofs; a failed send preserves the previous proofs. Staff login email OTP and optional authenticator requirements are unchanged.

Production `APP_URL` must be the trusted HTTPS origin/base path; do not derive email links from request Host headers. HTTP is accepted only for development localhost/loopback.

## Upgrade and local testing

Back up and restore-test before separately authorized deployment. Run `scripts/migrate_activation.php` dry-run with all exact host/port/database/server/server-ID/data-directory guards, then approved `--apply`. It adds only `activation_tokens`. Repeat runs validate the existing table. DDL implicitly commits; preserve additive schema rather than dropping it for rollback. Run prior security/lock migrations first and never rerun the installer over an existing database.

`MAIL_DRIVER=log` is development-only. Existing login/profile OTPs remain in the private development mail log. Activation messages go to an existing, protected `MAIL_DEV_OUTBOX_DIRECTORY` outside the project and public root. General logs contain delivery metadata, not activation links. Windows operators must verify effective owner/service/SYSTEM-only permissions; Unix directories must exclude group/other access. These messages are private credentials: never commit, share, serve, screenshot or paste their contents. Remove obsolete disposable messages securely after testing.

`tests/email_activation.php` uses the same disposable identity/session/scratch/HTTP guards as the security suite. It tests digest-only storage, expiry, reuse, malformed proof, locked accounts, concurrent consumption, numeric/link coherence, CSRF, audit and delivery rollback. A one-connection loopback SMTP fixture tests successful verified TLS, rejection of an untrusted certificate and refusal of plaintext. This proves transport behavior only, not provider delivery.

For real evidence, the owner must configure an authorized provider privately, receive a real registration activation email, activate once, confirm reuse is denied, and receive a real staff sign-in OTP. No real-provider delivery is claimed by automated fixtures.
