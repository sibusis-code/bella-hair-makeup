<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();

$view = $_GET['view'] ?? 'all';
$search = trim((string)($_GET['q'] ?? ''));

$allowedViews = ['all', 'upcoming', 'today', 'completed', 'cancelled'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'all';
}

switch ($view) {
    case 'today':
        $bookings = getTodaysBookings();
        break;
    case 'upcoming':
        $all = array_merge(getAllBookings('paid'), getAllBookings('pending_cash'));
        $bookings = array_filter($all, static fn(array $b): bool => (string)$b['appointment_date'] >= date('Y-m-d'));
        break;
    case 'completed':
        $bookings = getAllBookings('completed');
        break;
    case 'cancelled':
        $bookings = getAllBookings('cancelled');
        break;
    default:
        $bookings = getAllBookings();
        break;
}

if ($search !== '') {
    $s = strtolower($search);
    $bookings = array_filter($bookings, static function (array $b) use ($s): bool {
        return str_contains(strtolower((string)($b['name'] ?? '')), $s)
            || str_contains(strtolower((string)($b['phone'] ?? '')), $s)
            || str_contains(strtolower((string)($b['email'] ?? '')), $s)
            || str_contains(strtolower((string)($b['m_payment_id'] ?? '')), $s);
    });
}

$filename = 'bella-bookings-' . $view . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    exit;
}

/**
 * Neutralize CSV formula/macro injection. A cell that begins with = + - @ (or a
 * control char) is interpreted as a formula by Excel/LibreOffice/Sheets; a
 * malicious booking name like =HYPERLINK(...) or =cmd|... would then execute on
 * the admin's machine. Prefix any such cell with a single quote.
 */
function csvSafe($value): string
{
    $value = (string)$value;
    if ($value !== '' && preg_match('/^[=\-+@\t\r]/', $value) === 1) {
        return "'" . $value;
    }
    return $value;
}

function fputcsvSafe($handle, array $row): void
{
    fputcsv($handle, array_map('csvSafe', $row));
}

fputcsv($output, [
    'Booking ID',
    'Client Name',
    'Phone',
    'Email',
    'Service',
    'Location',
    'Mobile Address',
    'Preferred Stylist',
    'Payment Method',
    'Assigned Stylist',
    'Braid Size',
    'Cornrow Length',
    'Hairpiece Colour',
    'Date',
    'Time',
    'Deposit Amount',
    'Status',
    'Reference',
    'Created At'
]);

foreach ($bookings as $b) {
    fputcsvSafe($output, [
        (string)($b['id'] ?? ''),
        (string)($b['name'] ?? ''),
        (string)($b['phone'] ?? ''),
        (string)($b['email'] ?? ''),
        (string)($b['service'] ?? ''),
        (string)($b['location'] ?? ''),
        (string)($b['mobile_address'] ?? ''),
        (string)($b['preferred_stylist'] ?? ''),
        (string)($b['payment_method'] ?? ''),
        (string)($b['stylist_name'] ?? ''),
        (string)($b['braid_size'] ?? ''),
        (string)($b['cornrow_length'] ?? ''),
        (string)($b['hairpiece_color'] ?? ''),
        (string)($b['appointment_date'] ?? ''),
        (string)($b['appointment_time'] ?? ''),
        (string)($b['amount'] ?? ''),
        (string)($b['status'] ?? ''),
        (string)($b['m_payment_id'] ?? ''),
        (string)($b['created_at'] ?? '')
    ]);
}

fclose($output);
exit;
