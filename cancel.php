<?php
require_once __DIR__ . '/config.php';

// PayFast returns here on cancel with ?ref=<m_payment_id> (set in booking.php).
// If we have a reference, "Try Again" resumes the held payment instead of restarting.
$ref = trim((string)($_GET['ref'] ?? ''));
$retryUrl = $ref !== '' ? 'retry-payment.php?ref=' . rawurlencode($ref) : 'book.php';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Cancelled - Bella Hair | Makeup</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <section class="page-hero section-dark" style="padding:8rem 0 4rem;text-align:center;min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div>
      <p class="section-eyebrow light">Payment Status</p>
      <h1 class="section-title light">Payment <em>Cancelled</em></h1>
      <p style="color:#aaa;max-width:520px;margin:0 auto 1.25rem;">Your payment wasn't completed. <?php echo $ref !== '' ? 'Your time slot is still held for a few minutes — pick up right where you left off.' : 'Your booking wasn\'t placed.'; ?></p>
      <a href="<?php echo htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-gold"><?php echo $ref !== '' ? 'Resume payment' : 'Start booking'; ?></a>
      <?php if ($ref !== ''): ?>
        <div style="margin-top:1rem;"><a href="book.php" style="color:#aaa;text-decoration:underline;">Or start a new booking</a></div>
      <?php endif; ?>
    </div>
  </section>
</body>
</html>
