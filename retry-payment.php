<?php
/**
 * retry-payment.php — resume a cancelled-but-still-held PayFast payment.
 *
 * When a client cancels at PayFast, cancel.php sends them here with ?ref=<m_payment_id>.
 * Rather than make them rebuild the whole booking, we reload the held payment attempt,
 * re-reserve the slot (extend the 5-minute hold) and re-post the SAME cart to PayFast.
 * ITN remains the authoritative gate (it re-checks capacity before creating the booking),
 * so resuming is safe. If the hold has expired or the attempt is gone/already paid, we
 * send them back to the calendar with a friendly note.
 */

require_once __DIR__ . '/config.php';

$ref = trim((string)($_GET['ref'] ?? ''));

function retryBounce(string $message): void
{
    // Friendly fallback page → back to the calendar.
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Resume booking — Bella Hair | Makeup</title>
      <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
      <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
        <div style="max-width:520px;">
          <p class="section-eyebrow light">Booking</p>
          <h1 class="section-title light">Let's get you <em>booked</em></h1>
          <p style="color:#aaa;margin:0 auto 1.25rem;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
          <a href="book.php" class="btn btn-gold">Start a new booking</a>
        </div>
      </section>
    </body>
    </html><?php
    exit;
}

$mysqli = tryGetDbConnection();
if (!($mysqli instanceof mysqli) || $ref === '') {
    retryBounce('We couldn\'t find that booking to resume. Please start a new booking — it only takes a minute.');
}

// Load the held attempt(s) for this reference.
$stmt = $mysqli->prepare(
    'SELECT first_name, last_name, email, amount, status, hold_expires_at
     FROM booking_payment_attempts WHERE m_payment_id = ?'
);
$stmt->bind_param('s', $ref);
$stmt->execute();
$res = $stmt->get_result();
$attempts = [];
while ($row = $res->fetch_assoc()) {
    $attempts[] = $row;
}
$stmt->close();

if (!$attempts) {
    retryBounce('That booking has expired or was already completed. Please start a new booking.');
}

// Already paid? Send them to the confirmation rather than charging twice.
foreach ($attempts as $a) {
    if (($a['status'] ?? '') === 'paid') {
        header('Location: success.php');
        exit;
    }
}

// Hold expired → the slot may have been released; safest to rebook.
$now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$stillHeld = false;
foreach ($attempts as $a) {
    $exp = (string)($a['hold_expires_at'] ?? '');
    if ($exp !== '' && new DateTime($exp, new DateTimeZone(APP_TIMEZONE)) > $now) {
        $stillHeld = true;
        break;
    }
}
if (!$stillHeld) {
    retryBounce('Your held time slot has expired. Please choose a fresh time — it only takes a minute.');
}

// Payment must still be configured (e.g. live passphrase present in live mode).
$paymentConfigIssues = getPaymentConfigIssues();
if ($paymentConfigIssues) {
    retryBounce('Online payment is temporarily unavailable. Please try again shortly or contact us on WhatsApp.');
}

// Re-reserve the slot: extend the hold so the client has time to pay again.
$extend = $mysqli->prepare(
    "UPDATE booking_payment_attempts
     SET hold_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
     WHERE m_payment_id = ? AND status = 'initiated'"
);
if ($extend) {
    $extend->bind_param('s', $ref);
    $extend->execute();
    $extend->close();
}

$first = $attempts[0];
$totalAmount = 0.0;
foreach ($attempts as $a) {
    $totalAmount += (float)$a['amount'];
}

$itemName = count($attempts) > 1 ? 'Bella Multi Booking' : 'Bella Booking';

$payfastData = [
    'merchant_id'   => PAYFAST_MERCHANT_ID,
    'merchant_key'  => PAYFAST_MERCHANT_KEY,
    'return_url'    => getPayFastReturnUrl(),
    'cancel_url'    => getPayFastCancelUrl() . '?ref=' . rawurlencode($ref),
    'notify_url'    => getPayFastNotifyUrl(),
    'name_first'    => (string)$first['first_name'],
    'name_last'     => (string)$first['last_name'],
    'email_address' => (string)$first['email'],
    'm_payment_id'  => $ref,
    'amount'        => number_format($totalAmount, 2, '.', ''),
    'item_name'     => $itemName,
];

// Sign over exactly the non-empty fields that get posted (same rule as booking.php).
$payfastData = array_filter($payfastData, static function ($value): bool {
    return trim((string)$value) !== '';
});
$payfastData['signature'] = buildPayFastSignature($payfastData, PAYFAST_PASSPHRASE);

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resuming your payment…</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div>
      <p class="section-eyebrow light">Payment</p>
      <h1 class="section-title light">Resuming your <em>payment</em></h1>
      <p style="color:#aaa;">Taking you back to secure checkout…</p>
    </div>
  </section>

  <form id="payfastForm" action="<?php echo htmlspecialchars(getPayFastProcessUrl(), ENT_QUOTES, 'UTF-8'); ?>" method="post">
    <?php foreach ($payfastData as $key => $value): ?>
      <input type="hidden" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
  </form>
  <script>document.getElementById('payfastForm').submit();</script>
</body>
</html>
