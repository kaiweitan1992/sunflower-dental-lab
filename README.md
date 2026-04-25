# Sunflower Dental Lab

Open-source order &amp; invoice manager for small dental laboratories.
Built with vanilla **PHP 8.2** and **MySQL 8** — no framework dependencies, no
build step, no `node_modules`. Designed for a single shop with a handful of
staff users and easy deployment to **DigitalOcean App Platform**.

> 86 dental products and 6 categories are seeded out of the box (ceramic,
> implant, full metal, attachment, acrylic, treatment). Edit them in the UI
> any time.

---

## Features

- 🔐 **Login-required** for all data (bcrypt + hardened sessions + CSRF)
- 📦 **Product catalog** with search and category filter
- 🏥 **Clinic directory** (your customers) with full CRUD
- 🛒 **Cart-style ordering** with per-item patient names
- 🧾 **Invoice / receipt generation** with auto-numbering (atomic, transactional)
- 📊 **Records view** with date-range filters and revenue summary
- 🖨️ **Print-friendly** invoices &amp; receipts
- ⚙️ **Settings**: invoice numbering, password change
- ✨ **Open source under MIT** — fork it, brand it, deploy it

## Stack

| Layer    | What                                                                |
| -------- | ------------------------------------------------------------------- |
| Backend  | PHP 8.2 (PDO + sessions, no framework) — ~600 lines of clean code   |
| Database | MySQL 8 (InnoDB, utf8mb4)                                           |
| Frontend | Vanilla ES modules, no build step                                   |
| Hosting  | DigitalOcean App Platform + Managed MySQL (≈ $20/month)             |

---

## Quick start (local)

You need **PHP 8.2+**, **MySQL 8** (or compatible), and **Composer** (only for
autoloader optimization — the app also runs without it).

```bash
git clone https://github.com/YOUR_USER/sunflower-dental-lab.git
cd sunflower-dental-lab

# 1. Install (Composer is optional but recommended)
composer install              # creates vendor/autoload.php

# 2. Configure
cp .env.example .env
# edit .env → set DB_HOST, DB_NAME, DB_USER, DB_PASS

# 3. Create the database
mysql -u root -p -e "CREATE DATABASE sunflower CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Run the installer (creates tables, seeds products, creates admin)
php bin/install.php

# 5. Start PHP's built-in server
php -S localhost:8000 -t public/
```

Open <http://localhost:8000/login.php> and sign in.

> **Note:** the built-in server doesn't honour `.htaccess`. The frontend
> calls `/api/...` URLs which are rewritten to `/api.php/...` by Apache.
> For local dev, the `Http\Request::path()` parser also accepts the direct
> `/api.php/...` form, so everything works either way.

---

## Production deploy

See **[DEPLOY.md](DEPLOY.md)** for the complete step-by-step guide to:

1. Push your fork to GitHub
2. Create a DigitalOcean App + Managed MySQL
3. Wire environment variables
4. Run the installer in App Platform's Console tab
5. Add your custom domain &amp; HTTPS

Total time from zero to live: **about 25 minutes**. Total cost: **about $20/mo**
(plus your domain registration of around RM 50–60 per year).

---

## Project structure

```
sunflower-dental-lab/
├── public/              ← web root
│   ├── index.php        ← SPA shell (auth-gated)
│   ├── login.php        ← login form
│   ├── api.php          ← single JSON API entry point
│   ├── print.php        ← printable invoice / receipt
│   ├── .htaccess        ← URL rewrite + security headers
│   └── assets/          ← CSS + ES-module JS
├── src/                 ← PSR-4 PHP classes (Sunflower\…)
│   ├── Bootstrap.php    ← session/env/error setup
│   ├── Config.php       ← .env + getenv loader
│   ├── Db.php           ← PDO singleton + transaction helper
│   ├── Auth.php         ← bcrypt login, session regen
│   ├── Csrf.php         ← double-submit token
│   ├── Http/            ← Router, Request, Response
│   ├── Validation/V.php ← typed input validator
│   └── Controllers/     ← 6 thin controllers, one per resource
├── sql/
│   ├── schema.sql       ← 7 tables, FKs, indexes
│   └── seed.sql         ← categories + 86 products
├── bin/install.php      ← CLI installer
├── .do/app.yaml         ← App Platform spec template
├── composer.json
├── Procfile             ← tells App Platform docroot is public/
├── .env.example
├── README.md
├── DEPLOY.md
└── LICENSE              ← MIT
```

---

## API reference (JSON, all `/api/...`)

| Verb     | Path                       | Purpose                                |
| -------- | -------------------------- | -------------------------------------- |
| `POST`   | `/auth/login`              | Sign in (returns user + csrf token)    |
| `POST`   | `/auth/logout`             | Sign out                               |
| `GET`    | `/auth/me`                 | Current user                           |
| `POST`   | `/auth/change-password`    | Change own password                    |
| `GET`    | `/categories`              | List categories                        |
| `POST`   | `/categories`              | Create                                 |
| `PUT`    | `/categories/:id`          | Update                                 |
| `DELETE` | `/categories/:id`          | Delete (only if no products use it)    |
| `GET`    | `/products?q=&category_id=`| List products (filtered)               |
| `POST`   | `/products`                | Create                                 |
| `GET`    | `/products/:id`            | Read                                   |
| `PUT`    | `/products/:id`            | Update                                 |
| `DELETE` | `/products/:id`            | Soft-delete (deactivate)               |
| `GET`    | `/clinics?q=`              | List clinics                           |
| `POST`   | `/clinics`                 | Create                                 |
| `GET`    | `/clinics/:id`             | Read                                   |
| `PUT`    | `/clinics/:id`             | Update                                 |
| `DELETE` | `/clinics/:id`             | Delete (snapshots survive on invoices) |
| `GET`    | `/invoices?from=&to=&type=&clinic_id=&q=` | List with filters         |
| `POST`   | `/invoices`                | Create invoice or receipt              |
| `GET`    | `/invoices/:id`            | Read with line items                   |
| `DELETE` | `/invoices/:id`            | Delete (admin only)                    |
| `GET`    | `/stats`                   | Dashboard counters                     |
| `GET`    | `/settings`                | Read all settings                      |
| `PUT`    | `/settings`                | Update (admin only)                    |

All non-GET requests require the `X-CSRF-Token` header. The token is exposed
in the SPA shell via `<meta name="csrf-token">` and returned again from
`/auth/login` and `/auth/me`.

---

## Roadmap (not yet built)

The following were intentionally left out of v1 for clarity. Pull requests
welcome:

- File uploads (signature image, payment-proof photo)
- Multi-currency support (currently hard-coded to RM)
- Two-factor auth (TOTP)
- CSV / PDF export of records
- Per-clinic price lists / discounts
- Audit log

---

## License

[MIT](LICENSE) — do whatever you want, just keep the copyright notice.
