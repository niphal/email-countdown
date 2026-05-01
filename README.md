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

3. Ensure `data/` exists and is writable by the PHP user. The app creates `data/app.db` on first use.

4. On Apache, `data/.htaccess` denies HTTP access to the database directory.

5. Enable **GD** in `php.ini` if images fail (`extension=gd`), then restart PHP / the web server.

## Local (XAMPP on Windows)

Place the project under `htdocs` (for example `htdocs/email_timer`), start Apache, and open:

`http://localhost/email_timer/`

## Usage

1. Open the web UI (`index.php`).
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

There is no built-in authentication; deploy behind a private network, HTTP auth, or your own gate if the UI should not be public.

## Project layout

```
api/timers.php   # JSON CRUD
config.php       # SQLite path + helpers
timer.php        # GIF (default) or PNG image
index.php        # Dashboard UI
lib/GifCreator.php
data/            # SQLite DB (not in git) + .htaccess
```

## License

GPL-2.0 applies to `lib/GifCreator.php` (based on [Sybio/GifCreator](https://github.com/Sybio/GifCreator)). The rest of the app is provided as-is for your own use; add a license file if you need a explicit grant for the other files.
