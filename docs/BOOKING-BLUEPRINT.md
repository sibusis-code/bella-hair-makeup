# Bella Booking System — Blueprint (Living Document)

> **This is the foundation.** It defines the *starting point* for the booking-system redesign and
> is the reference every future change builds on. As we add features, we extend this document —
> we do not throw it away. Keep the [Changelog](#changelog) at the bottom up to date.
>
> **Status:** Blueprint v2.8 — *The calendar flow ([../book.php](../book.php)) is now the **only**
> booking system; the old standard form is retired. The whole site routes to it, and the database
> has been de-duplicated (single stylist source). Phases 0–5 + admin portal complete. Go-live
> hardening done (CSRF, admin roles, SEO/404, SMTP email, DB timezone). **Email is now required and
> every booking emails a receipt to the client and a notification to Bella.** **The business is now
> CASHLESS — every booking is paid online (PayFast 50% deposit); there is no cash option.** Admins
> manage their own staff (add / retire / remove / assign to services) in Settings.*
> **Scope:** The booking engine only (not the marketing website).
> Companion overview: [../README.md](../README.md).
> Running/testing locally + troubleshooting: [RUN-LOCALLY.md](RUN-LOCALLY.md).
> Deploying to the live server: [../DEPLOY.md](../DEPLOY.md).

---

## 1. The Core Idea

**Let the visitor see real availability *before* they fill in anything.**

Today the flow is *form-first*: the visitor types in all their details, picks a date/time, and only
when they submit do they discover the slot or stylist is already taken. That is frustrating and
loses bookings.

The new flow is **availability-first**: the visitor makes two quick picks (service + location),
immediately sees a **calendar of open dates and times with live, per-stylist availability**, picks
an open slot, and *only then* enters their details and pays. They decide fast, on real information.

---

## 2. The Decided Flow (Blueprint v1)

> **Locked decisions** (2026-06-14):
> 1. **Entry order:** *Service + location (+ sub-type / hair length) first → calendar → slot →
>    details → pay.* Sub-type and length are chosen **before** the calendar so availability is
>    accurate (they can affect duration/eligibility). *(Decision #4.)*
> 2. **Slot detail level:** *Per-stylist availability* — each slot shows which named stylists are
>    free, and the visitor can choose their stylist right there.
> 3. **Slot hold:** *5 minutes* while the visitor checks out. *(Decision #2.)*
> 4. **Multi-service:** *client-driven sequential booking with automatic clash-blocking* — the
>    visitor books one service/slot at a time; each booked slot is removed from what the next
>    service can pick, so two of their own bookings can never overlap. *(Decision #3.)*
> 5. **Admin can block time** so the calendar never overbooks against walk-ins. *(Decision #1.)*

```
STEP 1 — Choose what & where           (fast picks, before any form)
   ┌─────────────────────────────────────────┐
   │  Service:   [ Braids ▾ ]                 │
   │  Location:  [ Midrand ▾ ]                │
   │  Sub-type / hair length: [ ... ▾ ]       │
   │    (only when the service has options;   │
   │     shown here so the calendar is exact) │
   └─────────────────────────────────────────┘
                     │  (drives which slots & stylists exist)
                     ▼
STEP 2 — Pick a date                   (calendar = the centrepiece)
   ┌─────────────────────────────────────────┐
   │   June 2026        ‹  ›                   │
   │   Mo Tu We Th Fr Sa Su                    │
   │    2  3  4  5  6  7  ·                     │
   │    ●  ●  ◐  ✕  ●  ●                        │
   │   ● open   ◐ nearly full   ✕ full/closed  │
   └─────────────────────────────────────────┘
                     │
                     ▼
STEP 3 — Pick a time + stylist         (per-stylist availability)
   ┌─────────────────────────────────────────┐
   │  Thursday 5 June — Braids @ Midrand      │
   │                                          │
   │  07:30   Caro ✓    Emma ✕                 │
   │  11:30   Caro ✕    Emma ✓                 │
   │  14:30   Caro ✓    Emma ✓                 │
   │                                          │
   │  ○ No preference (we assign a free one)  │
   └─────────────────────────────────────────┘
                     │  (slot + stylist now chosen)
                     ▼
STEP 4 — Your details                  (only now do they type)
   ┌─────────────────────────────────────────┐
   │  Name · Phone · Email (REQUIRED — used   │
   │    to email the booking receipt)         │
   │  Remaining options (braid size,          │
   │    mobile address…)  — sub-type/length   │
   │    already chosen in Step 1              │
   │  + Add another service  (optional →      │
   │    re-runs Steps 1–3; already-booked     │
   │    slots are clash-blocked)              │
   └─────────────────────────────────────────┘
                     │
                     ▼
STEP 5 — Review & pay   (CASHLESS — online only)
   ┌─────────────────────────────────────────┐
   │  Summary of slot(s) + stylist(s)         │
   │  Deposit = 50% (+ travel fee if mobile)  │
   │  Pay:  ◉ Online deposit (PayFast)        │
   │        (no cash option — fully online)   │
   └─────────────────────────────────────────┘
                     │
                     ▼
STEP 6 — Confirmation
   After PayFast ITN confirms the deposit, the booking is saved as PAID
   and a receipt is emailed to the client + Bella.
```

### Payments — cashless (online only)
Bella is a **fully online / cashless** booking system. Every booking is paid **online via PayFast**;
there is **no "pay cash on arrival"** option anywhere in the flow. At Step 5 the client chooses how
much to pay online (owner spec 2026-06-18):
- **50% deposit** (default, non-refundable) — balance settled on the day; **or**
- **Pay in full (100%)** — for returning/regular clients who'd rather settle up front.
- The amount is **server-computed** in `booking.php` (service base + hair-length surcharge + add-ons
  at the chosen %, plus travel in full); `booking.php` accepts `paymentMethod` `online_deposit` or
  `online_full`. The slot is held for 5 minutes during checkout and saved as **`paid`** once the
  PayFast **ITN** confirms. Receipts/admin show "Deposit Paid (50%)" vs "Paid in Full".
- Legacy `pending_cash` records (from before the cashless move) still display/export in the CRM, but
  the status is never produced again and is not selectable when editing a booking.

### Staff management (admin-run)
Admins/managers manage their own team in **Settings** ([../admin-settings.php](../admin-settings.php)),
no developer needed:
- **Add staff** — enter a name; a unique `stylist_key` is generated and the person is created active.
- **Retire staff** — untick **Active** in the Stylists card (keeps their booking history; hides them
  from new bookings). This is the recommended "remove".
- **Delete staff** — the **Remove** button hard-deletes, but only if the person has **no bookings on
  record** (otherwise it refuses and tells you to retire instead).
- **Assign to services** — the **Mappings** card controls which service × location each stylist
  appears for (e.g. Makeup → Itumeleng, Pamela).
This is the single source of truth (`booking_stylists` + `booking_service_stylists`); the booking
calendar and availability engine read from it live.

### Why service + location come first
Availability is **not global** — it depends on what's being booked:
- **Time slots differ per service *and per day*** (Braids: Mon–Fri 07:30 / 11:30 / 14:30, but
  Sun only 10:30 / 13:30 because of shorter Sunday hours; Makeup: 9 slots/day; some services have
  none predefined). See the [Service Operations Matrix](#3a-service-operations-matrix-business-rules).
- **Stylists are tied to service + location** (see mapping below).
- **Capacity is per slot** (`MAX_STYLISTS_PER_SLOT = 2`).

So the calendar can only show the truth once it knows the service and location. Those two picks are
quick and feel nothing like "filling in a form."

---

## 3. How Availability Is Calculated (the rules behind the calendar)

These rules already exist in the codebase and the calendar must mirror them exactly so what the
visitor sees matches what actually gets booked.

**Eligible stylists** = the stylists allowed for the chosen *service + location*:

| Service group | Eligible stylists |
|---|---|
| General services @ Midrand | Caro, Emma, Patience |
| General services @ Copperleaf | Lincy, Charity |
| Makeup (any location) | Itumeleng, Pamela |
| Wig installation & frontal ponytail | Marlyn, Ibongiwe |
| Mobile-only (nails, lashes) | All stylists |

**A specific stylist is FREE for a date+time when:**
- They are an eligible stylist for that service+location, **and**
- They have **no** existing `salon_bookings` row at that date+time with status `paid` or
  `pending_cash`, **and**
- They have **no active (non-expired) slot hold** at that date+time (a checkout in progress;
  holds last **5 minutes** — Decision #2), **and**
- That date+time is **not admin-blocked** for them / for the slot (Decision #1).

**A time slot is OPEN when:**
- It is one of the service's valid slots (or a valid custom/early slot), **and**
- The number of bookings already in that slot is **below capacity** (1 for Braids, 2 for
  Cornrows/Hair styling/Makeup — see §3A), **and**
- At least one eligible stylist is still free (≥ 2 free braiders for Braids), **and**
- The slot is **not admin-blocked**, **and**
- It is not entirely consumed by active 5-minute holds.

**A day is shown as:**
- `● open` — has open slots, `◐ nearly full` — few slots left, `✕ full/closed` — no open slots,
  outside operating hours, a closed day, or in the past.

**Day/hours constraints to honour:**
- Operating hours per day (Mon–Wed 09:00–17:30, Thu–Fri 08:00–18:00, Sat 08:00–17:00,
  Sun Midrand 11:00–16:00 / **Copperleaf closed**, Public holidays 08:00–14:00).
- Copperleaf is **appointment-only**; Midrand allows walk-ins. Walk-ins don't go through this flow
  but still consume stylist time, so **staff block that time via the admin block-slot action**
  (Decision #1) and the calendar then shows it as unavailable.
- Early slots from **05:00 are shown on the calendar** with a clear **+R200** note (Decision #6),
  rather than being hidden "by arrangement" — the visitor can self-book them and sees the surcharge
  up front.
- Mobile-only services force location = *Mobile* and require an address (travel fee by zone).

---

## 3A. Service Operations Matrix (business rules)

> These are **firm business-operations rules** that drive how slots and stylists are counted. They
> define, per service: how many clients fit in one time slot, how many stylists each client needs,
> and the slot times by day. The availability engine and calendar **must** encode these exactly.

| Service | Slots by day | Clients per slot | Stylists per client | Stylist choice |
|---|---|---:|---:|---|
| **Braids** | Mon–Fri: 07:30, 11:30, 14:30 (3) · Sun: 10:30, 13:30 (2) | **1** | **2** (lead + helper) | Client picks preferred **lead** braider **only**; the helper is **auto-assigned** (Decision #8) |
| **Cornrows** | Same times as Braids by day for now (Mon–Fri 3 / Sun 2) — confirm with owners | **2** | **1** | Each client picks their preferred stylist |
| **Hair styling** | Same times as Braids by day for now — confirm with owners | **2** | **1** | Each client picks their preferred stylist |
| **Makeup** | Same times as Braids by day for now — confirm with owners | **2** | **1** | Each client picks their preferred stylist |

> **Day-specific slots apply to all four services** (Decision #9): Cornrows, Hair styling and Makeup
> also use the reduced Sunday set, not just Braids. **For now they share Braids' slot times**
> (Decision #10) — to be confirmed with the owners during testing.

*(Other services — wig installation, nails, lashes, relaxer, undo, mobile — follow the standard
"1 client / 1 stylist" model unless stated otherwise. To be confirmed as we build — see
[Resolved Decisions](#7-resolved-decisions-answered-2026-06-14).)*

### The two operating models

**Model A — "Two-on-one" (Braids).**
A slot holds **one client**, and **both** assigned braiders work on that single client together.
The client chooses a **preferred lead braider**; the system pairs a **second braider as helper**.
Booking a braids slot therefore:
- fills the slot completely (no second client can be added to it), **and**
- occupies **two** braiders for that time.

So a braids slot is **OPEN** only when it has no client yet **and at least two eligible braiders
are free** for that time.

**Model B — "Two clients per slot" (Cornrows, Hair styling, Makeup).**
A slot holds **up to two clients at once**, each worked on by **one** stylist. Each client picks
their own preferred stylist. The slot still has room for a second client (with the other stylist)
until both places are taken.

So one of these slots is **OPEN** when **fewer than 2 clients** are booked in it **and** the
client's chosen stylist is still free.

### How this refines the availability rules (Section 3)

- "Clients per slot" (1 for Braids, 2 for Cornrows/Hair styling/Makeup) is the real capacity the
  calendar shows — not a single global number.
- **Braids slot OPEN** = no client booked yet **AND** ≥ 2 eligible braiders free.
- **Capacity-2 slot OPEN** = clients booked < 2 **AND** the chosen stylist is free.
- **Slots are day-of-week dependent** — Sunday uses a reduced set (Braids: 10:30 / 13:30), and all
  services must respect each day's operating hours and closed days.

### Per-stylist display nuance (vs Blueprint v1 decision)

- **Capacity-2 services** → show per-stylist free/busy exactly as decided in Blueprint v1: the
  visitor sees which named stylists are free in each slot and picks one.
- **Braids (two-on-one)** → the slot itself shows **open / full** (1 client max). The visitor
  chooses their **preferred lead braider** from the braiders who are free; the **helper is assigned
  automatically** — the client does **not** choose the helper (Decision #8, resolved).

### Gaps vs code — status after the 2026-06-14 schema alignment

> The live DB is **database-driven** (see [BUILD-PHASE-0-1.md](BUILD-PHASE-0-1.md)). Most of these
> gaps were closed at the **data/schema** layer on 2026-06-14; the remaining ones are **code wiring**
> for Phase 1.

- ✅ **Per-service capacity** — added `booking_services.capacity` (Cornrows now 2, Braids 1, etc.);
  `config.php` reads it. *(Note: hair-colour/other-styling/relaxer kept at 1 — Decision #7.)*
- ✅ **Day-aware slots** — added `booking_service_slots.day_group` with weekday + Sunday (10:30/13:30)
  sets for all four+ services; missing time slots inserted.
- ✅ **5-minute hold storage** — `booking_payment_attempts.hold_expires_at` added.
- ✅ **Admin block-slot store** — `booking_slot_blocks` table created.
- ✅ **Add-ons storage** — `booking_addons` (+ mapping) seeded; `addons`/`addons_total`/
  `helper_stylist` columns added.
- ✅ **Cash status** — `pending_cash` added to `salon_bookings.status` enum.
- ✅ **ITN per-service capacity** — `itn.php` now re-checks via the shared engine
  (`availabilityRecheckSlot`): per-service capacity, Braids two-on-one, blocks; auto-assigns the
  helper. **(Phase 1 done.)**
- ✅ **Day-aware availability + holds + blocks** — live `availability.php` + engine in `config.php`;
  `booking.php` sets a 5-minute hold and routes its pre-pay check through the same engine.
- ✅ **Add-ons** — stored on attempts/bookings and surfaced by `availability.php`; the add-on
  selection **UI** is delivered with the Phase 2 calendar (totals are 0 until then).
- 🟡 **"Hair styling" scope** (Decision #7) still tentative — confirm exact service keys with owner.

---

## 3B. Add-ons (extras the client can choose)

> Add-ons are **optional extras attached to a service**, not separate bookings. They don't change
> the slot/stylist availability rules (§3) — they refine the **price** and the **details** of an
> existing booking. They're chosen in **Step 1's options** (alongside sub-type / length) so the
> price shown before checkout is correct.

**Common add-ons (Braids & Cornrows):**

| Add-on | Applies to | Meaning | Price |
|---|---|---|---|
| **Small** | Braids, Cornrows | Smaller braid/cornrow size (more time, finer work) | Surcharge — *TBD, confirm with owners* |
| **Colour blend** | Braids, Cornrows | Mixing/blending colour into the style | Surcharge — *TBD* |
| **Curly ends** | Braids, Cornrows | Curled ends finish | Surcharge — *TBD* |

**Weave sew-in:**

| Option | Meaning | Effect on price |
|---|---|---|
| **Bring your own bundle (BYO)** | Client supplies their own hair bundle for the sew-in | Reduces/changes the price vs salon-supplied — *exact rule TBD, confirm with owners* |

### Rules for add-ons
- **Multiple add-ons can be selected** on one service (e.g. Small + Colour blend + Curly ends).
- Each selected add-on is **recorded on the booking** and shown on the Step 5 review summary so the
  client sees exactly what they're paying for.
- Add-ons **adjust the total**, and therefore the **50% deposit** is calculated on the
  add-on-inclusive total.
- Add-ons **do not** change slot capacity or stylist eligibility — a booking with add-ons still
  occupies the same slot/stylist(s) as without.
- ⚠️ *To revisit when we build:* whether heavy add-ons (e.g. Small) should extend the booking's
  duration. For now, **fixed slots** (Decision #5) — add-ons are price-only.

> **Status:** add-on **list** is captured; **prices/amounts are TBD** and will be confirmed with the
> owners during testing. This list will grow as more add-ons are identified.

---

## 3C. Hair colour selection (owner spec, 2026-06-18)

Braiding clients choose the **hair-extension colour** as part of the booking. The colour range is
**style-aware** — the options shown depend on the style picked in Step 1 — so clients only ever see
colours that style actually comes in. Selecting a colour is **required** for these styles (we need it
to order the extensions); other services show no colour field.

| Colour range | Applies to | Codes offered |
|---|---|---|
| **Braids / Cornrows** | the `cornrows` service, and `braids` styles that aren't French Curl or Goddess | 1 (Black), 2 (Natural Black), 4 (Natural Brown), 30 (Copper Brown), 33 (Dark Brown), 27 (Golden Blonde), 1/30 (Ombré Copper Brown), 1/27 (Ombré Golden Blonde) |
| **French Curl** | `braids` sub-types containing *french-curl* (French Curls, Boho/Tribal/Knotless-Boho French Curls) | 1/30 (Ombré Black & Copper Brown), 1/27 (Ombré Black & Golden Brown), 1 (Black), C14 (3 Toned), 27 (Golden Blonde), 30 (Copper Brown) |
| **Goddess Braids** | `braids` sub-type *goddess-knotless-normal-braids* | 1 (Black), 2 (Natural Black), 4 (Dark Brown), 30 (Copper Brown), 27 (Golden Blonde), 27/613 (Colour Mix), 39 (Burgundy) |

**How it's built (reuses existing plumbing):**
- **Single source of truth** in `config.php`: `getHairColourGroups()` (the three ranges above),
  `hairColourGroupFor($service, $subType)` (which range applies, `''` if none), and
  `allowedHairColourValues()` (flat list for validation).
- **Picker** in `book.php` Step 4 — a "Hair colour" dropdown populated from the resolved range;
  it appears only for braids/cornrows and shows on the Step 5 review summary.
- **Validation** in `booking.php` — server requires a valid in-range colour for these styles.
- **Storage/display** — saved to the **existing** `salon_bookings.hairpiece_color` column (no new
  migration); already surfaced in `admin-booking-detail.php` and `admin-export-csv.php`.
- The stored value is the salon's own colour **code** (e.g. `1/30`, `C14`) so it reads the same in
  the admin view and the CSV.

---

## 3D. Mobile travel pricing — per kilometre (owner spec, 2026-06-18)

For mobile services the client gives their **exact** address and is charged an **accurate per-km
travel fee**, quoted **live at checkout** and included in the deposit.

**Decisions (owner):** **R10 / km**, measured by **Google Maps driving distance** from the **Midrand
studio** (12 Demo Street, Sandton), charged for the **round trip** (there & back). So the fee is
`one-way km × 2 × R10`, added to the deposit in **full** (not halved).

**How it's built:**
- **Accurate location** — Google **Places Autocomplete** on the address field (`book.php`); the client
  picks their real street/house, which gives us a `place_id`. This is what makes the distance reliable
  (free-typed text can't be measured), and directly answers the owner's "accurate location" ask.
- **Live quote** — `travel-quote.php` returns the fee for the chosen `place_id`; the booking UI shows
  a *Travel fee (round trip)* line and rolls it into the 50% deposit.
- **Authoritative price** — `booking.php` **recomputes** the fee server-side from the posted `place_id`
  before creating the PayFast attempt (the browser quote is never trusted). Logic is the single source
  of truth `computeMobileTravelFee()` in `config.php` (driving distance via `googleDrivingDistanceKm()`
  → Distance Matrix API). Stored in the existing `salon_bookings.travel_surcharge` column — no new DB.
- **Config-driven & safe** — keys/rate/origin live in `.env` (`GOOGLE_MAPS_BROWSER_KEY`,
  `GOOGLE_MAPS_SERVER_KEY`, `TRAVEL_RATE_PER_KM`, `TRAVEL_ROUND_TRIP`, `TRAVEL_ORIGIN_ADDRESS`,
  `TRAVEL_MAX_KM`). **With no keys set, the system falls back to the legacy zone surcharge**
  (R0/R150/R300 by suburb) so the site keeps working. See `DEPLOY.md` §3e for the Google setup.

> This supersedes the zone-tier model as the *primary* travel pricing once the Google keys are live;
> the zones remain only as the no-key fallback.

---

## 4. What This Means We Need to Build

The redesign turns one big form ([booking.php](../booking.php)) into a guided, availability-driven
flow. The central **new** piece is a way for the calendar to ask the server "what's free?" live.

| # | Component | New / Change | Notes |
|---|---|---|---|
| 1 | **Availability API** (e.g. `availability.php`) | **New** | Given `service`, `location`, and a date range, returns JSON of open days, open slots, and per-stylist free/busy. This is the heart of the calendar. Must reuse the exact rules in Section 3. |
| 2 | **Calendar UI** (Step 2–3) | **New** | Month calendar with day states + a slot/stylist picker. Mobile-friendly. Calls the Availability API. |
| 3 | **Service + location + options selector** (Step 1) | **Change** | Becomes the entry point, feeding the calendar. Also collects **sub-type / length** and **add-ons** (Small, Colour blend, Curly ends, BYO bundle for sew-in — see §3B) so the price is correct before checkout. |
| 4 | **Details form** (Step 4) | **Change** | Trimmed to what's left after slot+stylist+sub-type are chosen. **Multi-service = sequential clash-blocking** (Decision #3): "add another service" re-runs Steps 1–3 and the availability call excludes the visitor's already-picked slots, so their own bookings can't overlap. |
| 5 | **Review & pay** (Step 5) | **Reuse** | Existing deposit calc + PayFast + cash paths stay. |
| 6 | **Slot hold / race protection** | **New** | Reserve the chosen slot for **5 minutes** during checkout (Decision #2) so two people can't pay for the same slot; expired holds auto-release. `booking_payment_attempts` can back this with a `hold_expires_at`. Existing ITN re-check stays as the final safety net. |
| 7 | **Confirmation + ITN** ([itn.php](../itn.php)) | **Reuse** | Server-verified payment → save as `paid`. Already solid. |
| 8 | **Admin block-slot** (back office) | **New** | Staff mark a stylist/slot/day as unavailable (walk-ins, leave, breaks) so the calendar never overbooks (Decision #1). The Availability API must treat blocked times as unavailable. |

> **Principle:** the calendar is a *better front door* to the existing engine. The proven parts
> (deposit calculation, PayFast checkout, ITN confirmation, double-booking re-check, admin back
> office) are kept — we are changing how the visitor *gets to* them, not rebuilding payments.

---

## 5. Data We Already Have to Work With

- `salon_bookings` — confirmed appointments (the source of "what's taken"). Has
  `appointment_date`, `appointment_time`, `preferred_stylist`, `status`.
- `booking_payment_attempts` — in-progress checkouts (could double as a short-lived slot hold).
- Catalog defaults in `getDefaultBookingCatalog()` — services, per-service slots, capacities,
  stylists, and the service/location→stylist mapping.
- `MAX_STYLISTS_PER_SLOT = 2`, deposit = 50%, travel zones, operating hours.

*No business data is missing for v1 of the calendar* — the rules and the booking records are
already there. The main new work is exposing availability and presenting it.

---

## 6. Guiding Principles (keep these true as we add features)

1. **Show truth, fast.** What the visitor sees on the calendar must equal what can actually be
   booked — no "looked open, wasn't."
2. **Decide before you type.** Slot + stylist are chosen before any personal details are entered.
3. **Reuse the proven engine.** Deposits, PayFast, ITN, and admin tooling stay; we improve the
   front door.
4. **Mobile-first.** Most visitors are on phones — the calendar and slot picker must work well on
   small screens.
5. **One source of truth for rules.** Availability logic lives in one place (the Availability API)
   and is reused by the calendar and re-checked at payment confirmation.
6. **Don't break the marketing site.** Changes are scoped to the booking engine.

---

## 7. Resolved Decisions (answered 2026-06-14)

All ten open questions have been answered by the owner-side. They are now **locked decisions** that
the build must follow. A few carry a "confirm with owners during testing" tail — noted as such.

| # | Question | Decision | Status |
|---|---|---|---|
| 1 | Walk-ins / overbooking | Add an **admin block-slot** action; staff block time so the calendar can't overbook. | ✅ Locked |
| 2 | Slot hold duration | **5 minutes**, then auto-release. | ✅ Locked |
| 3 | Multi-service bookings | **Client-driven sequential booking with automatic clash-blocking** (book one at a time; picked slots removed from the next service's availability). | ✅ Locked |
| 4 | Sub-type / hair length timing | **Before the calendar** (Step 1) for accuracy. | ✅ Locked |
| 5 | Fixed slots vs real duration | **Keep fixed slots** for now. | ✅ Locked · revisit with owners |
| 6 | Early (05:00) / after-hours | **Show on the calendar with a +R200 note.** | ✅ Locked · confirm on test |
| 7 | "Hair styling" scope | Tentatively **ponytail, frontal-ponytail, hair-colour, other-styling, relaxer**. | 🟡 Tentative · confirm with owner |
| 8 | Braids helper choice | Client chooses **lead braider only**; helper **auto-assigned**. | ✅ Locked |
| 9 | Day-specific slots beyond Braids | **Yes** — Cornrows / Hair styling / Makeup also use reduced Sunday sets. | ✅ Locked |
| 10 | Exact per-service slot times | **Use the same times as Braids for now.** | ✅ Locked · confirm with owners |

### Still to confirm with owners during testing (not blockers)
- **#7** — the precise list of services that make up the capacity-2 "Hair styling" group.
- **#10 / #5 / #6** — final slot times per service, whether to keep fixed slots, and the early-slot
  surcharge presentation — all to be validated when the owners trial the system.

---

## 8. Roadmap (high level — we refine as we go)

- ✅ **Phase 0 — Groundwork.** Per-service capacity, day-aware slots, 5-min hold, admin block-slot
  store, add-ons storage. **Done — [BUILD-PHASE-0-1.md](BUILD-PHASE-0-1.md).**
- ✅ **Phase 1 — Availability API.** `availability.php` + engine in `config.php` returning open
  days/slots + per-stylist free/busy. **Done — [BUILD-PHASE-0-1.md](BUILD-PHASE-0-1.md).**
- ✅ **Phase 2 — Calendar UI.** [../book.php](../book.php): service+location entry → month calendar →
  slot/stylist picker, wired to the API. **Done.**
- ✅ **Phase 3 — Details + review + pay.** Folded into Phase 2 — `book.php` Steps 4–5 submit to the
  existing `booking.php` deposit/PayFast/cash/ITN pipeline. **Done.**
- ✅ **Phase 4 — Polish.** Truthful "held for 5 min at payment" note, **pre-submit availability
  re-check** in `book.php` (friendly "just taken" message instead of a dead-end), loading/empty
  states. *(Multi-service "add another" in the calendar deferred — the classic form still does
  multi-booking.)*
- ✅ **Phase 5 — Admin alignment.** [../admin-block-slots.php](../admin-block-slots.php): staff block
  a stylist / slot / whole day; the engine honours it instantly. Linked in the admin dashboard nav.
  *(Legacy `stylists` table kept — admin dashboard `LEFT JOIN stylists`; retiring it is a separate
  task.)*

*(Phases will gain detail and their own sections as we start each one.)*

---

## 9. Admin portal (for testing & operations)

The CRM/admin side is **already built** — no separate build was needed:

- **Login:** `/admin-login.php` (brute-force lockout, 1-hour session timeout).
- **Account:** username **`demoadmin`** (role `admin`). Password is owner-held; reset via
  `/admin-change-password.php` after logging in.
- **Screens:** dashboard (bookings, stats, today's slots), **Block Times**
  ([../admin-block-slots.php](../admin-block-slots.php)), users, settings, logs, booking
  edit/reschedule/cancel, CSV export.
- **Block Times** is the operational counterpart to the availability engine: any block added there
  is honoured instantly by `availability.php`, `book.php`, and the ITN re-check.

> Auth: server-side PHP sessions (`admin-functions.php`). State-changing admin actions are behind
> login; there is no CSRF token layer yet (consistent with the existing admin pages — a possible
> future hardening).

---

## Changelog

| Date | Version | Change |
|---|---|---|
| 2026-06-19 | Blueprint v2.8 | **Real price list ingested — per-type, per-length pricing ([../pricing.md](../pricing.md)).** Replaced the flat `base_price` + uniform length surcharge with a **price matrix** keyed by (service, subtype, length): `getDefaultServicePriceMatrix()` (code source of truth, 100 rows) + the DB table `booking_service_prices` (owner-editable, seeded by `db/migrations/2026-06-19_pricing_ingest.sql`); resolvers `getBookingItemPrice()` / `getServicePriceOptions()`. The booking form's **length dropdown is now data-driven** and shows each length's price (e.g. Knotless: Bra/Shoulder R650 / Waist R750 / Bum R950); the **Small/Medium/Large/Jumbo size field was removed** ("Small" is an add-on, Jumbo is a braid type). New categories added: **Locs, Sewin, Wash**, expanded **Wigs** (install + style + labour), and full Relaxer/Makeup/Treatment lists; **Goddess braids split** into Knotless/Normal. New add-ons **Beads R200, French Curl Ends R250** (Small R200, Colour Blend R100, Curling Ends R50). Deposit stays 50% of the resolved price; pay-in-full kept. Nails/Lashes (not in the list) keep their base price via fallback. |
| 2026-06-18 | Blueprint v2.7 | **Multi-service "Build your visit" (owner request).** Step 1 is now a builder: the client configures a service (with all options/add-ons), **＋ Add to my visit**, and repeats for more same-day services. The **first service anchors** the date/time/stylist (the proven availability/PayFast path is unchanged); additional services ride along as **salon-arranged, priced line items** (model the owner chose: *same day, one visit, salon arranges*). All services' prices combine into the 50%/100% total. Stored as JSON (`additional_services` + `_total`) on `salon_bookings`/`booking_payment_attempts`; priced server-side by `resolveAdditionalServices()` (never trusts the client); shown in admin + the receipt. Single-service bookings work exactly as before. DB: `db/migrations/2026-06-18_multiservice_build_your_visit.sql` (auto-healed by `ensure*` too). |
| 2026-06-18 | Blueprint v2.6 | **Cancel-resume + flow re-sequence (owner feedback).** (1) Cancelling at PayFast no longer restarts the booking — `cancel.php` now offers **"Resume payment"** (`retry-payment.php` rebuilds the still-held attempt and re-posts; the slot hold is extended; ITN re-checks capacity). (2) **Re-sequenced wizard** to the owner's order: *all* service options (sub-type, length, **braid size, cornrow length, hair colour, add-ons**) are chosen in **Step 1** before the date; Step 4 is just the client's details. Add-ons load up front via `getServiceAddons()`. Also closed a pre-existing gap (braid size now enforced client-side). **Next:** multi-service "build your visit" (same-day, salon-arranged line items) — model agreed, build pending. |
| 2026-06-18 | Blueprint v2.5 | **Pricing transparency + pay-in-full (owner feedback).** (1) **Hair-length surcharges** — longer braids cost more: Shoulder base, Bra +R150, Waist +R300, Butt +R450 (`getHairLengthSurcharges()` in config.php), shown in the picker + deposit + review. (2) **Add-ons are now priced** (Small +R200, Colour blend +R100, Curly ends +R50, BYO −R200) and shown on the review + rolled into the 50% — so the deposit is never "deceiving"; the review now also shows the **Total price**. (3) **Pay 50% OR 100%** — returning clients can settle in full at checkout (`payment_method` `online_full`); receipts/admin distinguish "Paid in Full" vs "Deposit Paid (50%)". (4) **Name-validation fix** — the wizard now enforces the same name rule as the server, so a short/initial name is caught on the form instead of bouncing the client back to step 1. Add-on prices via `db/migrations/2026-06-18_pricing_length_addons_fullpay.sql`; length + full-pay are code-only. |
| 2026-06-18 | Blueprint v2.4 | **Per-kilometre mobile travel pricing (§3D).** Mobile bookings now charge an **accurate per-km fee** (owner: **R10/km, round trip, from the Midrand studio**) via **Google Maps** driving distance, quoted **live at checkout**. Address **autocomplete** (Places) captures the exact location; `travel-quote.php` (new) gives the live quote; `booking.php` recomputes authoritatively before payment; single source of truth `computeMobileTravelFee()` in `config.php`. Stored in the existing `travel_surcharge` column — **no DB change**. Config-driven via `.env`; **falls back to the zone surcharge when no Google key is set** (site never breaks). CSP (`.htaccess`) extended for the Maps domains. See `DEPLOY.md` §3e. |
| 2026-06-18 | Blueprint v2.3 | **Style-aware hair colour selection (§3C).** Braiding clients now choose the extension **colour** in the booking; the options shown depend on the style (three ranges: **Braids/Cornrows**, **French Curl**, **Goddess Braids** — owner spec). Single source of truth in `config.php` (`getHairColourGroups()`, `hairColourGroupFor()`, `allowedHairColourValues()`), picker + review row in `book.php`, required validation in `booking.php`. Reuses the **existing** `salon_bookings.hairpiece_color` column (already shown in admin detail + CSV) — **no DB migration needed**. |
| 2026-06-16 | Blueprint v2.2 | **Cashless + staff management.** Removed the cash option entirely — bookings are **online-only** (PayFast 50% deposit). Dropped the cash UI in `book.php`, the cash branch + `cash_50` method in `booking.php` (`pending_cash` retained read-only for legacy), and removed cash from the admin status picker. Added **admin staff management** in Settings: add stylist, retire (deactivate), delete (only if no bookings), and assign to services via Mappings — functions in `admin-settings.php`. **Swapped Charmaine → Itumeleng** for Makeup (Charmaine had 0 bookings; retired + reassigned; see `db/migrations/2026-06-16_cashless_and_staff_swap.sql`). Updated config defaults + `js/main.js`. |
| 2026-06-16 | Blueprint v2.1 | **Maintenance / "coming soon" mode.** Added a single gate in `config.php` (`enforceMaintenanceMode()`): the public sees a branded `maintenance.php` (HTTP 503 + `Retry-After`) whenever a `.maintenance` file exists in the web root **or** `MAINTENANCE_MODE=true`. The team bypasses with `?preview=<MAINTENANCE_BYPASS_KEY>` (sets a 7-day cookie; `?preview=off` clears). `/admin-*`, `itn.php` and CLI are exempt. See `DEPLOY.md` §2.5. |
| 2026-06-15 | Blueprint v2.0 | **Go-live hardening + email.** Made **email a required field** on the booking form (client + server validation) so every booking has an address. **Booking receipts now email automatically to the client AND a notification to Bella** — on payment for online deposits (`itn.php`) and on submit for cash bookings (`booking.php`). Implemented a **real SMTP client** in `mail-functions.php` (STARTTLS/SSL + `AUTH LOGIN`, multipart plain+HTML, falls back to `mail()`), driven by `.env`. Added **CSRF tokens + role gating** across all admin POST pages, **SEO** (robots/sitemap/canonical/OG/LocalBusiness), branded **404**, **GA4** (config-driven via `GA4_MEASUREMENT_ID`), image `loading="lazy"` + `tools/optimize-images.php`, DB session timezone (+02:00), and `book.php` site chrome + keyboard accessibility. See `DEPLOY.md`. |
| 2026-06-14 | Blueprint v1 | Initial blueprint. Locked the availability-first flow: **service + location → calendar → per-stylist slot → details → pay.** Defined availability rules, components to build, principles, open questions, and roadmap. |
| 2026-06-14 | Blueprint v1.1 | Added the **Service Operations Matrix (§3A)**: two operating models — **Braids = two-on-one** (1 client/slot, 2 braiders, pick lead) and **Cornrows/Hair styling/Makeup = 2 clients/slot** (1 stylist each). Added **day-aware slots** (Braids Mon–Fri 07:30/11:30/14:30, Sun 10:30/13:30), refined availability rules, flagged code gaps (Cornrows capacity, Sunday slots), and added open questions 7–10. |
| 2026-06-15 | Blueprint v1.9 | **Retired the standard booking form; `book.php` is now the only booking UI.** `booking.php` became a headless processor (GET → redirects to `book.php`; POST → processes or shows errors); old form HTML removed. Repointed every site link (index/about/services/policy/cancel/success nav + CTAs) to `book.php`. **De-duplicated the DB:** consolidated admin code onto `booking_stylists` + `preferred_stylist`, then dropped the dead legacy `stylists` table + unused `salon_bookings.stylist_id` (FK `fk_stylist`) and cleared 9 stale abandoned attempts. See `db/migrations/2026-06-15_dedup_cleanup.sql`. |
| 2026-06-14 | Blueprint v1.8 | Documented the **admin portal** for testing/operations (§9): login at `/admin-login.php` as `demoadmin`, with the new **Block Times** screen wired to the availability engine. Confirmed the admin CRM was already built (login, dashboard, users, settings, logs, booking management). |
| 2026-06-14 | Blueprint v1.7 | **Phases 4–5 built.** Added the **admin block-slot screen** ([../admin-block-slots.php](../admin-block-slots.php)) + functions (`createSlotBlock`/`deleteSlotBlock`/`getUpcomingSlotBlocks`), linked in the dashboard nav — staff block a stylist/slot/whole day and the engine honours it instantly. Polished `book.php`: truthful hold note + **pre-submit availability re-check** (friendly "just taken" message). All booking-engine phases now complete and ready for end-to-end testing. |
| 2026-06-14 | Blueprint v1.6 | **Phase 2 built.** Added [../book.php](../book.php) — the calendar-first flow (service+location+sub-type → live month calendar → time + per-stylist picker → details + add-ons → review & pay). It calls `availability.php` and submits the existing field names to `booking.php`, reusing all proven deposit/PayFast/cash/ITN logic. Add-ons now read/validated/persisted (`resolveBookingAddons`), included in the deposit. `booking.php` links to the new calendar. |
| 2026-06-14 | Blueprint v1.5 | **Phase 1 built.** Added the availability engine in `config.php` (`computeAvailability` + `availabilityRecheckSlot`) and the `availability.php` JSON endpoint. Wired `itn.php` (per-service capacity, Braids two-on-one, blocks, auto-assigned helper) and `booking.php` (5-min holds, engine-based pre-pay check, helper/add-on persistence). Self-healing schema-ensure for the new columns/tables. See [BUILD-PHASE-0-1.md](BUILD-PHASE-0-1.md). |
| 2026-06-14 | Blueprint v1.4 | **Live DB aligned to the blueprint** (MariaDB 10.11, system confirmed database-driven). Applied Phase 0 migration: per-service `capacity`, day-aware `booking_service_slots` (weekday + Sunday), missing time slots, `pending_cash` enum fix, `hold_expires_at`, `booking_slot_blocks` table, add-ons tables + columns. Updated `config.php` to read capacity + weekday slots. Remaining gaps are code-wiring for Phase 1. See [BUILD-PHASE-0-1.md](BUILD-PHASE-0-1.md). |
| 2026-06-14 | Blueprint v1.3 | Added **§3B Add-ons**: common Braids/Cornrows add-ons (**Small, Colour blend, Curly ends**) and **weave sew-in BYO bundle** option. Rules: add-ons are price-only extras chosen in Step 1, multi-selectable, included in the 50% deposit, and don't change slot/stylist availability. Prices marked **TBD (confirm with owners)**. Updated §4 component #3. |
| 2026-06-14 | Blueprint v1.2 | **Resolved all 10 open questions** (§7 now "Resolved Decisions"). Locked: **admin block-slot** (#1), **5-min slot hold** (#2), **sequential clash-blocked multi-service** (#3), **sub-type/length before calendar** (#4), **fixed slots** (#5), **early 05:00 slots shown with +R200** (#6), **lead-braider-only choice, helper auto-assigned** (#8), **day-specific slots for all four services** (#9), **shared Braids slot times for now** (#10). Hair-styling scope (#7) tentative. Updated flow Steps 1 & 4, availability rules (§3), §3A matrix, components (§4 + new admin block-slot row), and code-gap list. |
