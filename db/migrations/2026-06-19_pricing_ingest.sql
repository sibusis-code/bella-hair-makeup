-- ===========================================================================
-- Bella — Ingest the owner's real price list (pricing.md), 2026-06-19
-- Target: MariaDB 10.11 (bella_hair). Idempotent (IF NOT EXISTS / INSERT IGNORE /
-- explicit UPDATEs). Apply with the matching code (config.php price matrix +
-- resolver, book.php data-driven lengths, booking.php matrix pricing).
--
-- Moves pricing from a flat base_price to a (service, subtype, length) matrix in
-- booking_service_prices. config.php getDefaultServicePriceMatrix() mirrors this
-- exactly as the code fallback — pricing.md is the source of truth for the numbers.
-- ===========================================================================

-- 1) Price matrix table -----------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_service_prices (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_key  VARCHAR(100) NOT NULL,
  subtype_key  VARCHAR(120) NOT NULL DEFAULT '',
  length_key   VARCHAR(40)  NOT NULL DEFAULT '',
  length_label VARCHAR(60)  NOT NULL DEFAULT '',
  price        DECIMAL(10,2) NOT NULL,
  sort_order   INT UNSIGNED NOT NULL DEFAULT 100,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_price (service_key, subtype_key, length_key),
  INDEX idx_price_lookup (service_key, subtype_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) New services -----------------------------------------------------------
INSERT IGNORE INTO booking_services
  (service_key, service_name, category_label, base_price, capacity, requires_sub_type, requires_hair_length, sub_type_label, info_text, sort_order, is_active)
VALUES
  ('locs',  'Locs',  'Locs',      550.00, 1, 1, 0, 'Loc Style',  'Faux/soft locs styling.', 60, 1),
  ('sewin', 'Sewin', 'Sewin',     650.00, 2, 1, 0, 'Sewin Type', 'Weave sew-in services.',  62, 1),
  ('wash',  'Wash',  'Hair Care', 200.00, 2, 1, 0, 'Wash Type',  'Wash & detangle.',        90, 1);

-- Relabel Wig Installation → Wigs (now holds install + style + labour subtypes).
UPDATE booking_services SET service_name = 'Wigs', category_label = 'Wigs' WHERE service_key = 'wig-installation';

-- Stylist eligibility for new services (everyone, like the generic default).
INSERT IGNORE INTO booking_service_stylists (service_id, stylist_id, location_id, is_active)
  SELECT s.id, st.id, NULL, 1
  FROM booking_services s JOIN booking_stylists st
  WHERE s.service_key IN ('locs','sewin','wash');

-- Weekday + Sunday slots for new services (reuse standard sets).
INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'weekday', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('07:30','11:00','14:00','16:30')
  WHERE s.service_key IN ('locs','sewin','wash');
INSERT IGNORE INTO booking_service_slots (service_id, slot_id, day_group, is_active)
  SELECT s.id, t.id, 'sun', 1 FROM booking_services s
  JOIN booking_time_slots t ON t.slot_key IN ('10:30','13:30')
  WHERE s.service_key IN ('locs','sewin','wash');

-- 3) Subtype reconciliation -------------------------------------------------
-- Helper note: subtype rows keyed by (service_id, subtype_key) via the table's natural set.
-- BRAIDS — split goddess, ensure full list; deactivate placeholders not in the price list.
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'braids' AND st.subtype_key IN ('box-braids','feed-in-braids','faux-locs','goddess-knotless-normal-braids');

INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'knotless-braids' k, 'Knotless Braids' l, 10 so UNION ALL
    SELECT 'normal-braids','Normal Braids',20 UNION ALL
    SELECT 'goddess-knotless-braids','Goddess Knotless Braids',30 UNION ALL
    SELECT 'goddess-normal-braids','Goddess Normal Braids',40 UNION ALL
    SELECT 'koroba-knotless-braids','Koroba Knotless Braids',50 UNION ALL
    SELECT 'koroba-normal-braids','Koroba Normal Braids',60 UNION ALL
    SELECT 'koroba-tribal-braids','Koroba Tribal Braids',70 UNION ALL
    SELECT 'knotless-boho-french-curls','Knotless Boho with French Curls',80 UNION ALL
    SELECT 'french-curls','French Curls',90 UNION ALL
    SELECT 'boho-french-curls','Boho French Curls',100 UNION ALL
    SELECT 'tribal-french-curls','Tribal French Curls',110 UNION ALL
    SELECT 'kinky-twist','Kinky Twist',120 UNION ALL
    SELECT 'jumbo-knotless-braids','Jumbo Knotless Braids',130 UNION ALL
    SELECT 'jumbo-normal-braids','Jumbo Normal Braids',140 UNION ALL
    SELECT 'tribal-braids','Tribal Braids',150 UNION ALL
    SELECT 'boho-tribal-braids','Boho Tribal Braids',160 UNION ALL
    SELECT 'lemonade-braids','Lemonade Braids',170 UNION ALL
    SELECT 'jayda-wayda-sewin','Jayda Wayda Sewin',180
  ) v WHERE s.service_key = 'braids';

-- CORNROWS — add straight-up; deactivate ones not in the list.
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'cornrows' AND st.subtype_key IN ('fulani-cornrows','cornrows-with-extensions');
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'straight-back-cornrows' k, 'Straightback Cornrows' l, 10 so UNION ALL
    SELECT 'stitch-cornrows','Stitch Cornrows',20 UNION ALL
    SELECT 'straight-up-cornrows','Straight Up Cornrows',30 UNION ALL
    SELECT 'wig-lines','Wig Lines (8-10 lines)',40 UNION ALL
    SELECT 'freehand','Freehand (12+ lines)',50
  ) v WHERE s.service_key = 'cornrows';

-- LOCS
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'invisible-locs' k, 'Invisible Locs' l, 10 so UNION ALL
    SELECT 'butterfly-locs','Butterfly Locs',20 UNION ALL
    SELECT 'river-locs','River Locs',30 UNION ALL
    SELECT 'nana-locs','Nana Locs',40
  ) v WHERE s.service_key = 'locs';

-- WIGS (install + style + labour as flat subtypes)
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'basic-wig-install' k, 'Basic Wig Installation' l, 10 so UNION ALL
    SELECT 'basic-wig-install-lines','Basic Wig Installation + Wig Lines',20 UNION ALL
    SELECT '360-wig-install','360 Wig Installation',30 UNION ALL
    SELECT 'wig-ponytail','Wig Style: Ponytail',40 UNION ALL
    SELECT 'wig-half-up-ponytail','Wig Style: Half Up Ponytail',50 UNION ALL
    SELECT 'wig-half-up-lines','Wig Style: Half Up Ponytail with Lines',60 UNION ALL
    SELECT 'wig-half-up-curls','Wig Style: Half Up Ponytail with Curls',70 UNION ALL
    SELECT 'wig-full-curls','Wig Style: Full Curls',80 UNION ALL
    SELECT 'wig-bridal-style','Wig Style: Bridal',90 UNION ALL
    SELECT 'wig-making','Wig Making',100 UNION ALL
    SELECT 'lace-wash','Lace Wash',110 UNION ALL
    SELECT 'lace-removal','Lace Removal',120 UNION ALL
    SELECT 'wig-customisation','Wig Customisation',130 UNION ALL
    SELECT 'wig-treatment','Wig Treatment',140
  ) v WHERE s.service_key = 'wig-installation';

-- SEWIN
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'weave-sewin' k, 'Weave Sewin' l, 10 so UNION ALL
    SELECT 'weave-sewin-brazilian','Weave Sewin (Brazilian)',20
  ) v WHERE s.service_key = 'sewin';

-- FRONTAL PONYTAIL (align labels/keys with the list)
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'hd-frontal-brazilian' k, 'HD Frontal Closure + 24in Brazilian Bundles' l, 10 so UNION ALL
    SELECT 'swiss-frontal-brazilian','Swiss Frontal Closure + 24in Brazilian Bundles',20 UNION ALL
    SELECT 'swiss-frontal-synthetic','Swiss Frontal Closure + 24in Bundles (option 2)',30 UNION ALL
    SELECT 'your-closure-bundles','With Your Closure + Bundles',40
  ) v WHERE s.service_key = 'frontal-ponytail';

-- PONYTAIL (sew-in ponytail styles)
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'ponytail' AND st.subtype_key IN ('sleek-ponytail','curly-ponytail','braided-ponytail');
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'curly' k, 'Curly Ponytail' l, 10 so UNION ALL
    SELECT 'straight','Straight Ponytail',20 UNION ALL
    SELECT 'half-up-sewin','Half Up Sewin Ponytail',30 UNION ALL
    SELECT 'afro-twist','Afro Twist',40
  ) v WHERE s.service_key = 'ponytail';

-- MAKEUP
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'makeup' AND st.subtype_key IN ('soft-glam','full-glam','photoshoot-makeup');
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'full-soft-glam' k, 'Full/Soft Glam (includes lashes)' l, 10 so UNION ALL
    SELECT 'eyebrow-shaping','Eyebrow Shaping',20 UNION ALL
    SELECT 'lesson-daily','Lesson: Daily Application (2hrs)',30 UNION ALL
    SELECT 'lesson-daily-group','Lesson: Daily Application (min 4 people)',40
  ) v WHERE s.service_key = 'makeup';

-- RELAXER
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'relaxer' AND st.subtype_key = 'dark-n-lovely';
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'pure-royal' k, 'Pure Royal' l, 10 so UNION ALL
    SELECT 'mizani-moisture','Mizani Moisture Treatment',20 UNION ALL
    SELECT 'mizani-strength','Mizani Strength',30 UNION ALL
    SELECT 'design-essential-anti-itchy','Design Essential Anti-Itchy',40 UNION ALL
    SELECT 'design-essential-moisture','Design Essential Moisture',50 UNION ALL
    SELECT 'native-child','Native Child',60 UNION ALL
    SELECT 'dark-n-lovely-moisture','Dark n Lovely Moisture',70
  ) v WHERE s.service_key = 'relaxer';

-- OTHER STYLING → Treatments
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'mizani-silk-press' k, 'Mizani Silk-Press' l, 10 so UNION ALL
    SELECT 'moisture-mayonnaise','Hair Moisture Mayonnaise (Deep Conditioning)',20
  ) v WHERE s.service_key = 'other-styling';

-- WASH
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'natural-hair' k, 'Natural Hair Wash' l, 10 so UNION ALL
    SELECT 'relaxed-hair','Relaxed Hair Wash',20 UNION ALL
    SELECT 'detangle','Detangle',30
  ) v WHERE s.service_key = 'wash';

-- UNDO
UPDATE booking_service_subtypes st JOIN booking_services s ON s.id = st.service_id
  SET st.is_active = 0
  WHERE s.service_key = 'undo' AND st.subtype_key IN ('undo-cornrows','undo-braids');
INSERT IGNORE INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
  SELECT s.id, v.k, v.l, v.so, 1 FROM booking_services s JOIN (
    SELECT 'undo-braids-normal' k, 'Undo Braids (Normal)' l, 10 so UNION ALL
    SELECT 'undo-braids-small','Undo Braids (Small)',20 UNION ALL
    SELECT 'undo-cornrows','Undo Cornrows',30
  ) v WHERE s.service_key = 'undo';

-- 4) Price matrix rows (pricing.md). length_key '' = single flat price. ----------
REPLACE INTO booking_service_prices (service_key, subtype_key, length_key, length_label, price, sort_order) VALUES
  -- Braids
  ('braids','knotless-braids','bra-shoulder','Bra/Shoulder Length',650,10),
  ('braids','knotless-braids','waist','Waist Length',750,20),
  ('braids','knotless-braids','bum','Bum Length',950,30),
  ('braids','normal-braids','bra-shoulder','Bra/Shoulder Length',550,10),
  ('braids','normal-braids','waist','Waist Length',650,20),
  ('braids','normal-braids','bum','Bum Length',850,30),
  ('braids','koroba-knotless-braids','','',850,10),
  ('braids','koroba-normal-braids','','',750,10),
  ('braids','koroba-tribal-braids','','',650,10),
  ('braids','goddess-knotless-braids','bra-shoulder','Bra/Shoulder Length',750,10),
  ('braids','goddess-knotless-braids','waist','Waist Length',850,20),
  ('braids','goddess-knotless-braids','bum','Bum Length',1050,30),
  ('braids','goddess-normal-braids','bra-shoulder','Bra/Shoulder Length',650,10),
  ('braids','goddess-normal-braids','waist','Waist Length',750,20),
  ('braids','goddess-normal-braids','bum','Bum Length',950,30),
  ('braids','knotless-boho-french-curls','bra','Bra Length',950,10),
  ('braids','knotless-boho-french-curls','waist','Waist Length',1200,20),
  ('braids','knotless-boho-french-curls','bum','Bum Length',1400,30),
  ('braids','french-curls','bra-shoulder','Bra/Shoulder Length',950,10),
  ('braids','french-curls','waist','Waist Length',1200,20),
  ('braids','french-curls','bum','Bum Length',1400,30),
  ('braids','boho-french-curls','shoulder','Shoulder Length',950,10),
  ('braids','boho-french-curls','waist','Waist Length',1300,20),
  ('braids','tribal-french-curls','','',950,10),
  ('braids','kinky-twist','bra-shoulder','Bra/Shoulder Length',650,10),
  ('braids','kinky-twist','waist','Waist Length',800,20),
  ('braids','jumbo-knotless-braids','bra','Bra Length',950,10),
  ('braids','jumbo-knotless-braids','waist','Waist Length',1200,20),
  ('braids','jumbo-knotless-braids','bum','Bum Length',1500,30),
  ('braids','jumbo-normal-braids','bra','Bra Length',850,10),
  ('braids','jumbo-normal-braids','waist','Waist Length',1050,20),
  ('braids','jumbo-normal-braids','bum','Bum Length',1150,30),
  ('braids','tribal-braids','bra','Bra Length',450,10),
  ('braids','tribal-braids','waist','Waist Length',550,20),
  ('braids','tribal-braids','bum','Bum Length',750,30),
  ('braids','boho-tribal-braids','bra-shoulder','Bra/Shoulder Length',650,10),
  ('braids','boho-tribal-braids','waist','Waist Length',750,20),
  ('braids','boho-tribal-braids','bum','Bum Length',850,30),
  ('braids','lemonade-braids','shoulder','Shoulder Length',750,10),
  ('braids','lemonade-braids','bra','Bra Length',850,20),
  ('braids','jayda-wayda-sewin','bra','Bra Length',550,10),
  ('braids','jayda-wayda-sewin','waist','Waist Length',650,20),
  -- Cornrows
  ('cornrows','straight-back-cornrows','bra-shoulder','Bra/Shoulder Length',400,10),
  ('cornrows','straight-back-cornrows','waist','Waist Length',450,20),
  ('cornrows','straight-back-cornrows','bum','Bum Length',500,30),
  ('cornrows','stitch-cornrows','bra-shoulder','Bra/Shoulder Length',450,10),
  ('cornrows','stitch-cornrows','waist','Waist Length',500,20),
  ('cornrows','stitch-cornrows','bum','Bum Length',550,30),
  ('cornrows','straight-up-cornrows','waist','Waist Length',650,10),
  ('cornrows','straight-up-cornrows','bum','Bum Length',750,20),
  ('cornrows','wig-lines','','',250,10),
  ('cornrows','freehand','','',350,10),
  -- Locs
  ('locs','invisible-locs','shoulder','Shoulder Length',550,10),
  ('locs','invisible-locs','waist','Waist Length',750,20),
  ('locs','butterfly-locs','','',1150,10),
  ('locs','river-locs','','',1200,10),
  ('locs','nana-locs','','',1400,10),
  -- Wigs
  ('wig-installation','basic-wig-install','','',500,10),
  ('wig-installation','basic-wig-install-lines','','',750,10),
  ('wig-installation','360-wig-install','','',800,10),
  ('wig-installation','wig-ponytail','','',150,10),
  ('wig-installation','wig-half-up-ponytail','','',150,10),
  ('wig-installation','wig-half-up-lines','','',200,10),
  ('wig-installation','wig-half-up-curls','','',250,10),
  ('wig-installation','wig-full-curls','','',350,10),
  ('wig-installation','wig-bridal-style','','',350,10),
  ('wig-installation','wig-making','','',600,10),
  ('wig-installation','lace-wash','','',50,10),
  ('wig-installation','lace-removal','','',100,10),
  ('wig-installation','wig-customisation','','',250,10),
  ('wig-installation','wig-treatment','','',350,10),
  -- Sewin
  ('sewin','weave-sewin','','',650,10),
  ('sewin','weave-sewin-brazilian','','',1800,10),
  -- Frontal Ponytail
  ('frontal-ponytail','hd-frontal-brazilian','','',3950,10),
  ('frontal-ponytail','swiss-frontal-brazilian','','',2800,10),
  ('frontal-ponytail','swiss-frontal-synthetic','','',1350,10),
  ('frontal-ponytail','your-closure-bundles','','',650,10),
  -- Ponytail
  ('ponytail','curly','','',500,10),
  ('ponytail','straight','','',450,10),
  ('ponytail','half-up-sewin','','',650,10),
  ('ponytail','afro-twist','','',550,10),
  -- Makeup
  ('makeup','full-soft-glam','','',750,10),
  ('makeup','eyebrow-shaping','','',100,10),
  ('makeup','lesson-daily','','',1400,10),
  ('makeup','lesson-daily-group','','',1250,10),
  -- Relaxer
  ('relaxer','pure-royal','','',350,10),
  ('relaxer','mizani-moisture','','',350,10),
  ('relaxer','mizani-strength','','',400,10),
  ('relaxer','design-essential-anti-itchy','','',450,10),
  ('relaxer','design-essential-moisture','','',350,10),
  ('relaxer','native-child','','',350,10),
  ('relaxer','dark-n-lovely-moisture','','',250,10),
  -- Other styling (Treatments)
  ('other-styling','mizani-silk-press','','',550,10),
  ('other-styling','moisture-mayonnaise','','',150,10),
  -- Wash
  ('wash','natural-hair','','',200,10),
  ('wash','relaxed-hair','','',150,10),
  ('wash','detangle','','',100,10),
  -- Undo
  ('undo','undo-braids-normal','','',150,10),
  ('undo','undo-braids-small','','',200,10),
  ('undo','undo-cornrows','','',50,10);

-- 5) Add-ons (Braids Extras from pricing.md). -------------------------------
UPDATE booking_addons SET price = 200.00, label = 'Small (S) Size' WHERE addon_key = 'small';
UPDATE booking_addons SET price = 100.00, label = 'Hairpiece Colour Blend' WHERE addon_key = 'colour-blend';
UPDATE booking_addons SET price = 50.00,  label = 'Curling Ends' WHERE addon_key = 'curly-ends';
INSERT IGNORE INTO booking_addons (addon_key, label, price, sort_order) VALUES
  ('beads',            'Beads',            200.00, 15),
  ('french-curl-ends', 'French Curl Ends', 250.00, 35);
-- Map the braid extras to braids (+ small/colour-blend/curly-ends to cornrows too).
INSERT IGNORE INTO booking_addon_services (addon_id, service_id)
  SELECT a.id, s.id FROM booking_addons a JOIN booking_services s ON s.service_key = 'braids'
  WHERE a.addon_key IN ('small','beads','curly-ends','french-curl-ends','colour-blend');
INSERT IGNORE INTO booking_addon_services (addon_id, service_id)
  SELECT a.id, s.id FROM booking_addons a JOIN booking_services s ON s.service_key = 'cornrows'
  WHERE a.addon_key IN ('small','colour-blend','curly-ends');

-- Verify:
-- SELECT service_key, subtype_key, length_key, price FROM booking_service_prices ORDER BY service_key, subtype_key, sort_order;
-- ===========================================================================
