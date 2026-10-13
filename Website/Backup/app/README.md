# Loaf'n Ladle — Client Portal & Shop

A complete PHP web application for the Loaf'n Ladle artisan bakery. Built with PHP, SQLite, and Stripe.

## Features

### Public Store
- **Homepage** — Hero, featured products, subscription plans preview, catering CTA
- **Shop** — Full menu with category filters and search
- **Product Pages** — Individual product detail with add-to-cart
- **Cart** — Session-based cart with quantity management
- **Checkout** — Stripe-powered secure checkout
- **Subscriptions** — Weekly bread subscription plans
- **Catering** — Catering menu and inquiry

### Client Portal
- **Register / Login** — Secure account creation and authentication
- **Dashboard** — Order history, active subscriptions, quick stats
- **Orders** — Full order history with status tracking
- **Subscriptions** — Manage active subscriptions, cancel anytime
- **Profile** — Update personal info, delivery address, change password

### Admin Panel (`/app/index.php?page=admin`)
- **Dashboard** — Revenue, orders, customers, subscription stats
- **Products** — Add/edit/toggle products with pricing and margins
- **Categories** — Manage product categories
- **Orders** — View and update order statuses
- **Subscriptions** — Monitor all customer subscriptions
- **Customers** — Customer list with order/subscription history
- **Settings** — Store info, tax rate, delivery radius
- **Stripe API** — Configure API keys, webhook setup guide
- **Sub Plans** — Manage subscription plan pricing and features

## Setup

### Requirements
- PHP 7.4+ with PDO SQLite extension
- Apache with mod_rewrite (or Nginx equivalent)
- cURL extension (for Stripe API calls)

### Installation
1. Place the `app/` folder in your web root or a subdirectory
2. Run the setup script (or do the steps manually):
   ```
   php app/setup.php
   ```
   It checks PHP/extensions, ensures `app/data/` and `app/logs/` are writable,
   creates `app/.env` from `app/.env.example`, and initializes the database.
3. Edit `app/.env` to set your admin email/password (and Stripe keys, if you have them)
4. Navigate to `http://yoursite.com/app/index.php`
5. The SQLite database is auto-created on first visit at `app/data/bakery.db`

### Admin Login
The admin account is seeded on first run from your `.env`:
- **Email:** `LNL_ADMIN_EMAIL` (default `admin@loafnladle.com`)
- **Password:** `LNL_ADMIN_PASSWORD` (default `admin123`)
- **⚠️ Set your own password in `.env` before deploying, or change it after first login!**

### Stripe Configuration
1. Log in as admin
2. Go to **Admin → Stripe API**
3. Enter your Stripe Publishable Key and Secret Key
4. Set up a webhook pointing to: `http://yoursite.com/app/index.php?page=webhook.stripe`
5. Listen for: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`

## URL Structure

All pages are served through `index.php?page=<pagename>`:

| Page | URL |
|------|-----|
| Home | `?page=home` |
| Shop | `?page=shop` |
| Cart | `?page=cart` |
| Checkout | `?page=checkout` |
| Subscriptions | `?page=subscriptions` |
| Catering | `?page=catering` |
| Login | `?page=login` |
| Register | `?page=register` |
| Client Portal | `?page=portal` |
| Admin Dashboard | `?page=admin` |

## File Structure

```
app/
├── index.php          # Main router
├── config.php         # App configuration
├── db.php             # Database class + schema + seed data
├── auth.php           # Authentication class
├── helpers.php        # Utility functions, cart, CSRF
├── stripe.php         # Stripe API service
├── layout.php         # HTML layout templates (public + admin)
├── .htaccess          # Security rules
├── data/              # SQLite database (auto-created)
├── logs/              # Error logs (auto-created)
├── pages/
│   ├── home.php
│   ├── shop.php
│   ├── product.php
│   ├── cart.php
│   ├── checkout.php
│   ├── checkout_success.php
│   ├── checkout_cancel.php
│   ├── subscriptions.php
│   ├── subscribe_checkout.php
│   ├── catering.php
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── portal.php
│   ├── portal_orders.php
│   ├── portal_subscriptions.php
│   ├── portal_profile.php
│   └── admin/
│       ├── dashboard.php
│       ├── products.php
│       ├── product_edit.php
│       ├── categories.php
│       ├── orders.php
│       ├── order_view.php
│       ├── subscriptions.php
│       ├── customers.php
│       ├── settings.php
│       ├── stripe.php
│       └── plans.php
└── actions/
    ├── cart_add.php
    ├── cart_update.php
    ├── cart_remove.php
    ├── order_create.php
    └── webhook_stripe.php
```

## Design

Matches the Loaf'n Ladle brand:
- **Colors:** Warm cream (`#faf8f3`), brown (`#8b4513`), gold (`#d4a574`)
- **Typography:** System UI / Segoe UI
- **Framework:** Tailwind CSS (CDN), Font Awesome 6 icons
- **Responsive:** Mobile-first design
