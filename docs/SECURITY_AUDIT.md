# Jersey Museum — Security & Quality Audit Report

| | |
|---|---|
| **Date** | 11 October 2026 |
| **Scope** | Whole application: storefront, checkout & payments (Razorpay, Cashfree, COD), admin console, invoicing, configuration, dependencies |
| **Environment** | Local build: PHP 8.4, MariaDB 11.4 (MySQL-compatible), Razorpay **test mode**, Microsoft Edge (Chromium) for browser tests |
| **Method** | Manual code review against OWASP ASVS 4 / OWASP Top 10 (2021) and real attacks against a running instance (XSS, SQL injection, IDOR, CSRF, privilege escalation, file upload, webhook forgery/replay); automated unit, integration, end-to-end, browser and responsive tests; dependency audit |
| **Result** | **20 issues found and fixed** (2 high, 9 medium, 9 low or functional). No open high or medium issues. 12 residual risks documented with recommendations (§4). |

---

## 1. Summary of findings

| ID | Severity | Finding | Status |
|---|---|---|---|
| F-01 | **High** | SMTP password written (base64) to `storage/logs` when Gmail rejected a login | ✅ Fixed: failures log only a label ("AUTH password"); the leaked log line was purged |
| F-02 | **High** | Privilege escalation: an admin with `admins.manage` could grant themselves **Super Admin**, widen their own role's permissions, or disable/edit a Super Admin | ✅ Fixed: only Super Admins can grant that role or touch Super Admin accounts; nobody can edit their own role or change their own role; the last Super Admin cannot be removed |
| F-03 | Medium | Content-Security-Policy blocked Razorpay's risk-detection script (`cdn.razorpay.com`), weakening the gateway's fraud checks | ✅ Fixed: added to `script-src`; verified in a real test payment |
| F-04 | Medium | Admin console protected by password only | ✅ Fixed: **email OTP two-step verification** (6 digits, HMAC-hashed at rest, 10-minute expiry, 5 attempts, throttled resend) |
| F-05 | Medium | Rate limits keyed on `CF-Connecting-IP`, a header any client can spoof, so login throttling could be bypassed | ✅ Fixed: the header is trusted only with `TRUST_CLOUDFLARE=true`; otherwise `REMOTE_ADDR` |
| F-06 | Medium | URL validator accepted `javascript://…%0aalert(1)` and protocol-relative `//evil` (stored-XSS vector in banner and social links) | ✅ Fixed: only `http(s)://` or site-relative `/path` allowed |
| F-07 | Medium | Regex-based CMS HTML sanitiser bypassable with entity-encoded `jav&#x61;script:` links | ✅ Fixed: replaced with a DOM allow-list sanitiser (tags, attributes, URL schemes) plus 10 XSS regression tests |
| F-08 | Medium | Error handler turned PHP *deprecation notices* into fatal errors, so a PHP upgrade could take checkout down (seen with `curl_close`) | ✅ Fixed: deprecations are logged as warnings; deprecated calls removed |
| F-09 | Low | Admin sessions had only an idle timeout | ✅ Fixed: plus a 12-hour absolute lifetime |
| F-10 | Low | `Cache-Control: no-store` missing on bag, login, register and password pages | ✅ Fixed |
| F-11 | Low | No `Cross-Origin-Opener-Policy` | ✅ Fixed: `same-origin-allow-popups` (keeps bank 3-D Secure popups working) |
| F-12 | Low | Dev dependency PHPUnit 11.5.0 affected by CVE-2026-24765 | ✅ Fixed: 11.5.50; `composer audit` clean. Dev only, never deployed |
| F-13 | Low | PHP builds without a CA bundle cannot verify the gateways' TLS certificates | ✅ Fixed: optional `HTTP_CA_BUNDLE` setting. **TLS verification is never disabled** |
| F-14 | Compliance | GST invoices reused the order number; CGST Rules 2017 (Rule 46) require a *consecutive serial number per financial year*, ≤ 16 characters | ✅ Fixed: `JM/26-27/000001…`, allocated under a row lock **at dispatch** (time of supply), so no numbers are burned on orders cancelled before shipping |
| F-15 | Compliance | Shipping and COD charges carried no GST; under the composite-supply rule they must be taxed at the principal supply's rate | ✅ Fixed: in both pricing and invoice; invoice reconciles to the paisa (unit-tested) |
| F-16 | Functional | Emails were sent inside the HTTP request, so a slow SMTP server would delay checkout | ✅ Fixed: sent after the response is flushed (`fastcgi_finish_request` / `litespeed_finish_request`) |
| F-17 | Functional | Horizontal scrolling on listing pages (all widths) and admin pages (phones) | ✅ Fixed: 125 page × device combinations now clean |
| F-18 | Functional | `slugify("Café")` produced `caf-e` on Windows (iconv transliteration quirk), giving bad product URLs | ✅ Fixed (caught by unit test) |
| F-19 | Functional | Invoice totals off-screen on phones | ✅ Fixed: mobile totals summary |
| F-20 | Medium | Customer email addresses were never verified, so anyone could register (and order) with an address they do not own | ✅ Fixed: **6-digit email OTP at sign-up** (HMAC-stored, 10-min expiry, 5 attempts, single use, 45 s resend cooldown); checkout, payment, reviews and returns require a verified email; admin email changes force re-verification. Shares one `OtpService` with admin 2FA |

---

## 2. Controls verified (with evidence)

| Area | Control | Evidence |
|---|---|---|
| **Injection** | 100% prepared statements; identifiers only from code; LIKE wildcards escaped; FULLTEXT operators stripped | Smoke: SQLi in search, slug, filters and sort is inert; DB intact |
| **XSS** | `e()` on all output; JSON-LD with `JSON_HEX_TAG`; DOM sanitiser for CMS; nonce-based CSP (no inline scripts allowed) | Smoke: reflected XSS escaped; Unit: 10 XSS payloads neutralised |
| **CSRF** | Per-session token on every POST; `SameSite=Lax`; webhooks exempt and HMAC-verified | Smoke: POST without token → 419 |
| **Authentication** | `password_hash` (bcrypt cost 12), timing-safe dummy verify, per-IP and per-account lockout, no email enumeration on reset, reset tokens SHA-256 hashed and single-use (60 min) | Smoke: wrong password, blocked customer cannot log in, reset token stored hashed |
| **Admin access** | Separate session, 2FA, idle + absolute timeouts, RBAC on every route group, sidebar hides forbidden sections, full audit log | Smoke: OTP required; wrong OTP rejected; limited admin gets 403 on Products/Settings; escalation attempts blocked; actions audit-logged |
| **Authorisation (IDOR)** | Every customer query scoped by `user_id`; admin lookups scoped by permission | Smoke: another customer cannot view, cancel or download the invoice of an order (404) |
| **Payments** | Server-side amount calculation; Razorpay HMAC(order\|payment) **and** API re-fetch of status/amount; Cashfree status fetched from API; webhook HMAC + replay window (Cashfree) + event-id dedupe; row-locked idempotent `markPaid`; amount-mismatch rejection; late payment on expired order auto-refunded; over-refund impossible | **Real Razorpay test payment** (card + OTP) confirmed the order; **real partial refund** processed; over-refund blocked; 12 webhook checks (forged, tampered, replayed, duplicate, amount-mismatch); Unit: signature tests |
| **Data integrity** | `SELECT … FOR UPDATE` on stock, coupons, payments, refunds, invoice sequence; `CHECK (stock >= 0)`; stock ledger | Integration: overselling impossible; ledger rows; consecutive invoice numbers |
| **File uploads** | MIME sniffed from bytes, `getimagesize`, GD re-encode (strips EXIF/payloads), random names, size cap, PHP disabled in `/uploads` | Smoke: PHP shell renamed `.jpg` rejected (sniffed `text/x-php`) |
| **Transport & headers** | HSTS (on HTTPS), CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP; HttpOnly, SameSite and Secure cookies | Smoke: headers and cookie flags asserted |
| **Secrets** | Only in `.env` (git-ignored) / environment; never in DB, front-end or logs | Code review; F-01 fixed; `security:check` |
| **Exposure** | Document root is `/public`; root `.htaccess` denies source, `.env`, logs; directory listing off | Smoke: `.env` and logs unreachable |
| **Open redirect** | `redirect()` and `back()` only same-origin | Smoke: hostile `Referer` ignored |
| **CSV injection** | Cells starting with `= + - @` prefixed | Code review |
| **Privacy (DPDP 2023)** | Minimal data; right to erasure (anonymise, keep tax records); no card data stored | Smoke: anonymisation erases email, phone, addresses |
| **Dependencies** | Zero runtime dependencies; dev deps audited | `composer audit`: no advisories |

---

## 3. Test evidence

| Suite | Command | Result |
|---|---|---|
| Syntax | `php bin/console lint` | All files OK |
| Unit + integration (PHPUnit 11.5.50) | `vendor/bin/phpunit` | **95 tests, 209 assertions, all passing**: email OTP (hash-only storage, single use, lockout, expiry, scoping, cooldown), money/GST maths, invoice numbering and amounts in words, coupons, shipping, state machines, refunds, returns, inventory, rate limiting, router, validator, sanitiser, gateway signatures |
| End-to-end + security probes | `php tests/smoke.php <url> <admin-email> <password>` | **104 checks, all passing**: form-error redirects, sign-up email OTP (blocked checkout, wrong code, attempts left, cooldown, reuse), full order lifecycle, 2FA, invoice on dispatch, returns and refunds, customer management, RBAC escalation attempts, XSS, SQLi, IDOR, CSRF, upload, headers |
| Real Razorpay (test mode) | `node tests/browser/razorpay-checkout.js` | Sign-up → email OTP verified → card payment with bank OTP captured, order confirmed; partial refund `processed` via Razorpay API |
| Responsive layout | `node tests/browser/responsive.js …` | **125 page × device combinations** (360, 390, 768, 1024, 1440 px; storefront + admin): 0 horizontal overflow, 0 JS errors |
| Configuration | `php bin/console security:check` | Run on the live server before launch (FAILs block go-live) |

---

## 4. Residual risks & recommendations

| # | Risk | Rating | Recommendation |
|---|---|---|---|
| R-1 | **Secrets shared in chat** (Gmail app password, Razorpay test secret) | High until rotated | Rotate both now; use fresh values only in the server `.env` |
| R-2 | Gmail SMTP credentials did not match the account tried (`535 BadCredentials`) | Functional | Confirm the Gmail address the app password belongs to. For production volume use a transactional provider (Amazon SES, Zoho ZeptoMail, Brevo); Gmail caps sending (≈500/day personal, 2,000/day Workspace) and needs SPF/DKIM on your own domain |
| R-3 | Fake COD orders (no phone verification) | Medium (business) | Add SMS OTP or an IVR/WhatsApp confirmation for COD, or lower the COD limit (Admin → Settings) |
| R-4 | Webhooks cannot reach a laptop | Medium (pre-launch) | Verify both webhooks on staging with a public HTTPS URL before launch (Razorpay dashboard → Webhooks → *Test*) |
| R-5 | Account enumeration on registration ("email already in use") | Low | Accepted UX trade-off; throttled to 5 attempts per 10 min per IP |
| R-6 | Per-account lockout can be abused to lock a user out for 15 min | Low | Accepted; password reset still works. Consider CAPTCHA (Cloudflare Turnstile) on login if abused |
| R-7 | Password reset doesn't revoke the user's other active sessions (file sessions) | Low | Move sessions to DB/Redis and revoke on password change |
| R-8 | Per-customer coupon limit isn't serialised for the *same* customer placing two orders at the same instant | Low | Lock the user row during checkout if this matters |
| R-9 | Image re-encoding needs GD with JPEG/WebP; without it, verified originals are stored | Low | Confirm on the host: `php -r "var_dump(function_exists('imagewebp'));"` |
| R-10 | CSP keeps `style-src 'unsafe-inline'` | Low | Accepted (styles cannot execute script); revisit if inline styles are removed |
| R-11 | Logs contain personal data (emails, gateway metadata) | Low | Keep `storage/` outside the web root (it is) and rotate/delete logs older than 90 days |
| R-12 | Real athlete images and brand marks in the intro photo | Legal | Replace with licensed imagery before launch (see ARCHITECTURE §12) |

### Compliance notes
* **PCI DSS:** card data never touches this server (hosted Razorpay/Cashfree checkout), which keeps you in the lightest category (SAQ-A). Keep it that way: never build a custom card form.
* **GST:** tax invoices meet Rule 46 (serial number per FY, GSTIN, HSN, place of supply with state code, CGST/SGST vs IGST, amount in words, signatory). *Not yet implemented:* **credit notes** for refunds and returns (Rule 53). Today refunds are recorded against the invoice; add credit-note documents before GST filing at volume. Set the GSTIN in Admin → Settings.
* **DPDP Act 2023:** privacy policy page, minimal collection, erasure on request (admin → customer → *Delete personal data*), tax records retained as the law requires.

---

## 5. Go-live security checklist

- [ ] Rotate the Gmail app password and Razorpay secret (R-1)
- [ ] `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set, `SESSION_SECURE=true`, `ADMIN_2FA=true`, `MAIL_DRIVER=smtp`, **live** gateway keys, webhook secrets
- [ ] `php bin/console security:check` shows no FAIL
- [ ] Document root points to `/public`; HTTPS certificate active
- [ ] Webhooks configured and tested from the gateway dashboards (R-4)
- [ ] Database user has privileges on this database only (no `GRANT`, no global privileges)
- [ ] Daily DB + `public/uploads` backups enabled and a restore tested once
- [ ] Cron jobs running (`orders:expire` every 5 min, `cleanup` daily)
- [ ] Super Admin accounts limited to the owner(s); day-to-day staff use limited roles
- [ ] Uptime monitoring and a weekly glance at `storage/logs/app-*.log`
