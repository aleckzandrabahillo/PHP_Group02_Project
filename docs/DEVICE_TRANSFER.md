# Device setup and private transfer

## What this repository includes

Current PHP application, editorial customer/staff UI, JavaScript/CSS/fonts, tests, schema/migration tools, runtime Python NLP and approved catalog manifests/images. MySQL remains authoritative for accounts, stock, orders, assessment results, matching evidence and saved data. No PayMongo integration is introduced.

## Required software

Install a maintained PHP 8.2+ release, Composer, MySQL/MariaDB with InnoDB and Python 3.10+. On Windows XAMPP may provide PHP/MySQL, but verify their supported versions before deployment. Enable PHP `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `openssl` and `curl`; permit `proc_open` for the standard-library Python interpreter. Use `composer install --no-plugins --no-scripts` with the included lockfile; do not update dependencies during transfer.

## Continue the owner's exact local data

1. Clone this repository on the other device. Preserve any existing installation separately.
2. Transfer the **encrypted private backup** outside the checkout and public root. Transfer its **key file separately** through a trusted private channel. Neither belongs in GitHub, chat, screenshots or a shared ZIP. The archive contains the database, private `.env`, original PII keyring, uploads and locked vendor files. Treat all extracted contents as confidential.
3. Use the separately supplied `unlock-transfer.py` helper locally (Python plus `cryptography` required) to authenticate and extract the archive into a new private folder. It refuses existing destinations and verifies each archived file hash. This authenticates the package; it is not a database restore test.
4. Create a **new empty database**, using a dedicated local account with the needed schema/data privileges. Verify its host, port, name and empty table list. Import extracted `database.sql` into that database using a trusted local database client. Do not import over an existing database and do not run `scripts/install.php` after restoring this dump. The dump contains the existing 35-table schema and data; it deliberately does not create database users, grants or server configuration.
5. Copy extracted `project/.env` into the clone privately, plus `project/public/uploads` into its corresponding directory. Keep the extracted PII keyring outside the checkout/public root with owner/service-only access. Retain **all original PII and MFA keys** for the copied database; generating replacements will break decryption and existing authenticator access. Composer installation is preferred; the private vendor copy is an optional offline copy of the existing locked dependencies.
6. Edit only the new device's private `.env`: set its actual database host/port/name/user/password; `APP_URL=http://localhost:18080`; the new absolute `NLP_PYTHON_BINARY`; and the new `PII_KEYRING_FILE` path. Preserve the MFA key and enabled PII setting. Update the real Defender executable path and verify scanning under the new service account. Keep SMTP/CAPTCHA credentials private and verify that localhost is an allowed CAPTCHA hostname.
7. Keep all Maya local experiment/simulation/readiness flags **false** until their documented guards are re-established and accepted on the new machine. The archive's `old-machine-reference` files are reference material containing machine-specific paths and database identity checks; do not run them unchanged or loosen their guards. Standard localhost COD works independently of Maya. See [Maya Sandbox](MAYA_SANDBOX.md) before enabling payment tests.
8. Start MySQL, then run from the clone:

```sh
php -S localhost:18080 -t public router.php
```

Use the actual PHP executable path if it is not on PATH (for example `C:\xampp\php\php.exe`). Starting XAMPP alone does not start this separate PHP server. Restart this command after reboot. This binds to localhost and does not publish the application.

9. Open `http://localhost:18080/`. Check the 213-product catalog/images, normal sign-in/activation/CAPTCHA, existing authenticator access, profile/address decryption, recommendations/Python, cart and existing orders. Run mutations only with synthetic data in a verified disposable environment. Do not disable security to pass setup checks. Keep the original device intact until the restored copy is verified.

The private dump was exported consistently from InnoDB using `--single-transaction` without changing existing rows. The archive's contents and authenticated decryption were verified on the source device. A database restoration and actual external-service acceptance on the destination are still required.

## Fresh teammate setup without owner records

Copy `.env.example` to `.env` and configure a new empty local database and independent credentials. Follow [README local setup](../README.md#local-setup), then create staff securely. Configure private MFA and PII keys before their associated writes. Review each guarded migration required by enabled features, including multiple delivery addresses. Do not assume the base installer includes every additive migration.

For the current catalog, follow [initial catalog refresh](CATALOG_REFRESH.md) then [catalog expansion](CATALOG_EXPANSION.md), with each script's preview and exact target/fingerprint guards. Do not rerun initial refresh after expansion. Catalog data changes require a valid staff actor; preserve existing prices, stock and order relationships. The installer alone does not create the current 213-product catalog.

## Deployment is separate

This source handoff does not enable hosting, HTTPS, email scheduling or payment webhooks. Keep development flags and private logs out of public hosting. Verify maintained runtimes, TLS, all staff MFA enrollment, SMTP, real upload scanning, backup restoration and Maya readiness before public acceptance. Never serve the repository root; serve only `public/`.
