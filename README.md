# Bella Hair & Makeup — Online Booking Demo

A demo online booking platform for a fictional hair & makeup studio, built as a
portfolio piece. All business details, customers, and credentials are **dummy data**.

**Live demo:** https://bella-hair-makeup.vercel.app

---

## Features

- **Availability-first calendar** — live view of open dates and time slots per service,
  business operating hours, closed days, and per-stylist availability.
- **Multi-service booking** — pick a date, time, service (with sub-types / lengths /
  add-ons), enter your details, and submit.
- **Booking confirmation** — a confirmation screen plus a **pre-filled WhatsApp message**
  to the studio (demo number). With a database connected, it can instead take a 50%
  deposit via PayFast.
- **Admin back office** — dashboard, booking management (reschedule / cancel / edit),
  users, settings, activity logs, and CSV export.
- **Runs with or without a database** — the public site and the booking flow work in a
  DB-free demo mode; connect MySQL to enable persistence, payments, and admin.

## Tech stack

- **PHP** (procedural, `mysqli`) — no framework
- **MySQL / MariaDB** — optional; falls back to an in-memory catalog for the demo
- **Vanilla JS + CSS** — no build step
- **PayFast** (sandbox) for deposit payments
- Deployed on **Vercel** via the community PHP runtime (`vercel-php`, see `vercel.json`)

## Run locally

```bash
cp .env.example .env      # values are demo/sandbox placeholders
php -S localhost:8000      # then open http://localhost:8000
```

The public pages and the full booking flow work **without a database** (demo mode). To
enable persistence, PayFast deposits, and the admin back office, point the `DB_*` values in
`.env` at a MySQL/MariaDB instance and apply the schema in `db/migrations/`.

## Project layout

| Path | Purpose |
|---|---|
| `index.php`, `about.php`, `services.php`, `policy.php` | Public marketing pages |
| `book.php` | Booking calendar + form |
| `booking.php` | Booking submission handler (validation, confirmation / checkout) |
| `availability.php` | JSON availability endpoint that powers the calendar |
| `itn.php`, `success.php`, `cancel.php` | PayFast payment callbacks & return pages |
| `admin-*.php` | Admin back office |
| `config.php` | Configuration, service catalog, pricing, and the availability engine |
| `db/migrations/` | SQL schema |

## Notes

- This is a **demo with dummy data** and PayFast **sandbox** credentials — not production-ready.
- On serverless hosting (Vercel) there is no MySQL and the filesystem is read-only, so the
  demo runs DB-free and bookings hand off to WhatsApp on a confirmation screen.
