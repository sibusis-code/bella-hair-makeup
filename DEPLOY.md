# Bella — Deployment & Go-Live Cutover

> Running/testing on your own machine first? See **[docs/RUN-LOCALLY.md](docs/RUN-LOCALLY.md)**
> (includes the "Service temporarily unavailable" fix: restart `php -S` after enabling mysqli).

This is plain PHP — **no build step**. Deploy = upload files to the Xneelo web root via the
control-panel File Manager or SFTP. The **live database is already migrated** (Phase 0 + dedup),
so the live site is currently running OLD code against the NEW schema — **uploading the code below
is urgent** (the live admin dashboard errors until you do).

---

## 1. Files to upload (code is ready locally)

**New files**
- `book.php`              — the calendar booking UI (new front door)
- `availability.php`      — availability JSON API
- `admin-block-slots.php` — admin Block Times screen
- `.htaccess`             — blocks `db/` + `docs/`, adds HSTS, `ErrorDocument 404`, keeps `.txt` public
- `robots.txt`           — crawl rules + sitemap pointer (blocks admin/db/docs)
- `sitemap.xml`          — public page sitemap
- `404.php`              — branded not-found page (wired via `.htaccess` ErrorDocument)
- `tools/optimize-images.php` — one-time CLI image optimiser (optional, run on server)

**Changed files**
- `config.php`            — availability engine + DB session timezone (+02:00) + **GA4 helper**
- `mail-functions.php`    — **real SMTP client** (STARTTLS/SSL + AUTH LOGIN, multipart plain+HTML)
- `booking.php`           — headless processor; **email now required**; **cash booking sends client
                            receipt + Bella notification**
- `itn.php`               — per-service capacity + Braids helper + add-ons; **amount no longer logged**
- `admin-functions.php`   — single stylist source + **CSRF helpers** (`csrfToken/csrfField/csrfVerify`),
                            **`requireAdminRole()`**, SameSite=Strict cookie, 8-char min password
- `admin-dashboard.php`, `admin-booking-detail.php`, `admin-booking-edit.php`,
  `admin-booking-cancel.php`, `admin-booking-reschedule.php`, `admin-change-password.php`,
  `admin-users.php`, `admin-settings.php`, `admin-block-slots.php`
                          — **CSRF token on every POST form + verify on submit**; destructive/
                            management pages now gated to `admin`/`manager` via `requireAdminRole`
- `book.php`             — site header/nav/footer + Google Fonts, canonical/OG/favicon, keyboard
                            a11y (calendar + chips), `for=` labels, deposit estimate, WhatsApp fallback
- `index.php`            — canonical/OG/favicon + LocalBusiness JSON-LD; gallery imgs `loading="lazy"`
- `about.php`, `services.php`, `policy.php` — canonical + favicon (lazy img on about)
- `index.php`, `cancel.php`, `success.php` — "Book Now"/CTA links point to `book.php`

**Cashless + staff update (2026-06-16) — also upload these changed files:**
- `book.php`             — **cash option removed** (online deposit only)
- `booking.php`          — **cashless**: only `online_deposit` accepted; cash branch removed
- `admin-booking-edit.php` — `pending_cash` no longer a selectable status
- `admin-settings.php`   — **staff management**: add stylist / remove (delete if no bookings) +
                            existing retire (deactivate) & service Mappings
- `config.php`, `js/main.js` — stylist roster default updated (Charmaine → Itumeleng)
- The **Charmaine → Itumeleng swap is already applied to the live database** (the change was made
  against the live DB). Recorded in `db/migrations/2026-06-16_cashless_and_staff_swap.sql` (for the
  restore trail — that file is NOT uploaded; `db/` is web-blocked).

**Hair colour selection (2026-06-18) — upload these 3 changed files only:**
- `config.php`   — adds `getHairColourGroups()` / `hairColourGroupFor()` / `allowedHairColourValues()`
                   (the three owner colour ranges: Braids/Cornrows, French Curl, Goddess Braids)
- `book.php`     — Step-4 **Hair colour** picker (style-aware) + review-summary row
- `booking.php`  — server validation: a valid in-range colour is **required** for braids/cornrows
- **No DB change** — reuses the existing `salon_bookings.hairpiece_color` column (already live;
  confirmed present on `salon_bookings` + `booking_payment_attempts`). The admin detail page and CSV
  export already display this column, so they need **no** re-upload.

**Per-km mobile travel pricing (2026-06-18) — upload these files:**
- `travel-quote.php` *(new)* — live travel-fee quote endpoint for the booking UI
- `config.php`   — Google Maps config + `computeMobileTravelFee()` / `googleDrivingDistanceKm()`
                   (R10/km driving distance from the Midrand studio, **round trip**)
- `book.php`     — address **autocomplete** + live travel-fee line + review row + `mobilePlaceId`
- `booking.php`  — authoritative server-side travel fee (recomputed before payment)
- `.htaccess`    — CSP updated to allow `maps.googleapis.com` / `maps.gstatic.com`
- **No DB change** — the fee is stored in the existing `salon_bookings.travel_surcharge` column.
- **Safe without keys:** until the Google keys are set in `.env`, the system automatically falls
  back to the zone-based surcharge (R0/R150/R300) and the autocomplete simply doesn't load — the
  site keeps working. Per-km activates the moment the keys are added (see §3e).

**Pricing + payment fixes (2026-06-18) — upload these files, then run the SQL:**
Addresses owner feedback (length pricing, add-on pricing, full-payment, name-fix).
- `book.php`     — (1) **name validation parity** so an initial/short name is caught on the form
                   (no more bounce to step 1); (2) **hair-length surcharges** shown in the picker +
                   deposit; (3) **add-on prices** shown in the review + rolled into the deposit;
                   (4) **"Pay 50% deposit / Pay in full"** choice on the review step.
- `config.php`   — `getHairLengthSurcharges()` (Bra +R150 / Waist +R300 / Butt +R450),
                   `paymentMethodLabel()`; allows the `online_full` method.
- `booking.php`  — authoritative amount = service + length + add-ons + travel; honours the
                   50%/100% choice; accepts `online_full`.
- `mail-functions.php` — receipt says **"Paid in Full"** vs **"Deposit Paid (50%)"**.
- `admin-booking-detail.php` — shows a readable payment method (deposit vs paid-in-full).
- **DB:** run `db/migrations/2026-06-18_pricing_length_addons_fullpay.sql` (sets the **add-on prices**:
  Small +R200, Colour blend +R100, Curly ends +R50, BYO −R200). **Apply it together with the code**
  — applying it earlier would raise the deposit before the booking page can explain why. Length
  surcharges and the full-payment option are **code only** (no DB rows). The `byo-bundle` discount is
  negative; the `price` column is signed `DECIMAL(10,2)`, so that's fine.

**Cancel-resume + flow re-sequence (2026-06-18) — upload these files:**
- `retry-payment.php` *(new)* — resumes a cancelled-but-still-held PayFast payment (rebuilds the
  same held cart from `booking_payment_attempts` and re-posts; ITN stays the authoritative gate).
- `cancel.php`   — now reads `?ref=` and offers **"Resume payment"** (back to where they left off)
                   instead of restarting; falls back to "Start booking" when there's no reference.
- `booking.php`  — sets the PayFast `cancel_url` to include `?ref=<m_payment_id>`.
- `book.php`     — **re-sequenced**: all service options (sub-type, length, **braid size, cornrow
                   length, hair colour, add-ons**) are now chosen in **Step 1** (before the date);
                   Step 4 is just the client's details. Also enforces braid-size client-side
                   (was server-only → used to bounce).
- `config.php`   — `getServiceAddons()` (per-service add-ons for Step 1).
- **No DB change.**

**Multi-service "Build your visit" (2026-06-18) — upload these files, then run the SQL:**
Clients can add several services to one same-day visit (the salon arranges the schedule).
- `book.php`     — Step 1 is now a **"Build your visit"** builder: configure a service, **＋ Add to
                   my visit**, repeat; the first service anchors the date/time; combined pricing.
- `config.php`   — `resolveAdditionalServices()` (validates + prices extras server-side) +
                   `getServiceAddons()`.
- `booking.php`  — folds the additional services into the deposit/full amount; stores them.
- `itn.php`      — carries `additional_services` into `salon_bookings`; passes `payment_method` +
                   extras to the receipt email.
- `mail-functions.php` — receipt lists the additional services.
- `admin-booking-detail.php` — shows the additional services + extras total on the booking.
- **DB:** run `db/migrations/2026-06-18_multiservice_build_your_visit.sql` (adds `additional_services`
  + `additional_services_total` to `salon_bookings` and `booking_payment_attempts`). The code also
  self-heals these columns on first use, but run the SQL so they exist immediately.
- **Single-service bookings are unchanged** (one service = configure + "See dates", as before).

**Owner price list ingest (2026-06-19) — upload these files, then run the SQL:**
Replaces the flat `base_price` + uniform length surcharge with the owner's **per-type, per-length**
prices (pricing.md), and adds new categories.
- `config.php`   — price matrix (`getDefaultServicePriceMatrix`) + resolvers (`getBookingItemPrice`,
                   `getServicePriceOptions`); catalog now carries `priceMatrix` (prefers DB rows,
                   falls back to code); goddess braids **split** into Knotless/Normal; new services
                   locs/sewin/wash in the offline catalog; `booking_service_prices` self-heals;
                   retired the old `getHairLengthSurcharges()`.
- `book.php`     — **data-driven length dropdown** (shows each length's price from the matrix);
                   removed the Small/Medium/Large/Jumbo size field; item/visit pricing reads the matrix.
- `booking.php`  — anchor + additional-service prices come from the matrix; length validated against
                   the type's price rows.
- `services.php` — public price list now mirrors the owner's `pricing.md` **exactly** (no other
                   prices/services): removed "Super Double Drawn Wig" and "Bridal Makeup" (not on the
                   owner's list), corrected Dark n Lovely to **R250**, and dropped the
                   "indicative examples / prices may vary" disclaimers. Static HTML — upload as-is.
- **DB:** run `db/migrations/2026-06-19_pricing_ingest.sql` — creates `booking_service_prices`, adds
  the **locs / sewin / wash** services, reconciles subtypes (goddess split, cornrows straight-up,
  wig styles/labour, relaxer brands, makeup, washes/undo), seeds **all 100 price rows**, and sets the
  add-ons (Small R200, Colour Blend R100, Curling Ends R50, **+ Beads R200, French Curl Ends R250**).
  *(Supersedes the add-on values in `2026-06-18_pricing_length_addons_fullpay.sql`.)*
- **This SQL is REQUIRED** — the new prices/subtypes/services only appear once it runs. The code
  falls back to the matrix transcription for existing services, but new services need the seed.
- Nails & Lashes aren't in pricing.md → they keep their current base price (matrix falls back). The
  two "Swiss Frontal" tiers and the deactivated cornrow subtypes (fulani, with-extensions) are flagged
  for owner confirmation.

**Do NOT upload** (keep off the server / they're git-ignored): `.env` stays as the server's own
copy, `db/backups/` (local PII snapshots), `_*.php` temp files (already deleted).

> Tip: upload `config.php`, `admin-functions.php`, `booking.php`, `itn.php` **together** so code and
> schema stay consistent during the swap.

---

## 2. Post-deploy smoke test (do immediately, sandbox still on)
- `https://bellahairandmakeup.co.za/book.php` loads with the calendar.
- `https://bellahairandmakeup.co.za/booking.php` → redirects to `book.php`.
- Step 5 shows **only** the online PayFast deposit (no cash option).
- Make a test **online deposit** booking (sandbox) → PayFast → returns to `success.php`.
- Log into `/admin-login.php` (`demoadmin`) → dashboard loads with **no SQL error**, the test
  booking shows the real stylist name → cancel/delete the test row.
- Settings → **Makeup shows Itumeleng (not Charmaine)**; try **Add staff member** then remove it.
- Block Times: add a block → it shows on the calendar → remove it.
- **PII check:** open `https://bellahairandmakeup.co.za/db/backups/` and any `.json` → expect **403**.
- **SEO:** `/robots.txt` and `/sitemap.xml` load (200, not 403); a bad URL like `/nope.php` shows the branded **404** page.
- **CSRF:** admin actions (mark complete, cancel, add user, save settings) still work normally
  after login. Re-using an old/forged form without the token → "Security check failed" (403).
- **Roles:** a `staff` user opening `admin-settings.php`/`admin-users.php`/cancel/edit → "Access denied".

---

## 2.5 Maintenance mode (put the site "under construction" while you test)
Keep real visitors out of the live site while the team tests, without taking it down.

- **Go dark:** create an empty file named **`.maintenance`** in the web root (Xneelo File Manager →
  New File). The public now sees a branded "We'll be right back" page (HTTP 503, SEO-safe). Admin
  pages (`/admin-*`) and the PayFast callback (`itn.php`) keep working.
- **Let the team in:** share this one-time link (uses `MAINTENANCE_BYPASS_KEY` from `.env`):
  `https://bellahairandmakeup.co.za/book.php?preview=YOUR_KEY`
  Opening it sets a 7-day cookie so that browser/device sees the **full real site** (booking +
  calendar + payment). `?preview=off` clears it. Works on phone and desktop.
- **Go live:** delete the `.maintenance` file. (Alternatively, `MAINTENANCE_MODE=true` in `.env`
  does the same as the file.)
- Set a strong **`MAINTENANCE_BYPASS_KEY`** in the server `.env` and don't share it publicly.

## 3. Go-live cutover (ONLY when you're ready to take real money)

These are the remaining CRITICAL items. Do them at the moment of going live, not during testing.

### 3a. PayFast → live  (`.env` on the server)
```
PAYFAST_SANDBOX=false
PAYFAST_MERCHANT_ID=<live merchant id>
PAYFAST_MERCHANT_KEY=<live merchant key>
PAYFAST_PASSPHRASE=<the passphrase set in your PayFast dashboard>   # required for live
```
Keep `PAYFAST_NOTIFY_URL_OVERRIDE=https://bellahairandmakeup.co.za/itn.php`.
Then do **one real low-value transaction** end-to-end and confirm the booking shows `paid` in admin;
refund it from the PayFast dashboard.

### 3b. Email deliverability  ✅ SMTP now implemented
`mail-functions.php` now has a real SMTP client (STARTTLS on 587, SSL on 465, `AUTH LOGIN`,
multipart plain+HTML). Bare `mail()` from a shared host usually lands in spam, so use SMTP:

1. In **Xneelo konsoleH** create the mailbox `bookings@bellahairandmakeup.co.za`.
2. On the server `.env` set:
   ```
   EMAIL_USE_SMTP=true
   EMAIL_SMTP_HOST=<the SMTP/outgoing server konsoleH lists for that mailbox>
   EMAIL_SMTP_PORT=587            # or 465 for SSL
   EMAIL_SMTP_USER=bookings@bellahairandmakeup.co.za
   EMAIL_SMTP_PASS=<the mailbox password>
   SEND_CLIENT_EMAILS=true
   SEND_ADMIN_EMAILS=true
   ```
3. (Recommended, complementary) add **SPF/DKIM/DMARC** DNS for the domain so the mail authenticates.

Test: make a real booking (cash and online) → the **client receipt** and the **Bella notification**
should land in the inbox, not spam. If SMTP auth fails, the code automatically falls back to `mail()`
and logs the reason to the PHP error log.

> Note: **email is now required** in the booking form so every booking has an address for the receipt.
> The client receipt is sent on payment (online) and on submit (cash); Bella gets a copy both ways.

### 3c. Google Analytics 4 (optional, do anytime)
Already wired — it just needs your ID:
1. Go to **analytics.google.com** → create a property (or use an existing one) → **Admin → Data
   Streams → Web** → add `https://bellahairandmakeup.co.za` → copy the **Measurement ID** (`G-XXXXXXXXXX`).
2. Put it in the server `.env`: `GA4_MEASUREMENT_ID=G-XXXXXXXXXX`.
The tag then loads automatically on the home, services, about, policy, booking and success pages.
If the field is blank, no analytics tag is emitted (zero overhead).

### 3d. Image optimisation (optional, do anytime)
`loading="lazy"` is already in the markup. To shrink the gallery JPEGs:
- **Easiest (no install):** drag the files in `images/hair` and `images/make-up` through
  **tinypng.com** or **squoosh.app**, then re-upload the smaller versions; **or**
- **One command on the server** (its PHP has GD): `php tools/optimize-images.php` — recompresses to
  ~82% and caps width at 1600px, backing up originals to `images/_originals/`. Safe to re-run.

### 3e. Per-kilometre mobile travel pricing (optional, do anytime)
Already wired — it needs two Google Maps API keys. Until they're set, mobile bookings use the simple
zone surcharge (R0/R150/R300) and the address autocomplete just doesn't appear; nothing breaks.

1. Go to **console.cloud.google.com** → create a project → **enable billing** (Google's free monthly
   Maps credit typically covers a salon's volume).
2. **APIs & Services → Library** → enable: **Maps JavaScript API**, **Places API**, **Distance Matrix API**.
3. **Credentials → Create credentials → API key**, make **two** keys and restrict each:
   - **Browser key** — *Application restriction:* HTTP referrers → `https://bellahairandmakeup.co.za/*`.
     *API restriction:* Maps JavaScript API + Places API.
   - **Server key** — *Application restriction:* IP addresses → your server's IP.
     *API restriction:* Distance Matrix API.
4. Put them in the server `.env`:
   ```
   GOOGLE_MAPS_BROWSER_KEY=<the referrer-restricted key>
   GOOGLE_MAPS_SERVER_KEY=<the IP-restricted key>
   TRAVEL_RATE_PER_KM=10              # owner spec
   TRAVEL_ROUND_TRIP=true            # charge there & back
   TRAVEL_ORIGIN_ADDRESS=12 Demo Street, Sandton, Johannesburg, 2196, South Africa
   TRAVEL_MAX_KM=0                   # optional cap (0 = none)
   ```
5. Test: on `book.php` pick a mobile service → start typing an address → choose a suggestion → the
   **Travel fee (round trip)** line appears and is included in the deposit. Confirm the same figure
   lands in admin (`travel_surcharge`). If a key/quota fails, it silently falls back to the zone fee.

> Security: the **browser** key is visible in page source by design — the referrer restriction is what
> protects it. The **server** key must stay only in `.env` (never in client code) and be IP-locked.

---

## 4. Still open after this round (tracked in the audit plan)
**Done in this batch (just upload):** CSRF on all admin forms; admin role separation; `book.php`
chrome (header/nav/footer/fonts) + keyboard a11y + deposit estimate + WhatsApp fallback; SEO
(robots.txt, sitemap.xml, canonical, OG, favicon, LocalBusiness schema); custom 404; image
`loading="lazy"`; DB session timezone (+02:00); SameSite=Strict cookie; 8-char min password;
amount removed from ITN logs.

**Now wired — owner just supplies a value / flips a switch (see §3):**
- **Email** — SMTP client implemented; create the Xneelo mailbox + set `EMAIL_USE_SMTP=true` and the
  4 SMTP vars (§3b). Email is now required on the form; receipts go to client + Bella.
- **GA4** — tag wired; paste `GA4_MEASUREMENT_ID=G-XXXXXXXXXX` in `.env` (§3c).
- **Images** — `loading="lazy"` done; run `php tools/optimize-images.php` or use TinyPNG (§3d).
- **PayFast live** — §3a, at go-live.

Lower-priority hardening still open: DB-level unique guard on paid slots (the ITN re-check already
prevents double-booking, so this is belt-and-suspenders), and moving `db/backups/` physically
outside the web root (currently blocked via `.htaccess`).
