# Fictional catalog refresh

The owner approved the supplied Drive workbook as the new **fictional demonstration** catalog, using its reference prices as demo selling prices and an opening quantity of **20 units per product**. These are not verified live retail prices or physical stock.

- Source: `Avela_Fictional_Product_Catalog_with_images_updated.xlsx`, supplied in the owner's Drive folder. An unchanged workbook, file inventory, source hashes and review notes are retained outside the repository with the import evidence.
- Catalog: `database/avela_catalog_20261030.json`, 100 stable `AVE-26-0001`–`AVE-26-0100` identities. Do not replace identities when adding future products.
- Images: 95 supplied images; five Hairluxe products have no supplied image and use the existing placeholder. One doubled `.webp.webp` filename was matched explicitly. Thirteen PNGs and seven JPEGs were mislabeled `.webp`; installed extensions match their actual contents, with original bytes unchanged.
- Public descriptions are neutral fictional catalog copy. Borrowed real-world ingredient lists, original brand research and FDA/clinical implications are not presented as fictional formulas or regulatory approval.
- Suitability: 76 entries have reviewed cosmetic demonstration tags; 24 remain outside recommendations because evidence is absent, medical-boundary review is pending, candidate tags are unsupported, or positioning conflicts. No chemical-treatment compatibility is inferred. This review is not manufacturer verification or clinical evidence.
- Existing matching weights, required assessment answers, runtime Python processing, treatment exclusions and evidence/signature checks remain authoritative. Styling is an explicit finishing category.

`scripts/refresh_catalog.php` previews by default. Apply requires the exact host, port, database, datadir, source hash, current catalog fingerprint and an existing eligible staff actor. Categories/products are locked, and business writes and audit records share one transaction. A repeat run never replenishes stock or overwrites later staff edits.

Existing demonstration products are **archived**, not deleted. Historical order items, saved routines, favorites, carts, receipts, images and original stock remain intact. An old bag item becomes unavailable and must be removed by its owner before checkout. Saved routines may require review against the new catalog.

Before another refresh, back up the database, verify restoration in an isolated schema, inspect the dry run and test checkout/matching against synthetic data. Never reuse this version's opening-stock approval as authorization to replenish existing products. No schema migration is required for this refresh.
