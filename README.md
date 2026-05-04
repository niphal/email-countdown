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

## Local (XAMPP on Windows)

Place the project under `htdocs` (for example `htdocs/email_timer`), start Apache, then open:

`http://YOUR_HOST/email_timer/install.php` (or open `install.php` under whatever path you deployed)

Finish the wizard, then use **Sign in** on the dashboard.

## Authentication

- Until **`data/secrets.php`** contains a valid password hash, **`index.php`** redirects to **`install.php`** (and the API returns **503** with an `install` hint).
- **`index.php`** (dashboard) and **`api/timers.php`** require a signed-in session (password from `data/secrets.php`).
- **`timer.php`** stays **public** (no cookie): email clients must load countdown images without logging in.
- Sessions use **HttpOnly** cookies, **SameSite=Lax**, and **Secure** when the request is HTTPS.
- Login form includes a **CSRF** token. After **5 failed** password attempts, login is blocked for **60 seconds**.
- Use **Log out** (or `logout.php`) on shared machines.

## Usage

1. Open the web UI and sign in (`index.php` redirects to `login.php` when needed).
2. Create a timer (end time, colors, optional label).
3. Use **Copy HTML** and paste into your ESP’s **custom HTML** (Braze HTML editor, etc.).
4. Replace the `href="#"` link in the snippet with your real landing URL.
5. Copied HTML uses a **root-relative** image `src` (e.g. `/email_timer/timer.php?id=…`). In production, mail should be served over **HTTPS**; prepend your public origin on that path if your ESP does not resolve root-relative URLs correctly.

### Timer image URL

- **Default (animated GIF):**  
  `/PATH/timer.php?id=TIMER_ID` (root-relative; prepend `https://your-public-site` when pasting into some ESPs)  
  Steps the countdown about once per second for up to 20 seconds after each load (client behavior may vary).

- **Static PNG:**  
  Append `&format=png` for a single frame at request time.

- **Per-recipient end time (Braze Liquid, etc.):**  
  Append `&end=UNIX_TIMESTAMP` (seconds). That value overrides the stored end time when the image is generated.

## API (JSON)

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `api/timers.php` | List timers |
| `POST` | `api/timers.php` | Create timer (JSON body: `name`, `ends_at`, optional `label`, colors, `width`, `height`) |
| `DELETE` | `api/timers.php?id=ID` | Delete timer |

Unauthenticated API calls receive **401** with JSON `{ "error": "Unauthorized" }`.

## Project layout

```
install.php           # Web installer (run once)
auth.php              # Session login helpers
login.php / logout.php
api/timers.php        # JSON CRUD (auth required)
config.php            # SQLite, JSON helpers, root-relative timer URL helper
timer.php             # GIF (default) or PNG image (public)
index.php             # Dashboard UI (auth required)
scripts/setup_secrets.php, scripts/hash_password.php
lib/GifCreator.php
data/                 # DB, secrets, .htaccess (secrets + DB not in git)
```

## License

GPL-2.0 applies to `lib/GifCreator.php` (based on [Sybio/GifCreator](https://github.com/Sybio/GifCreator)). The rest of the app is provided as-is for your own use; add a license file if you need a explicit grant for the other files.
