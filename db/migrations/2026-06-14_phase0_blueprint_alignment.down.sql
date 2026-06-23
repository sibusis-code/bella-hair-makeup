-- =============================================================================
-- ROLLBACK for 2026-06-14_phase0_blueprint_alignment.sql  (MariaDB 10.11)
-- WARNING: drops the columns/tables/rows added by the up-migration.
-- The status enum revert will blank out any rows currently set to 'pending_cash'.
-- =============================================================================

-- 7) Add-ons
ALTER TABLE salon_bookings
  DROP COLUMN IF EXISTS addons,
  DROP COLUMN IF EXISTS addons_total,
  DROP COLUMN IF EXISTS helper_stylist;
ALTER TABLE booking_payment_attempts
  DROP COLUMN IF EXISTS addons,
  DROP COLUMN IF EXISTS addons_total,
  DROP COLUMN IF EXISTS helper_stylist;
DROP TABLE IF EXISTS booking_addon_services;
DROP TABLE IF EXISTS booking_addons;

-- 6) Block-slot
DROP TABLE IF EXISTS booking_slot_blocks;

-- 5) Hold
ALTER TABLE booking_payment_attempts DROP COLUMN IF EXISTS hold_expires_at;

-- 4) Status enum (revert — note: any 'pending_cash' rows become '')
ALTER TABLE salon_bookings
  MODIFY COLUMN status ENUM('pending','confirmed','paid','completed','cancelled')
  NOT NULL DEFAULT 'pending';

-- 3) Day-aware slots (remove Sunday rows + non-Braids weekday seeds; restore unique key)
DELETE FROM booking_service_slots WHERE day_group = 'sun';
-- (Weekday seeds for non-braids services were added by this migration; remove if undoing fully:)
-- DELETE bss FROM booking_service_slots bss
--   JOIN booking_services s ON s.id = bss.service_id
--   WHERE bss.day_group = 'weekday' AND s.service_key <> 'braids';
ALTER TABLE booking_service_slots DROP INDEX IF EXISTS uq_bss_service_slot_day;
ALTER TABLE booking_service_slots
  ADD UNIQUE INDEX IF NOT EXISTS uq_booking_service_slot (service_id, slot_id);
ALTER TABLE booking_service_slots DROP COLUMN IF EXISTS day_group;

-- 2) Added time slots (only the ones this migration introduced)
DELETE FROM booking_time_slots WHERE slot_key IN
  ('06:45','07:00','09:15','10:30','11:45','13:30','14:15','15:30','16:30','16:45','after-hours');

-- 1) Capacity
ALTER TABLE booking_services DROP COLUMN IF EXISTS capacity;
