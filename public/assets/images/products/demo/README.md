# Approved Avela product image prototypes

These two user-supplied prototype assets were approved for Avela Checkpoint 4B.2 on 2026-10-14. They were copied without re-encoding or editing.

| SKU | Product | File | Dimensions | Bytes | MIME |
| --- | --- | --- | --- | ---: | --- |
| AVE-SMP-002 | Avela Everyday Fresh Shampoo | avela-ave-smp-002.webp | 1200 × 1500 | 30108 | image/webp |
| AVE-TOL-001 | Wide-Tooth Comb | avela-ave-tol-001.webp | 1200 × 1500 | 41124 | image/webp |

## Provenance and scope

Source: the repository owner's approved local prototype archive, supplied with explicit authorization to publish these assets in the Avela repository. Original creator, creation method, and any additional licensing terms were not supplied or independently verified; no third-party creator attribution or general reuse license is asserted here.

Both files are single-frame RGB WebP images. Validation included complete decoding, actual content-type checks, labels, and visual consistency with Avela's existing berry/rose wordmark and palette. The shampoo label reads Everyday Fresh Shampoo, 250 mL; the accessory label reads Wide-Tooth Comb. These are demonstration product illustrations, not evidence of a manufactured formulation or certification.

SHA-256:

- avela-ave-smp-002.webp: 41b58600e266d917eb8377fee4830fb4636c0d5857db75275808b6c50006aa9d
- avela-ave-tol-001.webp: d4a3594c91240d61e6e455f77f09eaf500fb39e16120f1709bf76a5b7fcb2cb8

## Integration boundary

Public URLs are `assets/images/products/demo/<filename>` relative to the application's public root. These assets do not assign themselves to products.

This checkpoint does not update product image_path values, the canonical twenty-product manifest, prices, stock, accounts, application logic, or CSS. Existing product placeholders continue until a separately approved image-assignment step.

The catalog importer intentionally expects NULL image paths and is not an image attachment tool. Later assignment must verify stable SKUs, actual files and expected current paths, preserve existing overrides, and use the normal guarded/audited database workflow.

Card/detail previews use the existing CSS in an external fixture, not modified product records. They do not certify Apache deployment, all-device appearance, or the eventual product-record integration.

## Additional imagery reviewed in Checkpoint 4B.7

The repository owner supplied an archive of eighteen AI-assisted fictional demonstration packshots. Contact-sheet and full-resolution label review accepted the thirteen files below under the checkpoint's publication criteria. Files were copied byte-for-byte, without editing, re-encoding, substitutions or generation. These are visual mockups, not photographs or verification of manufactured merchandise, formulations, ingredients or performance. The generator/individual creator and any additional third-party licensing terms were not independently supplied or verified; no general reuse license is asserted.

All thirteen are single-frame RGB WebP, 1200 x 1500. Actual format, complete decoding, file size, RIFF chunks, canonical filenames/product names and supplied checksums passed. Reviewed checksums are recorded in the separate database/demo_images.json. Canonical database/demo_catalog.json remains unchanged.

| SKU | File | Label review |
|---|---|---|
| AVE-SMP-001 | avela-ave-smp-001.webp | Gentle Hydrating Shampoo; no additional claim or quantity. |
| AVE-SMP-003 | avela-ave-smp-003.webp | Soft Curl Shampoo; 250 mL matches catalog. |
| AVE-SMP-004 | avela-ave-smp-004.webp | Light Wash Shampoo; 250 mL matches catalog. |
| AVE-CON-001 | avela-ave-con-001.webp | Repair Conditioner; no additional claim or quantity. |
| AVE-CON-002 | avela-ave-con-002.webp | Lightweight Conditioner; 250 mL matches catalog. |
| AVE-CON-003 | avela-ave-con-003.webp | Curl Comfort Conditioner; 250 mL matches catalog. |
| AVE-TRT-001 | avela-ave-trt-001.webp | Intensive Hair Mask; no additional claim or quantity. |
| AVE-TRT-002 | avela-ave-trt-002.webp | Soft Lengths Mask; 200 mL matches catalog. |
| AVE-TRT-003 | avela-ave-trt-003.webp | Wash-Day Detangling Cream; 200 mL matches catalog. |
| AVE-SER-001 | avela-ave-ser-001.webp | Smoothing Serum; no additional claim or quantity. |
| AVE-OIL-001 | avela-ave-oil-001.webp | Nourishing Hair Oil; no additional claim or quantity. |
| AVE-TOL-002 | avela-ave-tol-002.webp | Sectioning Clips Set; exactly four separate clips and 4 CLIPS label. |
| AVE-TOL-004 | avela-ave-tol-004.webp | Exactly three satin hair ties; branding only. |


The ivory/blush/berry palette, soft lighting and uncluttered packaging are consistent with the prototypes. Generated script wordmarks vary slightly, including upper/lowercase Avela; they are not exact reproductions of the original logo. No misspelled product name or visible impossible packaging was found in the accepted subset. Baseline catalog entries without specified quantity were not given invented quantities.

### Held files: do not publish or assign

Five supplied files failed label review and are deliberately absent from the assets and assignment manifest:

| SKU | Correction required |
|---|---|
| AVE-SER-002 | Remove invented oat milk/hibiscus ingredient text and unsupported hydrates/detangles/protects/healthier-looking claims. Keep the correct 150 mL quantity. |
| AVE-SER-003 | Remove unsupported added frizz/shine/healthier-looking performance text. Keep the correct 50 mL quantity. |
| AVE-OIL-002 | Remove unverified all-hair-types suitability and additional smooth/sleek claims. Keep the correct 50 mL quantity. |
| AVE-OIL-003 | BLOCKED: change 100 mL to canonical 60 mL, remove invented argan oil/rosehip oil/vitamin E and split-end prevention/other added benefit claims, and remove the unverified registered-trademark symbol. Correct or omit the accompanying fluid-ounce text and unsupported estimated-fill mark. |
| AVE-TOL-003 | Remove unsupported dry-hair-faster/reduce-frizz/kinder-to-hair claims. |

Corrected replacements require a new full visual/technical review and new checksum before inclusion. Supplying an archive or passing decoding does not approve label content. No held image was altered or replaced here.

### Integration and evidence boundary

The image-assignment manifest now has fifteen reviewed mappings: the original two unchanged prototypes plus thirteen additional assets. Five products intentionally retain no mapping until corrected imagery is approved. The focused regression harness derives image/audit/placeholder counts from this reviewed manifest, while keeping all exact target, authorization, rollback, concurrency and no-overwrite checks.

Synthetic disposable-database and desktop/mobile browser tests exercise the existing product cards/details without application or CSS changes. This source update does not deploy files to the personal runtime or write personal image_path values. Later local runtime synchronization and guarded, backed-up personal database assignment require their own explicit approvals. Never run the catalog importer to attach images.

Fresh verification: 154 PASS, 0 FAIL on a separately initialized disposable MariaDB database using real PDO, CLI, concurrent processes and HTTP. The rollback check injects a synthetic second-audit failure. Chromium browser checks at 1280 x 800 and 390 x 844 verified fifteen decoded card images, all fifteen matching detail images, five placeholders and no horizontal overflow. PHP syntax and Git whitespace checks passed. These checks do not certify Apache or physical-device behavior.
