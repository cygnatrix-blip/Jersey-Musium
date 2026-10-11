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
4. **Empty the website folder:** *Files → File Manager → public_html* → delete the default `default.php` / `index.html`. Git deployment needs an empty folder.

## 2. Pull the code from GitHub

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

*File Manager* → go **one level up** from `public_html` (to `domains/yourdomain.com/`) → *New file* → `jersey-musium.env`.

Paste the template below and fill in your values. The app finds this file automatically; it is never reachable from the web.

```ini
APP_NAME="Jersey Musium"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.yourdomain.com
APP_TIMEZONE=Asia/Kolkata
APP_KEY=                       # step 5 generates this

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
```

> 🔒 Use a **new** Gmail app password and **new** Razorpay keys here. The ones shared during development should be revoked.

## 4. File permissions

*File Manager* → right-click → *Permissions*:
* `public_html/storage/logs` → **755**
* `public_html/public/uploads` → **755**

Hostinger runs PHP as your own user, so 755 is enough. **Never use 777.**

## 5. Install the database & create your admin login

**With SSH** (hPanel → *Advanced → SSH Access* → enable, then connect with the shown command or the hPanel *Browser terminal*):

```bash
cd ~/domains/yourdomain.com/public_html
php bin/console key:generate          # copy the printed value into APP_KEY= in jersey-musium.env
php bin/console migrate               # creates all tables
php bin/console seed                  # roles, categories, policy pages, coupons (no demo products)
php bin/console admin:create          # your Super Admin email + password (min 12 chars)
php bin/console security:check        # must say "No blocking issues"
```

Want the 24 demo jerseys and banners to see the site filled? Run `php bin/console seed --demo=1` instead. Archive them later in Admin → Products.

**Without SSH (Single plan):** use *Advanced → Cron Jobs* to run each command **once**.
1. Add a cron job with the command `php /home/u123456789/domains/yourdomain.com/public_html/bin/console migrate`, set to every minute.
2. Wait one minute, then delete that job.
3. Repeat for `seed`.
4. For `admin:create`, use `admin:create --name="Your Name" --email=you@domain.com --password=YourLongPassword123`.
5. For the app key, generate one locally (`php -r "echo bin2hex(random_bytes(32));"`) and paste it into the env file.

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

1. Push changes to the `main` branch on GitHub.
2. Click **Deploy** in hPanel → Git, or let the webhook do it.
3. If the update contains a new file in `database/migrations/`, run `php bin/console migrate` (SSH or one-off cron).

Your `jersey-musium.env`, uploaded product images and logs are **not** in Git, so updates never overwrite them.

## Troubleshooting

| Symptom | Fix |
|---|---|
| "500 Internal Server Error" | Check `public_html/storage/logs/app-YYYY-MM-DD.log`. For a quick look, set `APP_DEBUG=true` for a minute, then back to `false` |
| Database connection error | `DB_HOST=localhost`; database and user names include the `u123456789_` prefix; user added to the database with *All privileges* |
| Emails not arriving | Check the `Mail failed` lines in the app log. Gmail needs an **app password** (Google Account → Security → 2-Step Verification → App passwords). If Gmail is blocked or limited, create a mailbox in hPanel → *Emails* and use `smtp.hostinger.com`, port `465`, `MAIL_ENCRYPTION=ssl` |
| CSS/images missing, pages show "/public/" in URL | The `.htaccess` in `public_html` is missing. Redeploy from Git (dotfiles are included) |
| Uploaded product images fail | `public/uploads` permission 755; the image is under 5 MB and a JPG, PNG or WEBP |
| Payments stay "pending" | Webhook URL or secret mismatch: check Razorpay → Webhooks → *Logs* |
