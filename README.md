# LALA WEARS — PHP Website

Premium clothing storefront with **Google Sign-In**, order tracking, and notifications.

## Run (local)

```bash
php -S localhost:8080 router.php
```

Open: http://localhost:8080

## Deploy on Railway

1. Push this repo to GitHub (include `Dockerfile` + `railway.toml`).
2. In Railway → **New Project** → **Deploy from GitHub repo**.
3. Railway builds with the `Dockerfile` (PHP 8.3 + SQLite, same as local).
4. Open the public URL (Settings → Networking → Generate Domain).

Important:
- Clear any **Custom Start Command** in Railway (Dockerfile starts the server).
- Root Directory = project root (folder that contains `Dockerfile`).
- After redeploy, logs should show: `Development Server (http://0.0.0.0:XXXX) started` — **not** FrankenPHP.
- Add a **Volume** at `/app/data` so orders/users survive redeploys.
- Optional volume: `/app/assets/uploads` for uploaded images.

### Custom domain + HTTPS (fix “Not secure”)

Browsers show **Not secure** when the site is opened over plain `http://` or the SSL certificate is missing.

Railway issues a free Let’s Encrypt certificate for custom domains automatically once DNS is correct.

1. In Railway → your service → **Settings** → **Networking** → **Custom Domain**.
2. Add `lalawearscraftedforstyle.com` (and `www` if you use it).
3. In your DNS provider, add **both** records Railway shows:
   - **CNAME** (or ALIAS / CNAME flattening for the root domain) → Railway target
   - **TXT** verification record (required — without it SSL will not issue)
4. Wait until Railway shows the domain as verified and the certificate as **Issued**.
5. Open **`https://lalawearscraftedforstyle.com`** (not `http://`).
6. Optional Railway variables (recommended):
   - `APP_URL` = `https://lalawearscraftedforstyle.com`
   - `FORCE_HTTPS` = `1` (default behaviour already redirects HTTP → HTTPS in production)

If you use **Cloudflare**:
- SSL/TLS mode = **Full** (not Flexible, not Full Strict)
- During first certificate issue, set the orange cloud to **DNS only**, wait for Railway SSL, then turn proxy back on

Also update Google OAuth redirect URIs to the `https://` domain if Google Sign-In is enabled.

## Customer login (Phone + OTP)

1. Open `/auth/login.php`
2. Enter **name** (new users), **phone**, and **email**
3. 6-digit OTP is sent via **Resend** to the email
4. Enter code on `/auth/verify.php` → account created / logged in

Config: `config/config.local.php` → `RESEND_API_KEY`, `RESEND_FROM`

> Resend sends **email**, not SMS. Phone is your account ID; OTP arrives on email.
> Free Resend (`onboarding@resend.dev`) usually only delivers to your Resend account email until you verify a domain.

## Admin Login

- URL: `/admin/login.php`
- Username: `admin`
- Password: `LalaAdmin@2026`

## Features

- Super Travel–inspired editorial design (League Spartan, rose accent)
- Google OAuth customer accounts (phone login removed)
- Checkout with size, qty, address, clothing photo details
- Order detail page + status timeline
- Notifications for customers & admin (new order / status updates)
- Admin: products CRUD, prices, images, orders, customers
