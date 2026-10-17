# Password changes and expiry

All roles can open `/account/password`. The owner is taken from the authenticated session, never a submitted user ID. CSRF, current-password verification and the shared server policy are mandatory. A successful transaction replaces the password hash, records `password_changed_at`, rotates a random credential version, consumes pending verification proofs and writes a secret-free audit. All sessions must sign in again, including the initiating session. Staff email OTP and existing authenticators remain required.

Incorrect current-password proofs use the same concurrency-safe three-attempt lockout. A failed audit rolls back the hash, timestamp, version and proof consumption. Passwords are never kept as old form values or included in audit details.

The reviewed registration checklist is shared with Change Password. It uses the effective server minimum, mandatory uppercase/lowercase/number/special counts and bcrypt's 72-byte limit. JavaScript is guidance; PHP decides acceptance. Administrators may strengthen each required class from one to four, but cannot remove any class or lower the 12-character minimum.

## Proposed coursework expiration

**Proposed period: 90 days, pending owner/instructor confirmation.** IAS C11 mentions expiration without a duration. This proposal supports coursework demonstration; it is not a claim that periodic expiration is universally preferred. Production policy should consider current organizational guidance and risk.

`PASSWORD_EXPIRY_DAYS=0` disables expiration by default. A separately approved value from 30 to 365 enables it. Known older timestamps then restrict protected access to Change Password and logout until a successful change; existing normal MFA still precedes authenticated access. New accounts have a creation-time password timestamp. Migrated accounts keep NULL because their original password age is unknown; no backdating, automatic password reset or surprise expiration occurs. Establish an approved notice/grace/transition plan before populating legacy timestamps. The complete expiration requirement remains a policy/evidence gap until that deployment decision is made.

## Migration and recovery

After a fresh restore-tested backup and separate deployment approval, run `scripts/migrate_passwords.php` with all exact host, port, database, server, server-ID and data-directory guards. Dry-run adds nothing; approved apply adds only nullable `users.password_changed_at` and empty-default `users.password_version`. Existing passwords and business records are unchanged. Repeat execution validates definitions and plans zero additions. DDL implicitly commits.

Do not roll back to source that ignores credential versions after passwords change. Preserve additive fields and current records. No forgotten-password reset is implemented here: contact an authorized Administrator through established project channels; never reveal a current password or verification code.

Authenticator setup keys are shown only during explicit, password-verified enrollment, masked by default and revealed only by a button. No external QR service receives them. Keep the private encryption key and one-use recovery codes secure; password changes do not remove the factor. A setup/login challenge created before a password change is rejected.
