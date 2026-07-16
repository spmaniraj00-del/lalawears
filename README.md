# LALA WEARS — PHP Website

Premium clothing storefront with **Google Sign-In**, order tracking, and notifications.

## Run

```bash
cd C:\Users\Mani272\Desktop\websit
php -S localhost:8080 router.php
```

Open: http://localhost:8080

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
