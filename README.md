# Email countdown

Small PHP app to build **countdown timers for email** (e.g. [Braze](https://www.braze.com/)). Inboxes do not run JavaScript, so timers are served as **images**: an **animated GIF** by default (about 20 one-second frames from load time) or a **static PNG** if you prefer.

## Requirements

- PHP **8.0+** with extensions: **GD**, **PDO**, **pdo_sqlite**, **mbstring**
- A web server (Apache, nginx + PHP-FPM, etc.)
- Write access to `data/` so SQLite can create `data/app.db`

## Install (WordPress-style, in the browser)

1. Clone the repository (or copy files into your web root).

   ```bash
   git clone https://github.com/niphal/email-countdown.git
   ```

2. Point the site’s document root at the project folder, or serve it from a subdirectory (e.g. `/email_timer/`).

3. **Open `install.php` in your browser** (e.g. `https://yourdomain.com/email_timer/install.php`).  
   It checks PHP extensions and `data/` permissions, then asks for an **administrator password** and writes **`data/secrets.php`** (gitignored). After that you are sent to **login**.

4. Visiting **`index.php`** before install redirects to **`install.php`**. After install, `install.php` only shows “Already installed” unless you remove `data/secrets.php` to run setup again.

5. On Apache, `data/.htaccess` denies HTTP access to the database directory.

6. Enable **GD** in `php.ini` if image generation fails (`extension=gd`), then restart PHP / the web server.

### CLI alternative (same result as the web installer)

```bash
php scripts/setup_secrets.php "your-strong-password"
```

Replace an existing hash: `php scripts/setup_secrets.php --force "new-password"`.  
`php scripts/hash_password.php` only prints a bcrypt line if you edit `secrets.php` by hand.

## Local development (any PHP stack)

Nothing in the code assumes **XAMPP**. Deploy like any small PHP app: PHP **8+**, a web server (**Apache**, **nginx** + **php-fpm**, **Caddy**, built-in `php -S` for quick tests), SQLite + GD enabled in `php.ini`, and a writable `data/` directory. On production Linux/VPS hosting this is the normal setup.

Point the document root (or a URL path) at this project, then open `install.php` under that path (e.g. `https://dev.example.com/email_timer/install.php`). Finish the wizard, then sign in.

## Authentication

- Until **`data/secrets.php`** contains a valid password hash, **`index.php`** redirects to **`install.php`** (and the API returns **503** with an `install` hint).
- Optional **`public_base_url`** in **`data/secrets.php`** (HTTPS, no trailing slash): used as the origin for **absolute** `img src` URLs in copied email HTML. If unset, the app uses the current request’s `Host` (set it when the admin UI is only on an internal URL).
- Installer/CLI setup also creates **`timer_signing_key`** in `data/secrets.php` for signed dynamic timer URLs.
- Sign-in uses **`data/secrets.php`** to bootstrap a **seeded owner** in SQLite (`users` + `workspace_members`) on first successful login. Use the seeded email **`owner@local.invalid`** (shown on the login form) plus the installer password unless you added other users in the DB. Production teams can add more users directly in the database for now; roles are **`owner`**, **`admin`**, **`editor`**, **`viewer`** (viewers can list timers but cannot create, update, or delete).
- **`index.php`** (dashboard) and **`api/*.php`** admin endpoints require a signed-in session with valid user + workspace context.
- **`timer.php`** stays **public** (no cookie): email clients must load countdown images without logging in.
- Sessions use **HttpOnly** cookies, **SameSite=Lax**, and **Secure** when the request is HTTPS.
- Login form includes a **CSRF** token. After **5 failed** password attempts, login is blocked for **60 seconds**.
- **Forgot password token flow**: request at `forgot_password.php`, consume at `reset_password.php` (1 hour expiry, one-time use).
- By default, reset links are appended to `data/reset-links.log` on the server (and `mail()` is attempted if configured).
- Use **Log out** (or `logout.php`) on shared machines.

## Usage

1. Open the web UI and sign in (`index.php` redirects to `login.php` when needed).
2. Create a timer (end time, colors, optional label).
3. Use **Copy HTML** and paste into your ESP’s **custom HTML** (Braze HTML editor, etc.).
4. Replace the `href="#"` link in the snippet with your real landing URL.
5. Copied HTML uses a **full absolute** `img src` (from your public site or `public_base_url` in `data/secrets.php`). Use **HTTPS** in production.

### Timer image URL

- **Default (animated GIF):**  
  `https://YOUR_PUBLIC_ORIGIN/PATH/timer.php?id=TIMER_ID` (origin from the dashboard request or `public_base_url`)  
  Steps the countdown about once per second for up to 20 seconds after each load (client behavior may vary).

- **Static PNG:**  
  Append `&format=png` for a single frame at request time.

- **Per-recipient end time (Braze Liquid, etc.):**  
  Append `&end=UNIX_TIMESTAMP` (seconds). That value overrides the stored end time when the image is generated.

- **Signed dynamic override (recommended):**  
  Append `&end=...&sig=...` where `sig` is the timer-specific HMAC from the dashboard's **Copy Dynamic HTML** button.
  Example:
  `https://YOUR_PUBLIC_ORIGIN/PATH/timer.php?id=TIMER_ID&end={{event_properties.end_ts}}&sig=...`

## API (JSON)

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `api/timers.php` | List timers in the current workspace |
| `POST` | `api/timers.php` | Create timer (`owner` / `admin` / `editor` only; JSON body: `name`, `ends_at`, optional `label`, colors, dimensions, fonts, layout) |
| `PUT` | `api/timers.php` | Update timer (same roles; JSON body includes `id`) |
| `DELETE` | `api/timers.php?id=ID` | Delete timer (same roles) |
| `GET` | `api/audit.php` | Workspace audit log (`owner` / `admin` / `editor`; optional `limit` ≤ 100) |
| `GET` | `api/billing.php` | Workspace plan + usage entitlements (timer caps, premium features) |
| `GET/POST/PATCH` | `api/admin_members.php` | Member/role admin (`owner` / `admin`) |

Unauthenticated API calls receive **401** with JSON `{ "error": "Unauthorized" }`. Missing write permission returns **403**.

## Project layout

```
install.php           # Web installer (run once)
auth.php              # Session login helpers
login.php / logout.php
forgot_password.php   # Request password reset token
reset_password.php    # Consume token and set new password
api/timers.php        # JSON CRUD (auth + workspace scoped)
api/audit.php         # Workspace audit entries (auth)
api/billing.php       # Workspace billing + entitlements (auth)
api/admin_members.php # Member invite/update role + active status (admin)
lib/platform.php      # workspaces / users migrations + audit helpers
lib/monetization.php  # plan catalog + feature gating helpers
config.php            # SQLite, JSON helpers, root-relative timer URL helper
timer.php             # GIF (default) or PNG image (public)
index.php             # Dashboard UI (auth required)
admin.php             # Admin UI (members + billing)
scripts/setup_secrets.php, scripts/hash_password.php
lib/GifCreator.php
data/                 # DB, secrets, .htaccess (secrets + DB not in git)
```

## License

GPL-2.0 applies to `lib/GifCreator.php` (based on [Sybio/GifCreator](https://github.com/Sybio/GifCreator)). The rest of the app is provided as-is for your own use; add a license file if you need a explicit grant for the other files.
