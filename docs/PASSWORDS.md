# Password changes and expiry

All roles can open `/account/password`. The owner is taken from the authenticated session, never a submitted user ID. CSRF, current-password verification and the shared server policy are mandatory. A successful transaction replaces the password hash, records `password_changed_at`, rotates a random credential version, consumes pending verification proofs and writes a secret-free audit. The initiating session is renewed after the successful change; every other session must sign in again. Staff email OTP and existing authenticators remain required.

Incorrect current-password proofs use the same concurrency-safe lockout (Administrator-selected threshold, default three; the configurable range and unresolved IAS interpretation are documented in SECURITY.md). A failed audit rolls back the hash, timestamp, version and proof consumption. Passwords are never kept as old form values or included in audit details.

The reviewed registration checklist is shared with Change Password. It uses the effective server minimum, mandatory uppercase/lowercase/number/special counts and bcrypt's 72-byte limit. JavaScript is guidance; PHP decides acceptance. Administrators may strengthen each required class from one to four, but cannot remove any class or lower the 12-character minimum.

## Approved coursework expiration

**Owner-approved coursework period: 90 days (October 23, 2026).** IAS C11 specifies expiration without a duration. This period supports coursework demonstration; it is not a claim that periodic expiration is universally preferred. Production policy should consider current organizational guidance and risk.

`PASSWORD_EXPIRY_DAYS=90` is the default for new deployments. An existing explicit `0` remains disabled until its separately authorized rollout; this source update does not modify private runtime settings. Supported enabled values are 30–365 days. Known older timestamps then restrict protected access to Change Password and logout until a successful change; existing normal MFA still precedes authenticated access. New accounts have a creation-time password timestamp. Migrated accounts keep NULL because their original password age is unknown; no backdating, automatic password reset or surprise expiration occurs. For an approved transition without rewriting those timestamps, set PASSWORD_LEGACY_GRACE_END to a prospective UTC ISO timestamp (for example, the rollout time plus 90 days). Unknown-age accounts remain eligible until that deadline, then must change their password; known ages continue using their real timestamp. The Change password page displays the applicable date. Missing transition configuration preserves the previous legacy behavior; invalid enabled policy values fail closed. Do not backdate accounts. Disposable tests cover the 90-day boundary and protected requests. Existing deployed accounts are not claimed compliant until the approved policy and legacy-account transition have been rolled out.

## Migration and recovery

After a fresh restore-tested backup and separate deployment approval, run `scripts/migrate_passwords.php` with all exact host, port, database, server, server-ID and data-directory guards. Dry-run adds nothing; approved apply adds only nullable `users.password_changed_at` and empty-default `users.password_version`. Existing passwords and business records are unchanged. Repeat execution validates definitions and plans zero additions. DDL implicitly commits.

Do not roll back to source that ignores credential versions after passwords change. Preserve additive fields and current records. No forgotten-password reset is implemented here: contact an authorized Administrator through established project channels; never reveal a current password or verification code.

Authenticator setup keys are shown only during explicit, password-verified enrollment, displayed in groups of four with explicit Copy key and View QR code actions. No external QR service receives them. Keep the private encryption key and one-use recovery codes secure; password changes do not remove the factor. A setup/login challenge created before a password change is rejected.
