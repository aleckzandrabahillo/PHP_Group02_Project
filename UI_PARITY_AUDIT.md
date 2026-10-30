> Current presentation and evidence: [29 October correction](UI_HANDOFF_CORRECTION_20261029.md). The functional feature map below remains applicable.

# Avela Site v8 presentation integration — 28 October 2026

## Baseline and scope

The functional baseline is the reviewed, uncommitted main/feature integration in `codex/integrate-main-development`: HEAD `2b7082f13d0cdd5f9c621bf50b2cfbecf0b20e3a`, MERGE_HEAD `1158429b3fc72d9560bfff55ab69d48594f49914`. Its existing staged index is preserved. UI work is an unstaged layer, plus new presentation assets and templates; no merge commit or push is performed.

Visual authority: owner-supplied `Avela_UI_Handoff_2026-10-28`, Site v8, source `60393812a3f085222feed8ffc4ee1390d986c7b3`. All 79 manifest-listed files passed original size/SHA-256 checks. The original handoff and research ZIP are read-only. The application remains native PHP/MySQL with vanilla JavaScript and runtime Python concern interpretation.

## Source-confirmed feature map

| Feature | Existing route / component | Role | Handler / data source | Required fields and actions | Presentation / preservation | Verification |
| --- | --- | --- | --- | --- | --- | --- |
| Home and discovery | `/`, `/about`, `/search`, `/search/suggest` | Public; role-aware actions | PublicController, Product, Favorite, Cart | Real product/category/concern IDs, permitted page search, genuine prices | Site v8 campaign, editorial photography, new inline wordmark; current SQL catalog and role-aware destinations | Public browser inspection; customer_journey; customer_followup |
| Catalog | `/shop` | Public | PublicController, Product | Category, concern, texture, scalp, routine step, stock, min/max price, query, four sort modes, bounded pagination | Same forms, names, escaped URLs, continuous loading and Load more fallback; restyled cards and filters | customer_journey; customer_followup; checkout_refinement |
| Product details | `/product?id=` | Public; customer purchase actions | PublicController, Product, Review | SKU, description, price, stock, suitability, description/reviews/recommendations tabs, quantity | All authoritative fields/tabs retained; new photographic presentation; honest missing-image fallback | customer_journey; browser product/tab checks |
| Favorites | `/favorites`, `/favorites/state`, POST `/favorites/toggle` | Customer | CustomerController, Favorite | CSRF, product ID; owned persistence, counts, search/sort, archive/stock handling | Existing header and product favorite controls; new shared tokens | customer_settings; customer_followup; security |
| Bag | `/cart`, POST `/cart/add`, `/cart/update`, `/cart/remove` | Customer | CustomerController, Cart | CSRF, product ID, positive quantity, current stock/category status | Full bag and compact native dialog follow the handoff; the private `/cart?panel=1` fragment uses the existing ownership guard and real cart. Existing quantity/POST removal contracts remain authoritative | checkout; customer_journey; new drawer browser checks |
| Checkout | GET/POST `/checkout`, POST `/checkout/shipping`, `/checkout/pricing` | Customer | CustomerController, CheckoutValidator, CheckoutService, ShippingService, DiscountService | Checkout key, CSRF, actual cart signature, contact/delivery, country/region, shipping method, coupon revision, supported payment choice | Simplified wordmark shell, light two-column form/sticky summary and mobile summary jump; every original field remains | checkout; checkout_refinement; discounts; account_experience |
| Maya and COD | `/payments/maya/return`, POST refresh/continue/webhook; order display | Customer / authenticated provider events | MayaController, MayaPaymentService, OrderPaymentStatus | Evidence-backed reconciliation, owned status checks, idempotency, stock reservations, transaction boundaries | Existing payment controls/messages preserved. No provider switch, card collection or fabricated success | maya_sandbox; maya_hosted_mode; order_delivery; order_payment_ui |
| Orders and receipts | `/orders`, `/orders/view`, POST `/orders/confirmation` | Customer, own records | CustomerController, Order, OrderReference, OrderReceipt, OrderNotification, OrderEmailQueue | Public reference, owned snapshots, timeline, delivery/payment distinction, email status and guarded resend | Current lists, continuous paging, details and receipt/email controls inherit new presentation | order_delivery; customer_settings; checkout_refinement |
| Registration and activation | `/register`, `/verify-otp`, POST `/otp/resend`, `/activate` | Guest / pending identity | AuthController, ActivationController; AuthService, OtpService, ActivationService | Full name, username, email, contact, both password fields, CAPTCHA; code first then separate single-use activation link | Wordmark-only shell and approved account image; original forms, limits, proof fields and errors retained | customer_auth_flow; email_activation; security |
| Sign-in and sessions | `/login`, POST `/logout`, `/account/session`, POST activity | All roles | AuthController, AuthService, AuthGuard, SecurityPolicy | CAPTCHA, password, session renewal/revocation, expiry, role-based redirect, persistent locks | Original authentication logic and session watcher; new form styling and mobile artwork | security; persistent_locks; password_security; customer_auth_flow |
| MFA and recovery | `/verify-authenticator`, GET/POST `/account/mfa`, `/account/mfa/recovery`, `/settings/security` | Own account / pending sign-in | MfaController, MfaService | Required staff email OTP; enrolled TOTP/recovery; passwords/proof, encrypted secret, deadlines, one-use recovery | All linked settings/challenge/setup/recovery pages remain; shared customer/staff scope maintained | mfa; mfa_setup; staff_ui; customer_settings |
| Password policy | GET/POST `/account/password` | Own authenticated account | PasswordController, PasswordService, SecurityPolicy | Current password, server-defined requirements, confirmation, expiry/grace, revoke other sessions | Existing policy/help and dynamic password feedback retained | password_security; password_confirmation; security |
| Customer overview and profile | `/customer`, `/profile`, POST update/image, `/profile/verify-otp` | Customer | CustomerController, User, CustomerProfileService, ProfileImageService | Own identity/photo, ordinary edits, current-mailbox OTP for email change | Account details lead; Hair profile, Routine, Orders, Settings and explicit CSRF sign-out remain; redundant sidebar Favorites removed because header retains it | account_experience; customer_settings; profile_uploads; audit_security |
| Saved address | POST `/profile/address`, shared address dialog | Customer, own record | AddressController, CustomerAddress, DeliveryValidator, PhilippineLocations | `country`, `region`, `location_version=2`, names, address, barangay, city, postal, phone, revision, CSRF | Country select remains PH-only. Region selects existing province/Metro Manila areas; normalization preserves geographic region and province in storage. Saved selections/revision and JSON errors retained | delivery_validation; account_experience; customer_followup |
| Assessment and saved hair profile | GET/POST `/assessment` | Customer | HairProfileController, HairProfile, HairProfileValidator, ConcernInterpreter | Texture, scalp, lengths, optional concerns/treatments, optional bounded concern text, profile version | Existing four groups plus review; saved-sheet/retake behavior, privacy notice, “Not sure”, memory-only progress; new shared design | hair_validation; hair_profiles; customer_journey; concerns; nlp_process |
| Explainable matching | `/recommendations` | Customer, own profile | RecommendationController, RecommendationService, ProductMatcher | Saved structured answers win, reviewed tags/evidence, unknowns, current eligibility, per-product reasons, plan fingerprint | Original explanation/alternative/routine product components remain; no static matcher or invented scores | product_matching; customer_journey; hair_profiles |
| Routine | GET/POST `/routine` | Customer, own record | RecommendationController, Routine, RoutineGuide | Plan fingerprint, current availability, stale saved-plan warning, selected product links | Existing saved/current routines and wash/between-wash guide preserved; home tabs are general editorial content only | routine_guide; customer_journey |
| Catalog management | `/catalog`, `/catalog/products`, `/catalog/categories`, `/catalog/inventory`, `/catalog/discounts` (GET/POST as registered) | Catalog Manager, Administrator | CatalogController, DiscountController; Product, Category, InventoryService, DiscountService | CRUD/archive/reactivation, real uploads, evidence tags, stock/version/revision tokens, pricing, dates, transaction/audit checks | Complete operational sidebar/forms/tables retained; paper/brand updates scoped so marketing layouts do not replace staff tasks | catalog_module; discounts; staff_pagination; staff_ui; audit_security |
| Administration | `/admin`, `/admin/users`, `/admin/staff`, `/admin/orders`, `/admin/orders/view`, `/admin/security`, auth/audit logs; corresponding POST actions | Administrator | AdminController, SecurityController; User, Order, SecurityPolicy, DashboardAnalytics, AuditService | Active/locked accounts, unlock, create staff/role, expected-state tokens, order fulfillment/cancellation and stock restoration, actual aggregates, policy, audit paging | Every module, role boundary, warning and destructive confirmation retained; new wordmark and paper | admin_module; audit_security; persistent_locks; create_staff; staff_ui |
| Staff own account | `/catalog/profile`, `/admin/profile`, POST `/account/profile`, `/account/profile/image`, verify-otp | Staff, own record | StaffProfileController, User, CustomerProfileService, upload services | Own profile/contact/photo, mailbox proof, CSRF and upload guards | Existing staff shell and guarded mobile navigation remain | account_experience; audit_security; profile_uploads |
| PII and uploads | All relevant profile/order writes and image endpoints | Service / authorized owner | PiiCipher, PiiStorage, PiiMigration; ImageUploadPolicy, UploadScanner | Context-bound encryption/keyring, no secrets in public paths, actual image decode, 2.5 MiB, private scanning/fail-closed | No service, key, scanner or schema changes for the UI; assets are approved presentation files | pii_encryption; pii_storage; profile_uploads; upload_scanning |
| Privacy/policies/errors | `/privacy`, `/terms`, `/refund-policy`, 403/404/405/413/419/422/500 | Appropriate audience | PublicController, existing policy/error views, middleware | Accurate PHP processing/storage/rights notices and review limits, honest validation/service failures | Actual privacy policy retained rather than static preview’s local-only policy; original error controls retained | privacy; security; checkout_refinement |
| New care notes | `/care-guide` and four allowlisted article routes | Public | PublicController::careGuide; local editorial templates | Real links, local text/category filtering, empty state, general care source links | Owner-supplied editorial content ported; account-storage wording corrected; no diagnosis or matching rule changes | Public route/asset/browser checks |

## Address capabilities and limits confirmed from source

The current application has **one structured saved delivery address per customer**, optimistic revision checks and an independently editable checkout recipient. It does not implement multiple/default/billing address books, a city/barangay lookup API, international delivery or a separate geographic-region/province cascade in the current visible form. These were not invented during this UI task. Both forms reuse `partials/region_field.php`, `PhilippineLocations` and the same server validators. Country is a native PH-only select; Region uses existing authoritative area choices and version-2 normalization. City, barangay and postal inputs keep their actual validation. Historical order address snapshots are unchanged.

## Isolation and evidence

Tests run from a separate application copy with generated/synthetic identities and a new disposable schema on port 13427. The normal website remains on 18080 with its original database on 13321. The test provider transport is confined to the separate loopback test router; it is not installed in the website. No real payment or outbound SMTP delivery is performed by those tests. Initial failing runs are retained alongside corrected results.

The final verification record and remaining practical limits are documented in `UI_INTEGRATION_20261028.md`. Browser verification does not establish exhaustive assistive-technology coverage, public deployment readiness, real mailbox delivery or live payment-provider availability.

## Complete registered route inventory

Every existing registration is retained; the five new registrations are public care-note GETs. Role/ownership and behavior checks remain in the handlers mapped above.

| Method / path | Actual registered handler |
| --- | --- |
| GET `/` | `[$public, 'landing']` |
| GET `/login` | `[$auth, 'loginForm']` |
| POST `/login` | `[$auth, 'login']` |
| GET `/shop` | `[$public, 'shop']` |
| GET `/about` | `[$public, 'about']` |
| GET `/care-guide` | `fn() => $public->careGuide()` |
| GET `/care-guide/know-your-texture` | `fn() => $public->careGuide('know-your-texture')` |
| GET `/care-guide/wash-day` | `fn() => $public->careGuide('wash-day')` |
| GET `/care-guide/condition-with-care` | `fn() => $public->careGuide('condition-with-care')` |
| GET `/care-guide/a-gentler-finish` | `fn() => $public->careGuide('a-gentler-finish')` |
| GET `/privacy` | `[$public, 'privacy']` |
| GET `/refund-policy` | `fn()=>$public->policy('refund')` |
| GET `/terms` | `fn()=>$public->policy('terms')` |
| GET `/search` | `[$public, 'search']` |
| GET `/search/suggest` | `[$public, 'searchSuggestions']` |
| GET `/product` | `[$public, 'product']` |
| GET `/register` | `[$auth, 'registerForm']` |
| POST `/register` | `[$auth, 'register']` |
| GET `/activate` | `[$activation, 'page']` |
| POST `/activate` | `[$activation, 'activate']` |
| GET `/verify-otp` | `[$auth, 'otpForm']` |
| POST `/verify-otp` | `[$auth, 'verifyOtp']` |
| POST `/otp/resend` | `[$auth, 'resendOtp']` |
| POST `/logout` | `[$auth, 'logout']` |
| GET `/account/session` | `fn()=>\App\Middleware\AuthGuard::sessionStatus()` |
| POST `/account/session/activity` | `fn()=>\App\Middleware\AuthGuard::sessionStatus(true)` |
| GET `/verify-authenticator` | `[$mfa, 'verifyForm']` |
| POST `/verify-authenticator` | `[$mfa, 'verify']` |
| GET `/account/mfa` | `[$mfa, 'settings']` |
| GET `/account/password` | `[$passwords, 'page']` |
| POST `/account/password` | `[$passwords, 'change']` |
| POST `/account/mfa` | `[$mfa, 'manage']` |
| GET `/account/mfa/recovery` | `[$mfa, 'recovery']` |
| GET `/customer` | `[$customer, 'dashboard']` |
| GET `/assessment` | `[$hairProfile, 'assessment']` |
| POST `/assessment` | `[$hairProfile, 'save']` |
| GET `/routine` | `[$customer, 'routine']` |
| GET `/recommendations` | `[$recommendations, 'matches']` |
| POST `/routine` | `[$recommendations, 'save']` |
| GET `/cart` | `[$customer, 'cart']` |
| GET `/checkout` | `[$customer, 'checkout']` |
| POST `/checkout` | `[$customer, 'placeOrder']` |
| POST `/checkout/pricing` | `[$customer, 'pricing']` |
| GET `/catalog/discounts` | `[$discounts, 'page']` |
| POST `/catalog/discounts` | `[$discounts, 'manage']` |
| POST `/checkout/shipping` | `[$customer, 'shipping']` |
| GET `/payments/maya/return` | `[$maya, 'returned']` |
| POST `/payments/maya/refresh` | `[$maya, 'refresh']` |
| POST `/payments/maya/continue` | `[$maya, 'continuePayment']` |
| POST `/payments/maya/webhook` | `[$maya, 'webhook']` |
| POST `/cart/add` | `[$customer, 'addToCart']` |
| POST `/cart/update` | `[$customer, 'updateCart']` |
| POST `/cart/remove` | `[$customer, 'removeFromCart']` |
| GET `/orders` | `[$customer, 'orders']` |
| POST `/orders/confirmation` | `[$customer, 'sendConfirmation']` |
| GET `/orders/view` | `[$customer, 'order']` |
| GET `/settings` | `fn() => $customer->page('settings','Account settings')` |
| GET `/settings/security` | `[$mfa, 'customerSettings']` |
| GET `/profile` | `fn() => $customer->page('profile','Profile')` |
| POST `/profile/image` | `[$customer, 'updateProfileImage']` |
| POST `/profile/address` | `fn() => (new \App\Controllers\AddressController())->save()` |
| POST `/profile/update` | `[$customer, 'updateProfile']` |
| GET `/profile/verify-otp` | `[$customer, 'profileOtpForm']` |
| POST `/profile/verify-otp` | `[$customer, 'verifyProfileOtp']` |
| GET `/favorites` | `[$customer, 'favorites']` |
| GET `/favorites/state` | `[$customer, 'favoriteState']` |
| POST `/favorites/toggle` | `[$customer, 'toggleFavorite']` |
| GET `/catalog` | `[$catalog, 'dashboard']` |
| GET `/catalog/products` | `[$catalog, 'products']` |
| POST `/catalog/products` | `[$catalog, 'manageProduct']` |
| GET `/catalog/categories` | `[$catalog, 'categories']` |
| POST `/catalog/categories` | `[$catalog, 'manageCategory']` |
| GET `/catalog/inventory` | `[$catalog, 'inventory']` |
| POST `/catalog/inventory` | `[$catalog, 'updateStock']` |
| POST `/account/profile` | `fn() => (new \App\Controllers\StaffProfileController())->update()` |
| POST `/account/profile/image` | `fn() => (new \App\Controllers\StaffProfileController())->image()` |
| GET `/account/profile/verify-otp` | `fn() => (new \App\Controllers\StaffProfileController())->verify()` |
| POST `/account/profile/verify-otp` | `fn() => (new \App\Controllers\StaffProfileController())->verify()` |
| GET `/catalog/profile` | `fn() => $catalog->page('profile','Profile')` |
| GET `/admin` | `[$admin, 'dashboard']` |
| GET `/admin/users` | `[$admin, 'customers']` |
| POST `/admin/users/status` | `fn() => $admin->accountChange('status')` |
| POST `/admin/users/unlock` | `[$admin, 'accountUnlock']` |
| GET `/admin/staff` | `[$admin, 'staff']` |
| POST `/admin/staff/create` | `[$admin, 'staffCreate']` |
| POST `/admin/staff/role` | `fn() => $admin->accountChange('role')` |
| GET `/admin/orders` | `[$admin, 'orders']` |
| GET `/admin/orders/view` | `[$admin, 'orderView']` |
| POST `/admin/orders/status` | `[$admin, 'orderStatus']` |
| GET `/admin/security` | `[$security, 'page']` |
| POST `/admin/security` | `[$security, 'save']` |
| GET `/admin/auth-logs` | `fn() => $admin->logs('auth')` |
| GET `/admin/audit-logs` | `fn() => $admin->logs('audit')` |
| GET `/admin/profile` | `fn() => $admin->page('profile','Profile')` |
