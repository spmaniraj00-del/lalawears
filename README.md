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

### Custom domain + HTTPS (www + Cloudflare + Railway)

Your live URL is:

**`https://www.lalawearscraftedforstyle.com`**

GoDaddy root domains often fail as a plain CNAME. Use **www** as the main site.

#### 1) Railway
1. Service → **Settings** → **Networking** → **Custom Domain**
2. Add **`www.lalawearscraftedforstyle.com`** (this is the important one)
3. Optional: also add bare `lalawearscraftedforstyle.com` only if Railway gives you a valid record for it
4. Copy the **CNAME** + **TXT** values Railway shows for `www`

#### 2) Cloudflare DNS (recommended)
Nameservers at GoDaddy should point to Cloudflare.

| Type | Name | Target | Proxy |
|------|------|--------|-------|
| CNAME | `www` | Railway target (e.g. `xxxx.up.railway.app`) | Proxied (orange) after SSL works |
| TXT | `_railway` / value Railway shows | Railway verification value | DNS only |
| CNAME | `@` (root) | `www.lalawearscraftedforstyle.com` | Proxied (Cloudflare CNAME flattening) |

Cloudflare SSL/TLS:
- Mode = **Full**
- First-time SSL: set `www` to **DNS only** (grey cloud), wait until Railway says certificate **Issued**, then turn orange cloud back on

#### 3) Cloudflare redirect (root → www)
Rules → Redirect Rules → create:

- If hostname equals `lalawearscraftedforstyle.com`
- Then dynamic redirect to `https://www.lalawearscraftedforstyle.com/${uri}`
- Status **301**

#### 4) App / Railway variables
- `APP_URL` = `https://www.lalawearscraftedforstyle.com`
- `FORCE_HTTPS` = `1`
- `FORCE_CANONICAL_HOST` = `1` (redirects bare domain → www)
- `TERMINALX_TOKEN` = payment API token (keep secret; rotate if exposed)
- `TERMINALX_CREATE_URL` = `https://terminalx999.space/api/create-order`
- `TERMINALX_STATUS_URL` = `https://terminalx999.space/api/check-order-status`

#### 4b) Forgot password / email (Resend) — same as before
Railway → your service → **Variables** → add:

| Variable | What to paste |
|----------|----------------|
| `RESEND_API_KEY` | From [resend.com/api-keys](https://resend.com/api-keys) (starts with `re_`) |
| `RESEND_FROM` | `onboarding@resend.dev` (same as earlier working emails) |
| `APP_URL` | `https://www.lalawearscraftedforstyle.com` |

Then **Redeploy**.

**Important (Resend free / testing sender):**
- From `onboarding@resend.dev`, mail usually goes **only** to the Gmail that owns your Resend account (the inbox where you already got “Reset Your Password - LALA WEARS”).
- Other Gmails will **not** get the link until you verify a domain in Resend and set e.g. `RESEND_FROM=noreply@yourdomain.com`.

Check **Inbox + Spam**. Subject: `Reset Your Password - LALA WEARS`.

#### 5) Google OAuth (Continue with Google)
Railway **Variables** (required or the Google button stays hidden):

| Variable | Value |
|----------|--------|
| `GOOGLE_CLIENT_ID` | from Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | from Google Cloud Console |
| `APP_URL` | `https://www.lalawearscraftedforstyle.com` |

In [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → OAuth client:

- **Authorized JavaScript origins:** `https://www.lalawearscraftedforstyle.com`
- **Authorized redirect URIs:** `https://www.lalawearscraftedforstyle.com/auth/google_callback.php`

After login, Gmail name + profile photo show in the header, account page, and reviews.

Open the site only as **`https://www.lalawearscraftedforstyle.com`**.

## Customer login

1. Open `/auth/login.php`
2. Prefer **Continue with Google** (Gmail) — photo & name sync automatically
3. Or sign in with email + password
4. New users: `/auth/register.php` (Google or email)

OTP / Resend (optional email flows): `config/config.local.php` → `RESEND_API_KEY`, `RESEND_FROM`

> Keep secrets in Railway Variables / `config.local.php` (gitignored). Do not commit client secrets.

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
