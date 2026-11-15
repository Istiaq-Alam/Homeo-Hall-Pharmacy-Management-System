# Installation and Deployment

## 1. Prerequisites

- PHP 8.3+ (this project runs on PHP 8.4) with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, and `fileinfo`.
- Composer 2.
- Node.js 20+ and npm.
- MySQL 8.x.
- A web server that can route requests to `public/index.php` (Nginx/Apache) in production.

`ext-gd` is not required. The project deliberately avoids PhpSpreadsheet because it currently requires GD.

## 2. Get the code and dependencies

```bash
cd hpms
composer install
npm install
```

For a reproducible production install, use `composer install --no-dev --optimize-autoloader` and `npm ci`.

## 3. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with real values. Do not commit it.

```dotenv
APP_NAME="Homeo-Hall"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Dhaka

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homeo_hall
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

OWNER_NAME="Chamber Owner"
OWNER_EMAIL=owner@example.com
OWNER_PASSWORD="use-a-long-unique-password"
```

For production change `APP_ENV=production` and `APP_DEBUG=false`, use HTTPS, and set:

```dotenv
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

Keep `SESSION_HTTP_ONLY=true` and `SESSION_SAME_SITE=lax`.

## 4. Create the MySQL database

```sql
CREATE DATABASE homeo_hall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'homeo_hall'@'localhost' IDENTIFIED BY 'replace-this-password';
GRANT ALL PRIVILEGES ON homeo_hall.* TO 'homeo_hall'@'localhost';
FLUSH PRIVILEGES;
```

Use a database name without a hyphen. If you must use one, quote it consistently in SQL and configuration.

## 5. Migrate and seed

```bash
php artisan migrate
php artisan db:seed
```

The seeder:

- Creates the seven default medicine categories.
- Creates the nine default expense categories.
- Creates default settings for Homeo-Hall, BDT, ৳, Asia/Dhaka, low-stock level 5, and report period 30 days.
- Creates the owner only when both `OWNER_EMAIL` and `OWNER_PASSWORD` are set.
- Throws an explicit error when exactly one owner variable is present.
- Does not create sample transactions.

After provisioning, remove `OWNER_PASSWORD` from the production environment if it is no longer needed; the owner can change the password in Settings. Do not run the seeder again with different owner credentials expecting a second account; it creates one matching account if absent.

## 6. Build frontend assets

Development:

```bash
npm run dev
```

Production:

```bash
npm run build
```

The build includes the self-hosted Inter fallback font. Do not deploy source-only assets without a build.

## 7. Run locally

```bash
php artisan serve
```

Open `http://127.0.0.1:8000`. The root redirects to the login page. Log in with the provisioned owner credentials. The dashboard is protected.

Useful commands:

```bash
php artisan route:list
php artisan optimize:clear
php artisan test --compact
```

## 8. Production checklist

1. Provision a MySQL 8 database with persistent storage and backups.
2. Set all environment values; never reuse `.env.example` passwords.
3. Generate `APP_KEY` on the server and keep it secret and stable.
4. Run `composer install --no-dev --optimize-autoloader`.
5. Run `npm ci && npm run build`.
6. Run `php artisan migrate --force`.
7. Run the seeder once to provision the owner.
8. Point the document root to `public/`.
9. Ensure `storage/` and `bootstrap/cache/` are writable by the web user.
10. Configure HTTPS and secure cookies.
11. Add a process that runs `php artisan queue:work` only if a queue-dependent feature is added later; the current core app records synchronously.
12. Verify `/health` returns HTTP 200 with both statuses `ok`.
13. Verify `/login` loads and a wrong password is throttled after repeated attempts.
14. Confirm database and file backups are scheduled and test a restore.

### Example Nginx location

```nginx
root /srv/homeo-hall/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
```

Use TLS and deny access to `.env`, `storage`, `database`, and VCS files.

## 9. Health check

`GET /health` returns:

```json
{"status":"ok","database":"ok"}
```

If the database cannot be reached it returns HTTP 503 with `unavailable` values. It exposes no credentials or business data.

## 10. Upgrades

```bash
php artisan down
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize:clear
php artisan up
```

Back up the database before migrations. Do not roll back business migrations casually; preserve transaction history.

## 11. Backup and restore

Full backup:

```bash
mysqldump --single-transaction --routines --triggers \
  -u your_user -p homeo_hall > homeo-hall-$(date +%F-%H%M).sql
```

Restore:

```bash
mysql -u your_user -p homeo_hall < homeo-hall-YYYY-MM-DD-HHMM.sql
php artisan optimize:clear
```

The Settings data export is a CSV ZIP for inspection/migration. It omits credentials and sessions and is not a complete restore backup.

## 12. Known installation limitations

- XLSX uses the built-in single-sheet writer rather than Laravel Excel/PhpSpreadsheet, due to the missing `ext-gd` requirement.
- Free hosting may use ephemeral filesystems. MySQL data, uploaded exports, and the database dump must be stored on persistent storage or externally.
- The app has one owner and no registration; provisioning is intentionally an operator action.
- Node and Composer lock files should be committed for reproducible builds.
