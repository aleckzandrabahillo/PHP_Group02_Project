# Avela handoff UI correction — 29 October 2026

The applicable customer presentation now uses the owner-supplied **28 October Site v8 handoff**, source `60393812a3f085222feed8ffc4ee1390d986c7b3`. This corrects the earlier styling pass, which retained too much of the old storefront structure. The running application is **http://localhost:18080/**.

## Corrected presentation

- The actual six handoff stylesheets are scoped in `public/assets/css/handoff-v3.css`. Customer layouts load this reference styling plus `handoff-functional.css`, instead of the obsolete customer/experience/refinement styling stack. Staff workspaces retain their applicable existing presentation and security features; the handoff has no staff/admin screen designs.
- Shop uses the two text columns and two large decorative photographs from the reference. Over the hero, the glass header is 65px high at 26px from the top; the panel begins at 101px. After the hero, the light header is flush at the top, 80px high, and the panel becomes full width. Rounded rectangular icon hovers, keyboard focus and Escape behavior are retained.
- The bag is a compact native side dialog with independent header, scrolling item area and fixed subtotal/footer. A long cart produces exactly one internal scroll region. The page behind it does not scroll. Actual quantities, stock warnings, totals and POST/CSRF update/removal forms remain. Loading, failure, empty state and a full-bag fallback are provided. On the full `/cart` page, the bag link retains normal navigation to avoid duplicate editable cart views.
- `GET /cart?panel=1` is the sole customer-controller presentation addition: it runs the existing customer guard, reads the signed-in customer's cart, sets private/no-store caching and renders the compact fragment. It cannot select another user's cart.
- Assessment uses Texture, Scalp, Lengths & habits, Your words and Review with the handoff rail, progress, illustrations and selected states. The required `hair_condition`, optional concerns/treatments/description, CSRF and `profile_version` remain. Review edits return to the proper step. Hair descriptions stay in memory until the existing server submission.
- Profile uses the handoff account rail, four account-detail rows (including read-only username), address panel and edit dialogs. Favorites use the handoff empty state and cards. Catalog/product layouts, compact purchase row, product-detail accordions, full bag, checkout, orders/settings, saved hair profile, routine and matching presentation use the same design language.
- Home texture dialogs and routine tabs, care-note cards/articles, exact supplied care SVG paths, wordmark, approved photography and local Manrope fonts are used. Existing real catalog photos and placeholders remain tied to actual records; concept product images were not assigned to unrelated products.
- Sign-in and registration use the handoff editorial layout and copy. The Authenticator setup choice is visible without an activation-only condition. Already-enrolled accounts still require their authenticator challenge after the password; staff email verification and enrolled TOTP remain in their original sequence. CAPTCHA and all real form fields remain.

## Handoff screen mapping

| Supplied reference | Working PHP screen |
| --- | --- |
| Homepage; Our approach | `/`; `/about` |
| Shop; product | `/shop` with all real filters/sorts; `/product?id=<actual ID>` |
| Bag; checkout | `/cart` and shared bag dialog; `/checkout` |
| Assessment; saved hair profile | `/assessment?edit=1`; `/assessment` |
| Routine | `/routine`; `/recommendations` preserves additional matching evidence and save controls |
| Profile; orders; settings | `/profile`; `/orders` and owned details; `/settings`, existing password/MFA subflows |
| Favorites; search | `/favorites`; `/search` and header suggestions |
| Care guide and four articles | `/care-guide`, `/care-guide/know-your-texture`, `/care-guide/wash-day`, `/care-guide/condition-with-care`, `/care-guide/a-gentler-finish` |
| Sign-in; sign-up | `/login`; `/register`, with real OTP, activation, authenticator and recovery flows |
| Privacy | `/privacy`, retaining the PHP application's accurate policy and server-processing disclosures |

This accounts for all 22 supplied static routes. No preview-only multiple-address book, browser-only account storage, mock product matching, fake prices, payment simulation or unsupported product claims replace the application. PH delivery/contact requirements and existing geographic normalization remain.

## Preservation and verification

**1,878 passing checks, 0 failures across 41 distinct regression suites.** The most recent result for each suite is listed in `UI_HANDOFF_CORRECTION_20261029.json`. This includes the new fragment authentication, staff RBAC, ownership, CSRF-form and private-cache checks, plus visible Authenticator/password/CAPTCHA controls. Existing MFA, lockout, activation, upload scanning, PII, Python NLP, matching precedence, concurrency, checkout, COD, Maya, receipts and email suites pass in the disposable test instance.

- All **274 PHP** and **21 JavaScript** source files pass syntax checks; Git whitespace check passes.
- **20/20** read-only HTTP/access checks pass against normal localhost. Protected pages still redirect guests to sign-in.
- **79/79** handoff files match their original manifest sizes and SHA-256 values. No expected package hashes were regenerated. Original extractions and the research ZIP were preserved.
- Models, services, core, middleware, configuration/database source files and the protected authentication/MFA/payment/assessment/recommendation controllers are byte-identical to the pre-UI integration backup. The live `.env` still matches the original copied configuration exactly.
- All **89 original route registrations** remain. The earlier five allowlisted care-note GET additions remain. Required form fields were moved into presentation components where appropriate, rather than removed.
- The existing staged integration entries, index bytes, HEAD and MERGE_HEAD are preserved. Current branch is `codex/integrate-main-development`; HEAD `2b7082f13d0cdd5f9c621bf50b2cfbecf0b20e3a`, feature tip `1158429b3fc72d9560bfff55ab69d48594f49914`. The merge and UI changes remain reviewable and uncommitted.
- Mutating tests used only the verified disposable `avela_pii_test_2026102801` schema on loopback port 13427, in a separate test application copy. The existing application database, other checkouts and private runtime configuration were not changed. No products were imported.
- Desktop (1440px and 1920px), tablet (820px) and mobile (390px) states were checked. Recorded mobile screen checks have one H1, no horizontal overflow and no broken images. Long-cart behavior, server-approved quantity/total changes, removal to empty state, Escape focus restoration, assessment progression/review, care filtering and routine artwork switching were observed in the browser.

## Review evidence and remaining limits

Snapshots, before-files, the correction diff, browser screenshots and private test logs are stored outside Git in `C:/Users/zandr/.codex/visualizations/2026/10/24/01a16d48-b2b8-7103-be6b-e66929e44897/ui-correction-20261028`. The current source manifest for the existing guarded restart shortcut is refreshed only after the preservation and regression checks pass. The original handoff manifest is untouched.

External CAPTCHA completion, live SMTP delivery, physical authenticator-device enrollment and actual Maya-provider checkout require their usual manual/provider verification; they were not bypassed or represented as live-provider tests. Existing real Maya Sandbox/COD behavior and readiness gates remain. No PayMongo, deployment, push, main merge, catalog import or database migration was performed. PRs #35, #36, #13 and #5 remain outside the integration milestone.

The earlier `UI_INTEGRATION_20261028.md` verification is historical. This report supersedes its visual-fidelity claims.

Temporary review cleanup completed: six test-only session bridges were removed; the isolated PHP server and disposable database were stopped gracefully, and the handoff preview server was stopped. Normal Avela at port 18080 remained healthy.

