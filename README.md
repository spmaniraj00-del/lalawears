# LALA WEARS — PHP Website

Premium clothing storefront with **Google Sign-In**, order tracking, and notifications.

## Run (local)

```bash
php -S localhost:8080 router.php
```

Open: http://localhost:8080

## Deploy on Railway

1. Push this repo to GitHub (include `Dockerfile`, `Caddyfile`, `railway.toml`).
2. In Railway → **New Project** → **Deploy from GitHub repo**.
3. Railway will build with the `Dockerfile` (FrankenPHP + SQLite).
4. Open the public URL Railway gives you (Settings → Domains → Generate Domain).

Important:
- Do **not** set a custom Start Command in Railway (Dockerfile already starts the server).
- Root Directory should be the project root (where `Dockerfile` lives).
- For real traffic, add a **Volume** mounted at `/app/data` so orders/users survive redeploys.
- Optional: mount another volume at `/app/assets/uploads` for product/review images.

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
