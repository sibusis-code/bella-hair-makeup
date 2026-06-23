# Bella — Running & Testing Locally

This is plain PHP (no build step). You run it with PHP's built-in web server.

## 1. Requirements
- **PHP 8.x** with the **`mysqli`** and **`openssl`** extensions enabled.
- Network access to the database host (the site connects to the Xneelo MySQL server
  `your-db-host.example.com`; if that host is unreachable from your machine the site still
  renders, see §5).

### Enable mysqli (one-time)
The booking engine and admin CRM need the `mysqli` driver. In `C:\php\php.ini` make sure this
line is **uncommented** (no leading `;`):
```
extension=mysqli
```
`extension_dir = "ext"` must also be set (it is by default). Confirm it's on:
```
php -r "echo extension_loaded('mysqli') ? 'mysqli ON' : 'mysqli OFF';"
```

## 2. Start the dev server
```
cd c:\Users\user1\Documents\MPLAI\Bella
php -S 127.0.0.1:8000
```
Then open **http://127.0.0.1:8000/index.php** (or `/book.php`, `/admin-login.php`).

> ⚠️ **If you change `php.ini`, you MUST restart the `php -S` server.** A running server reads
> `php.ini` only once at startup. This is the #1 cause of confusing local errors — see §4.

## 3. What to test
- **Public site:** index, services, about, policy.
- **Booking flow:** `/book.php` → pick service → calendar → time/stylist → details → review → pay.
  Use **cash** (`Pay 50% cash on arrival`) to avoid PayFast while testing; it saves a `pending_cash`
  booking and (if email is on) sends a receipt.
- **Admin CRM:** `/admin-login.php` (user `demoadmin`). Dashboard, block times, edit/cancel.

## 4. Troubleshooting

### "Service temporarily unavailable" on the calendar / availability step
`book.php` renders, but moving past the calendar shows this message. It means
`availability.php` could not get a database connection. Causes, in order of likelihood:

1. **The dev server is running with an old `php.ini` (mysqli was off when it started).**
   Fresh `php -r`/`php -l` commands work, but the long-running `php -S` server doesn't.
   **Fix:** stop the server (Ctrl-C in its window) and start it again.
   To stop a stray server on port 8000 from PowerShell:
   ```
   Get-NetTCPConnection -LocalPort 8000 -State Listen | %{ Stop-Process -Id $_.OwningProcess -Force }
   ```
2. **A stale "DB offline" breaker file.** If the DB ever looked slow/unreachable, the app
   suppresses further DB probes for `DB_OFFLINE_GRACE_SECONDS` (default 300s = 5 min). Clear it:
   ```
   del .runtime\*.marker
   ```
3. **The DB connect timeout is too short.** A remote DB handshake can take >1s. Keep
   `DB_CONNECT_TIMEOUT_SECONDS=5` in `.env` (we raised it from 1). On the production server the DB
   is local, so this is never an issue there.

Quick check that the endpoint itself is healthy (returns JSON, not the error):
```
curl "http://127.0.0.1:8000/availability.php?service=braids&location=midrand&date_from=2026-06-20&date_to=2026-06-25"
```

### "undefined function mysqli_report" / "Database driver unavailable"
The `mysqli` extension isn't enabled in the PHP that's running — see §1, then restart the server.

### Emails don't arrive locally
Local PHP can't send mail without an SMTP server. Configure SMTP in `.env`
(`EMAIL_USE_SMTP=true` + host/user/pass) to test real delivery, or just confirm the booking
saved in the admin CRM. See `DEPLOY.md` §3b.

## 4b. Maintenance / "coming soon" mode
To hide the site from visitors while testing (locally or live):
- **On:** create an empty file `.maintenance` in the project root (`type NUL > .maintenance` on
  Windows, or just `New Item`), or set `MAINTENANCE_MODE=true` in `.env`. Restart `php -S` if you
  changed `.env` (the file toggle is picked up live, no restart needed).
- **Bypass (see the real site):** open any page with `?preview=<MAINTENANCE_BYPASS_KEY>` (the key is
  in `.env`) — sets a 7-day cookie. `?preview=off` clears it. `/admin-*` and `itn.php` are never blocked.
- **Off:** delete `.maintenance` (or set `MAINTENANCE_MODE=false`).
Quick check: `curl -I http://127.0.0.1:8000/index.php` shows **503** when on, **200** when off.

## 5. Does the site need the database to work?
**Marketing pages do not.** If the DB (or the `mysqli` driver) is unavailable, `config.php`
degrades gracefully: `tryGetDbConnection()` returns `null` and pages fall back to the built-in
default catalog. So **index / services / about / policy render fully even with the DB down.**

The parts that genuinely need the database pause with a friendly message instead of crashing:
- `book.php` calendar → shows a "WhatsApp us to book" fallback.
- `booking.php` submit → "Booking submissions are temporarily unavailable… try again shortly."
- Admin CRM / `itn.php` → a clear 503 "Database driver unavailable".

This behaviour is intentional and is covered by the offline-mode logic in `config.php`
(`tryGetDbConnection`, `markDbOffline`, `isDbProbeSuppressed`).
