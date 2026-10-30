# Team setup

The `codex/avela-team-updates-20261030` branch contains the integrated application, editorial UI, account/security updates, recommendations, Help policies, reviewed fictional catalog and product images. It preserves both reviewed parents: main `2b7082f13d0cdd5f9c621bf50b2cfbecf0b20e3a` and feature/main-development `1158429b3fc72d9560bfff55ab69d48594f49914`. PRs #35, #36, #13 and #5 are separate work; PayMongo is not included.

## Get an independent copy

Keep any existing checkout and its local changes. To review the complete update in a different folder:

```sh
git clone --branch codex/avela-team-updates-20261030 https://github.com/aleckzandrabahillo/PHP_Group02_Project.git Avela-Team
cd Avela-Team
composer install
```

Use the locked dependencies; do not run a dependency update as part of setup. PHP 8.2+, the extensions listed in [README](../README.md), MySQL/MariaDB and Python 3.10+ are required. Concern processing uses runtime Python, not a static preview matcher.

## Configure your own local services

Follow [Local setup](../README.md#local-setup). Copy `.env.example` to a private `.env`, set your own database host/port/name and localhost URL, configure your own reCAPTCHA keys and generate your own authenticator encryption key using [MFA](MFA.md). Development log-mail is supported; its OTPs and activation links remain private. Do not copy another member's environment, encryption keys, sessions or mail logs.

For a **new, verified empty database only**, use the installer as documented in README. Existing installations need the guarded migrations described there, with a backup and tested restoration first. Cloning or pulling source does not migrate an existing database or copy the owner's accounts, stock changes, orders or saved profiles.

From the project root, the standard local server is:

```sh
php -S localhost:8000 -t public router.php
```

Use the URL configured in your `.env` consistently; register that hostname for reCAPTCHA. XAMPP's MySQL service must be running. Starting XAMPP alone does not start the PHP development server on a separate port. Port 18080 is the owner's existing private local launcher, not a dependency for teammates.

## Use the current fictional catalog

The [current manifest](../database/avela_catalog_20261030.json), [suitability metadata](../database/avela_suitability_20261030.json) and 95 product images are included. They describe a fictional coursework catalog with approved demonstration reference prices and an initial 20 units per new product; these are not verified retail prices or physical inventory.

Read [Catalog refresh](CATALOG_REFRESH.md) before applying data changes. `php scripts/refresh_catalog.php` previews by default. Its output identifies the connected database, server port, data directory and source/catalog fingerprints. Review those values against **your intended local database**. Apply is a separate operator action requiring the full identity/fingerprint arguments and your own eligible staff actor; the permitted options are listed in the script. Never reuse another member's target values or run it against an unverified database. This publishing update does not run the importer on any teammate's installation.

The importer archives old dummy products, keeps order/favorite/cart/routine relationships and does not replenish previously imported products on repeat runs. Existing bag items may need owner removal and old saved routines may need review. Five missing product photographs retain placeholders, and 24 entries are intentionally excluded from matching pending evidence review.

## Acceptance and collaboration

The latest recommendations/Help/bag pass completed 573 automated checks across 14 selected suites, 22 PHP/JavaScript syntax checks, eight local public-page checks and six served-asset hash checks. Earlier security and integration evidence remains in the focused documents. Tests with database writes require their own verified disposable synthetic target. DOM simulations are not visual-browser or real provider tests.

Desktop/tablet/mobile visual acceptance remains pending because local browser access was unavailable during the latest pass. Genuine external mail delivery, authenticator-device operation, public HTTPS and provider webhook/reconciliation need separate configured acceptance evidence. Existing Maya Sandbox/COD behavior remains guarded; source sharing does not enable payment readiness or expose a local site publicly.

Make teammate changes on separate branches and review them against this branch. Do not force-push over another member's work or combine the excluded PRs without a new compatibility review.
