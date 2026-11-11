# Campus2Career

Campus2Career is a PHP and MySQL career opportunity platform for students, graduates, employers and tertiary institutions in Ghana. This workspace is configured for the existing XAMPP installation at `E:\New folder` and the existing MySQL database `BIS`.

## Local setup

1. Start Apache and MySQL from the local XAMPP installation.
2. Keep the existing database name `BIS`. Do not create, drop, or reset a database for this application.
3. Copy `.env.example` to `.env` if `.env` does not already exist. Set the database host, port, user and the password configured for the local MySQL account. Keep `.env` private; it is excluded from version control and denied by Apache.
4. From `E:\New folder\htdocs\BIS`, apply the additive migrations:

   ```powershell
   & 'E:\New folder\php\php.exe' .\bin\migrate.php
   ```

   The migration runner creates only Campus2Career tables prefixed `c2c_`. It does not create a database or alter, rename, or delete existing BIS tables. It also seeds Palm University College and the initial opportunity categories. It is safe to run again.
5. Open [http://localhost/BIS/](http://localhost/BIS/). Apache must allow `.htaccess` overrides and have `mod_rewrite` enabled; the default XAMPP Apache setup used for this project has been smoke-tested at this URL.

The local `.env` must retain `DB_NAME=BIS` and `APP_BASE_PATH=/BIS`. The private document directory defaults to `E:\New folder\BIS-private-uploads`, outside the public web root. Set `C2C_PRIVATE_STORAGE_PATH` to another private writable directory if needed.

## First platform administrator

No administrator is created by the web registration form. Set `C2C_ADMIN_EMAIL`, `C2C_ADMIN_NAME`, and a unique password of 12–72 bytes in the private `.env`, then run:

```powershell
& 'E:\New folder\php\php.exe' .\bin\create-admin.php
```

The command refuses to create another administrator once one exists. Remove the three setup values from `.env` after the initial account is created. Sign in through the site and use the admin dashboard to verify organizations, review opportunities, manage institutions, resources, reports and announcements.

## User workflows

- Students and graduates register with an optional institution, complete their academic and career profile, browse and save opportunities, apply, upload private documents, and track status changes.
- Employers register with an organization profile. Listings remain drafts until submitted for review, then an administrator approves and publishes them. Organization owners can add representatives who already have active employer accounts.
- Institution administrators are assigned by a platform administrator to a registered student account. They can update the institution profile, publish opportunities and manage campus announcements.
- Only published, non-expired opportunities are shown to eligible users. Institution-only opportunities are restricted to students from that institution on both read and application routes.

## Email and scheduled notices

Email is disabled by default. To enable it, set `MAIL_ENABLED=1` and a valid `MAIL_FROM` address, then configure the PHP mail transport used by this XAMPP installation (for example, its configured sendmail or SMTP relay). Password-reset and verification email is not considered delivered unless PHP reports successful handoff. Local in-platform notifications work independently of email.

Run the deadline/expiration job once per day using Windows Task Scheduler:

```powershell
& 'E:\New folder\php\php.exe' 'E:\New folder\htdocs\BIS\bin\send-deadline-notices.php'
```

It expires past-deadline published listings and creates in-platform notices for saved opportunities due in three days. It does not send email.

## API

The read-only JSON API is available at `api/v1.php`:

- `?resource=categories`
- `?resource=stats`
- `?resource=opportunities&page=1&q=internship`
- `?resource=opportunity&id=1`
- `?resource=resources`

Cross-origin access is denied unless the exact allowed origins are listed, comma-separated, in `C2C_API_ORIGINS`. The API does not expose authenticated student or employer data and does not accept write requests. A future Vercel frontend should call this API over HTTPS from PHP-compatible hosting; the conventional PHP backend is not intended to run on Vercel.

## Security and operations

- Database access uses PDO prepared statements. Passwords use PHP password hashing; forms use CSRF tokens; authenticated sessions have idle and absolute timeouts.
- Uploaded PDF, DOC and DOCX files are size- and MIME-checked, randomly named and stored outside `htdocs`. Downloads require an ownership or organization/institution authorization check.
- Keep PHP error display disabled in production, use HTTPS, use a dedicated MySQL account with a non-empty password, configure mail transport, and restrict file-system access to the private upload directory before deploying.
- The default application upload limit is 5 MB per file. PHP must allow at least 8 MB per file and a `post_max_size` of at least 16 MB to accommodate multiple application documents.
- Migrations are additive and versioned under `database/migrations/`; they are not run from web requests. Review any future migration that could affect existing records before applying it.

## Verification performed

- PHP syntax checks passed for all application PHP files on the local PHP 8.5 runtime.
- The migrations were run against the existing `BIS` database. Existing tables remain present; Campus2Career data is isolated under `c2c_` table names.
- Local Apache smoke checks returned HTTP 200 for the homepage, opportunity listings, sign-in page and public categories API.
- Run `& 'E:\New folder\php\php.exe' .\bin\smoke-check.php` from the project directory to exercise student, employer, institution, admin, application-history and document-access queries. It creates temporary rows inside a transaction and rolls them back.

