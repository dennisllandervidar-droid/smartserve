# SmartServe

PHP and MySQL web application with administrator, office, student, and kiosk interfaces.

## Run locally

1. Use an Apache/PHP installation such as XAMPP with MySQL or MariaDB. Enable Apache rewrite support and PHP extensions used by the app, including mysqli, curl, and mbstring.
2. Place this project in your Apache document root, for example `C:\xampp\htdocs\smartserve`.
3. Create a database named `smartserve` in phpMyAdmin and import `database/schema.sql`.
4. Copy `global/database.local.example.php` to `global/database.local.php` and enter your own database settings. You can instead set `SMARTSERVE_DB_HOST`, `SMARTSERVE_DB_USER`, `SMARTSERVE_DB_PASSWORD`, and `SMARTSERVE_DB_NAME` in the server environment.
5. For email or SMS, copy the respective `global/email.local.example.php` or `global/sms.local.example.php` to the corresponding `.local.php` filename and configure your own service credentials. See `docs/email-notifications.md`.
6. Open `http://localhost/smartserve/`.

The schema contains no accounts, office/service settings, site settings, bookings, or other live rows. A complete working instance requires those settings and accounts to be populated, or a private backup to be restored locally by its owner. No administrator password is supplied here.

## Included and excluded files

Application source, existing SQL migrations, bundled dependencies, and static design assets are included. `database/schema.sql` contains the structure from the separate database export.

Live database rows, saved database/email/SMS credentials, uploaded profile photos, announcement uploads, and submitted identity documents are excluded. The original ZIP and full SQL backup are separate local originals and were not modified. Local settings and future uploads are ignored by Git.

The database connection now reads local configuration or environment variables instead of the saved production credentials. Existing application behavior is otherwise retained; this repository preparation is not a full application audit.

## Update GitHub

After editing your local checkout, review the changes and use Git:

```text
git add .
git commit -m "Describe your changes"
git push
```

Check that secrets and private data are absent before each commit. Upload source files as an extracted project so GitHub can display and track individual files.

## Validation

Existing checks are documented in `tests/README.md`. Use only a disposable local database when running integration tests. Preparing this repository does not deploy or change a live website.
