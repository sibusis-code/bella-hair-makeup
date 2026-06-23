-- ===========================================================================
-- Bella — Pricing update (owner spec, 2026-06-18)
-- Apply this AT DEPLOY TIME, together with the matching code (config.php,
-- book.php, booking.php, itn.php, mail-functions.php). Applying it before the
-- code is live would make the deposit jump without the booking page showing why.
--
-- What changed and where it lives:
--   * Hair-LENGTH surcharges (Bra +R150 / Waist +R300 / Butt +R450) — CODE ONLY,
--     in config.php getHairLengthSurcharges(). No DB rows needed.
--   * Pay-in-FULL option (50% deposit OR 100%) — CODE ONLY (payment_method
--     'online_full'); the existing payment_method column already stores it.
--   * Add-ON prices — DATA, set below (they were all R0 = free until now).
-- ===========================================================================

-- Add-on prices (owner spec). BYO bundle is a DISCOUNT (negative). The price
-- column is DECIMAL(10,2) signed, so negatives are valid.
UPDATE booking_addons SET price = 200.00  WHERE addon_key = 'small';
UPDATE booking_addons SET price = 100.00  WHERE addon_key = 'colour-blend';
UPDATE booking_addons SET price = 50.00   WHERE addon_key = 'curly-ends';
UPDATE booking_addons SET price = -200.00 WHERE addon_key = 'byo-bundle';

-- Verify:
-- SELECT addon_key, label, price FROM booking_addons ORDER BY addon_key;
