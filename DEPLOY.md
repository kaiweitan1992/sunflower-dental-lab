# Deploying to DigitalOcean

A complete walkthrough from "I have the source code" to "https://my-lab.com is
live with HTTPS." Roughly **25 minutes** of clicking, mostly waiting for the
first build.

---

## What you'll need (and what it costs)

| Item                                | Where                              | Cost (2026)        |
| ----------------------------------- | ---------------------------------- | ------------------ |
| GitHub account                      | <https://github.com>               | Free               |
| DigitalOcean account                | <https://digitalocean.com>         | Free *(US$200 starter credit for 60 days for new accounts)* |
| App Platform — basic web instance   | Auto-created in step 3             | **~$5 / month**    |
| Managed MySQL — smallest production | Auto-created in step 3             | **~$15 / month**   |
| Custom domain (optional)            | Cloudflare / Namecheap / exabytes  | ~RM 50–60 / year   |
| **Total ongoing**                   |                                    | **≈ US$20 / month** |

> Want to test before paying anything? You can swap the production MySQL for
> App Platform's "dev database" (free, but not backed up and shared with the
> app). See **Cheaper test setup** at the bottom of this file.

---

## Step 1 — Push the code to GitHub

```bash
cd sunflower-dental-lab
git init
git add .
git commit -m "Initial commit"
gh repo create sunflower-dental-lab --public --source=. --push
# Or, if you don't have GitHub CLI:
#   create the repo manually on github.com, then:
#   git remote add origin https://github.com/YOUR_USER/sunflower-dental-lab.git
#   git branch -M main
#   git push -u origin main
```

The repo can be **public** (recommended, since it's MIT-licensed and contains
no secrets) or private — App Platform handles both equally well.

---

## Step 2 — Create your DigitalOcean account

1. Go to <https://digitalocean.com> and sign up.
2. Verify your email and add a payment method. New accounts typically receive
   **US$200 in free credit valid for 60 days**, which is more than enough to
   run this app for that period for free.
3. (Optional) In **Settings → Billing → Alerts**, set a monthly cap (e.g. $25)
   so you'll get an email if usage spikes unexpectedly.

---

## Step 3 — Create the app

1. From the dashboard, click **Create → Apps**.
2. Choose **GitHub** as the source. Authorize DigitalOcean to read your
   repos if prompted, then pick `sunflower-dental-lab`, branch `main`,
   leave **Autodeploy** ✅ on.
3. DigitalOcean will scan the repo. It should detect:
   - **PHP web service** (because of `composer.json` and `Procfile`).
   - Source directory `/`.
4. Click **Edit** on the web service:
   - **Plan**: Basic, **$5/mo** (1 vCPU, 512 MB).
   - **HTTP routes**: leave `/`.
5. Click **Add Resource → Database**.
   - **Engine**: MySQL 8.
   - **Plan**: For production, pick **Production database / 1 GB / 10 GB SSD
     (~$15/mo)**. For testing, pick **Dev Database** (free, but warning: not
     backed up, deleted with the app).
   - Name it `db` (this exact name is what the env vars in step 6 reference).
6. **Region**: choose **SGP1 (Singapore)** — closest to Malaysia gives the
   lowest latency.
7. **App name**: `sunflower-dental-lab` (or whatever you prefer).
8. Click **Create Resources**. Initial build takes ~5 minutes.

---

## Step 4 — Set environment variables

While the first build runs, go to **Settings → App-Level Environment Variables**
and click **Edit**. Add these (copy-paste each row):

| Key                | Value                                           | Type   |
| ------------------ | ----------------------------------------------- | ------ |
| `DB_HOST`          | `${db.HOSTNAME}`                                | Plain  |
| `DB_PORT`          | `${db.PORT}`                                    | Plain  |
| `DB_NAME`          | `${db.DATABASE}`                                | Plain  |
| `DB_USER`          | `${db.USERNAME}`                                | Plain  |
| `DB_PASS`          | `${db.PASSWORD}`                                | **Encrypted** |
| `DB_SSL`           | `1`                                             | Plain  |
| `APP_ENV`          | `production`                                    | Plain  |
| `APP_DEBUG`        | `0`                                             | Plain  |
| `APP_TZ`           | `Asia/Kuala_Lumpur`                             | Plain  |
| `COOKIE_SECURE`    | `1`                                             | Plain  |
| `SESSION_NAME`     | `sfdl_sess`                                     | Plain  |
| `SESSION_LIFETIME` | `43200`                                         | Plain  |
| `BUSINESS_NAME`    | `Sunflower Malaysia Dental Lab`                 | Plain  |
| `BUSINESS_PHONE`   | `011-56739132`                                  | Plain  |
| `BUSINESS_ADDRESS` | *(your address, optional)*                      | Plain  |
| `INVOICE_PREFIX`   | `SF`                                            | Plain  |

> The `${db.HOSTNAME}` placeholders are App Platform's way of injecting the
> Managed MySQL connection details into your service. Don't type literal
> hostnames — paste the placeholders exactly as shown.

Click **Save**. App Platform will redeploy automatically (~3 minutes).

---

## Step 5 — Run the installer

Once the build is **Active**, open the **Console** tab on your `web` service
and run:

```bash
php bin/install.php --user=admin --pass='ChangeMe-To-Something-Strong-1!' --display='Administrator'
```

You should see:

```
› Checking database connection...
  ✓ Connected.
› Creating tables (if missing)...
  ✓ Schema applied.
› Seeding categories, products and default settings...
  ✓ 6 categories, 86 products in DB.
› Setting up admin user...
  ✓ Created admin user 'admin'.

Done. You can now visit your site and sign in.
```

> **Important:** change that password immediately after first login (Settings
> → Change password). Or pick a strong one upfront.

---

## Step 6 — Visit your live site

In the **Overview** tab, find your app's default URL — something like
`https://sunflower-dental-lab-xxxx.ondigitalocean.app`. Open
`/login.php` and sign in with the credentials you just created.

🎉 Your app is live with automatic HTTPS.

---

## Step 7 — Add a custom domain (optional)

You'll need to **register a domain** first — DigitalOcean does *not* sell
domain names. Recommended registrars:

- **Cloudflare Registrar** — at-cost prices, no markup (`.com` ~US$10/yr).
- **Namecheap** — easy, very common.
- **exabytes.my** or **shinjiru.com.my** — for `.my` / `.com.my` domains.

Once you own a domain (e.g. `mylab.com`):

1. In your DigitalOcean app: **Settings → Domains → Add Domain**.
2. Enter `mylab.com` (and optionally `www.mylab.com`).
3. DigitalOcean will give you either:
   - **CNAME records** to add at your registrar, OR
   - **Nameservers** if you let DigitalOcean manage DNS for the whole domain.
4. At your registrar's DNS settings, add the records DigitalOcean shows.
5. Wait 5–60 minutes for DNS propagation. SSL is automatic via Let's Encrypt.

You can verify DNS propagation with:

```bash
dig mylab.com +short
```

Once it points to the right place, your app is live at
`https://mylab.com`.

---

## Maintenance

### View logs
**Activity → Runtime Logs**, or in the Console tab `tail -f` is implicit
because PHP errors are logged to stderr and surfaced in real time.

### Connect to MySQL directly
**Database → Connection Details → mysql client flag**. Copy the `mysql`
command, paste in your terminal, and you're in.

```bash
mysql --host=... --user=doadmin --password=... --port=25060 --ssl-mode=REQUIRED defaultdb
```

You can also connect any MySQL GUI (TablePlus, DBeaver, etc.) using the same
host/port/user/password/SSL settings.

### Daily backups
Production-tier Managed MySQL is **backed up daily for free, retained 7 days**
by DigitalOcean. To restore: **Database → Settings → Restore from a backup**.

### Push code updates
Just `git push` to `main`. App Platform auto-deploys (zero-downtime). The
build logs are visible in the **Activity** tab.

### Run migrations / re-seed later
The `bin/install.php` script is **idempotent** — safe to re-run any time. It
will not overwrite existing products or your admin user. To add a new admin:

```bash
php bin/install.php --user=alice --pass='SomeStrongPwd!' --display='Alice Tan' --skip-seed
```

---

## Cheaper test setup (~$5/mo)

If you only want to evaluate the app without committing to $20/mo:

1. In step 3, when adding the database, pick **Dev Database** instead of
   Production. It's **free** but:
   - Limited to 512 MB RAM and ~1 GB storage.
   - **Not backed up.**
   - Deleted automatically if you destroy the app.
   - Should not be used for real customer data.
2. Total cost drops to just the **$5/mo web service**.

When you're ready to go live, you can **Add Resource → Database** with the
$15/mo production tier, then re-run `php bin/install.php` against the new
database (you'll lose any test data — that's fine for evaluation).

---

## Troubleshooting

| Symptom                                  | Likely cause / fix |
| ---------------------------------------- | ------------------ |
| `500 server_error` on every request      | Missing env vars. Check **Settings → App-Level Environment Variables**. Make sure all `DB_*` vars are set and `DB_PASS` is the **Encrypted** type. |
| Build fails with "no PHP detected"       | Make sure `composer.json` and `Procfile` exist at the **repo root**, not inside a subfolder. |
| `Database connection failed`             | Confirm `DB_SSL=1` is set. DigitalOcean Managed MySQL requires TLS. |
| Login works but every page reloads to login | `COOKIE_SECURE=1` is right for HTTPS but breaks plain HTTP. Make sure you're visiting the `https://` URL, not `http://`. |
| `419 csrf_failed` toasts                 | Browser blocked third-party cookies. Confirm the SameSite policy is `Lax` (it is by default in `Bootstrap::startSession`). |
| Want to reset the admin password         | Run `php bin/install.php --user=admin --pass='NewPwd!' --skip-seed` in the Console. |
| Want to wipe all data and start over     | Console → `mysql ... -e "DROP DATABASE sunflower; CREATE DATABASE sunflower;"` then re-run installer. |

---

## What I cannot do for you

To be transparent: a few things in this process **must** be done by you
personally, no matter who builds the app:

1. **Buy the domain.** Domain registration legally requires your name,
   address, and payment method. I cannot purchase one for you.
2. **Create the DigitalOcean account.** Same reason — it's tied to your
   identity and credit card.
3. **Apply for the $200 free-credit promotion.** This is bound to your DO
   account. New accounts get it automatically; you don't need a code.

Everything else — code, schema, deployment config, this guide — is in this
repository and ready to go.
