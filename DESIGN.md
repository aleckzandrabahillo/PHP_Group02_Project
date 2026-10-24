# Avela interface rules

Avela pairs expressive hair-care photography with a quiet consultation and shopping interface. ISSY's official storefront informed campaign scale and product discovery; rhode's informed whitespace, restrained type and consistent image presentation. No layouts, code, photography or branding are copied from either site.

## Tokens

| Role | Value |
| --- | --- |
| Paper | `#f7f5f0` |
| Secondary surface | `#e9e5de` |
| Ink | `#352f2a` |
| Secondary text | `#6d6359` |
| Action / espresso | `#51453d` |
| Controls | 6px radius, at least 44px touch target |
| Dialogs | 16px radius; viewport-bounded content |
| Spacing | 8px base; 16 / 24 / 32 / 48px groups |
| Text | Existing locally hosted Manrope; preserved Avela wordmark |

Use `refinement.css` tokens with the existing `customer.css` system. Keep one dominant headline, one clear primary action per task, equal outer gutters and larger space between sections than inside them. Avela's existing modestly rounded controls are an intentional brand exception to the taste skill's square/pill preference.

## Reusable patterns

- Storefront: approved editorial imagery, 4:5 product photographs, genuine product names/prices/availability. Keep empty image placeholders honest. No added photograph or product claim without approval.
- Checkout: one continuous right panel, square contained thumbnails. Only the product list scrolls after five measured rows; totals and Place order remain outside it. Amounts use integer cents on the server.
- Assessment: four manageable question groups followed by answer review, native controls and server validation. Answers stay in memory while navigating; no concern text in browser storage. A short progress label and bar replace jump links. Original texture diagrams are simplified guides, not diagnostic images.
- Results: selected answers first, inferred text signals in an expandable detail, existing tag-based product reasons beside the product. Never invent scores, ingredients or benefits.
- Profile: compact account navigation and a centered readable main column. Matching Edit/Add address buttons open native dialogs. Photo actions appear beneath the avatar; Change photo opens the native image picker. Staff edit only their own name/contact/photo, with existing-mailbox OTP for email changes. Password changes renew the initiating session and revoke other devices.
- Dialogs: native `dialog`, labelled title, close action, Escape, contained keyboard focus, restored opener and internal document scrolling. Policy text comes from the existing policy route, which remains shareable.
- Staff: familiar labelled navigation, compact filters and paginated tables, sentence-case headers and tabular amounts. Sidebar GET navigation progressively replaces only the content area; authorization remains server-side. Groups use + / − indicators and only the active group opens initially. Keep security, destructive-action confirmations and task density ahead of marketing styling.
- Account setup: use short customer-facing instructions, key-first actions and one task per step; preserve server deadlines, show remaining time, and replace expired controls with a clear restart. Keep technical implementation notes out of interface copy.
- Motion: existing finite shared effects for menus, dialogs, controls and status. No animation dependency or preference panel. Automatic Full motion follows the owner's explicit preference; there is no motion-preference control.

## Sources and maintenance

- Visual references: https://issycosmetics.com/ and https://www.rhodeskin.com/ (reviewed 2026-10-23).
- Interaction guidance: https://www.w3.org/WAI/tutorials/forms/multi-page/ and https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/.
- Philippine regions/provinces: PSA PSGC, 30 September 2026, https://psa.gov.ph/classification/psgc/provinces?vcode=82, CC BY 4.0. Keep NCR's metropolitan area distinct from provinces. Recheck PSA updates before changing this bundled list.
- The taste-skill direction is “The page is the object” (seed 2310): Avela's saved care plan becomes a useful consultation card. Account/staff surfaces use the “Achromatic ledger” direction with Avela's existing type and palette. Existing assets and working navigation take priority over unrelated drawn reference imagery or a replacement shell.

Validate desktop, tablet and phone rendering with actual fixture data, keyboard operation, reduced motion, long labels, empty states and server-side negative tests. Internal checkpoint records and screenshots belong outside Git.

Customer Shop, Favorites and Orders use continuous scrolling over bounded server batches, with an accessible Load more fallback. Favorites and Orders share 48px search/select controls and automatically apply search/status/sort changes. The single delivery Region selector lists Philippine provinces and Metro Manila; the server derives the geographic region and keeps historical address snapshots unchanged.

Selects progressively enhance native form controls into Avela listboxes with consistent 14px text, bounded scrolling, type-ahead, keyboard selection and native validation. Unsupported browsers keep native controls. Profile explanations use hover/focus tooltips rather than persistent paragraphs. Staff navigation opens one group at a time and always exposes a CSRF-protected Sign out action. Shipping shows its original area fee and a separate matching waiver while only the charged amount enters order/payment totals.

Saved hair assessment: one editorial consultation sheet pairs the approved lather photograph with recorded answers, selected concerns and one primary results action. Retake stays secondary and never overwrites answers on GET. Photo follows the useful summary on phones; no invented scores, diagnoses or product claims. Direction informed by Prose’s public consultation entry and Avela’s existing palette/type; no reference assets or code copied.

Recommendation results: keep the profile as a compact secondary column and lead with genuine routine product photographs, role, price and one recorded match reason. Alternatives retain individual reasons, availability, favorites and one explicit bag action. Mobile profile details collapse progressively, with a visible answer synopsis. Missing care steps stay visible without presenting unsupported products. Presentation draws on rhode’s public kit/routine hierarchy and Briogeo’s concern-led product discovery; assets, claims and matching logic remain Avela’s own.

Routine builder: care instructions lead, product matching follows as a distinct section. An ordered wash-day guide and between-wash guide use native expandable steps, labelled outline icons and a product link only where the saved/current plan contains that product. Scalp, texture, lengths and recorded concerns adapt instructions; no invented fixed schedule, dosage, ingredient or efficacy claim. General care sources: American Academy of Dermatology healthy-hair tips and Hair.com routine order; specific rinse-out/leave-in roles use reviewed catalog identities. Saved stale routines keep their warning and do not present a newly personalized guide as the original saved plan.


## Staff workspace

Administrator and Catalog Manager screens use the Framed sheet app direction (seed 1380695130), keeping Avela's existing wordmark, warm greige/espresso palette and locally hosted Manrope. A 228px labelled sidebar and 68px utility bar share the warm frame; the working sheet has 20px outer corners, 16px panels, 8px controls, 24px padding and a 16px content gap. The 68px utility bar intentionally accommodates accessible account/menu actions. Mobile navigation folds into the existing guarded drawer; the sheet gutters reduce to 16px.

Overviews answer the next operational task with real aggregates, one divided metric strip, proportionate charts and compact stock/activity queues. No vanity figures, fictitious campaigns, forecasts or replacement product assets. Zero and missing states remain honest. Tables use sentence-case headers, tabular right-aligned amounts, one status per relevant state, a fixed media slot and locally contained horizontal scrolling. Dense lists keep explicit filter submission; reporting-period selection progressively submits without an extra visible button. No action saves merely because a control looks selected.

Eva's numbered-pagination idea is independently adapted: validated server filters stay in escaped links; enhanced catalog lists keep stable Previous/Next nodes and restore numeric-button focus. Only the numeric range is rebuilt. Cursor-based lock history retains its existing older/newer navigation.

Details, status badges, form labels, dialogs and hover/focus/pressed/loading feedback share staff-scoped tokens. Longer policy/archive explanations are discoverable disclosures; warnings, stock race checks, CSRF fields, audit logic and destructive confirmations remain. Staff account/profile, password and authenticator screens inherit the same scope without changing Customer styling. Existing finite motion is reused; no animation dependency or preference control is added.

Reference: the owner's supplied ecommerce administration screenshots and https://dribbble.com/tags/ecommerce-admin-panel (24 October 2026), used for hierarchy and density only. No third-party art, branding, source or arbitrary sample metrics were copied. Desktop, tablet, phone, keyboard and regression evidence lives outside Git.


## Staff color and security terminology

The customer storefront is the palette authority. Staff colors reuse the shared `--avela-paper` (`#f7f5f0`), `--avela-panel` (`#e9e5de`), `--avela-ink` (`#352f2a`), `--avela-secondary` (`#6d6359`) and `--avela-action` (`#51453d`) tokens. Borders use the storefront's `#cfc7bc`; controls retain a sufficiently visible neutral border. Selected navigation and metrics use quiet greige surfaces rather than saturated cocoa or tan fills. Keep semantic status labels, existing rounded shapes and finite transitions. Staff working content and its utility bar share a 1560px maximum width, 24px desktop inset and 16px section rhythm; reporting controls align with the content instead of floating across a large empty row. Mobile gutters and the existing drawer remain responsive.

Orders is a direct Administrator navigation link, never an expandable single-choice group. Catalog Managers retain their existing catalog-only permissions. Use “2-step verification” for account settings, setup, sign-in titles and recovery navigation for every role. Keep “Google Authenticator” only as the app's proper name in setup instructions; internal routes, input names, audit action identifiers and verification controls remain compatible. Customer instructions accurately describe password plus optional phone code; staff instructions retain the required email step.

Direction: the owner's ecommerce administration references at https://dribbble.com/tags/ecommerce-admin-panel, adapted to Avela's existing components rather than copied. Readable text follows https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html (minimum 4.5:1 for normal text); use the focused contrast regression checks and actual desktop/phone rendering when changing these tokens.
