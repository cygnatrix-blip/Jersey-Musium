# Deploying Jersey Musium on Hostinger Shared Hosting

> **Who this is for:** the store owner or whoever manages the Hostinger account. No programming needed, just hPanel and (ideally) SSH.
> **Works on:** Hostinger *Premium* and *Business* shared plans (SSH + Git + Cron available). On *Single*, see the note in step 5.
> **Time:** about 30–45 minutes the first time.

The code lives on GitHub (`https://github.com/cygnatrix-blip/Jersey-Musium`). Hostinger pulls it straight into your site, and future updates are a single click.

```
/home/u123456789/domains/yourdomain.com/
├── jersey-musium.env      ← your secrets (passwords, keys). OUTSIDE the website folder.
└── public_html/           ← the project from GitHub
    ├── .htaccess          ← lets the web reach ONLY /public, blocks everything else
    ├── public/            ← the actual website (index.php, images, CSS, JS)
    ├── app/ config/ …     ← code (blocked from the web)
    └── storage/           ← logs (blocked from the web)
```

---

## 1. Prepare the hosting (hPanel)

1. **Domain & SSL:** hPanel → *Websites* → your domain → *Security → SSL* → install the free SSL. Wait until it shows **Active**.
2. **PHP version:** *Advanced → PHP Configuration* → choose **PHP 8.3** (8.2 minimum).
   On the *PHP extensions* tab make sure these are ticked: `pdo_mysql`, `mysqli`, `curl`, `mbstring`, `gd`, `fileinfo`, `openssl`, `dom`, `opcache`. They usually are by default.
3. **Database:** *Databases → Management* → create a database and user.
   Note the three values, e.g. database `u123456789_jersey`, user `u123456789_jm`, and a strong password (use the generator).
4. **Empty the website folder:** *Files → File Manager* → open the website folder → delete the default `default.php` / `index.html`. Both the ZIP and the Git method need an empty folder the first time.

## 2. Put the code on the server: ZIP upload (manual) **or** Git

Choose **one** method and stick with it.

### Option 1: ZIP upload (manual, no Git)

**Make the ZIP** (on the developer's computer, in the project folder):

```bash
php bin/console package        # → dist/jersey-musium-<date>-<commit>.zip
```

The ZIP contains only what the server needs: app, website files, `database/install/install.sql`, and the protective `.htaccess` files. It never contains your secrets file, tests, docs, logs or uploaded images. Paths inside it are Linux-safe.

> Don't build the ZIP with Windows *Send to → Compressed folder*: it can skip the `.htaccess` files and store `\` paths that break on Linux. Use `php bin/console package`.

**First install:**
1. **Turn off Git auto-deployment** if you set it up before (hPanel → *Advanced → Git* → delete the repository / auto-deploy), otherwise it may overwrite your files later.
2. *File Manager* → open the website folder:
   * main domain → `domains/example.com/public_html/`
   * subdomain like **jerseymusium.cygnatrix.com** → `domains/cygnatrix.com/public_html/jerseymusium/`
3. Delete everything inside it (it's only old code; your database is separate).
4. **Upload** the ZIP into that folder → right-click it → **Extract** → extract **into the same folder**, not into a new sub-folder. Then delete the ZIP.
5. Check: the folder now shows `app`, `bootstrap`, `public`, `storage`… and a `.htaccess` file.
   * If `.htaccess` isn't visible, enable *Settings → Show hidden files*.
   * If you only see one folder named like the ZIP, the files went one level too deep: move its contents up.

**Updating later with a new ZIP:**
1. Upload the new ZIP into the same folder → **Extract** → choose **overwrite / replace existing files**.
2. Delete the ZIP.

Your secrets file (outside this folder), uploaded product images (`public/uploads/`) and logs are not in the ZIP, so they are never overwritten. If the update notes mention a new database migration, import it in phpMyAdmin (step 5, "Future updates").

### Option 2: Pull the code from GitHub

hPanel → *Advanced → Git*:

| Field | Value |
|---|---|
| Repository | `https://github.com/cygnatrix-blip/Jersey-Musium.git` (or the SSH address if the repo is private, see below) |
| Branch | `main` |
| Directory | *(leave empty → installs into `public_html`)* |

Click **Create**, then **Deploy**.

**Private repository?**
1. Copy the *SSH key* shown on that Hostinger Git page.
2. In GitHub go to *Repository → Settings → Deploy keys → Add deploy key*, paste the key, and leave **write access off**.
3. Use the SSH repository address `git@github.com:cygnatrix-blip/Jersey-Musium.git`.

**Auto-deploy (optional):** Hostinger shows a *Webhook URL*. Add it in *GitHub → Settings → Webhooks* and every push to `main` will update the site.

> No `composer install` is needed. The app has zero runtime dependencies and ships its own autoloader.

## 3. Create the secrets file (outside the website folder)

*File Manager* → go to **`domains/yourdomain.com/`**, the folder that *contains* `public_html` → *New file* → `jersey-musium.env`.

| Your site is… | Project folder | Put `jersey-musium.env` in |
|---|---|---|
| a main domain, e.g. `example.com` | `domains/example.com/public_html` | `domains/example.com/` |
| a **subdomain inside another site's folder**, e.g. `shop.example.com` | `domains/example.com/public_html/shop` | `domains/example.com/` (**not** `public_html/`, which is the main website's public folder) |

The app searches up to four folders above itself, and always prefers a copy that is outside every `public_html`.

> **Keep the exact name `jersey-musium.env`. Do not rename it to `.env`.**
> The app looks for this file name, one folder above `public_html`, and reads it automatically.
> * A file called `.env` in that spot is **not** read.
> * Hostinger's File Manager hides names starting with a dot, so `.env` is easy to lose.
> * Being outside `public_html` means it can never be downloaded from the web.
>
> (A `.env` *inside* `public_html` also works and is blocked by `.htaccess`, but the outside location is safer.)

Paste the template below and fill in your values. **Real passwords go only in this file on the server, never into files on GitHub.**

```ini
APP_NAME="Jersey Musium"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.yourdomain.com
APP_TIMEZONE=Asia/Kolkata
APP_KEY=                       # the /setup page (step 5) gives you this value

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_jersey
DB_USERNAME=u123456789_jm
DB_PASSWORD=the-database-password

SESSION_NAME=jm_session
SESSION_LIFETIME=120
SESSION_SECURE=true

# Razorpay – start with rzp_test_ keys, switch to rzp_live_ keys after a test order
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

# Cashfree (optional)
CASHFREE_ENV=production
CASHFREE_APP_ID=
CASHFREE_SECRET_KEY=
CASHFREE_API_VERSION=2023-08-01

# Email – Gmail app password, or a Hostinger mailbox (smtp.hostinger.com, port 465, ssl)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=omkarjana8@gmail.com
MAIL_PASSWORD="your 16-character app password"
MAIL_FROM_ADDRESS=omkarjana8@gmail.com
MAIL_FROM_NAME="Jersey Musium"

UPLOAD_MAX_MB=5
ADMIN_2FA=true
TRUST_CLOUDFLARE=false

# One-time setup page (step 5). Any long random text (24+ characters, letters and numbers).
# Delete this line after your admin account is created.
SETUP_TOKEN=change-me-to-a-long-random-text-123
```

> 🔒 Use a **new** Gmail app password and **new** Razorpay keys here. The ones shared during development should be revoked.

## 4. File permissions

*File Manager* → right-click → *Permissions*:
* `public_html/storage/logs` → **755**
* `public_html/public/uploads` → **755**

Hostinger runs PHP as your own user, so 755 is enough. **Never use 777.**

## 5. Install the database & create your admin login

### Option A: phpMyAdmin (no SSH needed, recommended)

**5.1 Import the database (once)**
1. On your computer, download **`database/install/install.sql`** from GitHub: open the file → *Download raw file*.
2. hPanel → *Databases → phpMyAdmin* → **Enter phpMyAdmin** next to your database.
3. In phpMyAdmin, click your database name in the **left** column (e.g. `u123456789_jersey`). It must be **empty**, with no tables.
4. Top menu → **Import** → *Choose file* → select `install.sql` → leave everything else as is (format **SQL**, character set **utf-8**) → **Import** (or *Go*).
5. You should see a green success message and **35 tables** in the left column, including `admins`, `products`, `orders`, `roles` and `migrations`.

> ✅ This one file contains **all three migrations** (001 tables, 002 GST invoices, 003 email codes) and the starter data: 5 staff roles, 15 categories, 6 policy pages and 2 coupons. Import **only this file**. Do *not* import the separate files in `database/migrations/`.
>
> ⚠️ Import it **once**. If you import it again by mistake, phpMyAdmin stops with "Table 'users' already exists". Nothing is deleted, so just ignore it.

**5.2 Create your Super Admin login (one-time setup page)**
1. Make sure `SETUP_TOKEN=` in `jersey-musium.env` has a long random value (step 3).
2. Open **`https://www.yourdomain.com/setup?token=YOUR_SETUP_TOKEN`** in your browser.
3. **First time:** the page says *"APP_KEY is not set"* and shows a long key.
   1. Copy it into `APP_KEY=` in `jersey-musium.env` and save.
   2. Click **Check again**.
4. Fill in your name, the email that should receive admin sign-in codes, and a password (12+ characters, letters and numbers) → **Create Super Admin**.
5. You land on `/admin/login`. Sign in; a 6-digit code arrives by email.
6. **Delete the `SETUP_TOKEN=` line** from `jersey-musium.env`. The page already refuses to work once an admin exists, but tidy is safer.

> The setup page shows "Not found" if the token is missing or wrong, or if any admin already exists. Nobody else can use it.

**Future updates (phpMyAdmin):** if a later update adds a new file such as `database/migrations/004_something.sql`:
1. Import **just that new file** in phpMyAdmin.
2. In the *SQL* tab, run `INSERT INTO migrations (name) VALUES ('004_something.sql');` so the app knows it's done.

The update notes will say when this is needed.

> Demo products: the 24 demo jerseys with their images are only available through SSH (`seed --demo=1`), because their images are generated as files. On a live store you will add your own products anyway (Admin → Products).

### Option B: SSH (Premium/Business plans)

hPanel → *Advanced → SSH Access* → enable, then connect with the shown command or the hPanel *Browser terminal*:

```bash
cd ~/domains/yourdomain.com/public_html
php bin/console key:generate          # copy the printed value into APP_KEY= in jersey-musium.env
php bin/console migrate               # creates all tables
php bin/console seed                  # roles, categories, policy pages, coupons (no demo products)
php bin/console admin:create          # your Super Admin email + password (min 12 chars)
php bin/console security:check        # must say "No blocking issues"
```

Want the 24 demo jerseys and banners to see the site filled? Run `php bin/console seed --demo=1` instead. Archive them later in Admin → Products.

(With SSH you don't need `install.sql` or the setup page. Use one option or the other, not both.)

## 6. Scheduled jobs (Cron)

hPanel → *Advanced → Cron Jobs* → *Custom*:

| Schedule | Command | Why |
|---|---|---|
| `*/5 * * * *` (every 5 min) | `php /home/u123456789/domains/yourdomain.com/public_html/bin/console orders:expire` | Cancels unpaid online orders & puts the stock back on sale |
| `15 3 * * *` (daily 03:15) | `php /home/u123456789/domains/yourdomain.com/public_html/bin/console cleanup` | Clears old rate limits, reset tokens, abandoned guest bags |

Replace `u123456789` and `yourdomain.com` with your values (shown at the top of File Manager).

## 7. Payments: webhooks

**Razorpay Dashboard → Account & Settings → Webhooks → Add new webhook**
* URL: `https://www.yourdomain.com/webhooks/razorpay`
* Secret: any long random text; put the same text in `RAZORPAY_WEBHOOK_SECRET`
* Events: `payment.captured`, `payment.failed`, `refund.processed`, `refund.failed`
* Also: *Payment Capture* → **Automatic**

**Cashfree** (if used): *Developers → Webhooks* → `https://www.yourdomain.com/webhooks/cashfree`, version **2023-08-01**.

Then in the store go to **Admin → Settings → Payments** and enable the gateways you configured.

## 8. First-launch checklist

- [ ] `https://yourdomain.com` loads with the kick-off intro; `http://` redirects to `https://`
- [ ] These URLs must **NOT** open (expect "403 Forbidden"):
  - `/.env`
  - `/storage/logs/`
  - `/app/helpers.php`
  - `/.git/config`
  - `/composer.json`
- [ ] Register with a real email → the 6-digit code arrives → verified
- [ ] Admin login at `/admin` → code arrives by email
- [ ] Admin → Settings: store name, **GSTIN**, registered state, address, phone, shipping fees, COD rules
- [ ] Place a ₹1 test order with Razorpay test keys → order confirmed → refund it from the admin → both appear in Admin → Transactions/Refunds
- [ ] Switch to `rzp_live_` keys, set `CASHFREE_ENV=production`, run `php bin/console security:check` again
- [ ] Google Search Console: verify the domain (Settings field) and submit `https://www.yourdomain.com/sitemap.xml`
- [ ] hPanel → *Backups*: daily backups on (database + files)

## 9. Updating the site later

**ZIP method:**
1. Run `php bin/console package`.
2. Upload and extract the ZIP over the website folder, overwriting files (step 2, Option 1).

**Git method:**
1. Push to `main`.
2. Click **Deploy** in hPanel → Git (or let the webhook do it).

**Either way:** if the update contains a new file in `database/migrations/`, import it in phpMyAdmin (step 5, "Future updates") or run `php bin/console migrate` over SSH.

Your `jersey-musium.env`, uploaded product images and logs are in neither the ZIP nor Git, so updates never overwrite them.

## Troubleshooting

| Symptom | Fix |
|---|---|
| "500 Internal Server Error" | Check `public_html/storage/logs/app-YYYY-MM-DD.log`. For a quick look, set `APP_DEBUG=true` for a minute, then back to `false` |
| Database connection error | `DB_HOST=localhost`; database and user names include the `u123456789_` prefix; user added to the database with *All privileges* |
| Emails not arriving | Check the `Mail failed` lines in the app log. Gmail needs an **app password** (Google Account → Security → 2-Step Verification → App passwords). If Gmail is blocked or limited, create a mailbox in hPanel → *Emails* and use `smtp.hostinger.com`, port `465`, `MAIL_ENCRYPTION=ssl` |
| CSS/images missing, pages show "/public/" in URL | The `.htaccess` in `public_html` is missing. Redeploy from Git (dotfiles are included) |
| Uploaded product images fail | `public/uploads` permission 755; the image is under 5 MB and a JPG, PNG or WEBP |
| `/setup` says "Not found" | The `SETUP_TOKEN` in the URL must match the secrets file exactly (24+ characters), the file must be named `jersey-musium.env` one level above `public_html`, and no admin may exist yet. If you already created an admin, just sign in at `/admin` |
| phpMyAdmin import error "Table … already exists" | The database was not empty, so the import stopped without changing anything. Use an empty database, or skip the import if the tables are already there |
| Payments stay "pending" | Webhook URL or secret mismatch: check Razorpay → Webhooks → *Logs* |
