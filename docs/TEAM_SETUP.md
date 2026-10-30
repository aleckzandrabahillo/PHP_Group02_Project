# Team setup

The complete current working snapshot is on `main` in the private repository:

```sh
git clone https://github.com/aleckzandrabahillo/PHP_Group02_Project.git Avela
cd Avela
composer install --no-plugins --no-scripts
```

Follow [Device setup and transfer](DEVICE_TRANSFER.md) for the required environment, database and private-key steps. A clone transfers source and approved assets, including all 213 catalog images; it does not transfer the running database or owner credentials.

Preserve existing checkouts and make changes on separate branches. Do not force-push or combine other pending PRs without reviewing compatibility.

The preceding local audit recorded 958 passing checks across 22 selected suites, with no failures after corrections. This is historical evidence for the source snapshot, not a fresh test run on your computer or proof of real provider operation. Database-writing tests require verified disposable synthetic targets. SMTP delivery, physical authenticator use, public HTTPS and real Maya webhook/reconciliation acceptance remain separate checks. See the focused security and payment documents.
