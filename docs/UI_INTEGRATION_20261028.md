# Avela UI integration — 28 October 2026

The owner-supplied Site v8 design is applied to the working PHP application. The usual local URL remains **http://localhost:18080/**. Use a hard refresh if a browser retains earlier styles. The original handoff, old extractions, research ZIP and existing catalog remain unchanged.

## Presentation changes

- Supplied sculpted vector wordmark, local Manrope/font license, favicon, approved editorial photographs and very light porcelain/cream foundations.
- Full-height campaign with “Your hair. Your kind of care.”, glass-to-light header after the hero, restored glass when returning to the top, actual featured products, texture discovery, editorial sections and care notes.
- Shared Shop panel with real category/concern links and two decorative photographs; no image links/captions or close X. Search retains real catalog/permission-aware pages and includes the new public care notes.
- Restyled catalog/product cards and details, existing tabs, favorites, account/profile, address dialog, assessment/saved profile, matching/routine, orders and security screens. Existing staff navigation/forms/tables remain complete and operational, with shared paper and new brand treatment.
- Native right-side bag dialog uses the existing authorized `/cart` response and CSRF-protected update/removal endpoints. Quantity changes use existing delegated handlers and server totals. Loading/error/empty states, direct full-cart fallback, Escape and opener/empty-state focus are verified.
- Wordmark-only authentication and checkout headers; approved account photography; all actual CAPTCHA, password, OTP/activation, MFA and recovery fields retained. Checkout keeps contact/delivery/payment and coupon behavior, sticky overview and a mobile overview jump.
- System reduced-motion preferences now govern both CSS and the shared JavaScript animation lifecycle, including select popovers. Existing finite timings, submit protection and rapid-animation cleanup remain tested.

No static account/cart storage, preview matcher, example identity, illustrative shipping prices or concept product packaging was installed as live data. The existing accurate PHP privacy policy remains authoritative; static editorial claims about browser-only account storage were corrected.

## Functional contracts preserved

`UI_PARITY_AUDIT.md` maps all customer, Catalog Manager and Administrator features and every registered route. All **89 original route registrations** remain exactly present; five allowlisted public care-note GET routes were added. Existing view form field names remain present. Services, models, core, middleware, configuration and database source files are byte-identical to the pre-UI backup.

This preserves authentication, activation sequence, staff email OTP and enrolled TOTP, session/cookie behavior, current server password/lock policy, PII encryption/keyring handling, actual upload decode/scanning, ownership/RBAC, runtime Python NLP, evidence/matching precedence, version/revision tokens, transactions/stock/totals, COD, Maya Sandbox reconciliation/idempotency, receipts and email queue behavior. No PayMongo or other provider was introduced.

Country remains a PH-only selector in profile and checkout. Region retains existing province/metropolitan-area values and geographic normalization. Saved selection, required contact fields, revision/ownership checks and checkout shipping/pricing refresh remain. Multiple/default/billing address books and geographic city/barangay lookup APIs were not features of this baseline and were not invented by the design port.

## Actual verification

- **1,870 passing checks, 0 failures across 41 regression suites.**
- Syntax: **269 PHP files and 20 JavaScript files**, no errors. Git whitespace check passes.
- **20/20** localhost HTTP/access smoke checks pass, including all new editorial pages and guest rejection from customer/admin/catalog routes.
- **79/79** original handoff files still match the existing manifest size/hash values. No expected package hashes were regenerated.
- Desktop (1280px), mobile (390px) and tablet (820px) viewports were observed in the browser. Protected customer screens use synthetic test identities; home/shop/authentication use the running localhost application. Observed layouts have no page overflow or broken images.
- Browser actions verified country/region hydration, real bag quantity change (₱329 → ₱658), authoritative checkout total (₱738 including ₱80 shipping), removal/empty state, keyboard Escape/opener restoration, the hero/header transition and decorative Shop images. Care-note category filtering returns the correct two matching notes.
- Screenshots and detailed private test logs are outside Git in the task's `ui-port-20261028` artifact directory. Safe aggregate evidence is in `docs/evidence/ui_integration_20261028_tests.json`.

| Suite | Pass | Fail |
| --- | ---: | ---: |
| staff_ui | 27 | 0 |
| security | 77 | 0 |
| customer_auth_flow | 33 | 0 |
| email_activation | 36 | 0 |
| mfa | 55 | 0 |
| password_security | 56 | 0 |
| persistent_locks | 30 | 0 |
| mail_routing | 21 | 0 |
| mail_readiness | 31 | 0 |
| profile_uploads | 25 | 0 |
| upload_scanning | 6 | 0 |
| pii_encryption | 30 | 0 |
| concerns | 6 | 0 |
| product_matching | 29 | 0 |
| nlp_process | 23 | 0 |
| hair_validation | 36 | 0 |
| delivery_validation | 106 | 0 |
| staff_pagination | 39 | 0 |
| mfa_setup | 17 | 0 |
| order_payment_ui | 11 | 0 |
| password_confirmation | 18 | 0 |
| checkout | 98 | 0 |
| catalog_module | 133 | 0 |
| admin_module | 134 | 0 |
| audit_security | 41 | 0 |
| hair_profiles | 43 | 0 |
| account_experience | 69 | 0 |
| customer_settings | 50 | 0 |
| customer_journey | 50 | 0 |
| customer_followup | 49 | 0 |
| checkout_refinement | 46 | 0 |
| privacy | 22 | 0 |
| create_staff | 57 | 0 |
| pii_storage | 22 | 0 |
| maya_sandbox | 115 | 0 |
| order_delivery | 32 | 0 |
| discounts | 79 | 0 |
| routine_guide | 29 | 0 |
| maya_hosted_mode | 39 | 0 |
| motion | 28 | 0 |
| staff_contrast | 22 | 0 |

## Isolation and corrections during testing

The live application still uses its existing database on port 13321. Regression runs use a separate PHP copy at 18427 and verified synthetic schemas on the dedicated 13427 instance. Fresh test schemas were created without modifying existing schemas or importing the research/catalog package. The payment transport and mail outbox are test-only; no real payment or external mail delivery was claimed.

Initial results were preserved. The first test copy omitted ignored image fixtures and tracked upload-directory policies, so uploads correctly failed closed. Those files were restored only in the test copy. The mock provider status path was corrected to the current test scratch directory; encryption tests use a guarded synthetic PII-named schema. A clock-boundary test now compares its captured grace deadline; the About-photo assertion now verifies the newly approved images. None of these corrections relax application security.

Protected browser screenshots use normal test-created session fixtures in the isolated copy; the temporary bridge never exists in the working application. Test helpers/listeners are removed/stopped after review; synthetic evidence databases are retained for reproducibility.

## Git and local operation

Branch remains `codex/integrate-main-development`, HEAD `2b7082f13d0cdd5f9c621bf50b2cfbecf0b20e3a`, MERGE_HEAD `1158429b3fc72d9560bfff55ab69d48594f49914`. The original staged integration index is identical to the pre-UI snapshot. UI work is a distinct unstaged layer plus new files. No branch switch, commit, push, merge into main or deployment occurred. Other checkouts were not edited.

The existing manual `Start-Avela-Development.cmd` launcher now validates a reviewed runtime file manifest for this UI version instead of requiring every application file to equal the earlier feature tip. Exact source/root/Git identity and the existing database/configuration preflight remain enforced. Its healthy-running invocation passes. No automatic Windows startup task or additional public exposure was installed.

## Practical limits

The design adapts to actual catalog photographs/placeholders and extra backend-required fields rather than replacing them with eight preview products or omitting security steps. Staff layouts retain operational density because no staff replacement was supplied. Screenshot checks and automated regressions are not exhaustive assistive-technology certification. Real SMTP delivery, genuine Maya Sandbox connectivity, public webhook readiness and cloud deployment remain separately configured operational acceptance items; their existing guards/settings were not weakened.
