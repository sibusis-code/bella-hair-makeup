<?php
/**
 * book.php — Calendar-first booking flow (Phase 2).
 *
 * Steps: 1) service + location (+ sub-type/length)  2) calendar  3) time + stylist
 *        4) your details  5) review & pay.
 *
 * Availability is fetched live from availability.php (the Phase 1 engine). On submit
 * this posts the SAME field names that booking.php already processes, so all the proven
 * deposit/PayFast/ITN logic is reused unchanged (cashless — online deposit only).
 */

require_once __DIR__ . '/config.php';

$mysqli = tryGetDbConnection();
$catalog = ($mysqli instanceof mysqli) ? getBookingCatalog($mysqli) : getDefaultBookingCatalog();
$businessInfo = ($mysqli instanceof mysqli) ? getBusinessInfo($mysqli) : [];

$services = $catalog['services'] ?? [];
$locations = $catalog['locations'] ?? [];
$depositPct = BOOKING_DEPOSIT_PERCENTAGE;

// Build a compact catalog for the front-end (Step 1 + deposit calc).
$clientServices = [];
foreach ($services as $key => $meta) {
    $subtypes = [];
    foreach (($meta['subtypes'] ?? []) as $st) {
        $subtypes[] = ['key' => (string)$st['key'], 'label' => (string)$st['label']];
    }
    $clientServices[] = [
        'key' => (string)$key,
        'label' => (string)($meta['label'] ?? $key),
        'category' => (string)($meta['category'] ?? ''),
        'basePrice' => (float)($meta['base_price'] ?? 0),
        'requiresSubType' => !empty($meta['requires_sub_type']),
        'requiresHairLength' => !empty($meta['requires_hair_length']),
        'subTypeLabel' => (string)($meta['sub_type_label'] ?? 'Style'),
        'subtypes' => $subtypes,
        'mobileOnly' => !empty($meta['mobile_only']),
        'info' => (string)($meta['info'] ?? ''),
    ];
}

$clientLocations = [];
foreach ($locations as $key => $label) {
    $clientLocations[] = ['key' => (string)$key, 'label' => (string)$label];
}

// Add-ons per service, so they can be chosen in Step 1 (before the calendar). The
// availability call still returns add-ons too, but Step 1 reads this map up front.
$addonsByService = [];
if ($mysqli instanceof mysqli) {
    foreach (array_keys($services) as $svcKey) {
        $svcAddons = getServiceAddons($mysqli, (string)$svcKey);
        if ($svcAddons) {
            $addonsByService[(string)$svcKey] = $svcAddons;
        }
    }
}

$travelZones = getTravelZones();
$businessPhoneDisplay = (string)($businessInfo['phone_whatsapp'] ?? '071 234 5678');

// Footer / header contact details (mirrors index.php).
$phoneWhatsapp = htmlspecialchars($businessInfo['phone_whatsapp'] ?? '071 234 5678', ENT_QUOTES, 'UTF-8');
$phoneLandline = htmlspecialchars($businessInfo['phone_landline'] ?? '010 500 7562', ENT_QUOTES, 'UTF-8');
$whatsappNumber = preg_replace('/[^0-9]/', '', $phoneWhatsapp);
$whatsappLink = 'https://wa.me/27' . substr($whatsappNumber, -9);
$addressMidrand = htmlspecialchars($businessInfo['address_midrand'] ?? '12 Demo Street, Sandton', ENT_QUOTES, 'UTF-8');
$addressCopperleaf = htmlspecialchars($businessInfo['address_copperleaf'] ?? 'Copperleaf Golf Estate, Centurion', ENT_QUOTES, 'UTF-8');

if ($mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Book an Appointment | Bella Hair &amp; Makeup</title>
  <meta name="description" content="Book your hair or makeup appointment at Bella Hair &amp; Makeup — Midrand, Copperleaf &amp; mobile. See real open times and secure your slot with a 50% deposit." />
  <link rel="canonical" href="https://bellahairandmakeup.co.za/book.php" />
  <link rel="icon" href="images/logo.jpeg" type="image/jpeg" />
  <meta property="og:title" content="Book an Appointment | Bella Hair &amp; Makeup" />
  <meta property="og:description" content="Pick your service, see real open times, and secure your slot online." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://bellahairandmakeup.co.za/book.php" />
  <meta property="og:image" content="https://bellahairandmakeup.co.za/images/logo.jpeg" />
  <meta name="twitter:card" content="summary_large_image" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <style>
    .bk-wrap{max-width:880px;margin:0 auto;padding:7rem 1rem 4rem;}
    .bk-steps{display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:2rem;}
    .bk-steps li{list-style:none;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:#999;
      padding:.4rem .7rem;border-radius:999px;border:1px solid #2a2a2a;}
    .bk-steps li.active{color:#111;background:var(--gold,#c9a24b);border-color:transparent;font-weight:700;}
    .bk-steps li.done{color:var(--gold,#c9a24b);border-color:var(--gold,#c9a24b);}
    .bk-panel{display:none;}
    .bk-panel.active{display:block;animation:bkfade .25s ease;}
    @keyframes bkfade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
    .bk-card{background:#161616;border:1px solid #262626;border-radius:14px;padding:1.5rem;margin-bottom:1rem;}
    .bk-h{font-size:1.15rem;color:#fff;margin:0 0 .25rem;}
    .bk-sub{color:#999;font-size:.85rem;margin:0 0 1.25rem;}
    .bk-field{margin-bottom:1rem;}
    .bk-field label{display:block;color:#ccc;font-size:.8rem;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;}
    .bk-field select,.bk-field input,.bk-field textarea{width:100%;padding:.8rem;border-radius:9px;border:1px solid #333;
      background:#0e0e0e;color:#fff;font-size:1rem;}
    .bk-cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
    .bk-cal-head button{background:#222;color:#fff;border:1px solid #333;border-radius:8px;padding:.5rem .9rem;cursor:pointer;}
    .bk-cal-head button:disabled{opacity:.35;cursor:not-allowed;}
    .bk-cal-month{color:#fff;font-weight:700;font-size:1.05rem;}
    .bk-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.4rem;}
    .bk-dow{text-align:center;color:#777;font-size:.7rem;text-transform:uppercase;padding:.3rem 0;}
    .bk-day{aspect-ratio:1;border-radius:10px;border:1px solid #262626;background:#0e0e0e;color:#ddd;
      display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;font-size:.95rem;position:relative;}
    .bk-day .dot{width:6px;height:6px;border-radius:50%;margin-top:3px;}
    .bk-day.empty{border:none;background:transparent;cursor:default;}
    .bk-day.open .dot{background:#4caf50;}
    .bk-day.nearly_full .dot{background:#e0a52e;}
    .bk-day.full,.bk-day.closed,.bk-day.past{color:#555;cursor:not-allowed;background:#0a0a0a;}
    .bk-day.full .dot{background:#c0392b;}
    .bk-day.selected{outline:2px solid var(--gold,#c9a24b);color:#fff;}
    .bk-legend{display:flex;gap:1rem;flex-wrap:wrap;color:#888;font-size:.75rem;margin-top:1rem;}
    .bk-legend span{display:inline-flex;align-items:center;gap:.35rem;}
    .bk-legend i{width:8px;height:8px;border-radius:50%;display:inline-block;}
    .bk-slot{border:1px solid #2a2a2a;border-radius:11px;padding:.9rem 1rem;margin-bottom:.7rem;background:#0e0e0e;}
    .bk-slot.disabled{opacity:.45;}
    .bk-slot-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;}
    .bk-slot-time{color:#fff;font-weight:700;}
    .bk-slot-tag{font-size:.7rem;color:#e0a52e;}
    .bk-chips{display:flex;gap:.4rem;flex-wrap:wrap;}
    .bk-chip{padding:.4rem .7rem;border-radius:999px;border:1px solid #333;background:#161616;color:#ccc;font-size:.82rem;cursor:pointer;}
    .bk-chip.busy{opacity:.4;text-decoration:line-through;cursor:not-allowed;}
    .bk-chip.selected{background:var(--gold,#c9a24b);color:#111;border-color:transparent;font-weight:700;}
    .bk-actions{display:flex;justify-content:space-between;gap:1rem;margin-top:1rem;}
    .bk-note{color:#888;font-size:.8rem;}
    .bk-review-row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #222;color:#ccc;}
    .bk-review-row strong{color:#fff;}
    .bk-pay{display:flex;gap:.6rem;flex-wrap:wrap;margin:.5rem 0 1rem;}
    .bk-pay label{flex:1;min-width:200px;border:1px solid #333;border-radius:10px;padding:.9rem;cursor:pointer;color:#ccc;}
    .bk-pay input{margin-right:.5rem;}
    .bk-error{background:#3a1212;border:1px solid #7a2a2a;color:#f1b0b0;padding:.8rem 1rem;border-radius:9px;margin-bottom:1rem;font-size:.9rem;display:none;}
    .bk-loading{color:#888;text-align:center;padding:2rem;}
    .bk-deposit{color:var(--gold,#C9A96E);font-weight:700;font-size:1.1rem;}
    .bk-skip{position:absolute;left:-999px;top:0;background:var(--gold,#C9A96E);color:#111;padding:.6rem 1rem;border-radius:0 0 8px 0;z-index:1000;font-weight:700;}
    .bk-skip:focus{left:0;}
    .bk-day:focus-visible,.bk-chip:focus-visible{outline:2px solid var(--gold,#C9A96E);outline-offset:2px;}
    .bk-est{color:#bbb;font-size:.85rem;margin:.25rem 0 0;}
    .bk-est strong{color:var(--gold,#C9A96E);}
    .bk-visit-h{color:#fff;font-size:1rem;margin:.2rem 0 .6rem;}
    .bk-visit-item{display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;border:1px solid #2a2a2a;border-radius:10px;padding:.7rem .9rem;margin-bottom:.5rem;background:#0e0e0e;color:#ddd;}
    .bk-visit-item small{color:#999;}
    .bk-anchor-tag{color:var(--gold,#C9A96E);font-size:.72rem;}
    .bk-remove{background:transparent;border:none;color:#c0392b;font-size:1rem;cursor:pointer;margin-left:.5rem;}
    .bk-config{border:1px solid #222;border-radius:12px;padding:1rem;margin:.4rem 0 1rem;}
    .bk-visit-total{display:flex;justify-content:space-between;color:#fff;font-weight:700;padding:.5rem .2rem 0;}
    .bk-wa-float{position:fixed;right:1rem;bottom:1rem;z-index:900;display:inline-flex;align-items:center;gap:.5rem;
      background:#25d366;color:#062e16;font-weight:700;padding:.7rem 1rem;border-radius:999px;text-decoration:none;
      box-shadow:0 6px 18px rgba(0,0,0,.4);font-size:.9rem;}
    .bk-wa-float svg{width:20px;height:20px;}
  </style>
  <?php echo ga4Snippet(); ?>
</head>
<body class="section-dark" style="background:#0a0a0a;min-height:100vh;">

  <a href="#bkMain" class="bk-skip">Skip to booking</a>

  <!-- ======= NAVIGATION ======= -->
  <header class="site-header" id="header">
    <nav class="nav-container">
      <a href="index.php" class="nav-logo">
        <div class="nav-logo-img"><img src="images/logo.jpeg" alt="Bella Hair Makeup logo" /></div>
        <div>
          <span class="logo-brand">Bella</span>
          <span class="logo-sub">Hair | Make up</span>
        </div>
      </a>
      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php">Services &amp; Pricing</a></li>
        <li><a href="policy.php">Policy</a></li>
        <li><a href="book.php" class="nav-cta page-active">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <div class="bk-wrap" id="bkMain">
    <p class="section-eyebrow light" style="text-align:center;">Bella Hair &amp; Makeup</p>
    <h1 class="section-title light" style="text-align:center;margin-bottom:1.5rem;">Book an <em>Appointment</em></h1>

    <ul class="bk-steps">
      <li data-step="1" class="active">1 · Service</li>
      <li data-step="2">2 · Date</li>
      <li data-step="3">3 · Time</li>
      <li data-step="4">4 · Details</li>
      <li data-step="5">5 · Pay</li>
    </ul>

    <div class="bk-error" id="bkError"></div>

    <!-- STEP 1 -->
    <section class="bk-panel active" data-panel="1">
      <div class="bk-card">
        <h2 class="bk-h">Build your visit</h2>
        <p class="bk-sub">Add one or more services for the same day — we'll arrange the schedule. The first service sets your appointment time.</p>

        <!-- Services already added to this visit -->
        <div id="bkVisitList" style="display:none;"></div>

        <div class="bk-config" id="bkConfig">
          <div class="bk-field" id="bkLocationWrap">
            <label for="bkLocation">Location</label>
            <select id="bkLocation"></select>
          </div>
          <div class="bk-field">
            <label for="bkService">Service</label>
            <select id="bkService"></select>
            <p class="bk-est" id="bkEstimate"></p>
          </div>
          <div class="bk-field" id="bkSubTypeWrap" style="display:none;">
            <label for="bkSubType" id="bkSubTypeLabel">Style</label>
            <select id="bkSubType"></select>
          </div>
          <div class="bk-field" id="bkHairLengthWrap" style="display:none;">
            <label for="bkHairLength">Length &amp; price</label>
            <select id="bkHairLength"><option value="">Select length…</option></select>
          </div>
          <div class="bk-field" id="bkHairColourWrap" style="display:none;">
            <label for="bkHairColour">Hair colour</label>
            <select id="bkHairColour"><option value="">Select colour…</option></select>
          </div>
          <div class="bk-field" id="bkAddonsWrap" style="display:none;">
            <label>Add-ons (optional)</label>
            <div class="bk-chips" id="bkAddons"></div>
          </div>
          <button type="button" class="btn bk-add-btn" id="bkAddService" style="background:#1c1c1c;color:var(--gold,#C9A96E);border:1px dashed #4a4a4a;width:100%;margin-top:.5rem;">＋ Add this service to my visit</button>
        </div>

        <div class="bk-actions">
          <span></span>
          <button type="button" class="btn btn-gold" id="bkTo2">See available dates →</button>
        </div>
      </div>
    </section>

    <!-- STEP 2 -->
    <section class="bk-panel" data-panel="2">
      <div class="bk-card">
        <h2 class="bk-h">Choose a date</h2>
        <p class="bk-sub" id="bkCalSummary"></p>
        <div class="bk-cal-head">
          <button type="button" id="bkPrevMonth">‹</button>
          <span class="bk-cal-month" id="bkMonthLabel"></span>
          <button type="button" id="bkNextMonth">›</button>
        </div>
        <div id="bkCalBody"><div class="bk-loading">Loading availability…</div></div>
        <div class="bk-legend">
          <span><i style="background:#4caf50"></i> Open</span>
          <span><i style="background:#e0a52e"></i> Nearly full</span>
          <span><i style="background:#c0392b"></i> Full</span>
          <span style="color:#555">· Closed / past</span>
        </div>
        <div class="bk-actions">
          <button type="button" class="btn" id="bkBack1" style="background:#222;color:#fff;">← Back</button>
          <span></span>
        </div>
      </div>
    </section>

    <!-- STEP 3 -->
    <section class="bk-panel" data-panel="3">
      <div class="bk-card">
        <h2 class="bk-h">Pick a time &amp; stylist</h2>
        <p class="bk-sub" id="bkSlotSummary"></p>
        <div id="bkSlots"></div>
        <div class="bk-actions">
          <button type="button" class="btn" id="bkBack2" style="background:#222;color:#fff;">← Back</button>
          <span></span>
        </div>
      </div>
    </section>

    <!-- STEP 4 -->
    <section class="bk-panel" data-panel="4">
      <div class="bk-card">
        <h2 class="bk-h">Your details</h2>
        <p class="bk-sub">Almost done — once you confirm, we hold your slot for 5 minutes while you pay.</p>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
          <div class="bk-field" style="flex:1;min-width:160px;"><label for="bkFirst">First name</label><input id="bkFirst" autocomplete="given-name" maxlength="100" /></div>
          <div class="bk-field" style="flex:1;min-width:160px;"><label for="bkLast">Last name</label><input id="bkLast" autocomplete="family-name" maxlength="100" /></div>
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
          <div class="bk-field" style="flex:1;min-width:160px;"><label for="bkPhone">Phone</label><input id="bkPhone" type="tel" autocomplete="tel" maxlength="30" /></div>
          <div class="bk-field" style="flex:1;min-width:160px;"><label for="bkEmail">Email <span style="color:var(--gold,#C9A96E)">*</span></label><input id="bkEmail" type="email" autocomplete="email" maxlength="150" placeholder="For your booking receipt" required /></div>
        </div>
        <div class="bk-field" id="bkAddressWrap" style="display:none;">
          <label for="bkAddress">Service address</label>
          <input id="bkAddress" maxlength="255" placeholder="Where should we come to?" autocomplete="off" />
          <div id="bkTravelLine" style="margin-top:.35rem;color:var(--gold,#C9A96E);font-size:.85rem;"></div>
        </div>
        <div class="bk-field"><label for="bkNotes">Notes (optional)</label><textarea id="bkNotes" rows="2" maxlength="1000" placeholder="Any reference styles or details…"></textarea></div>
        <div class="bk-actions">
          <button type="button" class="btn" id="bkBack3" style="background:#222;color:#fff;">← Back</button>
          <button type="button" class="btn btn-gold" id="bkTo5">Review →</button>
        </div>
      </div>
    </section>

    <!-- STEP 5 -->
    <section class="bk-panel" data-panel="5">
      <div class="bk-card">
        <h2 class="bk-h">Review &amp; pay</h2>
        <div id="bkReview"></div>
        <div style="margin:1rem 0 .5rem;color:#ccc;">How would you like to pay online via PayFast?</div>
        <div class="bk-pay-choice" id="bkPayChoice" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.5rem;">
          <label style="display:flex;gap:.5rem;align-items:flex-start;color:#ddd;cursor:pointer;">
            <input type="radio" name="bkPay" value="deposit" checked />
            <span><strong>Pay 50% deposit now</strong> — <span id="bkPayDepositAmt"></span><br><small style="color:#999;">Balance settled on the day of your appointment.</small></span>
          </label>
          <label style="display:flex;gap:.5rem;align-items:flex-start;color:#ddd;cursor:pointer;">
            <input type="radio" name="bkPay" value="full" />
            <span><strong>Pay the full amount now</strong> — <span id="bkPayFullAmt"></span><br><small style="color:#999;">Nothing more to pay on the day.</small></span>
          </label>
        </div>
        <div class="bk-deposit" id="bkDepositLine"></div>
        <label style="display:flex;gap:.5rem;align-items:flex-start;color:#bbb;font-size:.85rem;margin:1rem 0;">
          <input type="checkbox" id="bkAgree" />
          <span>I agree to the <a href="policy.php" style="color:var(--gold,#c9a24b)">Booking Policy</a> and understand the <strong>non-refundable deposit</strong> confirms my booking.</span>
        </label>
        <div class="bk-actions">
          <button type="button" class="btn" id="bkBack4" style="background:#222;color:#fff;">← Back</button>
          <button type="button" class="btn btn-gold" id="bkSubmit">Confirm booking</button>
        </div>
      </div>
    </section>
  </div>

  <a href="<?php echo $whatsappLink; ?>" target="_blank" rel="noopener" class="bk-wa-float" aria-label="Chat to us on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/>
    </svg>
    WhatsApp us
  </a>

  <!-- ======= FOOTER ======= -->
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <span class="logo-brand">Bella</span>
        <span class="logo-sub">Hair | Make up</span>
        <p>Luxury hair and makeup studio serving Midrand &amp; Copperleaf, Gauteng.</p>
      </div>
      <div class="footer-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About</a></li>
          <li><a href="services.php">Services &amp; Pricing</a></li>
          <li><a href="policy.php">Booking Policy</a></li>
          <li><a href="book.php">Book Now</a></li>
        </ul>
      </div>
      <div class="footer-contact">
        <h4>Contact</h4>
        <ul>
          <li><a href="tel:<?php echo preg_replace('/[^0-9]/', '', $phoneLandline); ?>"><?php echo $phoneLandline; ?></a></li>
          <li><a href="<?php echo $whatsappLink; ?>" target="_blank" rel="noopener">WhatsApp: <?php echo $phoneWhatsapp; ?></a></li>
          <li><?php echo $addressMidrand; ?>,<br>Midrand, Gauteng</li>
          <li><?php echo $addressCopperleaf; ?></li>
        </ul>
      </div>
      <div class="footer-hours">
        <h4>Hours</h4>
        <ul>
          <li><span>Mon – Wed</span><span>09:00 – 17:30</span></li>
          <li><span>Thu – Fri</span><span>08:00 – 18:00</span></li>
          <li><span>Saturday</span><span>08:00 – 17:00</span></li>
          <li><span>Sunday</span><span>Midrand 11:00 – 16:00 | Copperleaf Closed</span></li>
          <li><span>Public Holidays</span><span>08:00 – 14:00</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Bella Hair | Makeup. All rights reserved.</p>
    </div>
  </footer>

  <div class="nav-backdrop" id="navBackdrop"></div>

  <!-- Hidden form that reuses booking.php's existing processing -->
  <form id="bkForm" method="POST" action="booking.php" style="display:none;">
    <input type="hidden" name="firstName" /><input type="hidden" name="lastName" />
    <input type="hidden" name="phone" /><input type="hidden" name="email" />
    <input type="hidden" name="service" /><input type="hidden" name="location" />
    <input type="hidden" name="stylist" /><input type="hidden" name="subType" />
    <input type="hidden" name="hairLength" /><input type="hidden" name="braidSize" /><input type="hidden" name="cornrowLength" /><input type="hidden" name="hairpieceColor" />
    <input type="hidden" name="mobileAddress" /><input type="hidden" name="mobilePlaceId" /><input type="hidden" name="preferredDate" />
    <input type="hidden" name="preferredTime" /><input type="hidden" name="notes" />
    <input type="hidden" name="paymentMethod" /><input type="hidden" name="depositAgree" />
    <input type="hidden" name="additionalServices" />
    <span id="bkAddonInputs"></span>
  </form>

  <script>
    const SERVICES = <?php echo json_encode($clientServices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const LOCATIONS = <?php echo json_encode($clientLocations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const ADDONS_BY_SERVICE = <?php echo json_encode($addonsByService, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    // Owner price list: PRICE_MATRIX[service][subtype] = [ {length_key,length_label,price} ].
    const PRICE_MATRIX = <?php echo json_encode($catalog['priceMatrix'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const HAIR_COLOURS = <?php echo json_encode(getHairColourGroups(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    // Mirror of config.php hairColourGroupFor() — which colour range applies to a style.
    function hairColourGroupFor(service, subType){
      if (service === 'cornrows') return 'braids-cornrows';
      if (service === 'braids'){
        if ((subType || '').indexOf('goddess') !== -1) return 'goddess-braids';
        if ((subType || '').indexOf('french-curl') !== -1) return 'french-curl';
        return 'braids-cornrows';
      }
      return '';
    }
    // Length/price rows for a service+subtype, and whether a length choice is needed.
    function priceRowsFor(service, subType){ return (PRICE_MATRIX[service] || {})[subType] || []; }
    function rowsNeedLength(rows){ return !(rows.length === 0 || (rows.length === 1 && rows[0].length_key === '')); }
    const DEPOSIT_PCT = <?php echo json_encode($depositPct); ?>;
    const TRAVEL_ZONES = <?php echo json_encode($travelZones, JSON_UNESCAPED_SLASHES); ?>;
    // Per-km mobile travel pricing (Google Maps). When MAPS_ENABLED is false we fall
    // back to the TRAVEL_ZONES tiers above so the page still works without a key.
    const MAPS_ENABLED = <?php echo mapsBrowserEnabled() ? 'true' : 'false'; ?>;
    const TRAVEL_RATE = <?php echo json_encode((float)TRAVEL_RATE_PER_KM); ?>;
    const TRAVEL_ROUND_TRIP = <?php echo TRAVEL_ROUND_TRIP ? 'true' : 'false'; ?>;
    // Hair-length surcharges (longer = pricier) — single source of truth is config.php.
    const WA_LINK = <?php echo json_encode($whatsappLink, JSON_UNESCAPED_SLASHES); ?>;
    const WA_FALLBACK = `<div class="bk-loading">We couldn't load live availability right now. Please try again, or <a href="${WA_LINK}" target="_blank" rel="noopener" style="color:var(--gold,#C9A96E);font-weight:700;">WhatsApp us</a> to book.</div>`;

    const state = { service:'', location:'', subType:'', subTypeLabel:'', hairLength:'',
      date:'', time:'', dbTime:'', label:'', stylist:'', addons:[], availability:null,
      monthCursor:null, basePrice:0, surcharge:0, mobileOnly:false,
      mobilePlaceId:'', travelFee:null, travelKm:null, travelSource:'',
      addonMap:{}, payFull:false,
      items:[] };  // "build your visit": one or more services; items[0] is the anchor (sets the time)

    const $ = (id) => document.getElementById(id);
    const svcByKey = (k) => SERVICES.find(s => s.key === k);

    // ---------- Step 1 ----------
    function initStep1(){
      const svc = $('bkService');
      // Group services by category into <optgroup>s (preserves catalog order).
      const groups = {};
      const order = [];
      SERVICES.forEach(s => {
        const cat = s.category || 'Other';
        if (!groups[cat]) { groups[cat] = []; order.push(cat); }
        groups[cat].push(s);
      });
      svc.innerHTML = '<option value="">Select a service…</option>' +
        order.map(cat =>
          `<optgroup label="${cat}">` +
          groups[cat].map(s => `<option value="${s.key}">${s.label}</option>`).join('') +
          `</optgroup>`
        ).join('');
      const loc = $('bkLocation');
      loc.innerHTML = '<option value="">Select a location…</option>' +
        LOCATIONS.map(l => `<option value="${l.key}">${l.label}</option>`).join('');
      $('bkHairLength').addEventListener('change', updateEstimate);
      svc.addEventListener('change', onServiceChange);
    }
    // Populate the length/price dropdown from the matrix for the chosen service+subtype.
    function refreshLengthOptions(){
      const s = svcByKey($('bkService').value);
      const sub = (s && s.requiresSubType) ? $('bkSubType').value : '';
      const rows = s ? priceRowsFor(s.key, sub) : [];
      const sel = $('bkHairLength');
      if (s && rowsNeedLength(rows)){
        const prev = sel.value;
        sel.innerHTML = '<option value="">Select length…</option>' +
          rows.map(r => `<option value="${r.length_key}">${r.length_label} — R${Number(r.price).toFixed(0)}</option>`).join('');
        if (prev && rows.some(r => r.length_key === prev)) sel.value = prev;
        $('bkHairLengthWrap').style.display = '';
      } else {
        sel.innerHTML = '<option value="">—</option>';
        sel.value = '';
        $('bkHairLengthWrap').style.display = 'none';
      }
      updateEstimate();
    }
    // Resolve the configurator's current price from the matrix (flat or chosen length).
    function configuratorPrice(){
      const s = svcByKey($('bkService').value);
      if (!s) return 0;
      const sub = s.requiresSubType ? $('bkSubType').value : '';
      const rows = priceRowsFor(s.key, sub);
      if (!rows.length) return Number(s.basePrice) || 0; // services not in the list (nails/lashes)
      if (!rowsNeedLength(rows)) return Number(rows[0].price) || 0;
      const r = rows.find(x => x.length_key === $('bkHairLength').value);
      return r ? Number(r.price) || 0 : 0;
    }
    function onServiceChange(){
      const s = svcByKey($('bkService').value);
      $('bkSubTypeWrap').style.display = 'none';
      $('bkHairLengthWrap').style.display = 'none';
      $('bkLocationWrap').style.display = '';
      $('bkHairColourWrap').style.display = 'none';
      state.addons = [];
      renderAddons([]);
      if (!s){ updateEstimate(); return; }
      // Sub-type
      if (s.requiresSubType && s.subtypes.length){
        $('bkSubTypeLabel').textContent = s.subTypeLabel || 'Style';
        $('bkSubType').innerHTML = '<option value="">Select…</option>' +
          s.subtypes.map(t => `<option value="${t.key}">${t.label}</option>`).join('');
        $('bkSubTypeWrap').style.display = '';
      }
      // Length/price options come from the price matrix (per service+subtype).
      refreshLengthOptions();
      refreshColourOptions();
      renderAddons(ADDONS_BY_SERVICE[s.key] || []);
      // Mobile-only services force location = mobile
      if (s.mobileOnly){
        $('bkLocation').value = 'mobile';
        $('bkLocationWrap').style.display = 'none';
      }
      updateEstimate();
    }
    // Hair-colour options depend on the service + chosen sub-type (Step 1).
    function refreshColourOptions(){
      const s = svcByKey($('bkService').value);
      const sub = (s && s.requiresSubType) ? $('bkSubType').value : '';
      const grp = s ? hairColourGroupFor(s.key, sub) : '';
      const colourSel = $('bkHairColour');
      if (grp && HAIR_COLOURS[grp]){
        const prev = colourSel.value;
        colourSel.innerHTML = '<option value="">Select colour…</option>' +
          HAIR_COLOURS[grp].map(c => `<option value="${c.value}">${c.label}</option>`).join('');
        if (prev && HAIR_COLOURS[grp].some(c => c.value === prev)) colourSel.value = prev;
        $('bkHairColourWrap').style.display = '';
      } else {
        colourSel.value = '';
        $('bkHairColourWrap').style.display = 'none';
      }
    }

    // Read + validate the current configurator. Returns {item, loc} or {err}.
    function readConfigurator(){
      const s = svcByKey($('bkService').value);
      if (!s){ return {err:'Please choose a service.'}; }
      const loc = s.mobileOnly ? 'mobile' : $('bkLocation').value;
      if (!loc){ return {err:'Please choose a location.'}; }
      // All services in one visit share a location.
      if (state.items.length && state.location && loc !== state.location){
        return {err:'All services in one visit must be at the same location. Finish this visit, or remove the others first.'};
      }
      if (s.requiresSubType && s.subtypes.length && !$('bkSubType').value){ return {err:'Please choose a ' + (s.subTypeLabel||'style') + '.'}; }
      const sub = s.requiresSubType ? $('bkSubType').value : '';
      // Length is required whenever the chosen type is priced per length.
      const rows = priceRowsFor(s.key, sub);
      const lengthKey = rowsNeedLength(rows) ? $('bkHairLength').value : '';
      if (rowsNeedLength(rows) && !lengthKey){ return {err:'Please choose a length.'}; }
      if (hairColourGroupFor(s.key, sub) && !$('bkHairColour').value){ return {err:'Please select a hair colour.'}; }
      const lengthRow = rows.find(r => r.length_key === lengthKey) || rows[0] || null;
      const price = configuratorPrice();
      // Snapshot the selected add-ons (key/label/price) so each item is self-contained.
      const addons = state.addons.map(k => { const a = state.addonMap[k] || {label:k, price:0}; return {key:k, label:a.label, price:Number(a.price)||0}; });
      return { loc, item: {
        service: s.key, label: s.label, subType: sub, subTypeLabel: s.subTypeLabel,
        length: lengthKey, lengthLabel: lengthRow ? lengthRow.length_label : '',
        hairColour: hairColourGroupFor(s.key, sub) ? $('bkHairColour').value : '',
        addons: addons, price: price, mobileOnly: !!s.mobileOnly
      }};
    }
    // Add the configured service to the visit and reset the configurator for the next one.
    function addToVisit(){
      const r = readConfigurator();
      if (r.err){ showError(r.err); return false; }
      if (!state.items.length) state.location = r.loc;
      state.items.push(r.item);
      hideError();
      // Reset the configurator (location persists for the visit).
      $('bkService').value = '';
      onServiceChange();
      renderVisitList();
      return true;
    }
    // Full price of one item: the matrix price (type + length) + add-ons.
    function itemFull(it){
      let t = Number(it.price) || 0;
      (it.addons || []).forEach(a => { t += Number(a.price) || 0; });
      return t;
    }
    function itemSummary(it){
      const bits = [
        it.subType ? it.subType.replace(/-/g,' ') : '',
        it.lengthLabel || '',
        it.hairColour ? 'Colour ' + it.hairColour : '',
        ...(it.addons || []).map(a => a.label)
      ].filter(Boolean);
      return bits.join(' · ');
    }
    function renderVisitList(){
      const box = $('bkVisitList');
      // Lock the location to the visit's shared location once the first service is added.
      const locSel = $('bkLocation');
      if (locSel){
        if (state.items.length && state.location){ locSel.value = state.location; locSel.disabled = true; }
        else { locSel.disabled = false; }
      }
      if (!state.items.length){ box.style.display = 'none'; box.innerHTML = ''; return; }
      box.style.display = '';
      let total = 0;
      const rows = state.items.map((it, idx) => {
        const price = itemFull(it); total += price;
        const sum = itemSummary(it);
        return `<div class="bk-visit-item"><div><strong>${it.label}</strong>${idx===0?' <span class="bk-anchor-tag">★ sets the time</span>':''}${sum?'<br><small>'+sum+'</small>':''}</div>`
          + `<div style="white-space:nowrap;">R${price.toFixed(2)} <button type="button" class="bk-remove" data-idx="${idx}" aria-label="Remove ${it.label}">✕</button></div></div>`;
      }).join('');
      box.innerHTML = '<div class="bk-visit-h">Your visit</div>' + rows
        + `<div class="bk-visit-total"><span>Services total</span><span>R${total.toFixed(2)}</span></div>`;
      box.querySelectorAll('.bk-remove').forEach(b => b.addEventListener('click', () => {
        state.items.splice(Number(b.dataset.idx), 1);
        if (!state.items.length) state.location = '';
        renderVisitList();
      }));
    }

    // ---------- Step 2: calendar ----------
    function monthRange(cursor){
      const y = cursor.getFullYear(), m = cursor.getMonth();
      const first = new Date(y, m, 1);
      const last = new Date(y, m + 1, 0);
      const today = new Date(); today.setHours(0,0,0,0);
      const from = first < today ? today : first;
      const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      return { from: fmt(from), to: fmt(last), first, last };
    }

    async function loadMonth(){
      const r = monthRange(state.monthCursor);
      $('bkMonthLabel').textContent = state.monthCursor.toLocaleString('en', {month:'long', year:'numeric'});
      $('bkCalBody').innerHTML = '<div class="bk-loading">Loading availability…</div>';
      const params = new URLSearchParams({ service: state.service, location: state.location, date_from: r.from, date_to: r.to });
      try{
        const res = await fetch('availability.php?' + params.toString());
        const data = await res.json();
        if (!data.ok){ $('bkCalBody').innerHTML = '<div class="bk-loading">'+(data.error||'No availability.')+'</div>'; return; }
        state.availability = data;
        renderCalendar(r);
      }catch(e){ $('bkCalBody').innerHTML = WA_FALLBACK; }
    }

    function dayStateMap(){
      const map = {};
      (state.availability?.days || []).forEach(d => map[d.date] = d);
      return map;
    }

    function renderCalendar(r){
      const map = dayStateMap();
      const dows = ['Su','Mo','Tu','We','Th','Fr','Sa'];
      let html = '<div class="bk-grid">' + dows.map(d => `<div class="bk-dow">${d}</div>`).join('');
      const firstDow = r.first.getDay();
      for (let i=0;i<firstDow;i++) html += '<div class="bk-day empty"></div>';
      const daysInMonth = r.last.getDate();
      for (let day=1; day<=daysInMonth; day++){
        const dt = new Date(r.first.getFullYear(), r.first.getMonth(), day);
        const ds = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
        const info = map[ds];
        const st = info ? info.state : 'closed';
        const clickable = st === 'open' || st === 'nearly_full';
        const sel = state.date === ds ? ' selected' : '';
        const a11y = clickable ? `tabindex="0" role="button" aria-label="${ds} — ${st.replace('_',' ')}"` : 'aria-disabled="true"';
        html += `<div class="bk-day ${st}${sel}" data-date="${ds}" ${clickable?'':'data-x="1"'} ${a11y}>${day}<span class="dot"></span></div>`;
      }
      html += '</div>';
      $('bkCalBody').innerHTML = html;
      $('bkCalBody').querySelectorAll('.bk-day[data-date]:not([data-x])').forEach(el => {
        el.addEventListener('click', () => { state.date = el.dataset.date; renderSlots(); goStep(3); });
      });
    }

    // ---------- Step 3: slots ----------
    function renderSlots(){
      const day = (state.availability?.days || []).find(d => d.date === state.date);
      const svc = svcByKey(state.service);
      $('bkSlotSummary').textContent = `${svc.label} @ ${LOCATIONS.find(l=>l.key===state.location).label} — ${fmtDate(state.date)}`;
      if (!day || !day.slots.length){ $('bkSlots').innerHTML = '<p class="bk-note">No times available on this day.</p>'; return; }
      const twoOnOne = state.availability.model === 'two-on-one';
      let html = '';
      day.slots.forEach(slot => {
        const dis = slot.open ? '' : ' disabled';
        const tag = slot.surcharge > 0 ? `<span class="bk-slot-tag">+R${slot.surcharge} early/after-hours</span>` : '';
        const cap = twoOnOne ? '' : `<span class="bk-note">${slot.clients_booked}/${slot.capacity} booked</span>`;
        let chips;
        if (twoOnOne){
          // Braids: choose preferred LEAD braider; helper auto-assigned.
          chips = slot.stylists.map(s => {
            const cls = s.free ? '' : ' busy';
            return `<span class="bk-chip${cls}" data-slot="${slot.time}" data-stylist="${s.key}" data-db="${slot.db_time}" data-label="${slot.label}" data-surch="${slot.surcharge}">${s.name}</span>`;
          }).join('');
          if (slot.open) chips += `<span class="bk-chip" data-slot="${slot.time}" data-stylist="no-preference" data-db="${slot.db_time}" data-label="${slot.label}" data-surch="${slot.surcharge}">No preference</span>`;
        } else {
          chips = slot.stylists.map(s => {
            const cls = s.free ? '' : ' busy';
            const dis2 = (s.free && slot.open) ? '' : ' busy';
            return `<span class="bk-chip${dis2}" data-slot="${slot.time}" data-stylist="${s.key}" data-db="${slot.db_time}" data-label="${slot.label}" data-surch="${slot.surcharge}">${s.name}</span>`;
          }).join('');
          if (slot.open) chips += `<span class="bk-chip" data-slot="${slot.time}" data-stylist="no-preference" data-db="${slot.db_time}" data-label="${slot.label}" data-surch="${slot.surcharge}">No preference</span>`;
        }
        html += `<div class="bk-slot${dis}"><div class="bk-slot-top"><span class="bk-slot-time">${slot.label}</span>${tag}${cap}</div><div class="bk-chips">${chips}</div></div>`;
      });
      $('bkSlots').innerHTML = html;
      $('bkSlots').querySelectorAll('.bk-chip:not(.busy)').forEach(el => {
        el.tabIndex = 0; el.setAttribute('role', 'button');
        el.addEventListener('click', () => {
          state.time = el.dataset.slot; state.dbTime = el.dataset.db; state.label = el.dataset.label;
          state.stylist = el.dataset.stylist; state.surcharge = Number(el.dataset.surch || 0);
          prepStep4(); goStep(4);
        });
      });
    }

    // ---------- Step 4 ----------
    function renderAddons(addons){
      // Remember each add-on's price/label so the deposit + review reflect them.
      state.addonMap = {};
      addons.forEach(a => { state.addonMap[a.key] = { label: a.label, price: Number(a.price) || 0 }; });
      if (!addons.length){ $('bkAddonsWrap').style.display='none'; $('bkAddons').innerHTML=''; return; }
      $('bkAddonsWrap').style.display = '';
      $('bkAddons').innerHTML = addons.map(a => {
        const p = Number(a.price) || 0;
        const tag = p > 0 ? ` (+R${p})` : (p < 0 ? ` (−R${Math.abs(p)})` : '');
        return `<span class="bk-chip" data-addon="${a.key}" data-price="${p}">${a.label}${tag}</span>`;
      }).join('');
      $('bkAddons').querySelectorAll('.bk-chip').forEach(el => {
        el.tabIndex = 0; el.setAttribute('role', 'button');
        el.addEventListener('click', () => {
          el.classList.toggle('selected');
          const k = el.dataset.addon;
          if (el.classList.contains('selected')) state.addons.push(k);
          else state.addons = state.addons.filter(x => x !== k);
        });
      });
    }
    function prepStep4(){
      // Service options are now chosen in Step 1; Step 4 only collects the client's
      // details (+ the mobile service address / travel quote).
      $('bkAddressWrap').style.display = state.mobileOnly ? '' : 'none';
      updateTravelLine();
    }
    function validateStep4(){
      // Mirror booking.php's server rules so users are stopped HERE (at the field)
      // instead of passing the wizard and bouncing to the error page (e.g. an initial
      // like "J" is non-empty but fails the 2+ letter rule server-side).
      const nameRe = /^[a-zA-Z\s'-]{2,100}$/;
      const fn = $('bkFirst').value.trim(), ln = $('bkLast').value.trim();
      if (!fn || !ln) return 'Please enter your first and last name.';
      if (!nameRe.test(fn)) return 'Please enter your full first name (letters only, at least 2 characters).';
      if (!nameRe.test(ln)) return 'Please enter your full last name (letters only, at least 2 characters).';
      const ph = $('bkPhone').value.trim();
      if (!ph) return 'Please enter your phone number.';
      if (!/^[0-9+\s\-()]{7,30}$/.test(ph)) return 'Please enter a valid phone number (at least 7 digits).';
      const em = $('bkEmail').value.trim();
      if (!em) return 'Please enter your email so we can send your booking receipt.';
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) return 'Please enter a valid email address.';
      if (state.mobileOnly){
        if (!$('bkAddress').value.trim()) return 'Please enter your service address.';
        if (MAPS_ENABLED && !state.mobilePlaceId) return 'Please pick your address from the suggestions so we can calculate your travel fee.';
      }
      return '';
    }

    // ---------- Step 5 ----------
    // Full price of the whole visit (all services + their length/add-ons), EXCLUDING travel.
    function priceableFull(){
      let t = (state.surcharge || 0);
      state.items.forEach(it => { t += itemFull(it); });
      return t;
    }
    // The 50% deposit (priceable parts halved) + travel in FULL — matches booking.php.
    function amountDeposit(){ return priceableFull() * DEPOSIT_PCT + travelFeeValue(); }
    // The whole amount (everything at 100% + travel) for clients who pay in full.
    function amountFull(){ return priceableFull() + travelFeeValue(); }
    // What the client is about to pay, given their 50%/100% choice.
    function chosenAmount(){ return state.payFull ? amountFull() : amountDeposit(); }
    // Travel fee: the live Google per-km quote when we have it, else the zone fallback.
    function travelFeeValue(){
      if (!state.mobileOnly) return 0;
      if (state.travelFee !== null && !isNaN(state.travelFee)) return Number(state.travelFee);
      return detectTravel($('bkAddress').value || '');
    }
    function detectTravel(addr){
      const low = addr.toLowerCase();
      for (const z in TRAVEL_ZONES){ for (const sub of TRAVEL_ZONES[z].suburbs){ if (low.indexOf(sub) !== -1) return Number(TRAVEL_ZONES[z].surcharge); } }
      return 0;
    }
    function updateTravelLine(msg){
      const el = $('bkTravelLine'); if (!el) return;
      if (!state.mobileOnly){ el.textContent = ''; return; }
      if (msg){ el.textContent = msg; return; }
      const fee = travelFeeValue();
      if (fee <= 0){ el.textContent = MAPS_ENABLED ? 'Pick your address from the suggestions to calculate travel.' : ''; return; }
      let txt = 'Travel fee' + (TRAVEL_ROUND_TRIP ? ' (round trip)' : '') + ': R' + fee.toFixed(2);
      if (state.travelSource === 'gmaps' && state.travelKm) txt += ' · ' + state.travelKm + ' km each way';
      el.textContent = txt;
    }
    // Google Places autocomplete → exact address + live per-km quote.
    let bkAutocomplete = null;
    window.initBrandMaps = function(){
      if (!MAPS_ENABLED || !window.google || !google.maps || !google.maps.places) return;
      const input = $('bkAddress'); if (!input) return;
      input.placeholder = 'Start typing your address…';
      bkAutocomplete = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: 'za' },
        fields: ['place_id', 'formatted_address'],
        types: ['address'],
      });
      bkAutocomplete.addListener('place_changed', onPlaceChosen);
    };
    function onPlaceChosen(){
      const place = bkAutocomplete && bkAutocomplete.getPlace();
      if (!place || !place.place_id){ state.mobilePlaceId=''; state.travelFee=null; state.travelKm=null; updateTravelLine(); return; }
      state.mobilePlaceId = place.place_id;
      if (place.formatted_address) $('bkAddress').value = place.formatted_address;
      fetchTravelQuote();
    }
    async function fetchTravelQuote(){
      if (!state.mobilePlaceId){ return; }
      state.travelFee = null; state.travelKm = null;
      updateTravelLine('Calculating travel…');
      try{
        const p = new URLSearchParams({ place_id: state.mobilePlaceId, address: $('bkAddress').value });
        const res = await fetch('travel-quote.php?' + p.toString());
        const d = await res.json();
        if (d && d.ok){ state.travelFee = Number(d.fee); state.travelKm = d.km; state.travelSource = d.source; }
      }catch(e){ /* leave null → zone fallback in travelFeeValue() */ }
      updateTravelLine();
      const p5 = document.querySelector('.bk-panel[data-panel="5"]');
      if (p5 && p5.classList.contains('active')) renderReview();
    }
    function renderReview(){
      const stylLabel = state.stylist === 'no-preference' ? 'No preference (we assign)' : capitalize(state.stylist);
      const rows = [
        ['Location', LOCATIONS.find(l=>l.key===state.location).label],
        ['Date', fmtDate(state.date)],
        ['Time', state.label + (state.surcharge>0 ? ' (+R'+state.surcharge+')' : '')],
        [state.service==='braids'?'Lead braider':'Stylist', stylLabel],
      ];
      // List every service in the visit with its price (first = the scheduled one).
      state.items.forEach((it, idx) => {
        const label = it.label + (idx === 0 ? ' ★' : '') + (it.subType ? ' — ' + it.subType.replace(/-/g,' ') : '');
        rows.push([idx === 0 ? 'Service' : 'Also', label + ' · R' + itemFull(it).toFixed(2)]);
        const sum = itemSummary(it);
        if (sum) rows.push(['', sum]);
      });
      if (state.mobileOnly){
        const tf = travelFeeValue();
        rows.push(['Travel' + (TRAVEL_ROUND_TRIP ? ' (round trip)' : ''),
          'R' + tf.toFixed(2) + (state.travelSource === 'gmaps' && state.travelKm ? ' · ' + state.travelKm + ' km each way' : '')]);
      }
      // Full price line so the 50% is never "deceiving".
      rows.push(['Total price', 'R' + amountFull().toFixed(2)]);
      $('bkReview').innerHTML = rows.map(r => `<div class="bk-review-row"><span>${r[0]}</span><strong>${r[1]}</strong></div>`).join('');
      $('bkPayDepositAmt').textContent = 'R' + amountDeposit().toFixed(2);
      $('bkPayFullAmt').textContent = 'R' + amountFull().toFixed(2);
      const amt = chosenAmount();
      $('bkDepositLine').textContent = (state.payFull ? 'Paying in full: R' : 'Paying now (50% deposit): R') + amt.toFixed(2);
    }

    async function verifySlotStillOpen(){
      try{
        const params = new URLSearchParams({ service: state.service, location: state.location, date_from: state.date, date_to: state.date });
        const res = await fetch('availability.php?' + params.toString());
        const data = await res.json();
        if (!data.ok) return true; // transient error — let booking.php do the authoritative re-check
        const day = (data.days || []).find(d => d.date === state.date);
        const slot = day && (day.slots || []).find(s => s.time === state.time);
        if (!slot || !slot.open) return false;
        if (state.stylist && state.stylist !== 'no-preference'){
          const st = (slot.stylists || []).find(x => x.key === state.stylist);
          if (!st || !st.free) return false;
        }
        return true;
      }catch(e){ return true; }
    }

    async function submitBooking(){
      if (!$('bkAgree').checked){ showError('Please agree to the booking policy to continue.'); return; }
      const btn = $('bkSubmit');
      btn.disabled = true; btn.textContent = 'Checking availability…';
      const ok = await verifySlotStillOpen();
      if (!ok){
        btn.disabled = false; btn.textContent = 'Confirm booking';
        showError('Sorry — that time was just taken. Please choose another slot.');
        await loadMonth();
        renderSlots();
        goStep(3);
        return;
      }
      btn.textContent = 'Confirming…';
      const f = $('bkForm');
      f.firstName.value = $('bkFirst').value.trim();
      f.lastName.value = $('bkLast').value.trim();
      f.phone.value = $('bkPhone').value.trim();
      f.email.value = $('bkEmail').value.trim();
      // The anchor (items[0]) is the scheduled booking; its options post as the primary fields.
      const anchor = state.items[0];
      f.service.value = anchor.service;
      f.location.value = state.location;
      f.stylist.value = state.stylist || 'no-preference';
      f.subType.value = anchor.subType;
      // The chosen length tier (matrix key) posts as hairLength; mirror to cornrowLength for cornrows display.
      f.hairLength.value = anchor.length || '';
      f.braidSize.value = '';
      f.cornrowLength.value = anchor.service === 'cornrows' ? (anchor.length || '') : '';
      f.hairpieceColor.value = anchor.hairColour || '';
      f.mobileAddress.value = state.mobileOnly ? $('bkAddress').value.trim() : '';
      f.mobilePlaceId.value = state.mobileOnly ? (state.mobilePlaceId || '') : '';
      f.preferredDate.value = state.date;
      f.preferredTime.value = state.time;
      f.notes.value = $('bkNotes').value.trim();
      f.paymentMethod.value = state.payFull ? 'online_full' : 'online_deposit'; // cashless — 50% deposit or pay in full
      f.depositAgree.value = '1';
      $('bkAddonInputs').innerHTML = (anchor.addons || []).map(a =>
        `<input type="hidden" name="addons[]" value="${a.key}">`).join('');
      // Additional same-day services (everything after the anchor) → JSON for the server.
      const additional = state.items.slice(1).map(it => ({
        service: it.service, subType: it.subType, hairLength: it.length,
        hairpieceColor: it.hairColour, addons: (it.addons || []).map(a => a.key)
      }));
      f.additionalServices.value = additional.length ? JSON.stringify(additional) : '';
      f.submit();
    }

    // ---------- nav helpers ----------
    function goStep(n){
      document.querySelectorAll('.bk-panel').forEach(p => p.classList.toggle('active', Number(p.dataset.panel) === n));
      document.querySelectorAll('.bk-steps li').forEach(li => {
        const s = Number(li.dataset.step);
        li.classList.toggle('active', s === n);
        li.classList.toggle('done', s < n);
      });
      hideError();
      if (n === 5) renderReview();
      window.scrollTo({top:0, behavior:'smooth'});
    }
    function showError(m){ const e=$('bkError'); e.textContent=m; e.style.display='block'; window.scrollTo({top:0,behavior:'smooth'}); }
    function hideError(){ $('bkError').style.display='none'; }
    function fmtDate(ds){ const d=new Date(ds+'T00:00:00'); return d.toLocaleDateString('en',{weekday:'long',day:'numeric',month:'long'}); }
    function capitalize(s){ return (s||'').charAt(0).toUpperCase()+(s||'').slice(1); }

    // ---------- wire up ----------
    initStep1();
    $('bkAddService').addEventListener('click', addToVisit);
    $('bkTo2').addEventListener('click', () => {
      // If a service is configured but not yet added, add it (single-service = one click).
      if ($('bkService').value){ if (!addToVisit()) return; }
      if (!state.items.length){ showError('Please add at least one service to your visit.'); return; }
      // The anchor (first service) drives the calendar, time and stylist.
      const anchor = state.items[0];
      state.service = anchor.service; state.subType = anchor.subType; state.subTypeLabel = anchor.subTypeLabel;
      state.hairLength = anchor.hairLength; state.basePrice = anchor.basePrice; state.mobileOnly = anchor.mobileOnly;
      state.monthCursor = new Date(); state.monthCursor.setDate(1);
      const extra = state.items.length > 1 ? ` (+${state.items.length - 1} more)` : '';
      $('bkCalSummary').textContent = `${anchor.label}${extra} @ ${LOCATIONS.find(l=>l.key===state.location).label}`;
      goStep(2); loadMonth();
    });
    $('bkBack1').addEventListener('click', () => goStep(1));
    $('bkBack2').addEventListener('click', () => goStep(2));
    $('bkBack3').addEventListener('click', () => goStep(3));
    $('bkBack4').addEventListener('click', () => goStep(4));
    $('bkPrevMonth').addEventListener('click', () => { state.monthCursor.setMonth(state.monthCursor.getMonth()-1); guardPrev(); loadMonth(); });
    $('bkNextMonth').addEventListener('click', () => { state.monthCursor.setMonth(state.monthCursor.getMonth()+1); guardPrev(); loadMonth(); });
    $('bkTo5').addEventListener('click', () => { const e=validateStep4(); if(e){showError(e);return;} goStep(5); });
    $('bkSubmit').addEventListener('click', submitBooking);
    function guardPrev(){
      const now = new Date(); now.setDate(1); now.setHours(0,0,0,0);
      if (state.monthCursor < now) state.monthCursor = now;
      $('bkPrevMonth').disabled = (state.monthCursor.getFullYear()===now.getFullYear() && state.monthCursor.getMonth()===now.getMonth());
    }
    guardPrev();

    // ---------- accessibility: keyboard activation for calendar days & chips ----------
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
      const el = document.activeElement;
      if (el && (el.classList.contains('bk-day') || el.classList.contains('bk-chip')) && !el.classList.contains('busy') && !el.classList.contains('empty')) {
        e.preventDefault();
        el.click();
      }
    });

    // ---------- conversion: live deposit estimate on Step 1 ----------
    function updateEstimate(){
      const s = svcByKey($('bkService').value);
      const box = $('bkEstimate');
      if (!box) return;
      if (!s){ box.textContent = ''; return; }
      const price = configuratorPrice();
      if (price <= 0){ box.innerHTML = ''; return; }
      const dep = price * DEPOSIT_PCT;
      box.innerHTML = 'Price <strong>R' + price.toFixed(2) + '</strong> · est. deposit (50%) <strong>R' + dep.toFixed(2) + '</strong> · add-ons & travel added on review';
    }
    $('bkService').addEventListener('change', updateEstimate);
    // Colour range depends on the chosen sub-type (Step 1).
    $('bkSubType').addEventListener('change', () => { refreshLengthOptions(); refreshColourOptions(); });

    // If the client edits the address text after picking a suggestion, the stored
    // place_id is stale — clear it so they must re-pick (keeps the quote accurate).
    $('bkAddress').addEventListener('input', () => {
      if (state.mobilePlaceId){ state.mobilePlaceId=''; state.travelFee=null; state.travelKm=null; state.travelSource=''; updateTravelLine(); }
    });

    // 50% deposit vs pay-in-full choice on the review step.
    document.querySelectorAll('input[name="bkPay"]').forEach(r => {
      r.addEventListener('change', () => { state.payFull = (r.value === 'full' && r.checked); renderReview(); });
    });
  </script>
<?php if (mapsBrowserEnabled()): ?>
  <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo rawurlencode(GOOGLE_MAPS_BROWSER_KEY); ?>&libraries=places&callback=initBrandMaps&loading=async"></script>
<?php endif; ?>
  <script src="js/main.js"></script>
</body>
</html>
