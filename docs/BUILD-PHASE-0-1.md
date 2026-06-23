# Build Spec — Phase 0 (Groundwork) + Phase 1 (Availability API)

> Detailed build spec for the first two phases of the [Booking Blueprint](BOOKING-BLUEPRINT.md).
> Phase 0 prepares the data/rules; Phase 1 exposes them as a live availability endpoint that the
> calendar (Phase 2) will call. **Scope: booking engine only.** Nothing here touches the marketing
> site.
>
> **Status:** Phase 0 schema/data **APPLIED to live DB on 2026-06-14** (MariaDB 10.11). **Phase 1
> (Availability API + engine) BUILT on 2026-06-14.** Companion: [BOOKING-BLUEPRINT.md](BOOKING-BLUEPRINT.md).
>
> **Phase 1 delivered:**
> - **Availability engine** in [../config.php](../config.php) — `computeAvailability()` (calendar
>   data) + `availabilityRecheckSlot()` (single source of truth for "is this bookable"), honouring
>   day-aware slots, per-service capacity, Braids two-on-one (≥2 free braiders, helper auto-assigned),
>   live 5-min holds, admin blocks, operating days (Copperleaf closed Sun), past-time, +R200 slots.
> - **[../availability.php](../availability.php)** — read-only JSON endpoint for the Phase 2 calendar.
> - **[../itn.php](../itn.php)** — final re-check now uses `availabilityRecheckSlot` (per-service
>   capacity, Braids rule, blocks); persists resolved lead + auto-assigned `helper_stylist` + add-ons.
> - **[../booking.php](../booking.php)** — pre-pay validation routed through the same engine; online
>   checkouts set a **5-minute hold** (`hold_expires_at`); cash bookings persist helper + add-on
>   columns. Add-on **values** still default to none until the Phase 2 add-on UI posts them.
> - Schema-ensure functions self-heal the new columns/tables on any environment.
> - Verified against the live DB (read-path): braids weekday/Sunday slots, Copperleaf-Sunday closed,
>   makeup capacity-2 (9 slots), cornrows capacity-2. Hold/block write-tests deferred (live-DB write
>   guard) but covered by the same code paths.

---

## 0. Catalog source — CONFIRMED database-driven

`getBookingCatalog($mysqli)` in [../config.php](../config.php) has two sources: the **DB tables** and
the `getDefaultBookingCatalog()` PHP fallback.

> ✅ **Verified on the live `bella_hair` DB (2026-06-14):** all catalog tables exist with data
> (`booking_services` 13, `booking_service_subtypes` 40, `booking_locations` 3, `booking_stylists`
> 9, `booking_time_slots`, `booking_service_stylists` 56, plus `booking_service_slots`). **The
> system is database-driven** — the PHP defaults are only a fallback. **All Phase 0 changes were
> applied to the DB tables**, and `config.php` was updated to read them.

### What was applied (migration `db/migrations/2026-06-14_phase0_blueprint_alignment.sql`)
- **Capacity:** added `booking_services.capacity`; Braids=1, Cornrows/Ponytail/Frontal/Makeup/
  Bridal=2, rest=1. `config.php getBookingCatalog()` now reads it.
- **Day-aware slots:** added `booking_service_slots.day_group` (`weekday`/`sun`), widened the unique
  key to `(service_id, slot_id, day_group)`, and seeded weekday (natural per-service) + Sunday
  (10:30/13:30) sets. `config.php` flat slot list now filters to `day_group='weekday'`.
- **Time slots:** added missing rows (06:45, 07:00, 09:15, 10:30, 11:45, 13:30, 14:15, 15:30, 16:30,
  16:45, after-hours).
- **Cash status fix:** added `pending_cash` to `salon_bookings.status` enum (was silently coerced to
  `''` under non-strict sql_mode).
- **5-min hold:** added `booking_payment_attempts.hold_expires_at`.
- **Admin block-slot:** created `booking_slot_blocks`.
- **Add-ons:** created `booking_addons` (+ `booking_addon_services` mapping) seeded with Small /
  Colour blend / Curly ends (Braids, Cornrows) and BYO bundle (Frontal Ponytail, Wig install);
  added `addons` / `addons_total` / `helper_stylist` columns to attempts + bookings.

> Pre-migration data backups (PII): `db/backups/pre_phase0_*` (git-ignored).
> Rollback: `db/migrations/2026-06-14_phase0_blueprint_alignment.down.sql`.

### Remaining follow-ups (Phase 2/3)
- **Calendar UI (Phase 2):** consume `availability.php` to render the month calendar + slot/stylist
  picker. The legacy `booking.php` dropdown still shows all times (capacity-disabled) — it keeps
  working; the calendar is the new front door.
- **Add-on UI (Phase 2/3):** backend stores `addons`/`addons_total` and includes them in the
  deposit; the form needs the add-on checkboxes (Small / Colour blend / Curly ends / BYO bundle) to
  post them. Until then add-on totals are 0.
- **Admin block-slot UI (Phase 4/5):** the engine respects `booking_slot_blocks`; staff currently
  add blocks via SQL until the admin screen is built.
- **Legacy `stylists` table** (Thandi/Naledi/Kyla) still flagged to retire in favour of
  `booking_stylists`.
- 🟡 **"Hair styling" scope** (Decision #7) still to confirm with owner.

---

## Phase 0 — Groundwork

Small, surgical data/rule changes that unblock everything else. Each is independently shippable.

### 0.1 Per-service capacity (stop using a single global number)

**Problem:** the ITN re-check ([../itn.php](../itn.php) line ~240) uses a **global**
`getMaxStylistsPerSlot()` (= 2) for every service, ignoring each service's real `capacity`. That is
wrong for **Braids** (must be 1 client/slot) and for the **capacity-2** services once we fix them.

**Change:**
- Read `capacity` from the catalog per service and enforce **that** number, not the global constant.
- Keep `getMaxStylistsPerSlot()` only as a safety ceiling.

**Catalog capacity targets** (per [Blueprint §3A](BOOKING-BLUEPRINT.md#3a-service-operations-matrix-business-rules)):

| Service | Current `capacity` | Target | Note |
|---|---:|---:|---|
| braids | 1 | **1** | already correct; but also needs the "≥ 2 free braiders" rule (0.4) |
| cornrows | 1 | **2** | ⬅ change |
| ponytail | 2 | 2 | ok |
| frontal-ponytail | 2 | 2 | ok |
| makeup | 2 | 2 | ok |
| bridal-makeup | 2 | 2 | ok |
| hair-colour / other-styling / relaxer | 1 | **2?** | 🟡 only if owners confirm they're the "Hair styling" capacity-2 group (Blueprint Decision #7) |
| wig-installation, nails, lashes, undo, mobile, other | 1 | 1 | standard 1:1 |

### 0.2 Day-aware slot times

**Problem:** `slot_keys` is a single flat list per service; there are no Sunday-specific slots, but
the business needs reduced Sunday sets (Braids: 10:30 / 13:30) for **all four** styling services
(Blueprint Decision #9), all sharing Braids' times for now (Decision #10).

**Change:**
- Extend each service with day-keyed slots. Keep `slot_keys` as the Mon–Fri default and add
  `slot_keys_by_day` overrides:
  ```php
  'braids' => [
      // ...
      'slot_keys' => ['07:30', '11:30', '14:30'],   // Mon–Fri (default)
      'slot_keys_by_day' => [
          'sun' => ['10:30', '13:30'],              // reduced Sunday set
      ],
      'capacity' => 1,
  ],
  ```
- Add a helper:
  ```php
  function getServiceSlotsForDate(array $service, string $date): array
  // returns the correct slot_keys for that calendar date:
  //   - 'sun'  → slot_keys_by_day['sun'] if set
  //   - public holiday → slot_keys_by_day['holiday'] if set
  //   - otherwise → slot_keys
  // also drops any slot outside that day's operating hours.
  ```
- For now apply the same Sunday override to **cornrows, ponytail, frontal-ponytail, makeup**
  (Decision #10 — shared times). Mark with a `// TODO confirm with owners` comment.

### 0.3 5-minute slot hold

**Problem:** `booking_payment_attempts` records in-progress checkouts but nothing expires or
reserves the slot, so two people can race for the same slot.

**Change:**
- Add a column via `ensurePaymentAttemptsTable()` ([../config.php](../config.php) line ~1421):
  ```sql
  ALTER TABLE booking_payment_attempts
    ADD COLUMN hold_expires_at DATETIME NULL AFTER status;
  ```
- When an attempt is created (booking.php), set `hold_expires_at = NOW() + INTERVAL 5 MINUTE`.
- A slot is consumed by a **live hold** when an attempt row has
  `status = 'initiated'` **AND** `hold_expires_at > NOW()`. Expired/`failed`/`completed` attempts do
  **not** consume the slot.
- No cron needed — expiry is evaluated at read time (`hold_expires_at > NOW()`), so expired holds
  naturally free up.

### 0.4 Braids "two-on-one" rule (≥ 2 free braiders)

**Change (rule, used by both Phase 1 and the ITN re-check):**
- A **braids** slot is OPEN only when: no client booked yet (capacity 1) **AND ≥ 2 eligible braiders
  are free** for that date+time (free = no paid/pending booking and no live hold).
- The client picks the **lead** braider only; the **helper is auto-assigned** from the remaining
  free eligible braiders (Decision #8). Record both on the booking (see 0.6 — `helper_stylist`).

### 0.5 Admin block-slot store

**Change — new table** (add an `ensureSlotBlocksTable()` like the others):
```sql
CREATE TABLE IF NOT EXISTS booking_slot_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location     VARCHAR(100) NOT NULL,
    stylist      VARCHAR(100) NULL,          -- NULL = blocks the whole slot/day for that location
    block_date   DATE NOT NULL,
    block_time   TIME NULL,                  -- NULL = whole day
    reason       VARCHAR(255) NULL,
    created_by   VARCHAR(100) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_block_date_time (block_date, block_time),
    INDEX idx_block_stylist (stylist)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
- Availability (Phase 1) and the ITN re-check must treat a matching block as **unavailable**:
  - whole-day block (`block_time IS NULL`) → that date is closed for that location (/stylist),
  - slot block with `stylist` → that stylist is busy at that slot,
  - slot block without `stylist` → the whole slot is closed at that location.
- The **admin UI** to create these is Phase 4/5; Phase 0 just creates the table + read logic so the
  engine respects manual SQL inserts in the meantime.

### 0.6 Add-ons storage

**Change:**
- Define the add-on catalog in config:
  ```php
  function getDefaultAddons(): array {
    return [
      // applies_to = which services can show this add-on
      'small'        => ['label' => 'Small',        'price' => 0.00, 'applies_to' => ['braids','cornrows']],
      'colour-blend' => ['label' => 'Colour blend', 'price' => 0.00, 'applies_to' => ['braids','cornrows']],
      'curly-ends'   => ['label' => 'Curly ends',   'price' => 0.00, 'applies_to' => ['braids','cornrows']],
      'byo-bundle'   => ['label' => 'Bring your own bundle', 'price' => 0.00, 'applies_to' => ['frontal-ponytail','wig-installation']],
      // prices are TBD — confirm with owners (Blueprint §3B). byo-bundle likely a discount (negative).
    ];
  }
  ```
- Persist selected add-ons on both `booking_payment_attempts` and `salon_bookings`:
  ```sql
  ALTER TABLE booking_payment_attempts ADD COLUMN addons TEXT NULL;            -- JSON list of keys
  ALTER TABLE booking_payment_attempts ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;
  ALTER TABLE salon_bookings          ADD COLUMN addons TEXT NULL;
  ALTER TABLE salon_bookings          ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;
  -- braids helper:
  ALTER TABLE booking_payment_attempts ADD COLUMN helper_stylist VARCHAR(100) NULL;
  ALTER TABLE salon_bookings          ADD COLUMN helper_stylist VARCHAR(100) NULL;
  ```
- The **50% deposit** is computed on `base_price + addons_total + travel_surcharge` (add-ons are
  price-only; they do **not** affect slot capacity — Blueprint §3B).

### Phase 0 acceptance
- Catalog reports correct per-service capacity; ITN enforces per-service capacity (not the global).
- `getServiceSlotsForDate()` returns weekday vs Sunday sets correctly.
- New columns/tables exist (idempotent `CREATE … IF NOT EXISTS` / guarded `ALTER`).
- Inserting a `booking_slot_blocks` row makes that slot disappear from availability (verified in 1.x).
- No regression: existing booking + ITN flow still saves bookings.

---

## Phase 1 — Availability API (`availability.php`)

A **read-only JSON endpoint** the calendar calls to render open days, open slots, and per-stylist
free/busy. **Single source of truth** for the rules in [Blueprint §3 / §3A](BOOKING-BLUEPRINT.md#3-how-availability-is-calculated-the-rules-behind-the-calendar).

### 1.1 Request
```
GET /availability.php?service=braids&location=midrand&date_from=2026-06-01&date_to=2026-06-30
                      [&sub_type=knotless-braids]
```
- `service`, `location` — required, validated against the catalog.
- `date_from`, `date_to` — required, capped to a sane range (e.g. ≤ 62 days) to limit load.
- `sub_type` — optional; reserved for future duration logic (fixed slots for now, Decision #5).
- Reject mobile-only services with a non-mobile location, etc. (mirror booking.php validation).

### 1.2 Response (shape)
```json
{
  "service": "braids",
  "location": "midrand",
  "capacity": 1,
  "model": "two-on-one",                  // or "capacity-n"
  "deposit_percentage": 0.5,
  "addons": [
    {"key":"small","label":"Small","price":0},
    {"key":"colour-blend","label":"Colour blend","price":0},
    {"key":"curly-ends","label":"Curly ends","price":0}
  ],
  "days": [
    {
      "date": "2026-06-05",
      "weekday": "thu",
      "state": "open",                    // open | nearly_full | full | closed | past
      "slots": [
        {
          "time": "07:30",
          "label": "07:30 AM",
          "open": true,
          "clients_booked": 0,
          "capacity": 1,
          "surcharge": 0,                 // 200 for before/after-hours
          "stylists": [
            {"key":"caro","name":"Caro","free":true},
            {"key":"emma","name":"Emma","free":true},
            {"key":"patience","name":"Patience","free":false}
          ]
        }
      ]
    }
  ]
}
```

### 1.3 Algorithm (per day in range, per slot)
Mirror the blueprint rules exactly:

1. **Skip past dates** and dates outside the location's operating days (Copperleaf closed Sun, etc.)
   → `state: closed/past`.
2. **Whole-day block?** (`booking_slot_blocks` with `block_time IS NULL` for this location) →
   `state: closed`.
3. **Slots for the date** = `getServiceSlotsForDate($service, $date)` (day-aware, 0.2). Include the
   05:00 early slot with `surcharge: 200` (Decision #6).
4. **Eligible stylists** = `serviceLocationStylists[service][location]` (catalog, already mapped).
5. For each slot, a stylist is **free** when ALL of:
   - eligible, **and**
   - no `salon_bookings` row at this date+time with `status IN ('paid','pending_cash')` for them,
     **and**
   - no **live hold** (`booking_payment_attempts.status='initiated' AND hold_expires_at > NOW()`)
     at this date+time for them, **and**
   - not blocked (`booking_slot_blocks` matching date+time, stylist or stylist-null).
6. **Slot open?**
   - **Braids (two-on-one):** `clients_booked == 0` **AND** ≥ 2 eligible braiders free.
   - **Capacity-n:** `clients_booked < capacity` **AND** ≥ 1 chosen-able stylist free.
   - (`clients_booked` = count of paid/pending + live holds in that slot, capped per service.)
7. **Day state:** `open` if any slot open; `nearly_full` if some but few; `full` if none open;
   `closed` if not operating.

> **Performance:** fetch the month's bookings, holds, and blocks for the service+location in **three
> queries** (date range), then compute slot states in PHP — avoid a query per slot/day.

### 1.4 Reuse, don't duplicate
- The free/open logic computed here **must** be the same logic the ITN re-check uses (extract a
  shared helper, e.g. `isStylistFree()` / `slotOpenState()` in config.php, called by both
  `availability.php` and `itn.php`). One source of truth (Blueprint Principle #5).

### 1.5 Non-functional
- **Read-only**, no writes, no auth (public) — but validate/whitelist all inputs and cap the date
  range. Return `application/json`. No secrets in output.
- Graceful empty result (`days: []`) rather than errors when nothing is open.

### Phase 1 acceptance
- Given seeded bookings/holds/blocks, the JSON matches hand-computed availability for:
  a normal weekday, a Sunday (reduced slots), a fully-booked slot, a blocked slot, a slot held by a
  live (non-expired) attempt, and a braids slot with only 1 free braider (→ shows full).
- Early 05:00 slot appears with `surcharge: 200`.
- An expired hold frees the slot without any cron.
- The same helper drives both this endpoint and the ITN re-check (no divergent logic).

---

## Suggested build order
1. 0.1 capacity + 0.4 braids rule (pure logic, no schema) → extract shared helper.
2. 0.2 day-aware slots.
3. 0.3 hold column, 0.5 blocks table, 0.6 add-ons columns (schema, idempotent).
4. Phase 1 endpoint on top of the shared helper.
5. Verify against the acceptance checks, then move to Phase 2 (Calendar UI).

---

## Changelog
| Date | Version | Change |
|---|---|---|
| 2026-06-14 | Spec v1 | Initial Phase 0 + Phase 1 build spec derived from Blueprint v1.3. |
