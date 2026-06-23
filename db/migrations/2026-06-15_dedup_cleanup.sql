-- =============================================================================
-- De-duplication cleanup (MariaDB 10.11, bella_hair)  |  Date: 2026-06-15
-- Makes booking_stylists + salon_bookings.preferred_stylist the SINGLE source of
-- truth for stylists. Run ONLY after the admin code migration that removes all
-- references to the legacy `stylists` table (already done in admin-functions.php
-- and admin-booking-edit.php).
--
-- Pre-checks confirmed on live DB (2026-06-15):
--   * salon_bookings.stylist_id : 0 rows in use (safe to drop)
--   * legacy `stylists` table   : 3 dummy rows (Thandi/Naledi/Kyla), unused
--   * booking_payment_attempts  : 9 stale 'initiated' rows > 1 day old (abandoned)
-- IRREVERSIBLE — back up affected tables first.
-- =============================================================================

-- 1) Drop the FK constraint first, then the unused legacy stylist column + indexes.
ALTER TABLE salon_bookings DROP FOREIGN KEY IF EXISTS fk_stylist;
ALTER TABLE salon_bookings DROP INDEX IF EXISTS idx_stylist_id;
ALTER TABLE salon_bookings DROP INDEX IF EXISTS idx_salon_bookings_stylist_id;
ALTER TABLE salon_bookings DROP COLUMN IF EXISTS stylist_id;

-- 2) Drop the legacy duplicate `stylists` table (replaced by booking_stylists).
DROP TABLE IF EXISTS stylists;

-- 3) Clear stale abandoned checkout attempts (never paid, older than 1 day).
--    These do not affect availability (no live hold) — purely table hygiene.
DELETE FROM booking_payment_attempts
  WHERE status <> 'paid' AND created_at < (NOW() - INTERVAL 1 DAY);
