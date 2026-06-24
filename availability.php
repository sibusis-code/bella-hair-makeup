<?php
/**
 * availability.php — read-only JSON availability endpoint (Phase 1).
 *
 * GET params:
 *   service     (required)  service_key, e.g. "braids"
 *   location    (required)  location_key, e.g. "midrand"
 *   date_from   (required)  YYYY-MM-DD
 *   date_to     (required)  YYYY-MM-DD  (range capped to 62 days)
 *
 * Returns open days, open slots, and per-stylist free/busy. The calendar UI
 * (Phase 2) consumes this. Logic lives in config.php computeAvailability().
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function availabilityFail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    availabilityFail(405, 'Method not allowed.');
}

$service = trim((string)($_GET['service'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($service === '' || $location === '') {
    availabilityFail(400, 'service and location are required.');
}

// Validate date format strictly (YYYY-MM-DD).
$tz = new DateTimeZone(APP_TIMEZONE);
$from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom, $tz);
$to = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo, $tz);
if ($from === false || $to === false
    || $from->format('Y-m-d') !== $dateFrom
    || $to->format('Y-m-d') !== $dateTo) {
    availabilityFail(400, 'date_from and date_to must be valid YYYY-MM-DD dates.');
}
if ($to < $from) {
    availabilityFail(400, 'date_to must not be before date_from.');
}
// Cap range to 62 days to limit load.
if ($from->diff($to)->days > 62) {
    $to = $from->modify('+62 days');
    $dateTo = $to->format('Y-m-d');
}

$mysqli = tryGetDbConnection();
if ($mysqli instanceof mysqli) {
    $result = computeAvailability($mysqli, $service, $location, $dateFrom, $dateTo);
    $mysqli->close();
} else {
    // DEMO MODE: no database (e.g. Vercel serverless). Serve availability from the
    // in-memory default catalog so the calendar still works with dummy data.
    $result = computeAvailabilityDemo($service, $location, $dateFrom, $dateTo);
}

if (isset($result['error'])) {
    $map = [
        'unknown_service' => 'Unknown service.',
        'unknown_location' => 'Unknown location.',
        'bad_date_range' => 'Invalid date range.',
    ];
    availabilityFail(400, $map[$result['error']] ?? 'Unable to compute availability.');
}

$result['ok'] = true;
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
