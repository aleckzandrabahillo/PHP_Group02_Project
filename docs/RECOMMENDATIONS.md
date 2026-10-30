# Product matches and My Routine

Customers use /assessment → /recommendations → /routine → the existing bag and Checkout. All profile/routine access is owner-bound; mutations require current customer eligibility and CSRF. Product records and purchasing constraints remain authoritative.

This covers proposal matching, explainable reasons and saved routine scope (HP04–HP08), ownership (RB02), validated secure POST handling (SE02, SE04) and transactional auditing (LG02).

## Suitability evidence

The current catalog is fictional. database/suitability_evidence.json records each approved demonstration SKU's source reference, review date, reviewed tags and evidence type. A checksum binds it to the unchanged canonical catalog manifest. Live identity, purpose and description must still match the reviewed specification. Only currently assigned tags that were reviewed are used; extra marketing words or unreviewed tags earn no points. A changed specification requires review. These records verify demonstration design metadata, not real formulations, ingredient performance or clinical effectiveness.

No chemical-treatment compatibility is verified for these formulations. For colored, bleached, permed or relaxed hair, cleansing/treatment candidates without explicit supporting metadata are left out. Structured history and recognized optional-text history are considered. Missing support produces a partial/empty routine rather than invented benefits.

## Deterministic matching

- Active products and active categories, reviewed metadata and a valid category/routine role are required.
- A known texture must fit the product's listed textures. Cleansers must fit a known scalp type.
- Each shared concern earns 4 points; cleansing/scalp compatibility earns 4; texture compatibility earns 2. Cleansers use oiliness concerns, while length-care products use their relevant concern tags.
- The weights are transparent project choices: concerns and cleansing/scalp needs take precedence over a general texture match. They are not scientifically calibrated efficacy scores or success probabilities.
- Equal scores sort by stable SKU, then product ID. Stock and prices do not add compatibility points.
- Unavailable compatible products remain visible; only currently available products enter a purchasable routine.
- The routine chooses one cleanser and conditioner where supported, at most one optional treatment/finish, and a detangling tool only for a relevant concern. It does not fill every category or add redundant products.

Reasons include canonical codes and their actual point contributions. Review details expose the source and date. Stock is checked again by the existing cart/Checkout paths.

## Additive migration and saved state

Before deploying this source to an existing database, back up and restore-test it, then review scripts/migrate_routines.php. Dry-run is the default. Supply all exact host, port, database, hostname, server-ID and data-directory guards; append --apply only with database-owner authorization.

The script creates only routine_contexts and validates an existing definition. Repeated execution adds nothing. MySQL DDL can commit implicitly. It never rewrites existing profiles, products, orders, routines or items; do not rerun the installer or drop the context table to undo a deployment.

Dry-run command: php scripts/migrate_routines.php --expect-host=HOST --expect-port=PORT --expect-database=DATABASE --expect-server=HOSTNAME --expect-server-id=SERVER_ID --expect-datadir=DATA_DIRECTORY. Use the exact inspected target values, not guessed examples.

Existing routines/routine_items remain in use. The context table stores the profile fingerprint, rule version, plan fingerprint and explanation snapshots. Save operations lock current eligibility/profile/catalog records and commit routine/items/context/audit together. Stale forms are rejected; repeated/concurrent saves of the same plan return the same routine. Changed profiles or suitability metadata mark saved routines stale. Current price/availability is shown, and stale routines require review before adding through the recommendation interface. Legacy routines remain readable and are marked for review.

## Verification

Run tests/product_matching.php for pure matching rules. tests/matching_integration.php requires explicit disposable database, port, data-directory, loopback HTTP, external session and scratch-directory guards. It covers eligibility, ownership, CSRF, evidence/tag changes, stock separation, stale forms, rollback, repeated/concurrent saves and routine-to-cart/Checkout. Run the existing hair assessment and Checkout suites as regressions. Synthetic HTTP sessions, actual browser interactions and real provider logins are separate evidence.


## Current profile-derived routine

`/routine` now presents **My Routine**, derived on read by the same RecommendationService, ProductMatcher and RoutineGuide used for current recommendations. Completing an assessment is sufficient: there is no extra Save Routine button. Current catalog availability, suitability evidence and selected-answer precedence still apply. This read does not create or overwrite historical `routines`/`routine_items`/context records. The legacy transactional save API remains compatible for existing integrations and historical data; its fingerprint and authorization checks are retained.
