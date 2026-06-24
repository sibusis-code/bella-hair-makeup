<?php
require_once __DIR__ . '/config.php';

$errors = [];
$formData = [
    'firstName' => '',
    'lastName' => '',
    'phone' => '',
    'email' => '',
    'service' => '',
    'location' => '',
    'stylist' => '',
    'subType' => '',
    'hairLength' => '',
    'mobileActualService' => '',
    'mobilePersonCount' => '',
    'mobileAddress' => '',
    'mobilePlaceId' => '',
    'braidSize' => '',
    'cornrowLength' => '',
    'hairpieceColor' => '',
    'paymentMethod' => 'online_deposit',
    'preferredDate' => '',
    'preferredTime' => '',
    'notes' => '',
    'depositAgree' => ''
];
$formData['bookings'] = []; // Initialize booking slots as an empty array

$mysqli = tryGetDbConnection();
if ($mysqli instanceof mysqli) {
  $bookingCatalog = getBookingCatalog($mysqli);
  $businessInfo = getBusinessInfo($mysqli);
} else {
  $bookingCatalog = getDefaultBookingCatalog();
  $businessInfo = [];
}

$businessPhoneDisplay = (string)($businessInfo['phone_whatsapp'] ?? '071 234 5678');
$businessCallDisplay = (string)($businessInfo['phone_call'] ?? $businessPhoneDisplay);
$businessWhatsappUrl = (string)($businessInfo['whatsapp_url'] ?? 'https://wa.me/27712345678');
$hoursMidrand = (string)($businessInfo['hours_midrand'] ?? 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: 11:00-16:00');
$hoursCopperleaf = (string)($businessInfo['hours_copperleaf'] ?? 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: Closed');

$businessPhoneTel = preg_replace('/[^0-9+]/', '', $businessPhoneDisplay);
if ($businessPhoneTel === '') {
  $businessPhoneTel = '+27712345678';
}

$depositPercentageLabel = getDepositPercentageLabel();
$serviceDepositMap = $bookingCatalog['serviceDepositMap'] ?? getServiceDepositMap($mysqli);
$timeSlotMap = $bookingCatalog['timeSlotMap'] ?? getDefaultBookingCatalog()['timeSlotMap'];
$servicesConfig = $bookingCatalog['services'] ?? getDefaultBookingCatalog()['services'];
$serviceOrder = $bookingCatalog['serviceOrder'] ?? array_keys($servicesConfig);
$locationsConfig = $bookingCatalog['locations'] ?? getDefaultBookingCatalog()['locations'];
$stylistsConfig = $bookingCatalog['stylists'] ?? getDefaultBookingCatalog()['stylists'];
$serviceLocationStylists = $bookingCatalog['serviceLocationStylists'] ?? getDefaultBookingCatalog()['serviceLocationStylists'];

$serviceGroupsForUi = [];
foreach ($serviceOrder as $serviceKey) {
  if (!isset($servicesConfig[$serviceKey])) {
    continue;
  }
  $category = trim((string)($servicesConfig[$serviceKey]['category'] ?? 'Services'));
  if ($category === '') {
    $category = 'Services';
  }
  if (!isset($serviceGroupsForUi[$category])) {
    $serviceGroupsForUi[$category] = [];
  }
  $serviceGroupsForUi[$category][] = $serviceKey;
}

$clientServiceConfig = [];
foreach ($servicesConfig as $serviceKey => $serviceMeta) {
  $subtypes = [];
  if (!empty($serviceMeta['subtypes']) && is_array($serviceMeta['subtypes'])) {
    foreach ($serviceMeta['subtypes'] as $subTypeMeta) {
      $subtypes[] = [
        'key' => (string)($subTypeMeta['key'] ?? ''),
        'label' => (string)($subTypeMeta['label'] ?? ''),
      ];
    }
  }

  $slots = [];
  if (!empty($serviceMeta['slot_keys']) && is_array($serviceMeta['slot_keys'])) {
    foreach ($serviceMeta['slot_keys'] as $slotKey) {
      if (isset($timeSlotMap[$slotKey])) {
        $slots[] = [
          'value' => $slotKey,
          'label' => (string)$timeSlotMap[$slotKey]['label'],
        ];
      }
    }
  }

  $clientServiceConfig[$serviceKey] = [
    'label' => (string)($serviceMeta['label'] ?? $serviceKey),
    'subTypeLabel' => (string)($serviceMeta['sub_type_label'] ?? 'Style'),
    'subtypes' => $subtypes,
    'showLength' => !empty($serviceMeta['requires_hair_length']),
    'requiresSubType' => !empty($serviceMeta['requires_sub_type']),
    'info' => (string)($serviceMeta['info'] ?? ''),
    'slots' => $slots,
    'capacity' => (int)($serviceMeta['capacity'] ?? 1),
    'mobileOnly' => !empty($serviceMeta['mobile_only']),
  ];
}

$clientDefaultSlots = [];
foreach ($timeSlotMap as $slotKey => $slotMeta) {
  $clientDefaultSlots[] = [
    'value' => $slotKey,
    'label' => (string)($slotMeta['label'] ?? $slotKey),
  ];
}

$serviceCapacityMap = [];
foreach ($clientServiceConfig as $svcKey => $svcData) {
  $serviceCapacityMap[$svcKey] = (int)($svcData['capacity'] ?? 1);
}

$mobileOnlyServices = [];
foreach ($clientServiceConfig as $svcKey => $svcData) {
  if (!empty($svcData['mobileOnly'])) {
    $mobileOnlyServices[] = $svcKey;
  }
}
$travelZones = getTravelZones();

$dbTimeToUiKey = [];
foreach ($timeSlotMap as $uiKey => $meta) {
  $dbTime = (string)($meta['db'] ?? '');
  if ($dbTime !== '') {
    $dbTimeToUiKey[$dbTime] = $uiKey;
  }
}

$slotStatusByDate = [];
$paidStatus = 'paid';
$pendingCashStatus = 'pending_cash';
$bookingTimezone = new DateTimeZone(APP_TIMEZONE);

if ($mysqli instanceof mysqli) {
  $paidStmt = $mysqli->prepare(
    'SELECT appointment_date, appointment_time, service, preferred_stylist FROM salon_bookings WHERE status IN (?, ?)'
  );
  $paidStmt->bind_param('ss', $paidStatus, $pendingCashStatus);
  $paidStmt->execute();
  $paidResult = $paidStmt->get_result();

  while ($row = $paidResult->fetch_assoc()) {
      $date = $row['appointment_date'];
      $dbTime = $row['appointment_time'];
      $uiKey = $dbTimeToUiKey[$dbTime] ?? substr($dbTime, 0, 5);
    $svc = (string)($row['service'] ?? '');
    $stylistValue = trim((string)($row['preferred_stylist'] ?? ''));

    if (!isset($slotStatusByDate[$date])) {
      $slotStatusByDate[$date] = [];
      }

    if (!isset($slotStatusByDate[$date][$uiKey])) {
      $slotStatusByDate[$date][$uiKey] = [];
    }

    if (!isset($slotStatusByDate[$date][$uiKey][$svc])) {
      $slotStatusByDate[$date][$uiKey][$svc] = [
        'count' => 0,
        'stylists' => []
      ];
    }

    $slotStatusByDate[$date][$uiKey][$svc]['count']++;
    if ($stylistValue !== '') {
      $slotStatusByDate[$date][$uiKey][$svc]['stylists'][] = $stylistValue;
    }
  }
  $paidStmt->close();
}

/**
 * Render the demo booking confirmation screen + a pre-filled WhatsApp deep link.
 * Used when there is no database (demo mode): no payment is taken; the client is
 * handed off to WhatsApp to confirm with the studio.
 */
function renderDemoBookingConfirmation(array $formData, array $servicesConfig, array $timeSlotMap, array $locationsConfig, array $stylistsConfig, string $whatsappUrl): void
{
    $waDigits = preg_replace('/\D/', '', $whatsappUrl);
    if ($waDigits === '') {
        $waDigits = '27712345678';
    }

    $fullName = trim((string)$formData['firstName'] . ' ' . (string)$formData['lastName']);
    $phone = (string)$formData['phone'];
    $email = (string)$formData['email'];
    $notes = trim((string)$formData['notes']);

    $rows = [];      // display rows: [service, dateLong, time24, location, stylist]
    $waItems = [];   // "Service on 15 June at 14:00 (Location)"
    foreach ($formData['bookings'] as $slot) {
        $svcKey = (string)($slot['service'] ?? '');
        $svcLabel = (string)($servicesConfig[$svcKey]['label'] ?? $svcKey);
        $locKey = (string)($slot['location'] ?? '');
        $locLabel = (string)($locationsConfig[$locKey] ?? $locKey);
        $stylistKey = (string)($slot['stylist'] ?? '');
        $stylistLabel = (string)($stylistsConfig[$stylistKey] ?? $stylistKey);

        $dateRaw = (string)($slot['preferredDate'] ?? '');
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
        $dateLong = $dateObj ? $dateObj->format('l, j F Y') : $dateRaw;  // Monday, 15 June 2026
        $dateShort = $dateObj ? $dateObj->format('j F') : $dateRaw;      // 15 June

        $timeKey = (string)($slot['preferredTime'] ?? '');
        $time24 = preg_match('/^\d{2}:\d{2}$/', $timeKey)
            ? $timeKey
            : (string)($timeSlotMap[$timeKey]['label'] ?? $timeKey);

        $rows[] = [$svcLabel, $dateLong, $time24, $locLabel, $stylistLabel];
        $waItems[] = $svcLabel . ' on ' . $dateShort . ' at ' . $time24 . ' (' . $locLabel . ')';
    }

    if (count($waItems) === 1) {
        $message = 'Hello, I would like to book ' . $waItems[0] . '.';
    } else {
        $numbered = [];
        foreach ($waItems as $i => $item) {
            $numbered[] = ($i + 1) . '. ' . $item;
        }
        $message = "Hello, I would like to make the following bookings:\n" . implode("\n", $numbered);
    }
    $message .= "\n\nName: " . $fullName . "\nPhone: " . $phone;
    if ($email !== '') {
        $message .= "\nEmail: " . $email;
    }
    if ($notes !== '') {
        $message .= "\nNotes: " . $notes;
    }

    $waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($message);
    $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Booking Request Ready — Bella Hair &amp; Makeup</title>
  <link rel="icon" href="images/logo.jpeg" type="image/jpeg" />
  <style>
    :root { --gold:#C9A96E; --ink:#1a1a1a; --paper:#faf7f2; }
    * { box-sizing: border-box; }
    body { margin:0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
           background:var(--paper); color:var(--ink); line-height:1.55; }
    .wrap { max-width:640px; margin:0 auto; padding:40px 20px 60px; }
    .card { background:#fff; border:1px solid #ece6db; border-radius:16px; padding:32px;
            box-shadow:0 10px 40px rgba(0,0,0,.06); }
    .logo { display:block; width:72px; height:72px; object-fit:cover; border-radius:50%;
            margin:0 auto 14px; border:2px solid var(--gold); }
    .tick { width:54px; height:54px; border-radius:50%; background:#e8f7ee; color:#1a9e54;
            display:flex; align-items:center; justify-content:center; font-size:30px;
            margin:0 auto 12px; }
    h1 { text-align:center; font-size:1.5rem; margin:.2rem 0 .3rem; }
    .sub { text-align:center; color:#6b6b6b; margin:0 0 22px; font-size:.95rem; }
    .demo-note { background:#fff8e8; border:1px solid #f0e2b8; color:#8a6d1f;
                 border-radius:10px; padding:10px 14px; font-size:.85rem; text-align:center; margin-bottom:22px; }
    table { width:100%; border-collapse:collapse; margin:0 0 10px; }
    td { padding:9px 4px; border-bottom:1px solid #f0ece4; vertical-align:top; font-size:.95rem; }
    td.k { color:#8a8a8a; width:34%; }
    td.v { font-weight:600; }
    .bk { border:1px solid #ece6db; border-radius:12px; padding:8px 14px; margin:0 0 12px; }
    .bk h3 { margin:8px 0; font-size:.8rem; letter-spacing:.08em; text-transform:uppercase; color:var(--gold); }
    .wa { display:flex; align-items:center; justify-content:center; gap:10px;
          background:#25D366; color:#fff; text-decoration:none; font-weight:700;
          padding:16px 20px; border-radius:12px; font-size:1.05rem; margin:18px 0 8px;
          box-shadow:0 8px 24px rgba(37,211,102,.35); }
    .wa:hover { background:#1ebe5b; }
    .links { text-align:center; margin-top:18px; font-size:.9rem; }
    .links a { color:var(--ink); }
    .preview { background:#f7f4ee; border:1px dashed #d9cfbb; border-radius:10px;
               padding:12px 14px; font-size:.82rem; white-space:pre-wrap; color:#555; margin-top:14px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <img class="logo" src="images/logo.jpeg" alt="Bella Hair &amp; Makeup" />
      <div class="tick">&#10003;</div>
      <h1>Your booking request is ready!</h1>
      <p class="sub">Thanks, <?php echo $esc($formData['firstName']); ?> — tap the button below to send it to us on WhatsApp and we’ll confirm your slot.</p>
      <div class="demo-note">Demo mode — no payment is taken. This sends a pre-filled message to our demo WhatsApp number.</div>

      <table>
        <tr><td class="k">Name</td><td class="v"><?php echo $esc($fullName); ?></td></tr>
        <tr><td class="k">Phone</td><td class="v"><?php echo $esc($phone); ?></td></tr>
        <?php if ($email !== ''): ?><tr><td class="k">Email</td><td class="v"><?php echo $esc($email); ?></td></tr><?php endif; ?>
      </table>

      <?php foreach ($rows as $i => $r): ?>
        <div class="bk">
          <h3><?php echo (count($rows) > 1 ? 'Booking ' . ($i + 1) : 'Your booking'); ?></h3>
          <table>
            <tr><td class="k">Service</td><td class="v"><?php echo $esc($r[0]); ?></td></tr>
            <tr><td class="k">Date</td><td class="v"><?php echo $esc($r[1]); ?></td></tr>
            <tr><td class="k">Time</td><td class="v"><?php echo $esc($r[2]); ?></td></tr>
            <tr><td class="k">Location</td><td class="v"><?php echo $esc($r[3]); ?></td></tr>
            <tr><td class="k">Stylist</td><td class="v"><?php echo $esc($r[4]); ?></td></tr>
          </table>
        </div>
      <?php endforeach; ?>

      <?php if ($notes !== ''): ?>
        <table><tr><td class="k">Notes</td><td class="v"><?php echo $esc($notes); ?></td></tr></table>
      <?php endif; ?>

      <a class="wa" href="<?php echo $esc($waLink); ?>" target="_blank" rel="noopener">
        <span>&#128241;</span> Send booking on WhatsApp
      </a>

      <div class="preview"><?php echo $esc($message); ?></div>

      <div class="links">
        <a href="book.php">&larr; Make another booking</a> &nbsp;·&nbsp; <a href="index.php">Home</a>
      </div>
    </div>
  </div>
</body>
</html>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // DEMO MODE: with no database (e.g. the Vercel serverless demo) we still run the
  // full input validation below, then hand the booking off to WhatsApp on the
  // confirmation screen instead of writing to the DB / redirecting to PayFast.
  $demoMode = !($mysqli instanceof mysqli);

  if (($mysqli instanceof mysqli) && !ensureSalonBookingsSchema($mysqli)) {
    $errors[] = 'Booking storage is not ready. Please try again.';
  }

    // Input sanitization - remove potentially malicious input
    foreach ($formData as $key => $_value) {
        if ($key === 'depositAgree') {
            $formData[$key] = isset($_POST[$key]) ? '1' : '';
            continue;
        }
      if ($key === 'bookings') {
        continue;
      }
        // Sanitize: trim whitespace, remove null bytes, limit length
        $raw = $_POST[$key] ?? '';
        if (($key === 'preferredDate' || $key === 'preferredTime') && is_array($raw)) {
          $raw = (string)($raw[0] ?? '');
        }
        $raw = str_replace("\0", '', $raw); // Remove null bytes
        $raw = trim($raw);
        
        // Length limits for text fields
        if ($key === 'firstName' || $key === 'lastName') {
            $formData[$key] = substr($raw, 0, 100);
        } elseif ($key === 'email') {
            $formData[$key] = substr($raw, 0, 150);
        } elseif ($key === 'phone') {
            $formData[$key] = substr($raw, 0, 30);
        } elseif ($key === 'notes') {
            $formData[$key] = substr($raw, 0, 1000); // Limit special requests
        } elseif ($key === 'mobileAddress') {
            $formData[$key] = substr($raw, 0, 255);
        } else {
            $formData[$key] = substr($raw, 0, 100);
        }
    }

    if ($formData['paymentMethod'] === '') {
      $formData['paymentMethod'] = 'online_deposit';
    }
    // Validation: First name
    if ($formData['firstName'] === '') {
        $errors[] = 'First name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $formData['firstName'])) {
        $errors[] = 'First name must contain only letters, spaces, hyphens, and apostrophes (2-100 characters).';
    }

    // Validation: Last name
    if ($formData['lastName'] === '') {
        $errors[] = 'Last name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $formData['lastName'])) {
        $errors[] = 'Last name must contain only letters, spaces, hyphens, and apostrophes (2-100 characters).';
    }

    // Validation: Phone - accept international format
    if ($formData['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\s\-()]{7,30}$/', $formData['phone'])) {
        $errors[] = 'Please enter a valid phone number (7-30 characters, may include +, spaces, hyphens).';
    }

    // Validation: Email (required — used to send the booking receipt to the client)
    if ($formData['email'] === '') {
        $errors[] = 'Email address is required so we can send your booking receipt.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($formData['email']) > 150) {
        $errors[] = 'Email address is too long.';
    }

    // Build booking items: one primary item + optional additional items.
    // Expected POST structure:
    //   - Primary booking: individual fields (service, location, stylist, preferredDate, preferredTime, etc.)
    //   - Additional bookings: bookings_extra[0][service], bookings_extra[0][location], bookings_extra[1][service], etc.
    $formData['bookings'] = [];
    $primaryBooking = [
      'service' => $formData['service'],
      'location' => $formData['location'],
      'stylist' => $formData['stylist'],
      'subType' => $formData['subType'],
      'hairLength' => $formData['hairLength'],
      'braidSize' => $formData['braidSize'],
      'cornrowLength' => $formData['cornrowLength'],
      'hairpieceColor' => $formData['hairpieceColor'],
      'mobileActualService' => $formData['mobileActualService'],
      'mobilePersonCount' => $formData['mobilePersonCount'],
      'mobileAddress' => $formData['mobileAddress'],
      'mobilePlaceId' => $formData['mobilePlaceId'],
      'preferredDate' => $formData['preferredDate'],
      'preferredTime' => $formData['preferredTime'],
    ];

    // Only include primary booking if at least service OR date is filled
    if ($primaryBooking['service'] !== '' || $primaryBooking['preferredDate'] !== '') {
      $formData['bookings'][] = $primaryBooking;
    }

    // Process additional bookings
    $extraBookings = $_POST['bookings_extra'] ?? [];
    if (is_array($extraBookings)) {
      foreach ($extraBookings as $index => $row) {
        if (!is_array($row)) {
          continue;
        }

        // Sanitize each field
        $rowSanitized = [
          'service' => substr(trim((string)($row['service'] ?? '')), 0, 100),
          'location' => substr(trim((string)($row['location'] ?? '')), 0, 100),
          'stylist' => substr(trim((string)($row['stylist'] ?? '')), 0, 100),
          'subType' => substr(trim((string)($row['subType'] ?? '')), 0, 100),
          'hairLength' => substr(trim((string)($row['hairLength'] ?? '')), 0, 100),
          'braidSize' => substr(trim((string)($row['braidSize'] ?? '')), 0, 50),
          'cornrowLength' => substr(trim((string)($row['cornrowLength'] ?? '')), 0, 50),
          'hairpieceColor' => substr(trim((string)($row['hairpieceColor'] ?? '')), 0, 50),
          'mobileActualService' => substr(trim((string)($row['mobileActualService'] ?? '')), 0, 100),
          'mobilePersonCount' => substr(trim((string)($row['mobilePersonCount'] ?? '')), 0, 100),
          'mobileAddress' => substr(trim((string)($row['mobileAddress'] ?? '')), 0, 255),
          'mobilePlaceId' => substr(trim((string)($row['mobilePlaceId'] ?? '')), 0, 255),
          'preferredDate' => substr(trim((string)($row['preferredDate'] ?? '')), 0, 100),
          'preferredTime' => substr(trim((string)($row['preferredTime'] ?? '')), 0, 100),
        ];

        // Only include if at least service OR date is filled (skip empty rows)
        if ($rowSanitized['service'] !== '' || $rowSanitized['preferredDate'] !== '') {
          $formData['bookings'][] = $rowSanitized;
        }
      }
    }

    // Keep first booking mapped to legacy fields for JS restore.
    if (!empty($formData['bookings'])) {
      $formData['service'] = $formData['bookings'][0]['service'];
      $formData['location'] = $formData['bookings'][0]['location'];
      $formData['stylist'] = $formData['bookings'][0]['stylist'];
      $formData['subType'] = $formData['bookings'][0]['subType'];
      $formData['hairLength'] = $formData['bookings'][0]['hairLength'];
      $formData['braidSize'] = $formData['bookings'][0]['braidSize'] ?? '';
      $formData['cornrowLength'] = $formData['bookings'][0]['cornrowLength'] ?? '';
      $formData['hairpieceColor'] = $formData['bookings'][0]['hairpieceColor'] ?? '';
      $formData['mobileActualService'] = $formData['bookings'][0]['mobileActualService'] ?? '';
      $formData['mobilePersonCount'] = $formData['bookings'][0]['mobilePersonCount'] ?? '';
      $formData['mobileAddress'] = $formData['bookings'][0]['mobileAddress'] ?? '';
      $formData['mobilePlaceId'] = $formData['bookings'][0]['mobilePlaceId'] ?? '';
      $formData['preferredDate'] = $formData['bookings'][0]['preferredDate'];
      $formData['preferredTime'] = $formData['bookings'][0]['preferredTime'];
    }

    // Cashless online payment — client pays 50% deposit OR 100% in full (owner spec).
    $allowedPaymentMethods = ['online_deposit', 'online_full'];
    // Length is validated against the price matrix per slot (see below). Hair-extension
    // colour ranges are style-aware — see hairColourGroupFor()/allowedHairColourValues().

    if (!in_array($formData['paymentMethod'], $allowedPaymentMethods, true)) {
      $errors[] = 'Please select a valid payment method.';
    }

    $validServices = array_keys($servicesConfig);
    $validLocations = array_keys($locationsConfig);
    $servicesRequiringSubType = [];
    $servicesRequiringLength = [];
    $serviceSubTypesByService = [];
    foreach ($servicesConfig as $serviceKey => $serviceMeta) {
      if (!empty($serviceMeta['requires_sub_type'])) {
        $servicesRequiringSubType[] = $serviceKey;
      }
      if (!empty($serviceMeta['requires_hair_length'])) {
        $servicesRequiringLength[] = $serviceKey;
      }
      $serviceSubTypesByService[$serviceKey] = [];
      if (!empty($serviceMeta['subtypes']) && is_array($serviceMeta['subtypes'])) {
        foreach ($serviceMeta['subtypes'] as $subTypeMeta) {
          if (isset($subTypeMeta['key'])) {
            $serviceSubTypesByService[$serviceKey][] = (string)$subTypeMeta['key'];
          }
        }
      }
    }

    if (empty($formData['bookings'])) {
        $errors[] = 'Please add at least one booking slot.';
    }

    // Validation: Appointment slots (date/time with 1-hour buffer for today)
    foreach ($formData['bookings'] as $index => $slot) {
        $slotNumber = $index + 1;
      if ($slot['service'] === '' || !in_array($slot['service'], $validServices, true)) {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a valid service.';
      }

      if ($slot['location'] === '' || !in_array($slot['location'], $validLocations, true)) {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a valid location.';
      }

      if ($slot['stylist'] === '' || $slot['stylist'] === 'no-preference') {
        $errors[] = 'Slot #' . $slotNumber . ': Please select a specific stylist.';
      }

        if ($slot['preferredDate'] === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Appointment date is required.';
            continue;
        }

        $chosenDate = DateTimeImmutable::createFromFormat('!Y-m-d', $slot['preferredDate'], $bookingTimezone);
        $today = new DateTimeImmutable('today', $bookingTimezone);
        if ($chosenDate === false || $chosenDate < $today) {
            $errors[] = 'Slot #' . $slotNumber . ': Appointment date must be today or in the future.';
            continue;
        }

        if (in_array($slot['service'], $servicesRequiringSubType, true) && trim((string)$slot['subType']) === '') {
          $errors[] = 'Slot #' . $slotNumber . ': Please select a style/type.';
        }

        if (!empty($serviceSubTypesByService[$slot['service']])) {
          if (!in_array((string)$slot['subType'], $serviceSubTypesByService[$slot['service']], true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Please select a valid style/type for the selected service.';
          }
        }

        // Length validation is now data-driven by the price matrix: if the chosen
        // type is priced per length, a valid length tier must be selected.
        $priceRows = getServicePriceOptions($bookingCatalog, (string)$slot['service'], (string)($slot['subType'] ?? ''));
        $needsLength = !(count($priceRows) === 0 || (count($priceRows) === 1 && (string)$priceRows[0]['length_key'] === ''));
        if ($needsLength) {
          $chosenLength = (string)($slot['hairLength'] ?? '');
          $validLengths = array_map(static fn($r) => (string)$r['length_key'], $priceRows);
          if ($chosenLength === '' || !in_array($chosenLength, $validLengths, true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Please select a valid length for this style.';
          }
        }

        $hairColourGroup = hairColourGroupFor((string)$slot['service'], (string)($slot['subType'] ?? ''));
        $hairpieceColorValue = (string)($slot['hairpieceColor'] ?? '');
        if ($hairColourGroup !== '') {
          if ($hairpieceColorValue === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Please select a hair colour.';
          } elseif (!in_array($hairpieceColorValue, allowedHairColourValues((string)$slot['service'], (string)($slot['subType'] ?? '')), true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Please select a valid hair colour.';
          }
        }

        // Mobile service validation
        if ($slot['service'] === 'mobile') {
          if (trim((string)$slot['mobileActualService']) === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Please select the actual service for mobile booking.';
          } else if (!in_array($slot['mobileActualService'], $validServices, true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Invalid actual service selected for mobile booking.';
          }
          
          if (!in_array($slot['mobilePersonCount'], ['1', '2', 'group'], true)) {
            $errors[] = 'Slot #' . $slotNumber . ': Please select number of people for mobile service.';
          }

          if (trim((string)($slot['mobileAddress'] ?? '')) === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Please provide the service address for mobile booking.';
          }
        }

        // Mobile-only service validation (nails, lashes — no actual-service/person-count needed)
        if (in_array($slot['service'], $mobileOnlyServices, true)) {
          if (trim((string)($slot['mobileAddress'] ?? '')) === '') {
            $errors[] = 'Slot #' . $slotNumber . ': Please provide your service address.';
          }
        }

        $maxDate = $today->modify('+90 days');
        if ($chosenDate > $maxDate) {
            $errors[] = 'Slot #' . $slotNumber . ': Appointments can only be booked up to 90 days in advance.';
        }

        if (!isset($timeSlotMap[$slot['preferredTime']])) {
            $errors[] = 'Slot #' . $slotNumber . ': Please choose a valid appointment time.';
            continue;
        }

        if ($chosenDate == $today) {
            $slotMap = $timeSlotMap[$slot['preferredTime']] ?? null;
            if ($slotMap && isset($slotMap['db'])) {
                $slotTimeObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' ' . $slotMap['db'], $bookingTimezone);
                $now = new DateTimeImmutable('now', $bookingTimezone);
                $bufferedNow = $now->modify('+1 hour');
                if ($slotTimeObj === false || $slotTimeObj <= $bufferedNow) {
                    $errors[] = 'Slot #' . $slotNumber . ': You can only book slots at least 1 hour from now.';
                }
            }
        }
    }

    // Validation: Check for conflicts WITHIN the same form submission
    // Only check conflicts if there are multiple valid bookings
    if (count($formData['bookings']) > 1 && empty($errors)) {
        $slotUsage = []; // Track slot usage by date + time
        $stylistSlotUsage = []; // Track stylist usage by date + time + stylist
        
        foreach ($formData['bookings'] as $index => $slot) {
            $slotNumber = $index + 1;
            
            // Skip conflict checking if essential fields are missing
            if (empty($slot['preferredDate']) || empty($slot['preferredTime']) || empty($slot['stylist'])) {
                continue;
            }
            
            $slotKey = $slot['preferredDate'] . '|' . $slot['preferredTime'] . '|' . $slot['service'];
            $stylistSlotKey = $slot['preferredDate'] . '|' . $slot['preferredTime'] . '|' . strtolower(trim($slot['stylist']));
            
            // Check if same stylist is booked twice for same time slot
            if (isset($stylistSlotUsage[$stylistSlotKey]) && $slot['stylist'] !== 'no-preference') {
                $conflictSlotNumber = $stylistSlotUsage[$stylistSlotKey];
                $timeLabel = isset($timeSlotMap[$slot['preferredTime']]) ? $timeSlotMap[$slot['preferredTime']]['label'] : $slot['preferredTime'];
                $stylistLabel = isset($stylistsConfig[$slot['stylist']]) ? $stylistsConfig[$slot['stylist']] : $slot['stylist'];
                $errors[] = "Slot #$slotNumber conflicts with Slot #$conflictSlotNumber: You cannot book the same stylist ($stylistLabel) twice for the same time slot ({$slot['preferredDate']} $timeLabel).";
            } else {
                $stylistSlotUsage[$stylistSlotKey] = $slotNumber;
            }
            
            // Count bookings per slot+service to check capacity
            if (!isset($slotUsage[$slotKey])) {
                $slotUsage[$slotKey] = [];
            }
            $slotUsage[$slotKey][] = $slotNumber;
            
            // Check if too many bookings for same service at same time
            $serviceCapacityForSlot = (int)($servicesConfig[$slot['service']]['capacity'] ?? 1);
            if (count($slotUsage[$slotKey]) > $serviceCapacityForSlot) {
                $timeLabel = isset($timeSlotMap[$slot['preferredTime']]) ? $timeSlotMap[$slot['preferredTime']]['label'] : $slot['preferredTime'];
                $errors[] = "Slot #$slotNumber: Too many bookings for {$slot['service']} at {$slot['preferredDate']} $timeLabel. Maximum $serviceCapacityForSlot booking(s) per time slot for this service.";
            }
        }
    }

    // Validation: Terms acceptance
    if ($formData['depositAgree'] !== '1') {
        $errors[] = 'You must agree to the booking policy and deposit terms.';
    }

    // Demo mode hands off to WhatsApp (no PayFast), so skip the gateway config check.
    if (!$demoMode && $formData['paymentMethod'] === 'online_deposit') {
      $paymentConfigIssues = getPaymentConfigIssues();
      if ($paymentConfigIssues) {
        $errors = array_merge($errors, $paymentConfigIssues);
      }
    }

    if (!$errors && ($mysqli instanceof mysqli)) {
        // Final availability re-check through the shared engine: per-service capacity,
        // Braids two-on-one (helper auto-assigned), live 5-min holds, and admin blocks.
        $resolvedSlots = [];   // index => ['lead' => ?, 'helper' => ?]
        $subSlotCount = [];    // service|date|time => clients counted this submission
        $subBusy = [];         // date|time => [stylistKeyLower => true]

        foreach ($formData['bookings'] as $index => $slot) {
            $slotNumber = $index + 1;
            $appointmentTimeForDb = isset($timeSlotMap[$slot['preferredTime']])
                ? $timeSlotMap[$slot['preferredTime']]['db']
                : '';

            $svc = (string)$slot['service'];
            $loc = (string)$slot['location'];
            $date = (string)$slot['preferredDate'];
            $lead = (string)($slot['stylist'] ?? '');
            $slotKeyC = $svc . '|' . $date . '|' . $appointmentTimeForDb;
            $busyKeyC = $date . '|' . $appointmentTimeForDb;

            $check = availabilityRecheckSlot($mysqli, $svc, $loc, $date, $appointmentTimeForDb, $lead, [
                'catalog' => $bookingCatalog,
                'extra_slot_count' => (int)($subSlotCount[$slotKeyC] ?? 0),
                'extra_busy' => array_keys($subBusy[$busyKeyC] ?? []),
            ]);

            if (empty($check['ok'])) {
                $reasonMap = [
                    'slot_full' => 'That time slot is fully booked for this service. Please choose another slot.',
                    'stylist_taken' => 'Your selected stylist is already booked for that slot. Please choose another slot or stylist.',
                    'stylist_not_eligible' => 'The selected stylist is not available for this service/location.',
                    'need_two_braiders' => 'Not enough braiders are free for this Braids slot (two are required). Please pick another time.',
                    'no_stylist_free' => 'No stylist is free for that slot. Please choose another slot.',
                    'blocked' => 'That time is not available for booking. Please choose another slot.',
                ];
                $errors[] = 'Slot #' . $slotNumber . ': ' . ($reasonMap[(string)($check['reason'] ?? '')] ?? 'That slot is no longer available. Please choose another.');
                continue;
            }

            $resLead = ($check['lead'] ?? '') !== ''
                ? (string)$check['lead']
                : ((strtolower($lead) !== '' && strtolower($lead) !== 'no-preference') ? strtolower($lead) : '');
            $resHelper = $check['helper'] ?? null;
            $resolvedSlots[$index] = ['lead' => $resLead, 'helper' => $resHelper];

            $subSlotCount[$slotKeyC] = (int)($subSlotCount[$slotKeyC] ?? 0) + 1;
            if ($resLead !== '') {
                $subBusy[$busyKeyC][$resLead] = true;
            }
            if (!empty($resHelper)) {
                $subBusy[$busyKeyC][$resHelper] = true;
            }
        }
    }

    // Multi-service "build your visit": additional same-day services ride on the
    // primary slot. Validate + price them server-side (never trust client prices).
    $additionalResolved = ['items' => [], 'total' => 0.0, 'json' => null, 'errors' => []];
    $rawAdditional = $_POST['additionalServices'] ?? '';
    if (($mysqli instanceof mysqli) && is_string($rawAdditional) && trim($rawAdditional) !== '') {
        $decodedAdditional = json_decode($rawAdditional, true);
        if (is_array($decodedAdditional)) {
            $additionalResolved = resolveAdditionalServices($mysqli, $decodedAdditional, $bookingCatalog);
            foreach ($additionalResolved['errors'] as $additionalError) {
                $errors[] = $additionalError;
            }
        } else {
            $errors[] = 'There was a problem reading your additional services. Please try again.';
        }
    }

    if (!$errors && $demoMode) {
        // No database: this is a demo booking. Skip PayFast/DB entirely and show a
        // confirmation screen with a pre-filled WhatsApp message to the demo number.
        renderDemoBookingConfirmation($formData, $servicesConfig, $timeSlotMap, $locationsConfig, $stylistsConfig, $businessWhatsappUrl);
        exit;
    }

    if (!$errors) {
        $mPaymentId = 'Bella-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
      // Add-ons apply to the primary booking item (the calendar flow books one service).
      $primaryAddons = ($mysqli instanceof mysqli)
        ? resolveBookingAddons($mysqli, (string)($formData['bookings'][0]['service'] ?? ''), (array)($_POST['addons'] ?? []))
        : ['keys' => [], 'json' => null, 'total' => 0.0];
      // Resolve each mobile slot's travel fee ONCE (accurate per-km via Google, with
      // zone fallback) so the displayed amount and the stored attempt agree and we
      // don't call the Distance Matrix API twice. See computeMobileTravelFee().
      $travelFeeBySlot = [];
      foreach ($formData['bookings'] as $i => $slot) {
        $travelFeeBySlot[$i] = in_array($slot['service'], $mobileOnlyServices, true)
          ? (float)computeMobileTravelFee((string)($slot['mobilePlaceId'] ?? ''), (string)($slot['mobileAddress'] ?? ''))['fee']
          : 0.0;
      }

      // Per-slot pricing (owner price list): the FULL service price comes from the price
      // matrix (type + length) via getBookingItemPrice(); deposit is 50% of the priceable
      // parts (service + add-ons + additional services), travel is charged in FULL.
      // Returning clients may pay 100% (paymentMethod 'online_full').
      $isFullPayment = ($formData['paymentMethod'] === 'online_full');
      // Additional same-day services (full price) attach to the primary slot (index 0).
      $additionalFull = (float)$additionalResolved['total'];
      $amountBySlot = [];
      foreach ($formData['bookings'] as $i => $slot) {
        // Matrix-priced services use the resolved price; services not in the list
        // (mobile composite, nails/lashes, other) fall back to the base-price path.
        $serviceFull = getServicePriceOptions($bookingCatalog, (string)$slot['service'], (string)($slot['subType'] ?? ''))
          ? getBookingItemPrice($bookingCatalog, (string)$slot['service'], (string)($slot['subType'] ?? ''), (string)($slot['hairLength'] ?? ''))
          : (float)getBookingDepositAmount($slot, $serviceDepositMap) * 2;
        $addonsFull = ($i === 0) ? (float)$primaryAddons['total'] : 0.0;
        $extraFull  = ($i === 0) ? $additionalFull : 0.0;
        $travelFull = (float)($travelFeeBySlot[$i] ?? 0.0);
        $priceable  = $serviceFull + $addonsFull + $extraFull;
        if ($isFullPayment) {
          $amountBySlot[$i] = round($priceable + $travelFull, 2);
        } else {
          $amountBySlot[$i] = round(($priceable * BOOKING_DEPOSIT_PERCENTAGE) + $travelFull, 2);
        }
      }
      $amountValue = array_sum($amountBySlot);

      if (!ensurePaymentAttemptsTable($mysqli)) {
        $errors[] = 'Unable to initialize payment processing. Please try again.';
      } else {
        $attemptStatus = 'initiated';
        // Online checkouts hold the slot for 5 minutes (race protection) while the
        // client completes the PayFast deposit.
        $holdExpr = 'DATE_ADD(NOW(), INTERVAL 5 MINUTE)';
        foreach ($formData['bookings'] as $index => $slot) {
          $appointmentTimeForDb = isset($timeSlotMap[$slot['preferredTime']])
              ? $timeSlotMap[$slot['preferredTime']]['db']
              : '';

          $insertAttemptStmt = $mysqli->prepare(
            'INSERT INTO booking_payment_attempts (m_payment_id, first_name, last_name, email, phone, service, location, stylist, helper_stylist, sub_type, hair_length, braid_size, cornrow_length, hairpiece_color, mobile_actual_service, mobile_person_count, mobile_address, travel_surcharge, addons, addons_total, additional_services, additional_services_total, preferred_date, preferred_time, notes, amount, payment_method, status, hold_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . $holdExpr . ')'
          );

          $travelSurchargeNumeric = $travelFeeBySlot[$index] ?? 0.0;
          $isPrimaryItem = ($index === 0);
          $addonsValue = $isPrimaryItem ? $primaryAddons['json'] : null;
          $addonsTotalValue = $isPrimaryItem ? (float)$primaryAddons['total'] : 0.00;
          $additionalJsonValue = $isPrimaryItem ? $additionalResolved['json'] : null;
          $additionalTotalValue = $isPrimaryItem ? (float)$additionalResolved['total'] : 0.00;
          $amountNumeric = $amountBySlot[$index] ?? 0.0;
          $attemptStylist = ($resolvedSlots[$index]['lead'] ?? '') !== '' ? $resolvedSlots[$index]['lead'] : $slot['stylist'];
          $attemptHelper = $resolvedSlots[$index]['helper'] ?? null;
          $insertAttemptStmt->bind_param(
            'sssssssssssssssssdsdsdsssdss',
            $mPaymentId,
            $formData['firstName'],
            $formData['lastName'],
            $formData['email'],
            $formData['phone'],
            $slot['service'],
            $slot['location'],
            $attemptStylist,
            $attemptHelper,
            $slot['subType'],
            $slot['hairLength'],
            $slot['braidSize'],
            $slot['cornrowLength'],
            $slot['hairpieceColor'],
            $slot['mobileActualService'],
            $slot['mobilePersonCount'],
            $slot['mobileAddress'],
            $travelSurchargeNumeric,
            $addonsValue,
            $addonsTotalValue,
            $additionalJsonValue,
            $additionalTotalValue,
            $slot['preferredDate'],
            $appointmentTimeForDb,
            $formData['notes'],
            $amountNumeric,
            $formData['paymentMethod'],
            $attemptStatus
          );

          if (!$insertAttemptStmt->execute()) {
            $errors[] = 'Unable to start payment session. Please try again.';
          }

          $insertAttemptStmt->close();
        }
      }

        if (!$errors) {
            // Cashless: online deposit via PayFast is the only payment path.
            {
              $itemName = count($formData['bookings']) > 1 ? 'Bella Multi Booking' : 'Bella Booking';

              $slotSummaries = [];
              foreach ($formData['bookings'] as $slot) {
                $slotSummaries[] = ucfirst(str_replace('-', ' ', (string)$slot['service']))
                  . ' @ ' . ucfirst((string)$slot['location'])
                  . ' (' . $slot['preferredDate'] . ' ' . ($slot['preferredTime'] ?? '') . ')';
              }

              $bookingSummary = implode(' | ', array_filter([
                  'Items: ' . count($formData['bookings']),
                  'Bookings: ' . implode(', ', $slotSummaries)
              ]));

              $firstSlot = $formData['bookings'][0];

              $payfastData = [
                  'merchant_id' => PAYFAST_MERCHANT_ID,
                  'merchant_key' => PAYFAST_MERCHANT_KEY,
                  'return_url' => getPayFastReturnUrl(),
                  // Carry the booking reference so cancel.php can offer "resume payment"
                  // (re-post this same held attempt) instead of restarting from scratch.
                  'cancel_url' => getPayFastCancelUrl() . '?ref=' . rawurlencode($mPaymentId),
                  'notify_url' => getPayFastNotifyUrl(),
                  'name_first' => $formData['firstName'],
                  'name_last' => $formData['lastName'],
                  'email_address' => $formData['email'],
                  'm_payment_id' => $mPaymentId,
                  'amount' => number_format((float)$amountValue, 2, '.', ''),
                  'item_name' => $itemName,
                  'custom_str1' => $firstSlot['preferredDate'],
                  'custom_str2' => $firstSlot['preferredTime'],
                  'custom_str3' => $firstSlot['service'],
                  'custom_str4' => $firstSlot['location'],
                  'custom_str5' => $bookingSummary
              ];

                // PayFast signature must be generated from exactly the same
                // non-empty fields that are posted in the final form.
                $payfastData = array_filter(
                  $payfastData,
                  static function ($value): bool {
                    return trim((string)$value) !== '';
                  }
                );

              $payfastData['signature'] = buildPayFastSignature($payfastData, PAYFAST_PASSPHRASE);
              ?>
              <!DOCTYPE html>
              <html lang="en">
              <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Redirecting to PayFast...</title>
                <link rel="stylesheet" href="css/style.css">
              </head>
              <body>
                <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
                  <div>
                    <p class="section-eyebrow light">Payment</p>
                    <h1 class="section-title light">Redirecting to <em>PayFast</em></h1>
                    <p style="color:#aaa;">Please wait while we redirect you to secure payment.</p>
                  </div>
                </section>

                <form id="payfastForm" action="<?php echo htmlspecialchars(getPayFastProcessUrl(), ENT_QUOTES, 'UTF-8'); ?>" method="post">
                  <?php foreach ($payfastData as $key => $value): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php endforeach; ?>
                </form>
                <script>
                  document.getElementById('payfastForm').submit();
                </script>
              </body>
              </html>
              <?php
              $mysqli->close();
              exit;
            }
        }
    }
}

if ($mysqli instanceof mysqli) {
  $mysqli->close();
}

/* =========================================================================
 * The standard booking form has been retired. book.php (the live-calendar
 * flow) is now the ONLY booking UI. This file remains the shared PROCESSOR
 * that book.php submits to (validation, deposit, PayFast, cash, ITN hand-off).
 *   - GET             -> send visitors to the new calendar.
 *   - POST w/ errors  -> show the errors with a link back to the calendar.
 * (Successful cash/online POSTs already rendered + exited above.)
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: book.php');
    exit;
}

$errorList = !empty($errors) ? $errors : ['Something went wrong with your booking. Please try again.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Booking — please check your details</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body class="section-dark" style="background:#0a0a0a;min-height:100vh;">
  <section class="page-hero section-dark" style="padding:8rem 1rem 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div style="max-width:560px;margin:0 auto;">
      <p class="section-eyebrow light">Booking</p>
      <h1 class="section-title light">Let's fix a <em>couple of things</em></h1>
      <div style="background:#3a1212;border:1px solid #7a2a2a;color:#f1b0b0;padding:1rem 1.25rem;border-radius:10px;text-align:left;margin:1.5rem 0;">
        <ul style="margin:0;padding-left:1.2rem;">
          <?php foreach ($errorList as $e): ?>
            <li style="margin-bottom:.4rem;"><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <a href="book.php" class="btn btn-gold">← Back to booking</a>
    </div>
  </section>
</body>
</html>
<?php
exit;
// __BOOKING_PROCESSOR_END__/n