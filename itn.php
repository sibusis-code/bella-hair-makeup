<?php
require_once __DIR__ . '/config.php';

function itnLog(string $message): void
{
    error_log('[ITN] ' . $message);
}

function itnOk(): void
{
    http_response_code(200);
    echo 'OK';
    exit;
}

function updateLegacyPendingBooking(mysqli $mysqli, string $mPaymentId, float $grossAmount): void
{
    $bookingStmt = $mysqli->prepare('SELECT id, amount, status FROM salon_bookings WHERE m_payment_id = ? LIMIT 1');
    if (!$bookingStmt) {
        itnLog('Legacy booking lookup prepare failed for ' . $mPaymentId);
        return;
    }

    $bookingStmt->bind_param('s', $mPaymentId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        itnLog('Legacy booking not found for ' . $mPaymentId);
        return;
    }

    $dbAmount = (float)$booking['amount'];
    if ($grossAmount > 0 && abs($dbAmount - $grossAmount) > 0.01) {
        itnLog('Legacy amount mismatch for ' . $mPaymentId);
        return;
    }

    if ($booking['status'] !== 'paid') {
        $paidStatus = 'paid';
        $pendingStatus = 'pending';
        $updateStmt = $mysqli->prepare('UPDATE salon_bookings SET status = ? WHERE m_payment_id = ? AND status = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('sss', $paidStatus, $mPaymentId, $pendingStatus);
            $updateStmt->execute();
            $updateStmt->close();
            itnLog('Legacy pending booking marked paid for ' . $mPaymentId);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    itnLog('Non-POST request method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    itnOk();
}

/**
 * Source-IP allow-list. PayFast only ever delivers ITNs from its own hosts.
 * We resolve those hostnames to IPs and reject anything else. This is fail-OPEN
 * only when DNS resolution itself yields nothing (so a resolver outage cannot
 * block real payments) — the enforced signature + server-side validate below
 * remain as the authoritative gates in that rare case.
 */
function itnRequestFromPayFast(): bool
{
    $remoteIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remoteIp === '') {
        return false;
    }

    $hosts = [
        'www.payfast.co.za',
        'sandbox.payfast.co.za',
        'w1w.payfast.co.za',
        'w2w.payfast.co.za',
    ];

    $allowed = [];
    foreach ($hosts as $host) {
        $ips = gethostbynamel($host);
        if (is_array($ips)) {
            $allowed = array_merge($allowed, $ips);
        }
    }

    if (empty($allowed)) {
        itnLog('IP allow-list: DNS resolution unavailable, deferring to signature + validate.');
        return true; // fail-open ONLY when we could not resolve any PayFast IP
    }

    return in_array($remoteIp, $allowed, true);
}

if (!itnRequestFromPayFast()) {
    itnLog('Rejected ITN from non-PayFast source IP=' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    itnOk();
}

$rawPost = file_get_contents('php://input');
$pfData = [];
if ($rawPost !== false && $rawPost !== '') {
    parse_str($rawPost, $pfData);
}

if (empty($pfData) && !empty($_POST)) {
    $pfData = $_POST;
    $rawPost = http_build_query($_POST);
}

itnLog('Received payload length=' . strlen((string)$rawPost));

if (empty($pfData) || empty($pfData['m_payment_id']) || empty($pfData['signature'])) {
    itnLog('Missing required fields. has_data=' . (!empty($pfData) ? '1' : '0'));
    itnOk();
}

$postedSignature = $pfData['signature'];
unset($pfData['signature']);

/**
 * ITN signature per PayFast's official spec: hash EVERY posted field (except
 * `signature`) in the exact order received — including empty ones — then the
 * passphrase. This differs from buildPayFastSignature() (used for the OUTBOUND
 * redirect), which skips empties; using the wrong one here would reject valid
 * ITNs that contain a blank field. See PayFast ITN integration guide.
 */
function buildPayFastItnSignature(array $pfData, string $passphrase): string
{
    $pairs = [];
    foreach ($pfData as $key => $value) {
        if ($key === 'signature') {
            continue;
        }
        $pairs[] = $key . '=' . urlencode(trim((string)$value));
    }
    $getString = implode('&', $pairs);
    if (trim($passphrase) !== '') {
        $getString .= '&passphrase=' . urlencode(trim($passphrase));
    }
    return md5($getString);
}

$generatedSignature = buildPayFastItnSignature($pfData, PAYFAST_PASSPHRASE);
if (!hash_equals($generatedSignature, $postedSignature)) {
    // HARD FAIL: a valid ITN is always signed with our passphrase. A mismatch
    // means a forged or tampered payload — reject before any DB work.
    itnLog('Signature mismatch for m_payment_id=' . (string)($pfData['m_payment_id'] ?? 'unknown') . '. Rejecting.');
    itnOk();
}

// Validate ITN payload with PayFast.
$validateUrl = getPayFastValidateUrl();
$payload = (string)$rawPost;
if ($payload === '') {
    $payload = http_build_query($pfData);
}
if (PAYFAST_PASSPHRASE !== '' && strpos((string)$payload, 'passphrase=') === false) {
    $payload .= ($payload === '' ? '' : '&') . 'passphrase=' . urlencode(PAYFAST_PASSPHRASE);
}

$ch = curl_init($validateUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);

$pfResponse = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($pfResponse === false || stripos((string)$pfResponse, 'VALID') === false) {
    itnLog('PayFast validation failed for ' . (string)($pfData['m_payment_id'] ?? 'unknown') . '. curl=' . $curlError . ' response=' . substr((string)$pfResponse, 0, 120));
    itnOk();
}

$paymentStatus = $pfData['payment_status'] ?? '';
$mPaymentId = $pfData['m_payment_id'];
$grossAmount = isset($pfData['amount_gross']) ? (float)$pfData['amount_gross'] : 0.00;

// Avoid logging monetary amounts to plain logs; m_payment_id is enough to trace.
itnLog('Validated: m_payment_id=' . $mPaymentId . ', payment_status=' . $paymentStatus);

if ($paymentStatus !== 'COMPLETE') {
    itnLog('Ignoring non-COMPLETE status for ' . $mPaymentId . ': ' . $paymentStatus);
    itnOk();
}

try {
    $mysqli = getDbConnection();
} catch (Throwable $e) {
    itnLog('DB connection failed for ' . $mPaymentId . ': ' . $e->getMessage());
    itnOk();
}

if (!ensurePaymentAttemptsTable($mysqli)) {
    itnLog('Could not ensure booking_payment_attempts table for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

if (!ensureSalonBookingsSchema($mysqli)) {
    itnLog('Could not ensure salon_bookings schema for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$attemptStmt = $mysqli->prepare('SELECT * FROM booking_payment_attempts WHERE m_payment_id = ? ORDER BY id ASC');
if (!$attemptStmt) {
    itnLog('Attempt lookup prepare failed for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$attemptStmt->bind_param('s', $mPaymentId);
$attemptStmt->execute();
$attemptResult = $attemptStmt->get_result();
$attempts = [];
while ($attemptRow = $attemptResult->fetch_assoc()) {
    $attempts[] = $attemptRow;
}
$attemptStmt->close();

if (empty($attempts)) {
    // Backward compatibility for historical pending bookings.
    updateLegacyPendingBooking($mysqli, $mPaymentId, $grossAmount);
    $mysqli->close();
    itnOk();
}

$totalAttemptAmount = 0.0;
foreach ($attempts as $attempt) {
    $totalAttemptAmount += (float)$attempt['amount'];
}

if ($grossAmount > 0 && abs($totalAttemptAmount - $grossAmount) > 0.01) {
    itnLog('Amount mismatch in booking_payment_attempts for m_payment_id ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

// If all attempts are already marked paid with booking IDs, treat as idempotent callback.
$allAttemptsSettled = true;
foreach ($attempts as $attempt) {
    if (($attempt['status'] ?? '') !== 'paid' || empty($attempt['booking_id'])) {
        $allAttemptsSettled = false;
        break;
    }
}

if ($allAttemptsSettled) {
    itnLog('Idempotent hit: already paid for m_payment_id=' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$existingPaidStmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM salon_bookings WHERE m_payment_id = ? AND status = ?');
$paidStatus = 'paid';
if (!$existingPaidStmt) {
    itnLog('Existing booking lookup prepare failed for ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

$existingPaidStmt->bind_param('ss', $mPaymentId, $paidStatus);
$existingPaidStmt->execute();
$existingPaidResult = $existingPaidStmt->get_result();
$existingPaidBooking = $existingPaidResult->fetch_assoc() ?: ['total' => 0];
$existingPaidStmt->close();

if ((int)$existingPaidBooking['total'] >= count($attempts)) {
    $paidAttemptStatus = 'paid';
    $syncAttemptStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ? WHERE m_payment_id = ?');
    if ($syncAttemptStmt) {
        $syncAttemptStmt->bind_param('ss', $paidAttemptStatus, $mPaymentId);
        $syncAttemptStmt->execute();
        $syncAttemptStmt->close();
    }

    $mysqli->close();
    itnOk();
}

// Validate capacity + stylist availability per slot using the shared availability
// engine (per-service capacity, Braids two-on-one, admin blocks). Holds are ignored
// here because this payer is finalizing their own booking. Resolved lead/helper
// stylists are captured for the insert step below.
// Serialize the recheck->insert critical section across concurrent ITNs so two
// payments competing for the last seat in a slot cannot both pass the capacity
// re-check (TOCTOU). A named advisory lock is used instead of a schema change so
// this is safe to deploy on the live DB. The lock is session-scoped and is
// released explicitly after commit (and implicitly on connection close on any
// early-exit path).
$itnLockName = 'bella_itn_finalize';
$lockRes = $mysqli->query("SELECT GET_LOCK('" . $mysqli->real_escape_string($itnLockName) . "', 10) AS got");
$lockRow = ($lockRes instanceof mysqli_result) ? $lockRes->fetch_assoc() : null;
if (!$lockRow || (int)($lockRow['got'] ?? 0) !== 1) {
    itnLog('Could not acquire finalize lock for ' . $mPaymentId . '; deferring (PayFast will retry).');
    $mysqli->close();
    itnOk();
}

$itnCatalog = getBookingCatalog($mysqli);
$pendingSlotCount = [];   // [service|date|time] => clients counted this payment
$pendingBusyStylist = []; // [date|time] => [stylistKeyLower => true]
$resolvedByAttemptId = []; // attempt id => ['lead'=>?, 'helper'=>?]

function itnMarkFailedAndExit(mysqli $mysqli, string $mPaymentId, string $reason): void
{
    $failedStatus = 'failed';
    $markStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ? WHERE m_payment_id = ?');
    if ($markStmt) {
        $markStmt->bind_param('ss', $failedStatus, $mPaymentId);
        $markStmt->execute();
        $markStmt->close();
    }
    itnLog('Paid ITN re-check failed (' . $reason . ') for m_payment_id: ' . $mPaymentId);
    $mysqli->close();
    itnOk();
}

foreach ($attempts as $attempt) {
    $svc = (string)$attempt['service'];
    $loc = (string)$attempt['location'];
    $date = (string)$attempt['preferred_date'];
    $time = (string)$attempt['preferred_time'];
    $lead = trim((string)($attempt['stylist'] ?? ''));

    $slotKey = $svc . '|' . $date . '|' . $time;
    $busyKey = $date . '|' . $time;

    $recheck = availabilityRecheckSlot($mysqli, $svc, $loc, $date, $time, $lead, [
        'catalog' => $itnCatalog,
        'ignore_holds' => true,
        'exclude_payment_id' => $mPaymentId,
        'extra_slot_count' => (int)($pendingSlotCount[$slotKey] ?? 0),
        'extra_busy' => array_keys($pendingBusyStylist[$busyKey] ?? []),
    ]);

    if (empty($recheck['ok'])) {
        itnMarkFailedAndExit($mysqli, $mPaymentId, (string)($recheck['reason'] ?? 'unavailable'));
    }

    // Resolve concrete lead/helper (helper auto-assigned for Braids).
    $resolvedLead = $recheck['lead'] ?? (($lead !== '' && strtolower($lead) !== 'no-preference') ? strtolower($lead) : '');
    $resolvedHelper = $recheck['helper'] ?? null;
    $resolvedByAttemptId[(int)$attempt['id']] = ['lead' => $resolvedLead, 'helper' => $resolvedHelper];

    // Track this payment's own usage so multi-slot payments don't self-collide.
    $pendingSlotCount[$slotKey] = (int)($pendingSlotCount[$slotKey] ?? 0) + 1;
    if ($resolvedLead !== '') {
        $pendingBusyStylist[$busyKey][$resolvedLead] = true;
    }
    if ($resolvedHelper !== null && $resolvedHelper !== '') {
        $pendingBusyStylist[$busyKey][$resolvedHelper] = true;
    }
}

$mysqli->begin_transaction();

try {
    $newBookingIds = [];
    foreach ($attempts as $attempt) {
        $attemptAmount = (float)$attempt['amount'];
        $fullName = trim((string)$attempt['first_name'] . ' ' . (string)$attempt['last_name']);

        $insertBookingStmt = $mysqli->prepare(
            'INSERT INTO salon_bookings (name, email, phone, appointment_date, appointment_time, service, location, preferred_stylist, helper_stylist, sub_type, hair_length, braid_size, cornrow_length, hairpiece_color, mobile_actual_service, mobile_person_count, mobile_address, travel_surcharge, addons, addons_total, additional_services, additional_services_total, client_notes, amount, payment_method, status, m_payment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$insertBookingStmt) {
            throw new RuntimeException('Insert booking prepare failed');
        }

        $attemptTravelSurcharge = (float)($attempt['travel_surcharge'] ?? 0.00);
        $resolved = $resolvedByAttemptId[(int)$attempt['id']] ?? ['lead' => '', 'helper' => null];
        $insertStylist = ($resolved['lead'] ?? '') !== '' ? $resolved['lead'] : (string)$attempt['stylist'];
        $insertHelper = $resolved['helper'] ?? null;
        $attemptAddons = $attempt['addons'] ?? null;
        $attemptAddonsTotal = (float)($attempt['addons_total'] ?? 0.00);
        $attemptAdditional = $attempt['additional_services'] ?? null;
        $attemptAdditionalTotal = (float)($attempt['additional_services_total'] ?? 0.00);
        $insertBookingStmt->bind_param(
            'sssssssssssssssssdsdsdsdsss',
            $fullName,
            $attempt['email'],
            $attempt['phone'],
            $attempt['preferred_date'],
            $attempt['preferred_time'],
            $attempt['service'],
            $attempt['location'],
            $insertStylist,
            $insertHelper,
            $attempt['sub_type'],
            $attempt['hair_length'],
            $attempt['braid_size'],
            $attempt['cornrow_length'],
            $attempt['hairpiece_color'],
            $attempt['mobile_actual_service'],
            $attempt['mobile_person_count'],
            $attempt['mobile_address'],
            $attemptTravelSurcharge,
            $attemptAddons,
            $attemptAddonsTotal,
            $attemptAdditional,
            $attemptAdditionalTotal,
            $attempt['notes'],
            $attemptAmount,
            $attempt['payment_method'],
            $paidStatus,
            $mPaymentId
        );
        $insertBookingStmt->execute();
        $newBookingId = (int)$mysqli->insert_id;
        $insertBookingStmt->close();
        $newBookingIds[] = $newBookingId;

        $paidAttemptStatus = 'paid';
        $updateAttemptStmt = $mysqli->prepare('UPDATE booking_payment_attempts SET status = ?, booking_id = ? WHERE id = ?');
        if (!$updateAttemptStmt) {
            throw new RuntimeException('Update attempt prepare failed');
        }

        $attemptId = (int)$attempt['id'];
        $updateAttemptStmt->bind_param('sii', $paidAttemptStatus, $newBookingId, $attemptId);
        $updateAttemptStmt->execute();
        $updateAttemptStmt->close();

        $pfPaymentId = $pfData['pf_payment_id'] ?? null;
        if ($pfPaymentId) {
            $updatePfIdStmt = $mysqli->prepare('UPDATE salon_bookings SET pf_payment_id = ? WHERE id = ?');
            if ($updatePfIdStmt) {
                $updatePfIdStmt->bind_param('si', $pfPaymentId, $newBookingId);
                $updatePfIdStmt->execute();
                $updatePfIdStmt->close();
            }
        }
    }

    $mysqli->commit();
    @$mysqli->query("SELECT RELEASE_LOCK('" . $mysqli->real_escape_string($itnLockName) . "')");
    itnLog('Finalized booking: m_payment_id=' . $mPaymentId . ', booking_count=' . (string)count($newBookingIds));
} catch (Throwable $e) {
    $mysqli->rollback();
    itnLog('Booking insert failed for ' . $mPaymentId . ': ' . $e->getMessage());
    $mysqli->close();
    itnOk();
}

if (SEND_CLIENT_EMAILS || SEND_ADMIN_EMAILS) {
    require_once __DIR__ . '/mail-functions.php';

    foreach ($attempts as $idx => $attempt) {
        $bookingData = [
            'id' => (int)($newBookingIds[$idx] ?? 0),
            'name' => trim((string)$attempt['first_name'] . ' ' . (string)$attempt['last_name']),
            'email' => (string)$attempt['email'],
            'phone' => (string)$attempt['phone'],
            'appointment_date' => (string)$attempt['preferred_date'],
            'appointment_time' => (string)$attempt['preferred_time'],
            'service' => (string)$attempt['service'],
            'amount' => (float)$attempt['amount'],
            'payment_method' => (string)($attempt['payment_method'] ?? 'online_deposit'),
            'additional_services' => (string)($attempt['additional_services'] ?? ''),
            'm_payment_id' => $mPaymentId,
            'status' => 'paid'
        ];

        if (SEND_CLIENT_EMAILS && $bookingData['email'] !== '') {
            sendBookingConfirmation($bookingData);
        }

        if (SEND_ADMIN_EMAILS) {
            sendAdminNewBookingNotification($bookingData);
        }
    }
}

$mysqli->close();
itnOk();
