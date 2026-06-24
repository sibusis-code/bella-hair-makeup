# Bella Hair | Makeup — Booking System Plan

> **Purpose of this document:** Understand the Bella business, its services and prices, and the
> system that is already in place — then lay out exactly what is needed to turn the booking
> feature into a complete, reliable system that **takes bookings, collects payment, saves
> appointments, and handles the full appointment flow.**
>
> This is a **planning document only**. No website code is changed here. The public website
> (Home, About, Services, Policy) stays as-is — our focus is the **booking engine** behind it.
>
> 📐 **Blueprint:** The redesign of the booking *flow* is captured in
> [docs/BOOKING-BLUEPRINT.md](docs/BOOKING-BLUEPRINT.md) — the living "starting point" guide we
> build on. Read that for the agreed availability-first flow; read this README for the wider
> business and system context.

---

## 1. The Business

**Bella Hair | Makeup** is a luxury hair and makeup studio in Gauteng, South Africa.
*("Bella" means "God" in Spanish — the brand is built around the spirit of servitude and
personalised service.)*

- **Flagship studio:** 12 Demo Street, Sandton, Midrand — *walk-ins welcome + appointments*
- **Satellite studio:** Copperleaf Golf & Country Estate, Centurion — *strictly appointment-only*
- **Mobile service:** stylists travel to the client (homes, weddings, events, film & corporate shoots)
- **Track record:** 10,000+ clients served, 751 portfolio posts

**Contact & social**
- WhatsApp: **071 234 5678** · Landline: **010 500 7562**
- Instagram: [@bella_hair_and_makeup](https://www.instagram.com/bella_hair_and_makeup)
- TikTok: [@bella_hair_and_makeup](https://www.tiktok.com/@bella_hair_and_makeup)
- Domain: `bellahairandmakeup.co.za`

**Operating hours**

| Day | Hours |
|---|---|
| Mon – Wed | 09:00 – 17:30 |
| Thu – Fri | 08:00 – 18:00 |
| Saturday | 08:00 – 17:00 |
| Sunday | Midrand 11:00 – 16:00 · Copperleaf closed |
| Public Holidays | 08:00 – 14:00 |

Early appointments from **05:00 by arrangement** carry a **+R200 surcharge**.

---

## 2. Booking Policy (rules the system must enforce)

- **Non-refundable 50% deposit** required to secure *every* appointment. The deposit is always
  calculated from the selected service price and deducted from the final bill.
- **Rescheduling** allowed up to **48 hours** before the appointment.
- **No refunds** for missed appointments. A no-show counts as a missed appointment.
- Arriving **more than 15 minutes late** = appointment treated as missed/cancelled.
- **Rectifications** done within 48 hours of treatment — **not applicable to makeup**; charged if
  caused by poor home care.

---

## 3. Services & Pricing

Every service price below is the **full service base price**. The client pays a **50% deposit
online (PayFast)** to confirm — Bella is **cashless**; the balance is paid at the appointment.

| Service | Base Price | Deposit (50%) | Notes |
|---|---:|---:|---|
| Braids | R1,600 | R800 | Sub-type + hair length required |
| Cornrows | R1,200 | R600 | Sub-type required |
| Ponytail | R800 | R400 | |
| Frontal Ponytail | R1,350 | R675 | |
| Relaxer | R300 | R150 | |
| Wig Installation | R1,500 | R750 | Install / 360 / frontal / glueless etc. |
| Hair Colour | R1,000 | R500 | Full colour, highlights, toner, root touch-up |
| Other Hair Styling | R1,000 | R500 | Silk press, updo, treatment & style |
| Makeup Artistry | R800 | R400 | Soft glam, full glam, photoshoot |
| Bridal Makeup | R1,350 | R675 | |
| Nails | R200 | R100 | **Mobile only** — technician comes to you |
| Lashes | R200 | R100 | **Mobile only** — technician comes to you |
| Undo (removal) | R50 | R25 | Cornrows R50 / Braids R150 |
| Mobile Service | R400 | R200 | +R200 travel; mobile fee added to actual service |
| Other | R1,000 | R500 | Custom / specify |

> Prices are defined in code (`getDefaultBookingCatalog()` in [config.php](config.php)) and can be
> overridden from the database catalog tables if those are populated. Wig pricing is shown as
> "price on request" on the public site but has a R1,500 base in the booking engine.

**Travel zones (mobile-only services — nails, lashes, mobile):**

| Zone | Fee | Example areas |
|---|---:|---|
| Zone A | R0 | Midrand, Centurion, Noordwyk, Kyalami, Waterfall, Sunninghill, Rivonia |
| Zone B | +R150 | Sandton, Randburg, Fourways, Roodepoort, Rosebank, Honeydew |
| Zone C | +R300 | Johannesburg CBD, Soweto, Germiston, Benoni, Vereeniging |

**Stylists & assignment rules**

| Stylist | Works on |
|---|---|
| Caro, Emma, Patience | Midrand general services |
| Lincy, Charity | Copperleaf general services |
| Itumeleng, Pamela | Makeup (all locations) |
| Marlyn, Ibongiwe | Wig installation & frontal ponytail |

- Up to **2 stylists can be booked per time slot** (`MAX_STYLISTS_PER_SLOT = 2`).
- Each service has a capacity and its own set of time slots (e.g. Braids: 07:30 / 11:30 / 14:30).

---

## 4. Technology Stack

- **Language:** PHP (procedural, `mysqli`)
- **Database:** MySQL (hosted on example-host.net / Xneelo — `your-db-host.example.com`, DB `bella_hair`)
- **Payments:** [PayFast](https://www.payfast.co.za) (South African gateway) — currently in **sandbox** mode
- **Email:** PHP `mail()` by default; optional SMTP supported
- **Config:** environment variables loaded from [.env](.env) via [config.php](config.php)
- **Timezone:** Africa/Johannesburg

---

## 5. The Booking System — What Already Exists

The booking engine is already substantially built. Here is the current architecture.

### Public-facing pages
| File | Role |
|---|---|
| [index.php](index.php) | Home page (marketing) |
| [services.php](services.php) | Services & pricing |
| [about.php](about.php) / [policy.php](policy.php) | About & booking policy |
| [booking.php](booking.php) | **The booking form + server-side processing** (multi-service supported) |
| [success.php](success.php) | PayFast return page (payment succeeded) |
| [cancel.php](cancel.php) | PayFast cancel page (client backed out) |
| [itn.php](itn.php) | **PayFast ITN webhook** — confirms payment server-to-server |

### Admin back office
| File | Role |
|---|---|
| [admin-login.php](admin-login.php) / [admin-logout.php](admin-logout.php) | Auth |
| [admin-dashboard.php](admin-dashboard.php) | Bookings overview & stats |
| [admin-booking-detail.php](admin-booking-detail.php) | View a single booking |
| [admin-booking-edit.php](admin-booking-edit.php) | Edit booking |
| [admin-booking-reschedule.php](admin-booking-reschedule.php) | Reschedule |
| [admin-booking-cancel.php](admin-booking-cancel.php) | Cancel |
| [admin-users.php](admin-users.php) / [admin-change-password.php](admin-change-password.php) | Admin user management |
| [admin-settings.php](admin-settings.php) | Business settings |
| [admin-logs.php](admin-logs.php) | System logs |
| [admin-export-csv.php](admin-export-csv.php) | Export bookings to CSV |
| [admin-functions.php](admin-functions.php) | Shared admin logic |

### Database tables
| Table | Purpose |
|---|---|
| `salon_bookings` | **Confirmed appointments** (paid online; legacy pending-cash kept read-only) |
| `booking_payment_attempts` | Pending checkout sessions (one row per slot, keyed by `m_payment_id`) |
| `system_logs` | Errors, payments, emails, auth events |
| `booking_services`, `booking_service_subtypes`, `booking_locations`, `booking_stylists`, `booking_time_slots`, `booking_service_stylists` | *Optional* DB-driven catalog (falls back to code defaults if absent) |
| Admin/user & notes tables | Admin accounts, booking notes |

### The booking flow (current behaviour)

```
Client fills booking.php form (1 or more services)
        │
        ▼
Server validates input + checks slot & stylist availability
        │
        ▼
Generates m_payment_id  →  writes row(s) to booking_payment_attempts
        │
        └── Payment = ONLINE DEPOSIT (PayFast)   ← cashless: the only path
                 └─ builds signed PayFast form → redirects client to PayFast
                            │
                            ├─ return_url  → success.php   (client sees "thank you")
                            ├─ cancel_url  → cancel.php    (client backed out)
                            └─ notify_url  → itn.php  (PayFast server confirms payment)
                                       │
                                       ▼
                            itn.php validates the payment with PayFast,
                            re-checks slot/stylist capacity, then writes the
                            paid booking(s) to salon_bookings (status = paid),
                            marks attempts paid, and sends confirmation emails.
```

**What works well today**
- Multi-service / multi-slot bookings in one checkout.
- 50% deposit auto-calculated per service; travel surcharge auto-detected by suburb.
- Double-booking protection at both form submission and ITN confirmation (slot + stylist).
- Idempotent ITN handling (PayFast can call it more than once safely).
- Cashless: online deposit (PayFast) is the only payment path.
- Graceful "database offline" mode so the marketing site never goes down.
- Email confirmations to client + admin (when enabled).

---

