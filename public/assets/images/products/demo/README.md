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
