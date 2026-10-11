# Jersey Musium

E-commerce platform for football jerseys. PHP 8 + MySQL, Razorpay & Cashfree payments, a full admin console, and an interactive "kick-off" intro animation.

**Documentation**
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): Part A explains the application in plain language (for owners and staff, including a daily-operations guide); Part B is the full technical reference (design, database, payments, GST invoicing, security, deployment).
- [docs/DEPLOY_HOSTINGER.md](docs/DEPLOY_HOSTINGER.md): step-by-step deployment on Hostinger shared hosting (Git, database, secrets, cron, webhooks, launch checklist).
- [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md): security and quality audit, findings, test evidence and go-live checklist.

## Quick start (local)

Requirements: PHP 8.2+ (`pdo_mysql`, `curl`, `mbstring`, `gd`, `fileinfo`), MySQL 8 / MariaDB 10.6+.

```bash
cp .env.example .env                 # set DB_* and APP_URL=http://localhost:8000, APP_DEBUG=true, SESSION_SECURE=false
composer dump-autoload               # optional
php bin/console migrate
php bin/console seed --demo=1        # roles, categories, policy pages, coupons + 24 demo jerseys & banners
php bin/console admin:create         # your super-admin login
php -S localhost:8000 -t public public/index.php
```

* Store: http://localhost:8000
* Admin: http://localhost:8000/admin
* Demo coupons: `KICKOFF10`, `FIRSTJERSEY`
* New customers verify their email with a 6-digit code before ordering; admin sign-in uses password + a 6-digit code emailed to the admin (with `MAIL_DRIVER=log` the code is written to `storage/logs/mail-*.log`).

Leave the payment keys empty and only Cash on Delivery is offered. Add Razorpay or Cashfree **test** keys to `.env` to test online payments.

## Tests

```bash
php bin/console lint                                   # syntax of every PHP file
vendor/bin/phpunit                                     # 95 unit + integration tests (separate DB jersey_musium_test)
php tests/smoke.php http://localhost:8000 <admin-email> <admin-password>   # 104 end-to-end + security checks (server must run with MAIL_DRIVER=log)
cd tests/browser && npm install && node razorpay-checkout.js               # real Razorpay test-mode payment
php bin/console security:check                         # production configuration audit
composer audit                                         # dependency CVEs
```

## Cron (production)

```
*/5 * * * *  php /path/to/bin/console orders:expire
15 3 * * *   php /path/to/bin/console cleanup
```

## Before going live

Read the **Go-live checklist** in [docs/ARCHITECTURE.md §14](docs/ARCHITECTURE.md#14-deployment-hostinger-or-any-apachelitespeed-host), especially the note on **image rights** for the intro photo (§12).
