-- =============================================================================
-- Cashless move + staff swap (MariaDB 10.11, bella_hair)  |  Date: 2026-06-16
--
-- 1) CASHLESS: no schema change required. New bookings are online-deposit only
--    (enforced in code: book.php / booking.php). The `pending_cash` status and the
--    `payment_method` values are RETAINED so any historical cash bookings still
--    display/export correctly — they are simply never created again.
--
-- 2) STAFF SWAP: replace "Charmaine" with "Itumeleng" for Makeup.
--    Charmaine had 0 bookings on record, so this is clean and reversible
--    (she is DEACTIVATED, not deleted; her service assignments move to Itumeleng).
--    NOTE: this was already applied to the live DB on 2026-06-16; this file is the
--    record / re-applies safely on a restored database (idempotent-ish).
-- =============================================================================

-- Add Itumeleng (inherits Charmaine's sort position), if not already present.
INSERT INTO booking_stylists (stylist_key, stylist_name, sort_order, is_active)
SELECT 'itumeleng', 'Itumeleng', s.sort_order, 1
FROM (SELECT sort_order FROM booking_stylists WHERE stylist_key = 'charmaine') AS s
WHERE NOT EXISTS (SELECT 1 FROM booking_stylists b WHERE b.stylist_key = 'itumeleng');

-- Move Charmaine's service/location assignments to Itumeleng.
-- (Remove any rows that would collide on the unique (service_id, location_id) key first.)
DELETE m FROM booking_service_stylists m
JOIN booking_stylists chr ON chr.stylist_key = 'charmaine' AND chr.id = m.stylist_id
JOIN booking_service_stylists itu
  ON itu.service_id = m.service_id AND itu.location_id = m.location_id
JOIN booking_stylists it ON it.stylist_key = 'itumeleng' AND it.id = itu.stylist_id;

UPDATE booking_service_stylists
SET stylist_id = (SELECT id FROM booking_stylists WHERE stylist_key = 'itumeleng')
WHERE stylist_id = (SELECT id FROM booking_stylists WHERE stylist_key = 'charmaine');

-- Retire Charmaine (kept for history; hidden from new bookings).
UPDATE booking_stylists SET is_active = 0 WHERE stylist_key = 'charmaine';

-- To reverse: UPDATE booking_stylists SET is_active = 1 WHERE stylist_key = 'charmaine';
-- (and re-assign services as needed in admin Settings → Mappings).
