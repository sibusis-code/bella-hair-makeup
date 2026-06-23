<?php
/**
 * success.php - PayFast browser return page.
 *
 * PRODUCTION RULE: This page is DISPLAY-ONLY.
 * All DB writes (booking finalization) are handled exclusively by itn.php (server-to-server ITN).
 * This page only reads the DB to show the correct status message to the client.
 */
require_once __DIR__ . '/config.php';

$statusTitle   = 'Payment Received';
$statusMessage = 'Thank you. Your payment was received. We are confirming your booking - this usually takes a few seconds.';
$statusVariant = 'ok';

if (isset($_GET['m_payment_id'])) {
    $mPaymentId = trim((string)$_GET['m_payment_id']);

    if ($mPaymentId !== '') {
    try {
      $mysqli = tryGetDbConnection();

      if (!($mysqli instanceof mysqli)) {
        $statusTitle = 'Payment Received';
        $statusMessage = 'Your payment was received. Our booking systems are temporarily syncing, so confirmation may take a little longer.';
        $statusVariant = 'ok';
      } else {

        // Check if ITN already finalized the booking (the happy path).
        $bookingStmt = $mysqli->prepare(
          'SELECT id FROM salon_bookings WHERE m_payment_id = ? AND status = ? LIMIT 1'
        );
        $paidStatus = 'paid';
        $bookingStmt->bind_param('ss', $mPaymentId, $paidStatus);
        $bookingStmt->execute();
        $bookingResult = $bookingStmt->get_result();
        $existingBooking = $bookingResult->fetch_assoc();
        $bookingStmt->close();

        if ($existingBooking) {
          $statusTitle   = 'Booking Confirmed';
          $statusMessage = 'Your payment is verified and your appointment is booked. See you soon!';
          $statusVariant = 'ok';
        } else {
          // ITN may not have fired yet - check the attempt record.
          $attemptStmt = $mysqli->prepare(
            'SELECT status FROM booking_payment_attempts WHERE m_payment_id = ? LIMIT 1'
          );
          $attemptStmt->bind_param('s', $mPaymentId);
          $attemptStmt->execute();
          $attemptResult = $attemptStmt->get_result();
          $attempt = $attemptResult->fetch_assoc();
          $attemptStmt->close();

          if ($attempt && ($attempt['status'] ?? '') === 'paid') {
            $statusTitle   = 'Booking Confirmed';
            $statusMessage = 'Your payment is verified and your appointment is booked. See you soon!';
            $statusVariant = 'ok';
          } elseif ($attempt && ($attempt['status'] ?? '') === 'failed') {
            $statusTitle   = 'Slot No Longer Available';
            $statusMessage = 'Your payment was received, but that time slot was taken by someone else. Please contact us to reschedule or for a refund.';
            $statusVariant = 'warn';
          } else {
            // ITN pending - do NOT write anything here; just inform the client.
            $statusTitle   = 'Payment Received';
            $statusMessage = 'Your payment was received. Booking confirmation is being processed and will be ready shortly. Keep your reference number below.';
            $statusVariant = 'ok';
          }
                }

        $mysqli->close();
      }
        } catch (Throwable $e) {
            error_log('[success.php] DB error for m_payment_id=' . $mPaymentId . ': ' . $e->getMessage());
            $statusTitle   = 'Payment Received';
            $statusMessage = 'Your payment was received. If you do not receive a confirmation shortly, please contact us with your reference number.';
            $statusVariant = 'ok';
        }
    }

    // Log: browser return for audit trail (no DB writes).
    error_log('[success.php] Browser return m_payment_id=' . ($mPaymentId ?? 'none') . ' status=' . $statusTitle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Status - Bella Hair | Makeup</title>
  <link rel="stylesheet" href="css/style.css">
  <?php echo ga4Snippet(); ?>
</head>
<body>
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div>
      <p class="section-eyebrow light">Payment Status</p>
      <h1 class="section-title light"><?php echo htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p style="color:<?php echo $statusVariant === 'ok' ? '#aaa' : '#f0c36d'; ?>;max-width:620px;margin:0 auto 1.25rem;">
        <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <?php if (isset($_GET['m_payment_id'])): ?>
        <p style="color:#aaa;font-size:0.9rem;margin-bottom:1.25rem;">Reference: <?php echo htmlspecialchars((string)$_GET['m_payment_id'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <a href="book.php" class="btn btn-gold">Back to Booking</a>
    </div>
  </section>
</body>
</html>