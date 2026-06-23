<?php
/**
 * travel-quote.php — live mobile travel-fee quote (owner spec 2026-06-18).
 *
 * GET params:
 *   place_id   (required)  Google Places place_id of the client's chosen address
 *   address    (optional)  the formatted address (used only for the zone fallback)
 *
 * Returns the per-kilometre driving fee from the Midrand studio (round trip by
 * default). This is a CONVENIENCE quote for the booking UI — booking.php always
 * recomputes the authoritative amount server-side before payment. Logic lives in
 * config.php computeMobileTravelFee().
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$placeId = trim((string)($_GET['place_id'] ?? ''));
$address = trim((string)($_GET['address'] ?? ''));

if ($placeId === '' && $address === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'place_id or address is required.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$quote = computeMobileTravelFee($placeId, $address);

echo json_encode([
    'ok'     => true,
    'fee'    => round((float)$quote['fee'], 2),
    'km'     => $quote['km'] !== null ? round((float)$quote['km'], 1) : null,
    'source' => $quote['source'],           // 'gmaps' = per-km driving, 'zone' = fallback tier
    'round_trip' => (bool)TRAVEL_ROUND_TRIP,
    'rate'   => (float)TRAVEL_RATE_PER_KM,
], JSON_UNESCAPED_SLASHES);
