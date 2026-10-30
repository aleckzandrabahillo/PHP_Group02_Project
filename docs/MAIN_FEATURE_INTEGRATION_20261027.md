# Main / feature integration review — 27 October 2026

The application integration is resolved and regression-tested as a staged, reviewable merge on `codex/integrate-main-development`. No merge commit, push, main update, deployment or redesign has been performed. `MERGE_HEAD` retains the feature parent for review; HEAD remains the specified main parent until a later commit.

## Inputs and isolation

- Base: `origin/main`, exactly `2b7082f13d0cdd5f9c621bf50b2cfbecf0b20e3a`.
- Feature: exactly `1158429b3fc72d9560bfff55ab69d48594f49914`.
- The pinned parents diverge: main-only 20 commits, feature-only 1 commit. Integration used a normal two-parent merge with `--no-commit --no-ff`, followed by individual conflict review. No blanket merge strategy was used.
- Worktree: the separately managed `avela-main-feature-integration/PHP_Group02_Project` checkout. All 40 pre-existing checkouts passed before/after comparison of HEAD, branch, status, staged diff and tracked/nonignored untracked file hashes. Their existing changes were preserved.
- PRs #35, #36, #13 and #5 were excluded as integration inputs. No PayMongo implementation was introduced. No product/research import command completed, and no existing application database was used.

## Resolution decisions and preserved contracts

There were 62 conflicted files, including coordinated add/add implementations. Parent-to-parent diffs, callers, templates and tests were reviewed together. Feature supplies the coordinated current application behavior; main's README breadth and order-upgrade guide remain. The application paths `app`, `public`, `config` and `database` match the reviewed feature tip after resolution; their differences from main are the reviewed feature changes, not a new architecture or redesign.

- Registration remains pending after its email code; only the subsequent, separate, versioned activation link activates the account. Activation is CSRF-protected POST-only and never signs in a user. Delivery/audit failures retain transactional behavior.
- Customer Authenticator enrollment is optional; enrolled customers require TOTP/recovery after password. Staff retain mandatory email OTP and any enrolled TOTP. Replay, factor/credential versions, current eligibility and session rotation remain.
- Server password requirements and persistent Administrator-released locks remain. Default lock threshold is three; the existing owner-requested 1–255 configuration is retained. New-deployment password age defaults to 90 days, while explicit private overrides and unknown legacy ages remain authoritative. No deployment policy or private setting was rewritten.
- CSRF, ownership/RBAC, live account eligibility, concurrency/revision/fingerprint tokens, encoded output, prepared queries and actor-attributed audits remain. Catalog Managers do not gain account/security administration.
- PHP/MySQL remain primary; Python concern interpretation executes at runtime. Assessment fields, normalization/negation behavior, evidence-based matching, reasons and saved routine invalidation remain. No trained model or medical workflow was introduced.
- Checkout retains server quotes, transactional stock/cart/order/audit work, idempotency and rollback. Selected PII snapshot/profile protection participates in those transactions; keys remain external. Existing selected-field scope is retained, including the documented exclusion of structured address copies.
- Maya remains Sandbox-only, with authenticated reference/amount/state verification, stock reservations, webhook/reconciliation handling and honest simulated-payment labels. Browser returns remain no payment proof. COD delivery does not invent collection evidence.
- Targeted receipts occur after commit; cancelled, unverified or simulated paid orders cannot issue genuine paid receipts. Claiming/uncertain SMTP handling prevents blind duplicate sends. Receipt arithmetic and escaped HTML/plain-text alternatives remain.
- Genuine upload validation, GD decoding, limits, generated filenames, storage policies and reference-safe retirement remain. Optional Defender scanning acts on private temporary files before public storage and fails closed when enabled but unavailable. Disabled mode makes no antivirus claim.

## Integration-only adjustments beyond the parent trees

README now accurately describes sequential activation, configurable locking, opt-in protection and required GD. The upload guide retains main's correct 2,621,440-byte/full-decoding contract and feature's real scan hook, removing contradictory stale text. The password guide now points to the actual configurable lockout contract rather than claiming a hard maximum of three.

Regression fixtures were made explicit without changing application policy:

- `checkout.php` and `catalog_module.php` accept `--fixture=fresh6`; the existing `demo20` default remains. Fresh mode uses an existing fresh-schema shampoo SKU and verifies exactly six starting products. It does not import the expanded catalog.
- Customer pagination tests follow all actual pages and retain database-order comparison; smaller catalogs are valid. Customer settings creates its own synthetic customer/saved profile rather than selecting another suite's account.
- Admin complexity setup uses the authorized policy service and restores its effective values; an UPDATE against a row absent in fresh schemas previously changed nothing.
- Account/discount suites retain exact database/port/URL allowlists, adding only this disposable integration target, and retain server-ID/datadir/environment checks. The fault connection uses the already verified port.
- The locked-staff image dry-run uses the unchanged reviewed comb asset whose product exists in the fresh schema. The complete image manifest validates successfully. No expected image hashes were regenerated and no images were assigned.

## Regression environment and evidence

A new MariaDB instance was initialized in a dedicated external datadir, bound to `127.0.0.1:13427`. Before writes, server port, exact datadir and fresh database identity were verified. Synthetic schemas were `avela_pii_test_20261027` and `avela_commerce_test_20261027`; the second target was required to be absent/empty before creation. Only fresh-schema six-product seeds and suite-owned synthetic fixtures were used. The normal installer and research/catalog import commands were not run against application data. Locked-user importer rejection is a negative authorization test, not an import.

The PHP test server used loopback port 18427, private external sessions/outbox/scratch, generated private test keys and log mail. Existing Composer-locked dependencies were copied after verifying the lockfile; dependencies were not updated. GD was enabled for test processes only. Production/application configuration was not copied or weakened.

After verification, only the recorded test processes were stopped. The generated worktree `.env`, private test logs and synthetic uploaded files were removed; tracked upload policies and locked dependencies remain. External evidence stays private.

For HTTP Maya/receipt tests, an external guarded router injected the existing payment-service constructor seams in memory. Actual controller authorization, CSRF and payment evidence logic executed. No application source instrumentation or provider requests were needed. This does not establish a genuine Maya transaction, public webhook ingress, worker scheduling or mailbox receipt.

An initial setup retry was rejected by automatic approval review because it relaxed freshness guards and could update/delete catalog rows. It was not executed. The safe alternative used a strictly new empty schema and the six-product fresh-schema fixture, with no expanded catalog import. A generated test-file ACL problem was also repaired before connection; no application configuration was accessed to solve it.

Final syntax checks: 262 PHP files and 19 JavaScript files passed. No unresolved conflict markers remain. Latest completed results: **40 suites, 1,842 checks passed, zero failed**. These are the exercised suites, not a claim that every repository test was run. Expanded-catalog-only scenarios were not populated/imported; in-memory matching and actual fresh-schema assessment/routine journeys were exercised.

Initial failures and blocked starts were retained in local run history. They exposed old 20-product/SKU/two-page/saved-account assumptions, absent policy fixture rows and old hardcoded targets; the affected suites were corrected and rerun. One initial guest-role assertion was not reproduced in six direct requests or subsequent complete admin runs; final admin results are 134/134. Image assets validated; its initial negative test reached missing product identity before account eligibility, which the minimal existing-asset fixture corrected.

| Suite | Passed | Failed | Final result |
| --- | ---: | ---: | --- |
| `security` | 77 | 0 | PASS |
| `customer_auth_flow` | 33 | 0 | PASS |
| `email_activation` | 36 | 0 | PASS |
| `mfa` | 55 | 0 | PASS |
| `password_security` | 56 | 0 | PASS |
| `persistent_locks` | 30 | 0 | PASS |
| `mail_routing` | 21 | 0 | PASS |
| `mail_readiness` | 31 | 0 | PASS |
| `profile_uploads` | 25 | 0 | PASS |
| `upload_scanning` | 6 | 0 | PASS |
| `pii_encryption` | 30 | 0 | PASS |
| `concerns` | 6 | 0 | PASS |
| `product_matching` | 29 | 0 | PASS |
| `nlp_process` | 23 | 0 | PASS |
| `hair_validation` | 36 | 0 | PASS |
| `delivery_validation` | 106 | 0 | PASS |
| `staff_ui` | 27 | 0 | PASS |
| `staff_pagination` | 39 | 0 | PASS |
| `mfa_setup` | 17 | 0 | PASS |
| `order_payment_ui` | 11 | 0 | PASS |
| `password_confirmation` | 18 | 0 | PASS |
| `staff_contrast` | 22 | 0 | PASS |
| `checkout` | 98 | 0 | PASS |
| `catalog_module` | 133 | 0 | PASS |
| `admin_module` | 134 | 0 | PASS |
| `audit_security` | 41 | 0 | PASS |
| `hair_profiles` | 43 | 0 | PASS |
| `account_experience` | 69 | 0 | PASS |
| `customer_settings` | 50 | 0 | PASS |
| `customer_journey` | 50 | 0 | PASS |
| `customer_followup` | 49 | 0 | PASS |
| `checkout_refinement` | 46 | 0 | PASS |
| `privacy` | 22 | 0 | PASS |
| `create_staff` | 57 | 0 | PASS |
| `pii_storage` | 22 | 0 | PASS |
| `maya_sandbox` | 115 | 0 | PASS |
| `order_delivery` | 32 | 0 | PASS |
| `discounts` | 79 | 0 | PASS |
| `routine_guide` | 29 | 0 | PASS |
| `maya_hosted_mode` | 39 | 0 | PASS |

## Open functional/deployment decisions

- IAS wording literally caps failed attempts at three while customization and the existing owner decision permit higher settings. Default-three behavior is retained; instructor acceptance of the configurable override remains unresolved. This merge does not turn owner approval into an instructor ruling.
- Existing deployment password-expiry overrides and any prospective legacy grace transition still require rollout decisions; source integration does not migrate unknown ages or expire shared accounts.
- Mandatory MFA role coverage, actual provider/mailbox delivery, HTTPS/webhook/worker operations and any broader gateway requirement remain separate acceptance decisions. No PayMongo or new production mode is authorized here.
- Encrypted-field/keyring/schema/backfill rollout and structured-address coverage require separate review. No existing application data was encrypted/backfilled in this milestone.
- Real service-identity Defender acceptance, maintained definitions and genuine detection testing remain deployment evidence gaps. The scanner regression exercised controlled processes/fail-closed behavior, not a malware-detection claim. Apache upload restrictions were source-reviewed; PHP's development server does not prove their enforcement.
- Full browser/assistive-technology/device acceptance and a polished visual redesign remain outside this integration milestone. No Sites/handoff draft was applied.

No incompatible source requirement needed a new user decision to prepare this merge. The open policy/deployment decisions above remain explicit rather than silently implemented.

## Per-file conflict decisions

The external conflict-resolution record also retains parent/resolution SHA-256 fingerprints. This list states why each conflicted file was resolved that way.

| File | Resolution rationale |
| --- | --- |
| `.env.example` | Retain feature opt-in PII/scanner/local-fixture settings and 90-day new-deployment default; never copy private deployment settings. |
| `DESIGN.md` | Retain both existing common guidance and feature staff/MFA specifications; no new redesign is introduced. |
| `app/Controllers/ActivationController.php` | Retain main CSRF POST-only activation with feature optional enrollment offer; never auto-login. |
| `app/Controllers/AdminController.php` | Keep existing authorization/staff creation; accurate mandatory staff-email plus optional-TOTP message. |
| `app/Controllers/AuthController.php` | Coordinate OTP-first/link-second activation, delivery retry and optional customer enrollment with resolved services. |
| `app/Controllers/CustomerController.php` | Keep main transactional checkout and ownership; feature targeted post-commit confirmation and owned CSRF resend. |
| `app/Controllers/MayaController.php` | Keep owned CSRF provider verification; feature bounded JSON refresh and post-commit receipt. |
| `app/Controllers/MfaController.php` | Keep main private authenticated enrollment; feature setup URI/deadline/progress and role-correct disable message. |
| `app/Controllers/PasswordController.php` | Keep authenticated no-store change form and feature effective expiry deadline. |
| `app/Models/Order.php` | Preserve owned order queries and snapshots; add encrypted reads, verified timestamp and queued-email status. |
| `app/Models/User.php` | Preserve queries and account checks; feature PII writes stay inside caller transactions and protected reads. |
| `app/Services/ActivationService.php` | Feature sequential OTP/link replaces combined proof while retaining main locked account, digest, expiry, rollback and trusted-origin safeguards. |
| `app/Services/AuthService.php` | Feature customer opt-in TOTP and separate activation flow retain main staff OTP, live eligibility, version locks and session finalization. |
| `app/Services/CheckoutService.php` | Main stock/order/cart transaction retained; feature encrypted snapshots participate in that transaction. |
| `app/Services/CustomerAddress.php` | Main revision, ownership and audit transaction retained; feature protects profile address write. |
| `app/Services/ImageUploadPolicy.php` | Main real decoding/resize/limits retained; feature scans private temporary file before public storage when explicitly enabled. |
| `app/Services/MailService.php` | Main verified TLS preserved; feature separate activation mail, escaped receipts and restricted local staff fixture transport. |
| `app/Services/MailTransportCheck.php` | Keep operator identity checks; feature explicit sender-only diagnostic cannot select arbitrary recipients. |
| `app/Services/MayaPaymentService.php` | Keep main authenticated evidence and transactional state/stock logic; feature records same-state checks and delivers only after commit. |
| `app/Services/MfaService.php` | Keep replay/recovery/credential-version guards; feature setup URI and customer-only flag clearing preserve mandatory staff email. |
| `app/Services/OrderEmailQueue.php` | Main claim/commit/uncertain-send handling retained; feature rejects cancelled, unverified and simulated paid receipts. |
| `app/Services/OtpService.php` | Main hashed/expiring/limited proof preserved; add account identity to guarded staff transport. |
| `app/Services/PasswordService.php` | Preserve explicit deployment overrides and unknown legacy ages; feature default 90 days with explicit prospective legacy grace. |
| `app/Services/SecurityPolicy.php` | Retain feature owner-requested configurable threshold with default three and all other secure bounds; instructor conflict remains documented. |
| `app/Views/account/mfa.php` | Coordinate existing feature step-based local QR enrollment with controllers, private cache policy and server proofs. |
| `app/Views/account/password.php` | Preserve CSRF/server-policy form, add effective password deadline and consistent MFA wording. |
| `app/Views/account/staff-profile.php` | Preserve existing owned profile/email proof form and feature staff presentation; sign-out remains in shared sidebar. |
| `app/Views/admin/dashboard.php` | Preserve real main analytics/security links and feature shared staff layout. |
| `app/Views/admin/log_records.php` | Preserve escaped filters/log values and authorized routes; feature shared pagination/status display. |
| `app/Views/admin/order.php` | Preserve recorded contact/items/timeline and escaping; retain feature staff layout. |
| `app/Views/admin/orders.php` | Preserve expected-status tokens, cancellation CSRF and stock evidence; feature pagination and status labels. |
| `app/Views/admin/security.php` | Keep main CSRF/locked-account/history controls and feature policy grouping matching actual configurable service. |
| `app/Views/admin/staff.php` | Keep main validation and server password requirements; feature accurately describes staff factors. |
| `app/Views/admin/users.php` | Existing shared account controls retained; feature customer label. |
| `app/Views/auth/verify-authenticator.php` | Keep CSRF and recovery fallback; text works for both customer and staff factor order. |
| `app/Views/auth/verify-otp.php` | Keep numeric proof form and main guards; describe required separate activation link. |
| `app/Views/catalog/dashboard.php` | Retain main real catalog aggregates/activity, feature shared staff composition and discounts link. |
| `app/Views/catalog/discounts.php` | Keep revisions, money calculations and CSRF; feature shared pagination and staff statuses. |
| `app/Views/customer/order.php` | Preserve escaped snapshots and timeline; feature honest provider/COD labels, owned refresh and email status. |
| `app/Views/customer/orders.php` | Keep owned history/continuous pagination; use feature evidence-aware payment label. |
| `app/Views/customer/settings.php` | Retain existing route; consistent feature MFA label. |
| `app/Views/layouts/app.php` | Keep main session watch/customer layout; load feature staff assets and payment feedback script. |
| `app/Views/partials/admin_accounts.php` | Keep self-protection, expected lock/status tokens and CSRF; feature shared statuses/pagination. |
| `app/Views/partials/dashboard_analytics.php` | Retain actual aggregates and accessible exact-data table; feature chart/grid and explicit exclusions. |
| `app/Views/partials/staff_sidebar.php` | Keep role-specific routes and CSRF logout; feature direct orders link and responsive grouped navigation. |
| `app/Views/public/privacy.php` | Keep privacy warnings and contact verification; update feature MFA terminology. |
| `docs/EMAIL.md` | Document feature sequential activation and targeted receipts alongside main strict TLS and deployment limits. |
| `docs/MAYA_SANDBOX.md` | Retain main Sandbox-only lifecycle contracts and feature bounded browser verification/receipt documentation. |
| `docs/PASSWORDS.md` | Combine feature expiry/session behavior with corrected current configurable lockout description; preserve requirement uncertainty. |
| `docs/SECURITY.md` | Retain explicit feature owner-versus-instructor policy distinction, secure defaults and opt-in deployment gates. |
| `docs/UPLOADS.md` | Synthesize main accurate byte/decoding limits with feature scan opt-in and resize description; remove contradictory stale assertions. |
| `public/assets/css/experience.css` | Main responsive/upload styles retained; feature password confirmation status styles. |
| `public/assets/js/app.js` | Main assessment/upload/staff handlers retained; feature stable pagination focus and single photo-upload handler. |
| `public/assets/js/password-policy.js` | Retain main policy guidance/reveal support; feature progressive confirmation feedback with no server-policy override. |
| `public/assets/js/staff-navigation.js` | Keep main same-origin partial navigation; feature longest matching path and active group handling. |
| `scripts/check_mail.php` | Keep all main exact-identity CLI guards; feature explicit configured-sender option. |
| `scripts/process_maya.php` | Preserve main guarded reconciliation; feature targeted receipt after committed provider verification. |
| `tests/checkout_refinement.php` | Keep existing negative checks; feature assertion matches preserved configurable policy. |
| `tests/email_activation.php` | Retain main expiry/replay/concurrency/CSRF/rollback coverage while exercising required sequential proofs. |
| `tests/mail_readiness.php` | Keep real loopback TLS rejection/acceptance tests; add restricted sender diagnostic and exact audit count. |
| `tests/maya_sandbox.php` | Keep main payment/state/stock regression cases; feature settings loader tests effective configuration rather than shadowed environment. |
| `tests/password_security.php` | Retain main lock/audit/concurrency/revocation tests; feature grace boundaries and protected checkout expiry coverage. |
