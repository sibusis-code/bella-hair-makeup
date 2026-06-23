-- =============================================================================
-- Phase 0 — Blueprint alignment migration
-- Target: MariaDB 10.11 (bella_hair)  |  Date: 2026-06-14
-- Idempotent: safe to re-run (uses IF [NOT] EXISTS / INSERT IGNORE).
-- See: docs/BOOKING-BLUEPRINT.md and docs/BUILD-PHASE-0-1.md
-- Rollback: db/migrations/2026-06-14_phase0_blueprint_alignment.down.sql
-- =============================================================================

-- ----------------------------------------------------------------------------
-- 1) Per-service capacity (Blueprint §3A / Decision: Braids=1, capacity-2 group)
-- ----------------------------------------------------------------------------
ALTER TABLE booking_services
  ADD COLUMN IF NOT EXISTS capacity TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER base_price;

UPDATE booking_services SET capacity = 1 WHERE service_key = 'braids';
UPDATE booking_services SET capacity = 2
  WHERE service_key IN ('cornrows','ponytail','frontal-ponytail','makeup','bridal-makeup');
-- hair-colour / other-styling / relaxer kept at 1 for now (Decision #7 = keep 1).

-- ----------------------------------------------------------------------------
-- 2) Missing time slots (Sunday Braids 10:30/13:30, 07:00 + Makeup set, after-hours)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO booking_time_slots (slot_key, slot_label, db_time, sort_order, is_active) VALUES
  ('06:45', '06:45 AM',                 '06:45:00',  645, 1),
  ('07:00', '07:00 AM',                 '07:00:00',  700, 1),
  ('09:15', '09:15 AM',                 '09:15:00',  915, 1),
  ('10:30', '10:30 AM',                 '10:30:00', 1030, 1),
  ('11:45', '11:45 AM',                 '11:45:00', 1145, 1),
  ('13:30', '01:30 PM',                 '13:30:00', 1330, 1),
  ('14:15', '02:15 PM',                 '14:15:00', 1415, 1),
  ('15:30', '03:30 PM',                 '15:30:00', 1530, 1),
  ('16:30', '04:30 PM',                 '16:30:00', 1630, 1),
  ('16:45', '04:45 PM',                 '16:45:00', 1645, 1),
  ('after-hours', 'After Hours (extra R200)', '18:00:00', 1800, 1);

-- ----------------------------------------------------------------------------
-- 3) Day-aware service slots (Decision #9: Sunday reduced set for all)
--    Add day_group, widen the unique key, then seed weekday + Sunday slots.
-- ----------------------------------------------------------------------------
ALTER TABLE booking_service_slots
  ADD COLUMN IF NOT EXISTS day_group VARCHAR(10) NOT NULL DEFAULT 'weekday' AFTER slot_id;

UPDATE booking_service_slots SET day_group = 'weekday' WHERE day_group IS NULL OR day_group = '';

ALTER TABLE booking_service_slots DROP INDEX IF EXISTS uq_booking_service_slot;
ALTER TABLE booking_service_slots
  ADD UNIQUE INDEX IF NOT EXISTS uq_bss_service_slot_day (service_id, slot_id, day_group);

-- Weekday sets (each service keeps its natural set — owner-chosen, revisit Decision #10).
INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('07:30','11:30','14:30')
  WHERE s.service_key = 'braids';

INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('07:30','11:00','14:00','16:30')
  WHERE s.service_key = 'cornrows';

INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('07:00','09:00','11:00','13:00','15:00')
  WHERE s.service_key = 'ponytail';

INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('07:00','09:00','11:00','13:00','15:00')
  WHERE s.service_key = 'frontal-ponytail';

INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('06:45','08:00','09:15','10:30','11:45','13:00','14:15','15:30','16:45')
  WHERE s.service_key = 'makeup';

INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('06:45','08:00','09:15','10:30','11:45','13:00','14:15','15:30','16:45')
  WHERE s.service_key = 'bridal-makeup';

-- Sunday reduced set (10:30 / 13:30 for all slot-based services — owner stated hours).
INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'sun', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('10:30','13:30')
  WHERE s.service_key IN ('braids','cornrows','ponytail','frontal-ponytail','makeup','bridal-makeup');

-- ----------------------------------------------------------------------------
-- 4) Cash booking status fix — add 'pending_cash' to the enum (was silently
--    coerced to '' because sql_mode is non-strict). booking.php already writes it.
-- ----------------------------------------------------------------------------
ALTER TABLE salon_bookings
  MODIFY COLUMN status ENUM('pending','pending_cash','confirmed','paid','completed','cancelled')
  NOT NULL DEFAULT 'pending';

-- ----------------------------------------------------------------------------
-- 5) 5-minute slot hold (Decision #2)
-- ----------------------------------------------------------------------------
ALTER TABLE booking_payment_attempts
  ADD COLUMN IF NOT EXISTS hold_expires_at DATETIME NULL AFTER status;

-- ----------------------------------------------------------------------------
-- 6) Admin block-slot store (Decision #1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_slot_blocks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location    VARCHAR(100) NOT NULL,
  stylist     VARCHAR(100) NULL,            -- NULL = whole slot/day for the location
  block_date  DATE NOT NULL,
  block_time  TIME NULL,                    -- NULL = whole day
  reason      VARCHAR(255) NULL,
  created_by  VARCHAR(100) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_block_date_time (block_date, block_time),
  INDEX idx_block_stylist (stylist),
  INDEX idx_block_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7) Add-ons (Blueprint §3B). Definitions + service mapping + per-booking storage.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_addons (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  addon_key   VARCHAR(60) NOT NULL UNIQUE,
  label       VARCHAR(120) NOT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- TBD, confirm with owners
  sort_order  INT UNSIGNED NOT NULL DEFAULT 100,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_addon_services (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  addon_id    INT UNSIGNED NOT NULL,
  service_id  INT UNSIGNED NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_addon_service (addon_id, service_id),
  INDEX idx_addon_services_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO booking_addons (addon_key, label, price, sort_order) VALUES
  ('small',        'Small',                  0.00, 10),
  ('colour-blend', 'Colour blend',           0.00, 20),
  ('curly-ends',   'Curly ends',             0.00, 30),
  ('byo-bundle',   'Bring your own bundle',  0.00, 40);

-- small / colour-blend / curly-ends apply to Braids & Cornrows
INSERT IGNORE INTO booking_addon_services (addon_id, service_id)
  SELECT a.id, s.id FROM booking_addons a
  JOIN booking_services s ON s.service_key IN ('braids','cornrows')
  WHERE a.addon_key IN ('small','colour-blend','curly-ends');

-- byo-bundle applies to sew-in style services
INSERT IGNORE INTO booking_addon_services (addon_id, service_id)
  SELECT a.id, s.id FROM booking_addons a
  JOIN booking_services s ON s.service_key IN ('frontal-ponytail','wig-installation')
  WHERE a.addon_key = 'byo-bundle';

-- Per-booking add-on storage + Braids helper stylist
ALTER TABLE booking_payment_attempts
  ADD COLUMN IF NOT EXISTS addons TEXT NULL,
  ADD COLUMN IF NOT EXISTS addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS helper_stylist VARCHAR(100) NULL;

ALTER TABLE salon_bookings
  ADD COLUMN IF NOT EXISTS addons TEXT NULL,
  ADD COLUMN IF NOT EXISTS addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS helper_stylist VARCHAR(100) NULL;

-- =============================================================================
-- End of migration.
-- =============================================================================
