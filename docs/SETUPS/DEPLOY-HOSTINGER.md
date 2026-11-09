# Deploy to Hostinger (combatpro.site)

How to put the platform live on Hostinger shared hosting (hPanel, with SSH access).

## The big picture

Everything runs on one domain. The frontend and the API share `https://combatpro.site`:

| Part | Local | Live |
|---|---|---|
| Frontend (React) | `localhost:5173` | `https://combatpro.site` |
| Backend (Laravel API) | `localhost:8000/api` | `https://combatpro.site/api` |
| Database | Laragon MySQL | MySQL database made in hPanel |
| `php artisan dev` (email worker + scheduler) | your terminal | two cron jobs |

One domain means no subdomain to set up and no CORS problems (the browser sees one site).

## Server details

| Item | Value |
|---|---|
| SSH | `ssh -p 65002 u257225233@147.93.78.135` |
| Home folder | `/home/u257225233` |
| PHP (SSH default) | 8.3.36 at `/usr/bin/php` (too old; the app needs 8.4.1+) |
| PHP 8.4 (use this) | `/opt/alt/php84/usr/bin/php` |
| Composer / Git | 2.9.8 / 2.52.0 |
| Website folder | `/home/u257225233/domains/combatpro.site/public_html` |

Never write the SSH or database passwords in this file.

The old site (Vue + old Laravel) was deleted from `public_html` in November 2026. Its MySQL database was not
touched; delete it in hPanel → Databases if it is not needed.

## Folder layout on the server

```
/home/u257225233/
├── combat/                          ← the project, OUTSIDE the website folder (visitors cannot reach it)
│   ├── backend/                     ← Laravel: app/, config/, vendor/, .env, storage/ ...
│   └── frontend/                    ← source only, not used by the live site
│
└── domains/combatpro.site/
    ├── DO_NOT_UPLOAD_HERE           ← Hostinger marker, leave it
    └── public_html/                 ← the live website
        ├── index.html, assets/      ← React build (contents of frontend/dist)
        ├── .htaccess                ← /api and /up go to Laravel, everything else to React
        ├── index.php                ← small file that starts ~/combat/backend
        └── storage → /home/u257225233/combat/backend/storage/app/public   (uploaded photos)
```

Only `public_html` is reachable from the web. `.env` and its passwords stay in `~/combat/backend`, outside it.

## Before you start

The server gets the code from GitHub (`github.com/ChocooChocoo/project-combat`), not from your PC.
Commit, push and merge your work into `main` first. Uncommitted changes will not reach the server.

`backend/.env`, `frontend/.env` and `frontend/dist` are not in Git (on purpose). You create `.env` on the
server by hand and upload `dist/` yourself.

## Step 1: Prepare Hostinger (hPanel)

1. **PHP version** — Advanced → PHP Configuration → PHP **8.4**. The locked packages need PHP 8.4.1 or newer (8.3 gives a
   500 error: "Your Composer dependencies require a PHP version >= 8.4.1"). The website and SSH use separate PHP
   versions. In SSH, `php` is 8.3, so run every PHP command below as `/opt/alt/php84/usr/bin/php`.
2. **Database** — Databases → MySQL Databases. Create one and write down the database name, username and
   password. Hostinger adds a prefix, like `u257225233_combat`.
3. **SSL** — turn on free SSL for `combatpro.site`.

## Step 2: Connect over SSH

From PowerShell on your PC (Windows has `ssh` built in):

```bash
ssh -p 65002 u257225233@147.93.78.135
```

## Step 3: Let the server read the GitHub repo

Skip this if the repo is public.

For a private repo, make a read-only deploy key on the server:

```bash
ssh-keygen -t ed25519 -C "hostinger"     # press Enter at every question
cat ~/.ssh/id_ed25519.pub                # copy the line it prints
```

On GitHub: repo → Settings → Deploy keys → Add deploy key. Paste the line. Leave "write access" off.

## Step 4: Get the code and install the backend

```bash
cd ~
git clone git@github.com:ChocooChocoo/project-combat.git combat   # public repo: use the https:// URL
cd ~/combat/backend
/opt/alt/php84/usr/bin/php $(which composer) install --no-dev --optimize-autoloader
cp .env.example .env
```

`--no-dev` skips testing tools. A live server does not need them.

## Step 5: Fill in the live `.env`

Open it with `nano .env` (save: Ctrl+O, Enter, Ctrl+X). Values that differ from local:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://combatpro.site
FRONTEND_URL=https://combatpro.site

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u257225233_combat
DB_USERNAME=u257225233_user
DB_PASSWORD=your-db-password

QUEUE_CONNECTION=database
SANCTUM_EXPIRATION=480

# Copy the MAIL_* lines (Gmail SMTP) from your local backend/.env
```

Why these matter:

- `APP_DEBUG=false` — `true` would show error details and secrets to visitors.
- `APP_URL` — builds links to uploaded photos. Wrong value = photos do not show.
- `FRONTEND_URL` — allowed site for API calls (CORS) and the base for links inside emails (for example
  employee invites). No slash at the end.
- `DB_HOST` — usually `localhost` on Hostinger; hPanel shows the exact value.

Then create the app secret key:

```bash
/opt/alt/php84/usr/bin/php artisan key:generate
```

## Step 6: Set up the database and app

```bash
/opt/alt/php84/usr/bin/php artisan migrate --force            # create all tables (--force is required in production)
/opt/alt/php84/usr/bin/php artisan app:sync-permissions       # load the permission catalog
/opt/alt/php84/usr/bin/php artisan app:seed-system-roles      # starter roles
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
```

Do not run `DatabaseSeeder` on the live site unless you want its sample data there.

## Step 7: Connect the website folder to Laravel

Create `public_html/index.php`. It is Laravel's normal `public/index.php` with paths pointing to
`~/combat/backend`:

```bash
cat > ~/domains/combatpro.site/public_html/index.php <<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = '/home/u257225233/combat/backend';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP
```

Link uploaded photos into the website folder:

```bash
ln -s /home/u257225233/combat/backend/storage/app/public ~/domains/combatpro.site/public_html/storage
```

This replaces `php artisan storage:link`, which links into `backend/public` (not served on this layout).

## Step 8: Cron jobs (replace `php artisan dev`)

Shared hosting cannot keep a program running all the time, so cron runs it every minute.
hPanel → Advanced → Cron Jobs → add two jobs, both **every minute**:

```
/opt/alt/php84/usr/bin/php /home/u257225233/combat/backend/artisan schedule:run
/opt/alt/php84/usr/bin/php /home/u257225233/combat/backend/artisan queue:work --stop-when-empty --max-time=55
```

- Job 1 runs the scheduled tasks in `backend/routes/console.php` (membership expiry, reminders,
  no-shows, staff absences, token cleanup).
- Job 2 is the email worker. Without it, verification codes and other emails are never sent.

## Step 9: Build and upload the frontend (from your PC)

Create `frontend/.env.production`:

```env
VITE_API_URL=https://combatpro.site/api
```

Create `frontend/public/.htaccess` (Vite copies it into `dist/` on every build):

```apache
DirectoryIndex index.html

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /

    # Send www.combatpro.site to combatpro.site
    RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
    RewriteRule ^ https://%1%{REQUEST_URI} [L,R=301]

    # Send http to https
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Pass the login token (Authorization header) to Laravel
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # API and health check go to Laravel
    RewriteRule ^(api|up)(/.*)?$ index.php [L]

    # Real files (assets, photos in /storage) are served as they are
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Everything else is a React route: refreshing /gyms/5/schedule loads index.html
    RewriteRule ^ index.html [L]
</IfModule>
```

- The Authorization rule matters: without it Apache drops the token and every API call returns 401.
- The www redirect keeps every visitor on one address, so CORS and saved logins stay consistent.

Build and upload:

```bash
cd frontend
pnpm build
scp -P 65002 -r dist/* u257225233@147.93.78.135:~/domains/combatpro.site/public_html/
scp -P 65002 dist/.htaccess u257225233@147.93.78.135:~/domains/combatpro.site/public_html/
```

- `scp` uses capital `-P` for the port (`ssh` uses small `-p`).
- The second line is needed because `dist/*` skips hidden files like `.htaccess`.
- Do not delete `index.php` or the `storage` link in `public_html` when you replace frontend files.

## Step 10: Make yourself platform admin

Register your account on `https://combatpro.site` first, then on the server:

```bash
cd ~/combat/backend
/opt/alt/php84/usr/bin/php artisan app:make-admin your@email.com
```

## Step 11: Test

1. Open `https://combatpro.site/up` — Laravel's health page should load.
2. Open `https://combatpro.site`, register, and check that the verification email arrives within a
   minute or two (proves the queue cron works).
3. Log in and click through a few screens. Refresh a deep page like `/gyms/...` — it should not 404.
4. Upload a profile photo and check it shows.
5. Open `https://www.combatpro.site` — it should jump to `https://combatpro.site`.
6. Problems? Read `~/combat/backend/storage/logs/laravel.log`.

## Updating later

Backend (on the server):

```bash
cd ~/combat && git pull
cd backend && /opt/alt/php84/usr/bin/php $(which composer) install --no-dev --optimize-autoloader
/opt/alt/php84/usr/bin/php artisan migrate --force && /opt/alt/php84/usr/bin/php artisan config:cache && /opt/alt/php84/usr/bin/php artisan route:cache
```

Frontend (on your PC): `pnpm build`, then the two `scp` lines from Step 9.

### Going live with gym subscriptions (P05)

Do these once, the first time you deploy the subscription plans, staff grace minimum and payroll attendance counts.

1. **Back up the live database first** (hPanel → Databases → phpMyAdmin → Export).
2. **Run the update above.** The new migrations add the plans (Starter, Pro, Elite), the 30-day trial setting
   and payroll's late-day count. They also give every gym that is already approved a 30-day trial from today,
   and raise any staff grace setting below 10 minutes to 10.
3. **Check the `schedule:run` cron from Step 8 is running.** It sends owners the "trial ends soon" notice at
   08:10 each day. Nothing else needs a new cron; plan limits are worked out on every request.
4. **Point PayMongo at the live webhook.** PayMongo dashboard → Developers → Webhooks → add
   `https://combatpro.site/api/webhooks/paymongo` for the event `checkout_session.payment.paid`, and put its
   signing secret in `PAYMONGO_WEBHOOK_SECRET` in the live `.env` (then `/opt/alt/php84/usr/bin/php artisan config:cache`). Without it,
   payments still count when the owner returns from PayMongo, but not if they close the tab first.
5. **Use live PayMongo keys** (`pk_live_…` / `sk_live_…`) in the live `.env` only when you want real money
   taken. Test keys only accept test payments.
6. **Set the real prices and modules.** Sign in as platform admin → Subscription plans. Pro (PHP 1,499) and
   Elite (PHP 2,999) start as placeholders. The trial length is on the same screen.
7. **Test once:** approve a test gym, check its owner sees "Free trial", then pay for a plan with a PayMongo
   test payment before switching to live keys.

## Optional: log in without a password

On your PC:

```bash
ssh-keygen -t ed25519                    # only if you have no key yet
type $env:USERPROFILE\.ssh\id_ed25519.pub
```

Paste the output into hPanel → SSH Access → SSH Keys.

## Troubleshooting

- **`.env` change does nothing** — run `/opt/alt/php84/usr/bin/php artisan config:cache` again. The cached settings win over the file.
- **Every API call returns 401 after login** — the Authorization rule is missing from `public_html/.htaccess`.
- **API calls return the React page instead of JSON** — the `^(api|up)` rule is missing or `index.php` is
  not in `public_html`.
- **Emails never arrive, no queue errors** — some hosts block outgoing Gmail SMTP. Try port 465 with
  `MAIL_SCHEME=smtps`, or use a Hostinger email account.
- **Paths do not match** — Hostinger menu names and folders change sometimes. Use what hPanel shows.
- **`schedule:run` logs "The Process class relies on proc_open"** — Hostinger disables `proc_open`, which Laravel
  uses to launch scheduled commands. `backend/routes/console.php` therefore uses `Schedule::call(fn () =>
  Artisan::call('...'))` (runs in-process) instead of `Schedule::command(...)`. Keep it that way.
- **Cron job runs but nothing happens** — in hPanel's cron form, pick the **Custom** command type. The default PHP type
  puts `/usr/bin/php /home/u257225233/` in front of what you type (PHP 8.3, wrong). Use the full
  `/opt/alt/php84/usr/bin/php ...` command (Step 8).
- **Sample data on the live site** — `db:seed --force` wipes every app table and loads demo data. All seeded accounts use
  the password `Password_123`, including the platform admin `admin@combatplatform.ph`. On a public site, change those
  passwords or remove the seeded users before real use.
