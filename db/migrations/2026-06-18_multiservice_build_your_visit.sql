-- ===========================================================================
-- Bella — Multi-service "Build your visit" (owner spec, 2026-06-18)
-- Apply with the matching code (config.php, book.php, booking.php, itn.php,
-- mail-functions.php, admin-booking-detail.php).
--
-- A booking now supports a PRIMARY (anchor) service that is scheduled normally,
-- plus zero or more ADDITIONAL same-day services that the salon arranges. The
-- extras are stored as JSON line items on the booking, with their combined price.
--
-- NOTE: config.php ensureSalonBookingsSchema()/ensurePaymentAttemptsTable() add
-- these columns automatically (idempotent ALTERs) on the first booking after
-- deploy, so this file is belt-and-suspenders / documentation. Safe to run anyway.
-- ===========================================================================

ALTER TABLE salon_bookings
  ADD COLUMN additional_services TEXT NULL AFTER addons_total,
  ADD COLUMN additional_services_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER additional_services;

ALTER TABLE booking_payment_attempts
  ADD COLUMN additional_services TEXT NULL AFTER addons_total,
  ADD COLUMN additional_services_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER additional_services;

-- Verify:
-- SHOW COLUMNS FROM salon_bookings LIKE 'additional_services%';
