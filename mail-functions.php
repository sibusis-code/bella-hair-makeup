<?php
// Email functions for booking notifications
require_once __DIR__ . '/config.php';

/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool
{
    $plainBody = $plainBody ?: strip_tags($htmlBody);
    
    if (!EMAIL_USE_SMTP) {
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }
    
    return mailViaSMTP($to, $subject, $htmlBody, $plainBody);
}

/**
 * Send email via PHP mail() function
 */
function mailViaPhp(string $to, string $subject, string $htmlBody, string $plainBody): bool
{
    [$headers, $body] = buildMimeMessage($subject, $htmlBody, $plainBody);
    $params = '-f' . EMAIL_FROM_ADDRESS;
    return @mail($to, $subject, $body, $headers, $params);
}

/**
 * Build a multipart/alternative (plain + HTML) MIME body and headers (CRLF).
 * Returns [headersString, bodyString].
 */
function buildMimeMessage(string $subject, string $htmlBody, string $plainBody): array
{
    $boundary = 'bella-' . bin2hex(random_bytes(12));
    $host = parse_url(getSiteBaseUrl(), PHP_URL_HOST) ?: 'bellahairandmakeup.co.za';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . EMAIL_FROM_ADDRESS,
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Bella-CRM/1.0',
    ];

    $body  = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $plainBody . "\r\n\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= '--' . $boundary . "--\r\n";

    return [implode("\r\n", $headers), $body];
}

function smtpExpect($conn, array $expected, string $stage): bool
{
    $reply = '';
    while (($line = fgets($conn, 515)) !== false) {
        $reply .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    $code = (int)substr(trim($reply), 0, 3);
    if (!in_array($code, $expected, true)) {
        error_log('[mail] SMTP ' . $stage . ' unexpected reply: ' . trim($reply));
        return false;
    }
    return true;
}

function smtpSend($conn, string $command): void
{
    fwrite($conn, $command . "\r\n");
}

/**
 * Send email via SMTP (using mail() with SMTP settings)
 * Note: For production, consider using PHPMailer or SwiftMailer
 */
function mailViaSMTP(string $to, string $subject, string $htmlBody, string $plainBody): bool
{
    if (EMAIL_SMTP_HOST === '' || EMAIL_SMTP_USER === '' || EMAIL_SMTP_PASS === '') {
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }

    $port = EMAIL_SMTP_PORT > 0 ? EMAIL_SMTP_PORT : 587;
    $useImplicitTls = ($port === 465);
    $host = ($useImplicitTls ? 'ssl://' : '') . EMAIL_SMTP_HOST;
    $localHost = parse_url(getSiteBaseUrl(), PHP_URL_HOST) ?: 'bellahairandmakeup.co.za';

    // Verify the SMTP server's TLS certificate (prevents MITM capture of the
    // mailbox credentials sent during AUTH LOGIN).
    $tlsContext = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => EMAIL_SMTP_HOST,
            'SNI_enabled' => true,
        ],
    ]);

    $errno = 0; $errstr = '';
    $conn = @stream_socket_client($host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $tlsContext);
    if (!$conn) {
        error_log('[mail] SMTP connect failed: ' . $errstr . ' (' . $errno . ') — falling back to mail()');
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }
    stream_set_timeout($conn, 15);

    $ok = smtpExpect($conn, [220], 'greeting');
    smtpSend($conn, 'EHLO ' . $localHost);
    $ok = $ok && smtpExpect($conn, [250], 'EHLO');

    if ($ok && !$useImplicitTls) {
        smtpSend($conn, 'STARTTLS');
        $ok = $ok && smtpExpect($conn, [220], 'STARTTLS');
        // Apply certificate-verification options to the live stream before the handshake.
        stream_context_set_option($conn, 'ssl', 'verify_peer', true);
        stream_context_set_option($conn, 'ssl', 'verify_peer_name', true);
        stream_context_set_option($conn, 'ssl', 'peer_name', EMAIL_SMTP_HOST);
        if ($ok && !@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            error_log('[mail] SMTP STARTTLS handshake failed');
            $ok = false;
        }
        if ($ok) {
            smtpSend($conn, 'EHLO ' . $localHost);
            $ok = smtpExpect($conn, [250], 'EHLO-TLS');
        }
    }

    if ($ok) {
        smtpSend($conn, 'AUTH LOGIN');
        $ok = smtpExpect($conn, [334], 'AUTH');
        smtpSend($conn, base64_encode(EMAIL_SMTP_USER));
        $ok = $ok && smtpExpect($conn, [334], 'AUTH-user');
        smtpSend($conn, base64_encode(EMAIL_SMTP_PASS));
        $ok = $ok && smtpExpect($conn, [235], 'AUTH-pass');
    }

    if ($ok) {
        smtpSend($conn, 'MAIL FROM:<' . EMAIL_FROM_ADDRESS . '>');
        $ok = smtpExpect($conn, [250], 'MAIL FROM');
        smtpSend($conn, 'RCPT TO:<' . $to . '>');
        $ok = $ok && smtpExpect($conn, [250, 251], 'RCPT TO');
        smtpSend($conn, 'DATA');
        $ok = $ok && smtpExpect($conn, [354], 'DATA');
    }

    if ($ok) {
        [$headers, $body] = buildMimeMessage($subject, $htmlBody, $plainBody);
        $message = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
            . 'To: ' . $to . "\r\n"
            . $headers . "\r\n\r\n"
            . $body;
        $message = preg_replace('/^\./m', '..', $message); // dot-stuffing
        smtpSend($conn, $message . "\r\n.");
        $ok = smtpExpect($conn, [250], 'message');
    }

    smtpSend($conn, 'QUIT');
    fclose($conn);

    if (!$ok) {
        error_log('[mail] SMTP send failed for ' . $to . ' — falling back to mail()');
        return mailViaPhp($to, $subject, $htmlBody, $plainBody);
    }
    return true;
}

/**
 * Send booking confirmation email to client
 */
function sendBookingConfirmation(array $booking): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $service = htmlspecialchars($booking['service'] ?? 'Styling', ENT_QUOTES, 'UTF-8');
    $amount = number_format($booking['amount'], 2);
    $paymentId = htmlspecialchars($booking['m_payment_id'], ENT_QUOTES, 'UTF-8');
    
    // New: Include all booking details
    $subType = !empty($booking['sub_type']) ? htmlspecialchars($booking['sub_type'], ENT_QUOTES, 'UTF-8') : '';
    $hairLength = !empty($booking['hair_length']) ? htmlspecialchars($booking['hair_length'], ENT_QUOTES, 'UTF-8') : '';
    $location = htmlspecialchars($booking['location'] ?? '', ENT_QUOTES, 'UTF-8');
    $stylist = htmlspecialchars($booking['preferred_stylist'] ?? '', ENT_QUOTES, 'UTF-8');
    $mobileActualService = !empty($booking['mobile_actual_service']) ? htmlspecialchars($booking['mobile_actual_service'], ENT_QUOTES, 'UTF-8') : '';
    $mobilePersonCount = !empty($booking['mobile_person_count']) ? htmlspecialchars($booking['mobile_person_count'], ENT_QUOTES, 'UTF-8') : '';

    // Additional same-day services ("build your visit"). Build readable lines for both bodies.
    $additionalHtml = '';
    $additionalPlain = '';
    if (!empty($booking['additional_services'])) {
        $extras = json_decode((string)$booking['additional_services'], true);
        if (is_array($extras)) {
            foreach ($extras as $extra) {
                $label = htmlspecialchars((string)($extra['label'] ?? $extra['service'] ?? 'Service'), ENT_QUOTES, 'UTF-8');
                $price = number_format((float)($extra['price'] ?? 0), 2);
                $additionalHtml .= "<div>• {$label} — R{$price}</div>";
                $additionalPlain .= "  - " . strip_tags($label) . " (R{$price})\n";
            }
        }
    }

    // Cash bookings haven't paid the deposit yet — adjust the wording accordingly.
    $isCash = (($booking['status'] ?? '') === 'pending_cash') || (($booking['payment_method'] ?? '') === 'cash_50');
    // Returning clients can settle 100% up front (payment_method 'online_full').
    $isFull = (($booking['payment_method'] ?? '') === 'online_full');
    $headerSubtitle = $isCash ? 'Your booking is received!' : 'Your booking is confirmed!';
    $depositLabel = $isCash
        ? 'Deposit Due (cash on arrival)'
        : ($isFull ? 'Paid in Full' : 'Deposit Paid (50%)');
    
    // Format display values
    $additionalDisplay = $additionalHtml ? "<div class=\"detail-row\"><span class=\"detail-label\">Also booked:</span><span class=\"detail-value\">$additionalHtml</span></div>" : '';
    $subTypeDisplay = $subType ? "<div class=\"detail-row\"><span class=\"detail-label\">Style:</span><span class=\"detail-value\">$subType</span></div>" : '';
    $hairLengthDisplay = $hairLength ? "<div class=\"detail-row\"><span class=\"detail-label\">Hair Length:</span><span class=\"detail-value\">$hairLength</span></div>" : '';
    $locationDisplay = $location && $location !== 'no-preference' ? "<div class=\"detail-row\"><span class=\"detail-label\">Location:</span><span class=\"detail-value\">$location</span></div>" : '';
    $stylistDisplay = $stylist && $stylist !== 'no-preference' ? "<div class=\"detail-row\"><span class=\"detail-label\">Stylist:</span><span class=\"detail-value\">$stylist</span></div>" : '';
    
    // Mobile service display
    $mobileDisplay = '';
    if ($service === 'mobile' && $mobileActualService) {
        $personCountLabel = '';
        if ($mobilePersonCount === '1') $personCountLabel = '1 Person';
        elseif ($mobilePersonCount === '2') $personCountLabel = '2 People';
        elseif ($mobilePersonCount === 'group') $personCountLabel = 'Group (3+ people)';
        else $personCountLabel = $mobilePersonCount;
        
        $mobileDisplay .= "<div class=\"detail-row\"><span class=\"detail-label\">Actual Service:</span><span class=\"detail-value\">$mobileActualService</span></div>";
        $mobileDisplay .= "<div class=\"detail-row\"><span class=\"detail-label\">Number of People:</span><span class=\"detail-value\">$personCountLabel</span></div>";
    }
    
    $subject = 'Booking Confirmation - Bella Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .detail-row { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin-bottom: 10px; }
        .detail-label { font-weight: bold; color: #666; }
        .detail-value { color: #333; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
        .button { display: inline-block; padding: 10px 20px; background: #c9a961; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bella Hair & Makeup</h1>
            <p>$headerSubtitle</p>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <p>Thank you for booking with us! We're excited to see you. Here are your appointment details:</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">$appointmentDate</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">$appointmentTime</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value">$service</span>
                </div>
                $additionalDisplay
                $subTypeDisplay
                $hairLengthDisplay
                $locationDisplay
                $stylistDisplay
                $mobileDisplay
                <div class="detail-row">
                    <span class="detail-label">$depositLabel:</span>
                    <span class="detail-value">R$amount</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reference:</span>
                    <span class="detail-value">$paymentId</span>
                </div>
            </div>
            
            <p><strong>Important:</strong></p>
            <ul>
                <li>Please arrive 10 minutes early</li>
                <li>If you need to reschedule, contact us at least 24 hours in advance</li>
                <li>Cancellations made less than 24 hours before may forfeit your deposit</li>
            </ul>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
            
            <p>We look forward to seeing you!</p>
            
            <p>Best regards,<br>The Bella Team</p>
        </div>
        
        <div class="footer">
            <p>Bella Hair & Makeup | Midrand & Copperleaf, Gauteng<br>
            Questions? Contact us: <a href="mailto:" . EMAIL_FROM_ADDRESS . ">" . EMAIL_FROM_ADDRESS . "</a></p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "Bella Hair & Makeup - Booking Confirmation\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Thank you for booking with us!\n\n";
    $plainBody .= "Your Appointment:\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n";
    $plainBody .= "Service: $service\n";
    if ($additionalPlain) $plainBody .= "Also booked (same visit):\n" . $additionalPlain;
    if ($subType) $plainBody .= "Style: $subType\n";
    if ($hairLength) $plainBody .= "Hair Length: $hairLength\n";
    if ($location && $location !== 'no-preference') $plainBody .= "Location: $location\n";
    if ($stylist && $stylist !== 'no-preference') $plainBody .= "Stylist: $stylist\n";
    $plainBody .= "$depositLabel: R$amount\n";
    $plainBody .= "Reference: $paymentId\n\n";
    $plainBody .= "Important:\n";
    $plainBody .= "- Please arrive 10 minutes early\n";
    $plainBody .= "- Reschedule requests need 24 hours notice\n";
    $plainBody .= "- Cancellations within 24 hours may forfeit your deposit\n\n";
    $plainBody .= "We look forward to seeing you!\n";
    $plainBody .= "The Bella Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send status update email to client
 */
function sendStatusUpdateEmail(array $booking, string $newStatus): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    
    $statusMessages = [
        'confirmed' => 'Your appointment has been confirmed by our team.',
        'paid' => 'Payment received! Your appointment is confirmed.',
        'completed' => 'Thank you for visiting Bella! We hope you loved your experience.',
        'cancelled' => 'Your appointment has been cancelled. Please contact us for rescheduling.'
    ];
    
    $message = $statusMessages[$newStatus] ?? 'Your booking status has been updated.';
    
    $subject = 'Booking Status Update - Bella Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .status-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .status-label { font-weight: bold; color: #c9a961; text-transform: uppercase; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bella Hair & Makeup</h1>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <div class="status-box">
                <p class="status-label">Status: " . ucfirst($newStatus) . "</p>
                <p>$message</p>
            </div>
            
            <p><strong>Appointment Details:</strong></p>
            <p>Date: $appointmentDate<br>
            Time: $appointmentTime</p>
            
            <p>If you have any questions, please contact us.</p>
            
            <p>Best regards,<br>The Bella Team</p>
        </div>
        
        <div class="footer">
            <p>Bella Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "Bella Hair & Makeup - Status Update\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Status: " . ucfirst($newStatus) . "\n";
    $plainBody .= "$message\n\n";
    $plainBody .= "Appointment: $appointmentDate at $appointmentTime\n\n";
    $plainBody .= "The Bella Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send cancellation email to client
 */
function sendCancellationEmail(array $booking, string $reason): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $reasonText = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
    
    $subject = 'Booking Cancelled - Bella Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .alert { background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bella Hair & Makeup</h1>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <div class="alert">
                <p><strong>Your appointment has been cancelled.</strong></p>
                <p><strong>Reason:</strong> $reasonText</p>
            </div>
            
            <p><strong>Original Appointment:</strong></p>
            <p>Date: $appointmentDate<br>
            Time: $appointmentTime</p>
            
            <p>If you would like to reschedule or have questions, please contact us.</p>
            
            <p>Best regards,<br>The Bella Team</p>
        </div>
        
        <div class="footer">
            <p>Bella Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "Bella Hair & Makeup - Booking Cancelled\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Your appointment has been cancelled.\n\n";
    $plainBody .= "Reason: $reasonText\n\n";
    $plainBody .= "Original Appointment:\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n\n";
    $plainBody .= "Contact us if you'd like to reschedule.\n";
    $plainBody .= "The Bella Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}

/**
 * Send admin notification of new booking
 */
function sendAdminNewBookingNotification(array $booking): bool
{
    $subject = 'New Booking - ' . htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8') . ' - Bella CRM';
    
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $clientPhone = htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8');
    $appointmentDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
    $service = htmlspecialchars($booking['service'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $amount = number_format($booking['amount'], 2);
    $paymentId = htmlspecialchars($booking['m_payment_id'], ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8');
    
    $siteUrl = getSiteBaseUrl();
    $bookingUrl = $siteUrl . '/admin-booking-detail.php?id=' . $booking['id'];
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #c9a961; color: white; padding: 15px; text-align: center; }
        .content { padding: 15px; }
        .details { background: #f9f9f9; padding: 10px; margin: 10px 0; }
        .detail { margin: 5px 0; }
        .button { display: inline-block; padding: 8px 15px; background: #c9a961; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Booking - $clientName</h1>
        </div>
        
        <div class="content">
            <p>A new booking has been received. Please review and confirm with the client.</p>
            
            <div class="details">
                <div class="detail"><strong>Client:</strong> $clientName</div>
                <div class="detail"><strong>Email:</strong> $clientEmail</div>
                <div class="detail"><strong>Phone:</strong> $clientPhone</div>
                <div class="detail"><strong>Date:</strong> $appointmentDate</div>
                <div class="detail"><strong>Time:</strong> $appointmentTime</div>
                <div class="detail"><strong>Service:</strong> $service</div>
                <div class="detail"><strong>Deposit:</strong> R$amount</div>
                <div class="detail"><strong>Status:</strong> $status</div>
                <div class="detail"><strong>Payment ID:</strong> $paymentId</div>
            </div>
            
            <a href="$bookingUrl" class="button">View in CRM</a>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "NEW BOOKING\n\n";
    $plainBody .= "Client: $clientName\n";
    $plainBody .= "Email: $clientEmail\n";
    $plainBody .= "Phone: $clientPhone\n";
    $plainBody .= "Date: $appointmentDate\n";
    $plainBody .= "Time: $appointmentTime\n";
    $plainBody .= "Service: $service\n";
    $plainBody .= "Deposit: R$amount\n";
    $plainBody .= "Status: $status\n";
    $plainBody .= "Payment ID: $paymentId\n\n";
    $plainBody .= "View in CRM: $bookingUrl";
    
    return sendEmail(EMAIL_ADMIN_ADDRESS, $subject, $htmlBody, $plainBody);
}

/**
 * Send reschedule notification to client
 */
function sendRescheduleEmail(array $booking, string $oldDate, string $oldTime): bool
{
    $clientName = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
    $clientEmail = htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8');
    $oldDateFormatted = date('l, F d, Y', strtotime($oldDate));
    $oldTimeFormatted = date('g:i A', strtotime($oldTime));
    $newDate = date('l, F d, Y', strtotime($booking['appointment_date']));
    $newTime = date('g:i A', strtotime($booking['appointment_time']));
    
    $subject = 'Appointment Rescheduled - Bella Hair & Makeup';
    
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #c9a961; }
        .content { padding: 20px; background: #f9f9f9; }
        .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #c9a961; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; color: #666; }
        .footer { padding: 15px; text-align: center; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bella Hair & Makeup</h1>
            <p>Your appointment has been rescheduled</p>
        </div>
        
        <div class="content">
            <p>Hi $clientName,</p>
            
            <p>Your appointment has been rescheduled. Here are your updated details:</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="label">Previous Appointment:</span><br>
                    $oldDateFormatted at $oldTimeFormatted
                </div>
                <div class="detail-row">
                    <span class="label">New Appointment:</span><br>
                    $newDate at $newTime
                </div>
            </div>
            
            <p>If you have any questions about the new appointment time, please let us know as soon as possible.</p>
            
            <p>We look forward to seeing you!</p>
            
            <p>Best regards,<br>The Bella Team</p>
        </div>
        
        <div class="footer">
            <p>Bella Hair & Makeup | Midrand & Copperleaf, Gauteng</p>
        </div>
    </div>
</body>
</html>
HTML;

    $plainBody = "Bella Hair & Makeup - Appointment Rescheduled\n\n";
    $plainBody .= "Hi $clientName,\n\n";
    $plainBody .= "Your appointment has been rescheduled.\n\n";
    $plainBody .= "Previous: $oldDateFormatted at $oldTimeFormatted\n";
    $plainBody .= "New: $newDate at $newTime\n\n";
    $plainBody .= "Please confirm if this time works for you.\n";
    $plainBody .= "The Bella Team";
    
    return sendEmail($clientEmail, $subject, $htmlBody, $plainBody);
}
