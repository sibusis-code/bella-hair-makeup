<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) { http_response_code(404); die('Booking not found.'); }

$booking = getBookingById($bookingId);
if (!$booking) { http_response_code(404); die('Booking not found.'); }

$notes       = getBookingNotes($bookingId);
$stats       = getBookingStats();
$noteSuccess = '';
$noteError   = '';
$referer     = $_GET['from'] ?? 'upcoming';
$shareView   = isset($_GET['share']); // Staff share view (print-friendly)

// Any state-changing POST on this page must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
}

// Handle mark complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'])) {
    updateBookingStatus($bookingId, 'completed');
    header('Location: admin-booking-detail.php?id=' . $bookingId . '&from=' . urlencode($referer));
    exit;
}

// Handle cancel (destructive — managers/admins only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    requireAdminRole(['admin', 'manager']);
    $reason = trim($_POST['cancel_reason'] ?? '');
    cancelBooking($bookingId, $reason);
    header('Location: admin-booking-detail.php?id=' . $bookingId . '&from=' . urlencode($referer));
    exit;
}

// Handle add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note'] ?? '');
    if ($note !== '') {
        if (addBookingNote($bookingId, $note)) {
            $noteSuccess = 'Note added.';
            $notes = getBookingNotes($bookingId);
            $booking = getBookingById($bookingId);
        } else {
            $noteError = 'Failed to add note.';
        }
    }
}

function detailBadge(string $status): string
{
    $map = [
        'paid'      => ['#d1fae5', '#065f46', 'Paid'],
        'pending_cash' => ['#ffedd5', '#9a3412', 'Pending Cash'],
        'completed' => ['#dbeafe', '#1e40af', 'Completed'],
        'cancelled' => ['#fee2e2', '#991b1b', 'Cancelled'],
        'pending'   => ['#fef9c3', '#92400e', 'Pending'],
        'confirmed' => ['#e0e7ff', '#3730a3', 'Confirmed'],
    ];
    $c = $map[$status] ?? ['#f3f4f6', '#374151', ucfirst($status)];
    return '<span style="background:'.$c[0].';color:'.$c[1].';padding:0.3rem 0.9rem;border-radius:99px;font-size:0.85rem;font-weight:700;letter-spacing:0.04em;">'.htmlspecialchars($c[2],ENT_QUOTES,'UTF-8').'</span>';
}

$waNumber = preg_replace('/\D/', '', (string)($booking['phone'] ?? ''));
$isActive = !in_array($booking['status'], ['cancelled', 'completed'], true);
$isPaid   = $booking['status'] === 'paid';

// If share view requested, show print-friendly staff view
if ($shareView) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Staff Brief — Booking #<?php echo $bookingId; ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Montserrat', sans-serif; background: #fff; color: #111; padding: 0; }
            @media print { body { background: #fff; } }
            
            .container { max-width: 900px; margin: 0 auto; }
            .header { background: #111; color: #fff; padding: 2rem; text-align: center; margin-bottom: 2rem; }
            .header h1 { font-size: 1.8rem; margin-bottom: 0.3rem; }
            .header .ref { font-size: 1.5rem; font-weight: 700; color: #c9a961; }
            .header .meta { font-size: 0.85rem; color: #aaa; margin-top: 0.5rem; }
            
            .section { margin-bottom: 2rem; page-break-inside: avoid; }
            .section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #999; margin-bottom: 0.8rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem; }
            
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
            .grid.full { grid-template-columns: 1fr; }
            
            .field { margin-bottom: 1rem; }
            .label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #aaa; margin-bottom: 0.25rem; display: block; }
            .value { font-size: 0.95rem; color: #111; font-weight: 500; word-break: break-word; }
            .value.highlight { font-size: 1.3rem; color: #c9a961; font-weight: 700; }
            .value.accent { color: #065f46; font-weight: 600; }
            
            .badge { display: inline-block; padding: 0.3rem 0.75rem; background: #f3f4f6; color: #111; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
            .badge.paid { background: #d1fae5; color: #065f46; }
            .badge.completed { background: #dbeafe; color: #1e40af; }
            
            .notes-box { background: #f9fafb; border-left: 4px solid #c9a961; padding: 1.25rem; border-radius: 6px; }
            .notes-box .value { white-space: pre-wrap; line-height: 1.6; }
            
            .actions { background: #fef9ec; border-radius: 6px; padding: 1.25rem; margin-bottom: 2rem; }
            .actions p { margin-bottom: 0.75rem; }
            .actions strong { color: #92400e; font-weight: 700; }
            
            .footer { text-align: center; color: #aaa; font-size: 0.8rem; margin-top: 3rem; border-top: 1px solid #f0f0f0; padding-top: 1.5rem; }
            
            .back-btn { display: inline-block; margin-bottom: 1.5rem; color: #c9a961; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
            .back-btn:hover { text-decoration: underline; }
            
            @media print {
                .back-btn { display: none; }
                .actions { background: #fafafa; border: 1px solid #eee; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>&from=<?php echo urlencode($referer); ?>" class="back-btn">← Back to Booking</a>
            
            <div class="header">
                <div class="ref">Booking #<?php echo $bookingId; ?></div>
                <div class="meta"><?php echo date('l, d F Y — g:i A', strtotime((string)$booking['created_at'])); ?></div>
            </div>
            
            <!-- Quick Action Section -->
            <div class="actions">
                <p><strong>Status:</strong> <?php echo detailBadge((string)$booking['status']); ?></p>
                <p><strong>Appointment:</strong> <?php echo date('l, d F Y @ g:i A', strtotime((string)$booking['appointment_date'] . ' ' . $booking['appointment_time'])); ?></p>
                <p><strong>Deposit Paid:</strong> R<?php echo number_format((float)$booking['amount'], 2); ?></p>
            </div>
            
            <!-- Client Information -->
            <div class="section">
                <div class="section-title">👤 Client Information</div>
                <div class="grid">
                    <div class="field">
                        <span class="label">Client Name</span>
                        <span class="value"><?php echo htmlspecialchars((string)$booking['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="field">
                        <span class="label">Phone</span>
                        <span class="value"><a href="tel:<?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?>" style="color:#c9a961;text-decoration:none;font-weight:600;"><?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                    </div>
                    <div class="field">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars((string)$booking['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Service Details (WHAT NEEDS TO BE DONE) -->
            <div class="section">
                <div class="section-title">💇 Service Details — What to Do</div>
                <div class="grid">
                    <div class="field">
                        <span class="label">Service</span>
                        <span class="value highlight"><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', (string)($booking['service'] ?? 'N/A'))), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($booking['sub_type'])): ?>
                    <div class="field">
                        <span class="label">Type / Style</span>
                        <span class="value accent"><?php echo htmlspecialchars(ucfirst((string)$booking['sub_type']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['hair_length'])): ?>
                    <div class="field">
                        <span class="label">Hair Length</span>
                        <span class="value"><?php echo htmlspecialchars(ucfirst((string)$booking['hair_length']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['braid_size'])): ?>
                    <div class="field">
                        <span class="label">Braids Size</span>
                        <span class="value"><?php echo htmlspecialchars(ucfirst((string)$booking['braid_size']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['cornrow_length'])): ?>
                    <div class="field">
                        <span class="label">Cornrow Length</span>
                        <span class="value"><?php echo htmlspecialchars(str_replace('-', ' / ', ucfirst((string)$booking['cornrow_length'])), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['hairpiece_color'])): ?>
                    <div class="field">
                        <span class="label">Hairpiece Colour</span>
                        <span class="value"><?php echo htmlspecialchars(strtoupper((string)$booking['hairpiece_color']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    $additionalServices = [];
                    if (!empty($booking['additional_services'])) {
                        $decodedExtra = json_decode((string)$booking['additional_services'], true);
                        if (is_array($decodedExtra)) { $additionalServices = $decodedExtra; }
                    }
                    if ($additionalServices):
                    ?>
                    <div class="field">
                        <span class="label">Additional Services (same visit, salon-arranged)</span>
                        <span class="value">
                            <?php foreach ($additionalServices as $extra): ?>
                                <?php
                                $extraBits = array_filter([
                                    $extra['subType'] ?? '',
                                    !empty($extra['hairLength']) ? $extra['hairLength'] . ' length' : '',
                                    $extra['braidSize'] ?? '',
                                    $extra['cornrowLength'] ?? '',
                                    !empty($extra['hairpieceColor']) ? 'Colour ' . $extra['hairpieceColor'] : '',
                                    !empty($extra['addons']) ? 'add-ons: ' . implode(', ', (array)$extra['addons']) : '',
                                ]);
                                ?>
                                • <strong><?php echo htmlspecialchars((string)($extra['label'] ?? $extra['service'] ?? 'Service'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php echo $extraBits ? htmlspecialchars(' — ' . str_replace('-', ' ', implode(' · ', $extraBits)), ENT_QUOTES, 'UTF-8') : ''; ?>
                                — R<?php echo number_format((float)($extra['price'] ?? 0), 2); ?><br>
                            <?php endforeach; ?>
                            <em>Extras total: R<?php echo number_format((float)($booking['additional_services_total'] ?? 0), 2); ?></em>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="field">
                        <span class="label">Location</span>
                        <span class="value"><?php echo htmlspecialchars(ucfirst((string)($booking['location'] ?? 'N/A')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($booking['mobile_address'])): ?>
                    <div class="field">
                        <span class="label">Mobile Service Address</span>
                        <span class="value"><?php echo htmlspecialchars((string)$booking['mobile_address'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['travel_surcharge']) && (float)$booking['travel_surcharge'] > 0): ?>
                    <div class="field">
                        <span class="label">Travel Surcharge</span>
                        <span class="value">R<?php echo number_format((float)$booking['travel_surcharge'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stylist Assignment (WHERE & HOW) -->
            <div class="section">
                <div class="section-title">👨‍💼 Stylist Assignment — Where & How</div>
                <div class="grid full">
                    <div class="field">
                        <span class="label">Preferred Stylist</span>
                        <span class="value highlight"><?php echo htmlspecialchars(ucfirst((string)($booking['preferred_stylist'] ?? 'Not specified')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($booking['stylist_name'])): ?>
                    <div class="field">
                        <span class="label">Assigned To (if different)</span>
                        <span class="value accent"><?php echo htmlspecialchars((string)$booking['stylist_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Special Instructions (IMPORTANT) -->
            <?php if (!empty($booking['client_notes'])): ?>
            <div class="section">
                <div class="section-title">⚠️ Special Instructions & Notes</div>
                <div class="notes-box">
                    <span class="label">Client Notes (Allergies, Preferences, Requirements)</span>
                    <span class="value"><?php echo nl2br(htmlspecialchars((string)$booking['client_notes'], ENT_QUOTES, 'UTF-8')); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment & Reference -->
            <div class="section">
                <div class="section-title">💳 Payment & Reference</div>
                <div class="grid full">
                    <div class="field">
                        <span class="label">Deposit Amount</span>
                        <span class="value highlight">R<?php echo number_format((float)$booking['amount'], 2); ?></span>
                    </div>
                    <div class="field">
                        <span class="label">Status</span>
                        <span class="value"><?php echo detailBadge((string)$booking['status']); ?></span>
                    </div>
                    <div class="field">
                        <span class="label">Payment Method</span>
                        <span class="value"><?php echo htmlspecialchars(paymentMethodLabel((string)($booking['payment_method'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($booking['m_payment_id'])): ?>
                    <div class="field">
                        <span class="label">Booking Reference</span>
                        <span class="value" style="font-family:monospace;font-size:0.8rem;"><?php echo htmlspecialchars((string)$booking['m_payment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['pf_payment_id'])): ?>
                    <div class="field">
                        <span class="label">PayFast Transaction ID</span>
                        <span class="value" style="font-family:monospace;font-size:0.8rem;"><?php echo htmlspecialchars((string)$booking['pf_payment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Admin Notes (If any) -->
            <?php if (!empty($booking['admin_notes'])): ?>
            <div class="section">
                <div class="section-title">📋 Admin Notes</div>
                <div class="notes-box">
                    <span class="value"><?php echo nl2br(htmlspecialchars((string)$booking['admin_notes'], ENT_QUOTES, 'UTF-8')); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Printed from Bella CRM — <?php echo date('d F Y, g:i A'); ?></p>
                <p>Keep this brief on hand during the appointment</p>
            </div>
        </div>
        
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 500);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?php echo $bookingId; ?> — Bella CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        :root {
            --gold: #c9a961;
            --sidebar-bg: #181c24;
            --sidebar-txt: #e5e7eb;
            --sidebar-active: #23283a;
            --sidebar-border: #23283a;
            --main-bg: #f4f6fa;
            --card-bg: #ffffff;
            --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
            --muted: #8891a1;
            --text: #1f2937;
        }
        body { font-family: 'Montserrat', sans-serif; background: var(--main-bg); color: var(--text); font-size: 0.9rem; }

        .layout { display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 230px; flex-shrink: 0;
            background: var(--sidebar-bg); color: var(--sidebar-txt);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; height: 100vh;
            overflow-y: auto; z-index: 100;
        }
        .sidebar-logo { padding: 1.4rem 1.2rem 1rem; border-bottom: 1px solid var(--sidebar-border); }
        .sidebar-logo .brand { font-size: 1.8rem; font-weight: 700; color: var(--gold); letter-spacing: 0.05em; }
        .sidebar-logo .sub { font-size: 0.8rem; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; }
        .sidebar-nav { flex: 1; padding: 1rem 0; }
        .nav-section { padding: 0.4rem 1.2rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted); }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.72rem 1.2rem; color: var(--sidebar-txt); text-decoration: none;
            font-size: 0.95rem; font-weight: 600; transition: all 0.15s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a > span:first-child {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: rgba(201,169,97,0.18);
            color: var(--gold);
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar-nav a:hover,
        .sidebar-nav a.active { color: var(--gold); background: var(--sidebar-active); border-left-color: var(--gold); }
        .sidebar-nav a .count {
            margin-left: auto; background: var(--gold); color: #111;
            font-size: 0.7rem; font-weight: 700; border-radius: 99px;
            padding: 0.1rem 0.5rem; min-width: 1.4rem; text-align: center;
        }
        .sidebar-footer { border-top: 1px solid var(--sidebar-border); padding: 1rem 1.2rem; }
        .admin-chip { margin-bottom: 0.8rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--sidebar-border); }
        .admin-chip .name { font-size: 0.9rem; font-weight: 700; color: #f3f4f6; }
        .admin-chip .role { margin-top: 0.1rem; font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .sidebar-footer a { color: var(--muted); text-decoration: none; font-size: 0.95rem; display: block; padding: 0.35rem 0; }
        .sidebar-footer a:hover { color: var(--gold); }

        /* ── Main ── */
        .main { flex: 1; margin-left: 230px; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: #fff; padding: 0.95rem 1.2rem;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50;
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-left a { color: #c9a961; text-decoration: none; font-size: 0.85rem; }
        .topbar-left a:hover { text-decoration: underline; }
        .topbar h1 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #111; }

        .content { padding: 1rem 1.2rem; }

        /* ── Hero bar ── */
        .hero {
            background: var(--card-bg); border-radius: 12px; padding: 1rem 1.2rem;
            box-shadow: var(--card-shadow);
            display: flex; align-items: center; gap: 1.5rem;
            margin-bottom: 1rem; flex-wrap: wrap;
        }
        .hero-ref { font-size: 1.3rem; font-weight: 700; color: #111; }
        .hero-ref span { color: var(--gold); }
        .hero-meta { flex: 1; }
        .hero-meta .created { font-size: 0.78rem; color: #aaa; margin-top: 0.2rem; }
        .hero-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; }

        /* ── Grid ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
        .core-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* ── Cards ── */
        .card { background: var(--card-bg); border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 1rem; overflow: hidden; }
        .card-header { padding: 0.85rem 1rem; border-bottom: 1px solid #edf0f4; display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #111; flex: 1; }
        .card-body { padding: 0.95rem 1rem; }

        /* ── Info rows ── */
        .info-row { display: flex; align-items: flex-start; padding: 0.52rem 0; border-bottom: 1px solid #f2f4f7; }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 115px; flex-shrink: 0; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #8a94a5; padding-top: 0.1rem; }
        .info-value { flex: 1; font-size: 0.88rem; color: #111; word-break: break-word; }
        .info-value a { color: var(--gold); text-decoration: none; }
        .info-value a:hover { text-decoration: underline; }

        /* ── Buttons ── */
        .btn {
            display: inline-block; padding: 0.5rem 1.1rem; border-radius: 6px;
            font-size: 0.82rem; font-weight: 600; text-decoration: none;
            border: none; cursor: pointer; text-align: center; white-space: nowrap;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-gold  { background: var(--gold); color: #fff; }
        .btn-green { background: #d1fae5; color: #065f46; }
        .btn-blue  { background: #dbeafe; color: #1e40af; }
        .btn-wa    { background: #dcfce7; color: #166534; }
        .btn-red   { background: #fee2e2; color: #991b1b; }
        .btn-gray  { background: #f3f4f6; color: #374151; }

        .action-btn {
            display: block; width: 100%; padding: 0.75rem 1rem;
            border-radius: 8px; font-size: 0.88rem; font-weight: 600;
            text-decoration: none; text-align: left; border: none; cursor: pointer;
            margin-bottom: 0.6rem; transition: opacity 0.15s;
        }
        .action-btn:last-child { margin-bottom: 0; }
        .action-btn:hover { opacity: 0.88; }
        .action-btn .icon { margin-right: 0.5rem; }

        /* ── Cancel modal ── */
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; align-items: center; justify-content: center; }
        .modal-bg.open { display: flex; }
        .modal { background: #fff; border-radius: 10px; padding: 1.75rem; width: 100%; max-width: 440px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); }
        .modal h3 { margin: 0 0 1rem 0; font-size: 1rem; }
        .modal textarea { width: 100%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.65rem; font-family: inherit; font-size: 0.85rem; resize: vertical; }
        .modal-actions { display: flex; gap: 0.6rem; margin-top: 1rem; justify-content: flex-end; }

        /* ── Notes ── */
        .note-item { padding: 0.85rem 1rem; background: #f9fafb; border-left: 3px solid var(--gold); border-radius: 6px; margin-bottom: 0.75rem; }
        .note-item:last-child { margin-bottom: 0; }
        .note-meta { display: flex; justify-content: space-between; font-size: 0.75rem; color: #aaa; margin-bottom: 0.35rem; }
        .note-meta .author { font-weight: 600; color: #555; }
        .note-text { font-size: 0.85rem; color: #333; line-height: 1.5; white-space: pre-wrap; }

        .note-form textarea { width: 100%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 0.65rem; font-family: inherit; font-size: 0.85rem; resize: vertical; }
        .note-form textarea:focus { outline: none; border-color: #c9a961; }

        .flash { padding: 0.65rem 1rem; border-radius: 6px; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.75rem; }
        .flash-ok  { background: #d1fae5; color: #065f46; }
        .flash-err { background: #fee2e2; color: #991b1b; }

        .ref-pill { font-size: 0.72rem; background: #f3f4f6; color: #666; padding: 0.25rem 0.6rem; border-radius: 99px; font-family: monospace; word-break: break-all; }

        .bottom-nav {
            display: none;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 60px;
            background: var(--sidebar-bg);
            border-top: 1px solid var(--sidebar-border);
            z-index: 200;
        }
        .bottom-nav a {
            flex: 1;
            color: #c5ccda;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .bottom-nav a.active { color: var(--gold); }

        @media (max-width: 1100px) {
            .sidebar { width: 64px; }
            .sidebar-logo .brand,
            .sidebar-logo .sub,
            .nav-section,
            .sidebar-nav a span:last-child,
            .sidebar-nav a .count,
            .sidebar-footer {
                display: none;
            }
            .sidebar-nav a {
                justify-content: center;
                padding: 0.78rem 0;
                border-left: none;
            }
            .main { margin-left: 64px; }
            .detail-grid { grid-template-columns: 1fr; }
            .core-cards { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 0.55rem 0.4rem 4.4rem; }
            .hero { padding: 0.85rem 0.9rem; }
            .topbar { padding: 0.75rem 0.9rem; }
            .core-cards { grid-template-columns: 1fr; }
            .bottom-nav { display: flex; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- Sidebar (same as dashboard) -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="brand">Bella</div>
            <div class="sub">Hair | Makeup &mdash; CRM</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Views</div>
            <a href="admin-dashboard.php?view=upcoming">
                <span>U</span><span>Upcoming</span>
                <?php if ($stats['upcoming_bookings'] > 0): ?><span class="count"><?php echo $stats['upcoming_bookings']; ?></span><?php endif; ?>
            </a>
            <a href="admin-dashboard.php?view=today">
                <span>T</span><span>Today</span>
                <?php if ($stats['today_bookings'] > 0): ?><span class="count"><?php echo $stats['today_bookings']; ?></span><?php endif; ?>
            </a>
            <a href="admin-dashboard.php?view=completed"><span>C</span><span>Completed</span></a>
            <a href="admin-dashboard.php?view=cancelled"><span>X</span><span>Cancelled</span></a>
            <a href="admin-dashboard.php?view=all"><span>A</span><span>All Bookings</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="admin-chip">
                <div class="name"><?php echo htmlspecialchars(trim((string)(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="role"><?php echo htmlspecialchars(ucfirst((string)($admin['role'] ?? 'admin')), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a href="admin-change-password.php">Change Password</a>
            <a href="admin-logout.php" style="color:#ef4444;">Logout</a>
        </div>
    </aside>

    <nav class="bottom-nav" aria-label="Mobile Navigation">
        <a href="admin-dashboard.php?view=upcoming">Upcoming</a>
        <a href="admin-dashboard.php?view=today">Today</a>
        <a href="admin-dashboard.php?view=completed">Done</a>
        <a href="admin-dashboard.php?view=cancelled">Cancel</a>
        <a href="admin-dashboard.php?view=all" class="active">All</a>
    </nav>

    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <a href="admin-dashboard.php?view=<?php echo urlencode($referer); ?>">← Dashboard</a>
                <h1>Booking #<?php echo $bookingId; ?></h1>
                <?php echo detailBadge((string)$booking['status']); ?>
            </div>
        </div>

        <div class="content">

            <!-- Hero bar -->
            <div class="hero">
                <div>
                    <div class="hero-ref">Booking <span>#<?php echo $bookingId; ?></span></div>
                    <div class="hero-meta">
                        <div class="created">Created <?php echo date('d M Y, g:i A', strtotime((string)$booking['created_at'])); ?></div>
                    </div>
                </div>
                <div style="flex:1;"></div>
                <div class="hero-actions">
                    <a href="?id=<?php echo $bookingId; ?>&share=1" class="btn btn-gold" target="_blank" rel="noopener">Share with Staff</a>
                    <?php if (!empty($waNumber)): ?>
                        <a href="https://wa.me/<?php echo $waNumber; ?>" target="_blank" rel="noopener" class="btn btn-wa">WhatsApp</a>
                    <?php endif; ?>
                    <?php if (!empty($booking['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-blue">Call</a>
                    <?php endif; ?>
                    <?php if ($isPaid): ?>
                        <form method="post" style="margin:0;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="mark_complete" value="1">
                            <button type="submit" class="btn btn-green" onclick="return confirm('Mark booking #<?php echo $bookingId; ?> as completed?')">✓ Mark Complete</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isActive): ?>
                        <button class="btn btn-red" onclick="document.getElementById('cancelModal').classList.add('open')">✕ Cancel</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Two-col grid -->
            <div class="detail-grid">

                <!-- Left: info cards -->
                <div>

                    <div class="core-cards">

                    <!-- Client card -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Client</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Name</span>
                                <span class="info-value" style="font-weight:600;"><?php echo htmlspecialchars((string)$booking['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone</span>
                                <span class="info-value">
                                    <a href="tel:<?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php if (!empty($waNumber)): ?>
                                        &nbsp;<a href="https://wa.me/<?php echo $waNumber; ?>" target="_blank" rel="noopener" style="font-size:0.75rem;color:#166534;background:#dcfce7;padding:0.1rem 0.5rem;border-radius:99px;">WA</a>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><a href="mailto:<?php echo htmlspecialchars((string)$booking['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$booking['email'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                            </div>
                        </div>
                    </div>

                    <!-- Service & Details card -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Service & Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Service</span>
                                <span class="info-value" style="font-weight:700;color:#c9a961;font-size:1rem;"><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', (string)($booking['service'] ?? 'N/A'))), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php if (!empty($booking['sub_type'])): ?>
                            <div class="info-row">
                                <span class="info-label">Type</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst((string)$booking['sub_type']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['hair_length'])): ?>
                            <div class="info-row">
                                <span class="info-label">Hair Length</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst((string)$booking['hair_length']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['braid_size'])): ?>
                            <div class="info-row">
                                <span class="info-label">Braids Size</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst((string)$booking['braid_size']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['cornrow_length'])): ?>
                            <div class="info-row">
                                <span class="info-label">Cornrow Length</span>
                                <span class="info-value"><?php echo htmlspecialchars(str_replace('-', ' / ', ucfirst((string)$booking['cornrow_length'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['hairpiece_color'])): ?>
                            <div class="info-row">
                                <span class="info-label">Hairpiece Colour</span>
                                <span class="info-value"><?php echo htmlspecialchars(strtoupper((string)$booking['hairpiece_color']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Location</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst((string)($booking['location'] ?? 'N/A')), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php if (!empty($booking['mobile_address'])): ?>
                            <div class="info-row">
                                <span class="info-label">Mobile Service Address</span>
                                <span class="info-value"><?php echo htmlspecialchars((string)$booking['mobile_address'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['travel_surcharge']) && (float)$booking['travel_surcharge'] > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Travel Surcharge</span>
                                <span class="info-value">R<?php echo number_format((float)$booking['travel_surcharge'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['preferred_stylist'])): ?>
                            <div class="info-row">
                                <span class="info-label">Preferred Stylist</span>
                                <span class="info-value" style="font-weight:600;"><?php echo htmlspecialchars(ucfirst((string)$booking['preferred_stylist']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($booking['stylist_name'])): ?>
                            <div class="info-row">
                                <span class="info-label">Assigned To</span>
                                <span class="info-value accent"><?php echo htmlspecialchars((string)$booking['stylist_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Appointment card -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Appointment</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Date</span>
                                <span class="info-value" style="font-weight:600;"><?php echo date('l, d F Y', strtotime((string)$booking['appointment_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Time</span>
                                <span class="info-value" style="font-weight:600;color:#c9a961;font-size:1.05rem;"><?php echo date('g:i A', strtotime((string)$booking['appointment_time'])); ?></span>
                            </div>
                            <?php if (!empty($booking['cancellation_reason'])): ?>
                            <div class="info-row">
                                <span class="info-label">Cancel Reason</span>
                                <span class="info-value" style="color:#991b1b;"><?php echo htmlspecialchars((string)$booking['cancellation_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment card -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Payment</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Status</span>
                                <span class="info-value"><?php echo detailBadge((string)$booking['status']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Deposit</span>
                                <span class="info-value" style="font-size:1.15rem;font-weight:700;color:#c9a961;">R<?php echo number_format((float)$booking['amount'], 2); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Method</span>
                                <span class="info-value"><?php echo htmlspecialchars(paymentMethodLabel((string)($booking['payment_method'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php if (!empty($booking['m_payment_id'])): ?>
                            <div class="info-row">
                                <span class="info-label">Ref</span>
                                <span class="info-value"><span class="ref-pill"><?php echo htmlspecialchars((string)$booking['m_payment_id'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    </div>

                    <!-- Client Notes (if present) -->
                    <?php if (!empty($booking['client_notes'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Special Instructions</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-value" style="white-space:pre-wrap;line-height:1.6;background:#fef9ec;padding:1rem;border-radius:6px;border-left:3px solid #92400e;">
                                <?php echo htmlspecialchars((string)$booking['client_notes'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Notes & Activity</h3>
                            <span style="font-size:0.78rem;color:#aaa;"><?php echo count($notes); ?> note<?php echo count($notes) !== 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($noteSuccess): ?><div class="flash flash-ok"><?php echo htmlspecialchars($noteSuccess, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                            <?php if ($noteError): ?><div class="flash flash-err"><?php echo htmlspecialchars($noteError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

                            <form method="post" class="note-form" style="margin-bottom:1.25rem;">
                                <?php echo csrfField(); ?>
                                <textarea name="note" rows="3" placeholder="Add an internal note…" style="margin-bottom:0.6rem;" required></textarea>
                                <button type="submit" name="add_note" class="btn btn-gold">Add Note</button>
                            </form>

                            <?php if (!empty($notes)): ?>
                                <?php foreach ($notes as $n): ?>
                                <div class="note-item">
                                    <div class="note-meta">
                                        <span class="author"><?php echo htmlspecialchars(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo date('d M, g:i A', strtotime((string)$n['created_at'])); ?></span>
                                    </div>
                                    <div class="note-text"><?php echo nl2br(htmlspecialchars((string)$n['note'], ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#aaa;font-size:0.85rem;margin:0;">No notes yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Right: quick actions -->
                <div>
                    <div class="card" style="position:sticky;top:74px;">
                        <div class="card-header"><h3>Quick Actions</h3></div>
                        <div class="card-body">

                            <a href="?id=<?php echo $bookingId; ?>&share=1" class="action-btn" style="background:#c9a961;color:#fff;" target="_blank" rel="noopener">
                                Share with Staff
                            </a>

                            <?php if (!empty($waNumber)): ?>
                                <a href="https://wa.me/<?php echo $waNumber; ?>" target="_blank" rel="noopener" class="action-btn" style="background:#dcfce7;color:#166534;">
                                    WhatsApp
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($booking['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars((string)$booking['phone'], ENT_QUOTES, 'UTF-8'); ?>" class="action-btn" style="background:#dbeafe;color:#1e40af;">
                                    Call
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($booking['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars((string)$booking['email'], ENT_QUOTES, 'UTF-8'); ?>" class="action-btn" style="background:#f3f4f6;color:#374151;">
                                    Email
                                </a>
                            <?php endif; ?>

                            <?php if ($isPaid): ?>
                                <div style="border-top:1px solid #f3f4f6;margin:0.75rem 0;"></div>
                                <form method="post" style="margin:0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="mark_complete" value="1">
                                    <button type="submit" class="action-btn" style="background:#d1fae5;color:#065f46;" onclick="return confirm('Mark this booking as completed?')">
                                        Mark Complete
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($isActive): ?>
                                <a href="admin-booking-reschedule.php?id=<?php echo $bookingId; ?>" class="action-btn" style="background:#fef9ec;color:#92400e;">
                                    Reschedule
                                </a>
                                <div style="border-top:1px solid #f3f4f6;margin:0.75rem 0;"></div>
                                <button onclick="document.getElementById('cancelModal').classList.add('open')" class="action-btn" style="background:#fee2e2;color:#991b1b;">
                                    Cancel Booking
                                </button>
                            <?php endif; ?>

                            <?php if (!$isActive): ?>
                                <div style="padding:0.75rem 0;color:#aaa;font-size:0.82rem;text-align:center;">
                                    Booking is <?php echo htmlspecialchars((string)$booking['status'], ENT_QUOTES, 'UTF-8'); ?>.
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div><!-- /detail-grid -->
        </div><!-- /content -->
    </div><!-- /main -->
</div><!-- /layout -->

<!-- Cancel modal -->
<div id="cancelModal" class="modal-bg">
    <div class="modal">
        <h3>Cancel Booking #<?php echo $bookingId; ?></h3>
        <p style="font-size:0.85rem;color:#666;margin:0 0 1rem 0;">Please provide a reason for cancellation (optional).</p>
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="cancel_booking" value="1">
            <textarea name="cancel_reason" rows="3" placeholder="Reason for cancellation…"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('cancelModal').classList.remove('open')">Dismiss</button>
                <button type="submit" class="btn btn-red" onclick="return confirm('Confirm cancellation?')">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>