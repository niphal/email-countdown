# Email countdown

Small PHP app to build **countdown timers for email** (e.g. [Braze](https://www.braze.com/)). Inboxes do not run JavaScript, so timers are served as **images**: an **animated GIF** by default (about 20 one-second frames from load time) or a **static PNG** if you prefer.

## Requirements

- PHP **8.0+** with extensions: **GD**, **PDO**, **pdo_sqlite**, **mbstring**
- A web server (Apache, nginx + PHP-FPM, etc.)
- Write access to `data/` so SQLite can create `data/app.db`

## Install

1. Clone the repository (or copy files into your web root).

   ```bash
   git clone https://github.com/niphal/email-countdown.git
   ```

2. Point the site’s document root at the project folder, or serve it from a subdirectory (e.g. `/email_timer/`).

3. **Configure login** (creates `data/secrets.php` and a bcrypt hash):

   ```bash
   php scripts/setup_secrets.php "your-strong-password"
   ```

   To replace an existing hash: `php scripts/setup_secrets.php --force "new-password"`  
   (Advanced: `php scripts/hash_password.php` only prints a hash if you prefer to edit the file by hand.)  
   `data/secrets.php` is **gitignored** — do not commit it.

4. Ensure `data/` exists and is writable by the PHP user. The app creates `data/app.db` on first use.

5. On Apache, `data/.htaccess` denies HTTP access to the database directory.

6. Enable **GD** in `php.ini` if images fail (`extension=gd`), then restart PHP / the web server.

## Local (XAMPP on Windows)

Place the project under `htdocs` (for example `htdocs/email_timer`), start Apache, and open:

`http://localhost/email_timer/`

Then open `login.php`, sign in with the password you hashed, and you’ll be redirected to the dashboard.

## Authentication

- **`index.php`** (dashboard) and **`api/timers.php`** require a signed-in session (password from `data/secrets.php`).
- **`timer.php`** stays **public** (no cookie): email clients must load countdown images without logging in.
- Sessions use **HttpOnly** cookies, **SameSite=Lax**, and **Secure** when the request is HTTPS.
- Login form includes a **CSRF** token. After **5 failed** password attempts, login is blocked for **60 seconds**.
- Use **Log out** (or `logout.php`) on shared machines.

## Usage

1. Open the web UI and sign in (`index.php` redirects to `login.php` when needed).
2. Create a timer (end time, colors, optional label).
3. Use **Copy HTML** and paste into your ESP’s **custom HTML** (Braze HTML editor, etc.).
4. Replace the placeholder `https://example.com` link with your real landing URL.
5. For production email, the image URL must be **HTTPS** on a public host (not `localhost`).

### Timer image URL

- **Default (animated GIF):**  
  `https://YOUR_DOMAIN/PATH/timer.php?id=TIMER_ID`  
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
auth.php              # Session login helpers
login.php / logout.php
api/timers.php        # JSON CRUD (auth required)
config.php            # SQLite path + helpers
timer.php             # GIF (default) or PNG image (public)
index.php             # Dashboard UI (auth required)
scripts/hash_password.php
lib/GifCreator.php
data/                 # DB, secrets, .htaccess (secrets + DB not in git)
```

## License

GPL-2.0 applies to `lib/GifCreator.php` (based on [Sybio/GifCreator](https://github.com/Sybio/GifCreator)). The rest of the app is provided as-is for your own use; add a license file if you need a explicit grant for the other files.
