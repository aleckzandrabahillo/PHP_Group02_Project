# IAS code and evidence presentation guide

Prepared 24 October 2026 against the updated `IT 108 - IAS` worksheet, C3:H28. This guide focuses on controls that are difficult to demonstrate through ordinary screens. The owner's manual VERIFIED marks are preserved in the original workbook; they do not expand the scope of automated or external-service evidence.

Open the canonical project in VS Code: `C:\Users\zandr\Projects\Avela\PHP_Group02_Project`. Use Ctrl+P, enter a path below, then use Ctrl+F for the named function. Present the relevant function and one passing/negative test rather than scrolling through an entire file.

## Safe short evidence run

From the project root in PowerShell:

```powershell
& 'C:\xampp\php\php.exe' tests/ias_code_evidence.php
& 'C:\xampp\php\php.exe' tests/pii_encryption.php
```

The first command passed 50 checks on 24 October. It uses SQLite `:memory:` with synthetic records and does not load `.env`, start a login session, read private records, send messages or contact Maya. It proves the exercised PHP logic, not MySQL locking or whole-site security. The second passed 30 checks using temporary in-memory encryption keys; it never displays those keys. These commands can be rerun without changing existing application databases.

Do not casually run the other database/HTTP suites against localhost:18080. Those suites require a separate guarded disposable environment. Their historical acceptance results are supporting evidence, not a fresh run in this presentation.

## C11/C12: password strength and expiration

- Open [Validator.php](../app/Core/Validator.php), `passwordError()`, for the minimum length and required character groups.
- Open [SecurityPolicy.php](../app/Services/SecurityPolicy.php), `RULES` and `validate()`, for Administrator settings and server-side validation.
- Open [PasswordService.php](../app/Services/PasswordService.php), `expiresAt()` and `expired()`, for the 90-day calculation. [AuthGuard.php](../app/Middleware/AuthGuard.php), `auth()`, redirects an expired account to password change.
- Show the last three expiry checks in `tests/ias_code_evidence.php`: a password changed 89 days ago is valid, one changed 91 days ago is expired, and its deadline is exactly 90 days from the change. This avoids changing actual account timestamps or waiting 90 days.
- Explain: **“The browser checklist helps the user, but PHP checks the password again. We calculate expiration from the recorded password-change date and enforce it when the account accesses protected pages.”**
- Distinguish the approved coursework policy from a universal security recommendation. Legacy accounts use the separately configured transition deadline; do not claim their historical password ages were known.

## C14/C15: session expiry and server-side access control

- Open [Session.php](../app/Core/Session.php), `start()` and `forgetAuth()`, for inactivity handling and removal of authenticated state.
- Open [AuthGuard.php](../app/Middleware/AuthGuard.php), `currentUser()` and `roles()`. Account status, role, password version and MFA version are checked against current records, not just the stored menu state.
- Open [session-watch.js](../public/assets/js/session-watch.js) for the browser's expiry response, then [AdminController.php](../app/Controllers/AdminController.php), `accountChange()`, for a protected mutation.
- Explain: **“Hiding an Administrator link is not the permission check. The server checks who is making the request and whether their account is still eligible. An expired or revoked session cannot continue through a direct URL.”**
- The memory checks demonstrate inactive/unknown-role rejection and persistent locks. Full HTTP role denial and timed logout are covered by `tests/security.php`, `tests/persistent_locks.php` and `tests/checkout_refinement.php` in the isolated environment; do not describe the memory checks as a full browser session test.

## C16: SQL injection protection

- Open [User.php](../app/Models/User.php), `findByIdentifier()`: `prepare()` defines the query and `execute()` passes the supplied identifier separately.
- Open [Database.php](../app/Core/Database.php), `connection()`: MySQL prepared statements use `PDO::ATTR_EMULATE_PREPARES => false`.
- Open [Product.php](../app/Models/Product.php) to show that dynamic sorting uses a predefined allowlist, rather than inserting arbitrary user text into an SQL keyword position.
- Run the safe evidence command. A normal synthetic identifier finds its account; the SQL-like identifier returns no account.
- Explain: **“The entered value is treated as data. It cannot change the meaning of this query. Sort choices are also checked against allowed options.”**
- The synthetic lookup runs on SQLite; historical MySQL suites supply additional database-specific evidence. One example does not prove every query in the system is immune to every attack.

## C17: cross-site scripting protection

- Open [helpers.php](../app/Support/helpers.php), `e()`: it calls `htmlspecialchars()` with quote encoding and UTF-8 handling.
- Open [admin_accounts.php](../app/Views/partials/admin_accounts.php) for `e()` applied to displayed account data.
- Show the safe check confirming that script markup and quotes are converted to display text.
- Explain: **“User-entered text is encoded before it is placed in the page, so markup is shown as text instead of executed as code.”**
- This demonstrates HTML output handling; do not claim that one encoder is appropriate for every JavaScript/URL context or substitutes for reviewing all insertion points.

## C18: cross-site request forgery protection

- Open [Csrf.php](../app/Core/Csrf.php), `token()`, `validate()` and `enforce()`.
- Open [AdminController.php](../app/Controllers/AdminController.php), `accountChange()`, to show enforcement before the change. Forms receive a token through `csrf_field()`.
- Show passing checks for a matching token and rejected missing, incorrect, array and different-session tokens.
- Explain: **“A request to change data must carry the token belonging to the signed-in session. Missing or wrong tokens are rejected before the action is performed.”**
- Full HTTP rejection is exercised by the guarded security/Admin/Catalog suites. The safe command checks token logic without performing account mutations.

## C19: password hashing

- Open [PasswordService.php](../app/Services/PasswordService.php), `change()`, and [AuthService.php](../app/Services/AuthService.php), `attempt()`.
- Point to `password_hash()` and `password_verify()`. Show the safe tests accepting the correct synthetic password and rejecting a different one.
- Explain: **“We store a one-way password hash, not a readable password. During sign-in, PHP verifies the supplied password against that hash.”**
- Never open real password records, print hashes or show an App Password. Hashing is different from reversible personal-data encryption.

## C20: selected personal-data encryption

- Open [PiiCipher.php](../app/Services/PiiCipher.php), `FIELDS`, `encrypt()`, `decrypt()` and `context()`.
- `FIELDS` explicitly lists customer profile phone/address and order phone/address snapshots. AES-256-GCM both encrypts and detects changes. The context ties the encrypted value to its table, field and record.
- Open [PiiStorage.php](../app/Services/PiiStorage.php), `write()`: the protected value is saved to its encrypted column and the corresponding original column is cleared. Encrypted records do not silently fall back to readable values when protection is unavailable.
- Run `tests/pii_encryption.php`. Show round-trip PASS, different ciphertext for repeated encryption, and rejection of wrong keys, altered contents and wrong record/field contexts. No real key is used.
- Explain: **“For these approved fields, the database stores protected values. The application needs its separate private key to read them, and altered values are rejected.”**
- Limit: names, emails, separate structured-address copies and staff contact fields are not all covered. Avela never stores card numbers or CVVs. The four-field rollout was verified in the disposable review; do not call this complete encryption of all personal information.

## C21: SSL/TLS

- Open [MailService.php](../app/Services/MailService.php), `smtpMailer()`, for encrypted SMTP and certificate/name verification.
- Open [MayaSandboxClient.php](../app/Services/MayaSandboxClient.php), `request()`, for HTTPS-only requests, certificate verification and disabled redirects.
- Explain: **“Email and payment connections verify the remote service's secure connection. Whole-site HTTPS is a separate deployment requirement.”**
- **Code cannot prove that Avela itself is served over trusted HTTPS.** The existing localhost URL uses HTTP. A later approved HTTPS deployment must be checked in the browser and server environment. Maya's secure page does not satisfy whole-site Avela HTTPS.

## C22: image validation and actual virus scanning

- Open [ProductImageService.php](../app/Services/ProductImageService.php), `store()`, and [ProfileImageService.php](../app/Services/ProfileImageService.php), `store()`: upload origin, permitted extension, actual MIME, image structure, dimensions, 2.5 MB size limit and random stored name.
- Open [ImageUploadPolicy.php](../app/Services/ImageUploadPolicy.php), `store()`: the private temporary upload is scanned **before** any public image is written. `decode()` also rejects unreadable image data.
- Open [UploadScanner.php](../app/Services/UploadScanner.php), `assertClean()`: when the Defender driver is enabled, it invokes only an approved Windows Defender executable for that one file. A nonzero result prevents storage; it does not pretend to clean the upload.
- Open [UploadScanProcess.php](../app/Services/UploadScanProcess.php), `run()`: fixed argument array, no shell, bounded execution and no private scanner output printed to the page.
- Historical acceptance on 24 October: clean image passed; the official harmless EICAR antivirus test file was detected and rejected before public storage; real-time protection stayed enabled and no Defender exclusions were added. This is separate from ordinary JPG/type/size validation. Do not create or upload malware for the class.
- Open the share-safe [recorded scanner acceptance](evidence/defender_acceptance_20261024.json). It contains result booleans only and identifies this as historical local acceptance, not a new scan in this update. The sample was scanned, never executed.
- Explain: **“The image must pass both our upload validation and the enabled antivirus scan before it becomes public. If the scanner is unavailable or rejects the file, we refuse the upload.”**
- Limit: scanning can be disabled by deployment configuration; source inspection alone cannot prove it is enabled. A Windows Defender pass is local Windows evidence, not acceptance of a future Linux host. Antivirus reduces risk; it does not guarantee every threat will be detected.

## C23: secure payment confirmation, not just opening checkout

- Open [MayaSandboxClient.php](../app/Services/MayaSandboxClient.php), `verifiedPayment()`, `validatePayment()` and `request()`: server-authenticated Sandbox retrieval and exact matching of order reference, payment ID, PHP currency and amount.
- Open [MayaPaymentService.php](../app/Services/MayaPaymentService.php), `reconcile()`: provider evidence is checked before order/payment changes. Existing terminal states and stock reservation state prevent repeated callbacks from duplicating deductions or releases.
- Open `enqueue()` in the same file: genuine TLS, source-IP allowlist and bounded input are required. Arbitrary forwarded headers are not trusted. Do not claim an unimplemented webhook signature scheme; final payment trust comes from authenticated provider retrieval and exact matching.
- Explain: **“Returning to our success page is not proof of payment. Our server asks Maya for the payment result, checks it belongs to the correct order and amount, then updates the order once.”**
- Only Maya Sandbox is integrated; the rubric's other gateways are examples, not additional implemented APIs. No production payment claim. Hosted checkout and previously recorded authenticated status evidence exist; public HTTPS webhook delivery and scheduled-worker acceptance remain separate incomplete environment checks.
- `tests/maya_sandbox.php` exercises controlled provider responses; simulated evidence must not be described as a genuine Maya transaction.

## C24/C27: lock history and trustworthy transaction records

- Open [User.php](../app/Models/User.php), `recordFailure()`: the account is locked for update; the failure counter, separate `locked_accounts` incident and audit are part of the same transaction.
- Open [SecurityManagement.php](../app/Services/SecurityManagement.php), `unlock()` and `savePolicy()`: the acting Administrator and current account eligibility are rechecked. An unlock records who performed it without erasing the incident.
- Open [AuditService.php](../app/Services/AuditService.php), `record()`, and [Database.php](../app/Core/Database.php), `transaction()`. Required audit writes are in the same transaction as the business change; failure rolls back the operation.
- Explain: **“A change and its required record succeed together. The record identifies the action, affected record, actor and time. Unlocking an account keeps its earlier lock history.”**
- Failure/rollback/concurrent-change evidence lives in `tests/security.php`, `tests/admin_module.php`, `tests/catalog_module.php`, `tests/persistent_locks.php` and `tests/audit_security.php`; run only with the disposable guards. System-originated events may correctly have no human actor; do not invent a username for them.

## Latest registration and Customer sign-in clarification

The owner relayed the instructor's requirement on 24 October: registration OTP verification and account activation must be separate, sequential steps. `AuthService::register()` sends only the email code. `ActivationService::verifyAndSendLink()` consumes a correct code and sends the separate activation email while the Customer stays pending. `consume()` alone activates through the versioned, expiring, one-use link; activation redirects to Login and offers optional Authenticator setup after sign-in. Old combined-message links cannot bypass the new sequence. No schema migration or personal-data changes are part of this update.

Customer Authenticator is explicitly optional. Enrolled Customers use password → Authenticator/recovery; unenrolled Customers use password-only sign-in. Customer login no longer sends email OTP. Enrolled staff also use password → Authenticator/recovery. Until enrollment, staff can use email/username and password to set up their factor; mandatory staff MFA coverage is incomplete until every staff member enrolls. Email proofs for enrollment/account changes remain. `tests/customer_auth_flow.php`, `tests/email_activation.php` and `tests/mfa.php` distinguish these paths using synthetic accounts and private local mail, not real inbox delivery.

## C9/C10: updated Administrator attempt setting

- Open [SecurityPolicy.php](../app/Services/SecurityPolicy.php), `RULES`: **three is the default**, not the maximum. The Administrator may choose 1–255, the existing counter's storage capacity. [SecurityManagement.php](../app/Services/SecurityManagement.php), `savePolicy()`, preserves Administrator authorization and auditing.
- Existing saved settings take precedence; this code change does not silently reset an Administrator's chosen value. `.env` and existing database records were not changed.
- The safe evidence run accepts 3, 4, 5, 10, 100 and 255 and rejects malformed/overflow input. The MySQL security suite passed 77 checks in a fresh isolated database on 24 October, including exact locking at 5, 10 and 255 plus authorization, CSRF and audit checks. These are synthetic database/HTTP tests, not changes to real users.
- Explain: **“The initial setting is three failed attempts. The Administrator can choose a different threshold, and the same server-side lock and audit rules apply.”**
- Workbook C9 literally says a maximum of three; C10 permits customization. The owner's latest request authorizes values above three, but is not evidence of an instructor clarification. Show the default and configurable behavior separately; do not claim a hard maximum of three anymore.

## Corrections to older remarks in the supplied workbook

- G11: the later completion record verifies the 90-day policy in the disposable review; do not leave it described as source-only implementation.
- G20: the approved four-field rollout was verified in that review. Broader personal-data coverage is still partial.
- G22: later clean-file and harmless positive-detection acceptance were completed. Future-host scanning acceptance remains separate.
- G23/H23: Maya Sandbox alone is present. A hosted page opening, verified payment status and a delivered webhook are three different kinds of evidence.
- G28/H28: the owner agreed to monitor the configured privacy inbox and its rendered contact was verified. Final policy/terms approval remains outstanding. That approval cannot be established by a code demonstration.

The original Excel was read only and has not been overwritten. Do not display `.env`, private key files, real OTP logs, session files, recovery codes, actual delivery details or payment secrets while sharing your screen.
