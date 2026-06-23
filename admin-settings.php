<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = requireAdminRole(['admin', 'manager']);

$notice = '';
$noticeType = 'success';

$businessKeys = [
    'brand_name',
    'phone_whatsapp',
    'phone_call',
    'phone_landline',
    'whatsapp_url',
    'hours_midrand',
    'hours_copperleaf',
    'address_midrand',
    'address_copperleaf',
];

function parseCsvKeys(string $value): array
{
    $parts = preg_split('/\s*,\s*/', trim($value));
    if (!is_array($parts)) {
        return [];
    }

    $clean = [];
    foreach ($parts as $part) {
        $part = trim(strtolower($part));
        if ($part === '') {
            continue;
        }
        $clean[$part] = true;
    }

    return array_keys($clean);
}

$mysqli = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = trim((string)($_POST['action'] ?? ''));
    $transactionStarted = false;

    try {
        if ($action === 'save_business') {
            $stmt = $mysqli->prepare('INSERT INTO business_settings (setting_key, setting_value, is_active, updated_by) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_active = 1, updated_by = VALUES(updated_by)');
            if (!$stmt) {
                throw new RuntimeException('Could not prepare business settings statement.');
            }

            $updatedBy = (string)($admin['username'] ?? 'admin');
            foreach ($businessKeys as $key) {
                $value = trim((string)($_POST['business'][$key] ?? ''));
                $stmt->bind_param('sss', $key, $value, $updatedBy);
                $stmt->execute();
            }
            $stmt->close();
            $notice = 'Business settings saved.';
        } elseif ($action === 'save_services') {
            $services = $_POST['services'] ?? [];
            if (!is_array($services)) {
                throw new RuntimeException('Invalid services payload.');
            }

            $stmt = $mysqli->prepare('UPDATE booking_services SET service_name = ?, category_label = ?, base_price = ?, requires_sub_type = ?, requires_hair_length = ?, sub_type_label = ?, info_text = ?, sort_order = ?, is_active = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException('Could not prepare service update statement.');
            }

            foreach ($services as $serviceId => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int)$serviceId;
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string)($row['service_name'] ?? ''));
                $category = trim((string)($row['category_label'] ?? ''));
                $basePrice = (float)($row['base_price'] ?? 0);
                $requiresSub = !empty($row['requires_sub_type']) ? 1 : 0;
                $requiresLength = !empty($row['requires_hair_length']) ? 1 : 0;
                $subTypeLabel = trim((string)($row['sub_type_label'] ?? ''));
                $infoText = trim((string)($row['info_text'] ?? ''));
                $sortOrder = max(0, (int)($row['sort_order'] ?? 100));
                $isActive = !empty($row['is_active']) ? 1 : 0;

                if ($name === '' || $category === '' || $basePrice < 0) {
                    continue;
                }

                $stmt->bind_param('ssdiissiii', $name, $category, $basePrice, $requiresSub, $requiresLength, $subTypeLabel, $infoText, $sortOrder, $isActive, $id);
                $stmt->execute();
            }
            $stmt->close();
            $notice = 'Services updated.';
        } elseif ($action === 'save_slots') {
            $slots = $_POST['slots'] ?? [];
            if (!is_array($slots)) {
                throw new RuntimeException('Invalid slots payload.');
            }

            $stmt = $mysqli->prepare('UPDATE booking_time_slots SET slot_label = ?, db_time = ?, sort_order = ?, is_active = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException('Could not prepare slot update statement.');
            }

            foreach ($slots as $slotId => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int)$slotId;
                if ($id <= 0) {
                    continue;
                }

                $label = trim((string)($row['slot_label'] ?? ''));
                $dbTime = trim((string)($row['db_time'] ?? ''));
                $sortOrder = max(0, (int)($row['sort_order'] ?? 100));
                $isActive = !empty($row['is_active']) ? 1 : 0;

                if ($label === '' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $dbTime)) {
                    continue;
                }

                $stmt->bind_param('ssiii', $label, $dbTime, $sortOrder, $isActive, $id);
                $stmt->execute();
            }
            $stmt->close();
            $notice = 'Time slots updated.';
        } elseif ($action === 'save_stylists') {
            $stylists = $_POST['stylists'] ?? [];
            if (!is_array($stylists)) {
                throw new RuntimeException('Invalid stylists payload.');
            }

            $stmt = $mysqli->prepare('UPDATE booking_stylists SET stylist_name = ?, sort_order = ?, is_active = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException('Could not prepare stylist update statement.');
            }

            foreach ($stylists as $stylistId => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int)$stylistId;
                if ($id <= 0) {
                    continue;
                }

                $name = trim((string)($row['stylist_name'] ?? ''));
                $sortOrder = max(0, (int)($row['sort_order'] ?? 100));
                $isActive = !empty($row['is_active']) ? 1 : 0;

                if ($name === '') {
                    continue;
                }

                $stmt->bind_param('siii', $name, $sortOrder, $isActive, $id);
                $stmt->execute();
            }
            $stmt->close();
            $notice = 'Stylists updated.';
        } elseif ($action === 'add_stylist') {
            // Add a new staff member. The stylist_key is auto-generated (slug) and
            // made unique; assign them to services afterwards in the Mappings card.
            $name = trim((string)($_POST['new_stylist_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Staff name is required.');
            }

            $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            if ($base === '') {
                $base = 'stylist';
            }

            $checkStmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM booking_stylists WHERE stylist_key = ?');
            if (!$checkStmt) {
                throw new RuntimeException('Could not prepare staff lookup.');
            }
            $key = $base;
            $suffix = 1;
            while (true) {
                $checkStmt->bind_param('s', $key);
                $checkStmt->execute();
                $exists = (int)$checkStmt->get_result()->fetch_assoc()['c'] > 0;
                if (!$exists) {
                    break;
                }
                $suffix++;
                $key = $base . '-' . $suffix;
            }
            $checkStmt->close();

            $maxSort = (int)($mysqli->query('SELECT COALESCE(MAX(sort_order),0)+10 AS s FROM booking_stylists')->fetch_assoc()['s']);
            $insStmt = $mysqli->prepare('INSERT INTO booking_stylists (stylist_key, stylist_name, sort_order, is_active) VALUES (?, ?, ?, 1)');
            if (!$insStmt) {
                throw new RuntimeException('Could not prepare staff insert.');
            }
            $insStmt->bind_param('ssi', $key, $name, $maxSort);
            $insStmt->execute();
            $insStmt->close();
            $notice = 'Staff member “' . $name . '” added (key: ' . $key . '). Now assign them to services in the Mappings card below.';
        } elseif ($action === 'delete_stylist') {
            // Permanently remove a staff member — only if no bookings reference them.
            // If they have history, deactivate via the Stylists card instead (preserves records).
            $stylistId = (int)($_POST['stylist_id'] ?? 0);
            if ($stylistId <= 0) {
                throw new RuntimeException('Invalid staff member.');
            }

            $keyStmt = $mysqli->prepare('SELECT stylist_key, stylist_name FROM booking_stylists WHERE id = ?');
            $keyStmt->bind_param('i', $stylistId);
            $keyStmt->execute();
            $stylistRow = $keyStmt->get_result()->fetch_assoc();
            $keyStmt->close();
            if (!$stylistRow) {
                throw new RuntimeException('Staff member not found.');
            }
            $stylistKey = (string)$stylistRow['stylist_key'];

            $refStmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM salon_bookings WHERE preferred_stylist = ? OR helper_stylist = ?');
            $refStmt->bind_param('ss', $stylistKey, $stylistKey);
            $refStmt->execute();
            $refCount = (int)$refStmt->get_result()->fetch_assoc()['c'];
            $refStmt->close();

            if ($refCount > 0) {
                throw new RuntimeException('“' . $stylistRow['stylist_name'] . '” has ' . $refCount . ' booking(s) on record and cannot be deleted. Untick “Active” in the Stylists card to retire them while keeping history.');
            }

            $mysqli->begin_transaction();
            $transactionStarted = true;
            $delMap = $mysqli->prepare('DELETE FROM booking_service_stylists WHERE stylist_id = ?');
            $delMap->bind_param('i', $stylistId);
            $delMap->execute();
            $delMap->close();
            $delStylist = $mysqli->prepare('DELETE FROM booking_stylists WHERE id = ?');
            $delStylist->bind_param('i', $stylistId);
            $delStylist->execute();
            $delStylist->close();
            $mysqli->commit();
            $transactionStarted = false;
            $notice = 'Staff member “' . $stylistRow['stylist_name'] . '” removed.';
        } elseif ($action === 'save_mappings') {
            $map = $_POST['service_location_stylists'] ?? [];
            $serviceSlots = $_POST['service_slots'] ?? [];
            if (!is_array($map) || !is_array($serviceSlots)) {
                throw new RuntimeException('Invalid mapping payload.');
            }

            $servicesResult = $mysqli->query('SELECT id FROM booking_services');
            $serviceIds = [];
            while ($row = $servicesResult->fetch_assoc()) {
                $serviceIds[] = (int)$row['id'];
            }

            $locationsResult = $mysqli->query('SELECT id, location_key FROM booking_locations');
            $locationIdByKey = [];
            while ($row = $locationsResult->fetch_assoc()) {
                $locationIdByKey[(string)$row['location_key']] = (int)$row['id'];
            }

            $stylistsResult = $mysqli->query('SELECT id, stylist_key FROM booking_stylists');
            $stylistIdByKey = [];
            while ($row = $stylistsResult->fetch_assoc()) {
                $stylistIdByKey[(string)$row['stylist_key']] = (int)$row['id'];
            }

            $slotsResult = $mysqli->query('SELECT id, slot_key FROM booking_time_slots');
            $slotIdByKey = [];
            while ($row = $slotsResult->fetch_assoc()) {
                $slotIdByKey[(string)$row['slot_key']] = (int)$row['id'];
            }

            $mysqli->begin_transaction();
            $transactionStarted = true;

            $deleteMapStmt = $mysqli->prepare('DELETE FROM booking_service_stylists WHERE service_id = ? AND location_id = ?');
            $insertMapStmt = $mysqli->prepare('INSERT INTO booking_service_stylists (service_id, stylist_id, location_id, is_active) VALUES (?, ?, ?, 1)');

            if (!$deleteMapStmt || !$insertMapStmt) {
                throw new RuntimeException('Could not prepare mapping statements.');
            }

            foreach ($map as $serviceIdRaw => $locationRows) {
                $serviceId = (int)$serviceIdRaw;
                if ($serviceId <= 0 || !in_array($serviceId, $serviceIds, true) || !is_array($locationRows)) {
                    continue;
                }

                foreach ($locationRows as $locationKey => $csvStylists) {
                    $locationKey = trim((string)$locationKey);
                    if (!isset($locationIdByKey[$locationKey])) {
                        continue;
                    }
                    $locationId = $locationIdByKey[$locationKey];

                    $deleteMapStmt->bind_param('ii', $serviceId, $locationId);
                    $deleteMapStmt->execute();

                    $stylistKeys = parseCsvKeys((string)$csvStylists);
                    foreach ($stylistKeys as $stylistKey) {
                        if (!isset($stylistIdByKey[$stylistKey])) {
                            continue;
                        }
                        $stylistId = $stylistIdByKey[$stylistKey];
                        $insertMapStmt->bind_param('iii', $serviceId, $stylistId, $locationId);
                        $insertMapStmt->execute();
                    }
                }
            }

            $deleteMapStmt->close();
            $insertMapStmt->close();

            $deleteSlotsStmt = $mysqli->prepare('DELETE FROM booking_service_slots WHERE service_id = ?');
            $insertSlotsStmt = $mysqli->prepare('INSERT INTO booking_service_slots (service_id, slot_id, is_active) VALUES (?, ?, 1)');

            if (!$deleteSlotsStmt || !$insertSlotsStmt) {
                throw new RuntimeException('Could not prepare service-slot statements.');
            }

            foreach ($serviceSlots as $serviceIdRaw => $csvSlots) {
                $serviceId = (int)$serviceIdRaw;
                if ($serviceId <= 0 || !in_array($serviceId, $serviceIds, true)) {
                    continue;
                }

                $deleteSlotsStmt->bind_param('i', $serviceId);
                $deleteSlotsStmt->execute();

                $slotKeys = parseCsvKeys((string)$csvSlots);
                foreach ($slotKeys as $slotKey) {
                    if (!isset($slotIdByKey[$slotKey])) {
                        continue;
                    }
                    $slotId = $slotIdByKey[$slotKey];
                    $insertSlotsStmt->bind_param('ii', $serviceId, $slotId);
                    $insertSlotsStmt->execute();
                }
            }

            $deleteSlotsStmt->close();
            $insertSlotsStmt->close();

            $mysqli->commit();
            $transactionStarted = false;
            $notice = 'Mappings updated.';
        }
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $mysqli->rollback();
        }
        $notice = 'Update failed: ' . $e->getMessage();
        $noticeType = 'error';
    }
}

$businessInfo = getBusinessInfo($mysqli);

$services = [];
$servicesResult = $mysqli->query('SELECT * FROM booking_services ORDER BY sort_order ASC, service_name ASC');
if ($servicesResult instanceof mysqli_result) {
    while ($row = $servicesResult->fetch_assoc()) {
        $services[] = $row;
    }
}

$slots = [];
$slotsResult = $mysqli->query('SELECT * FROM booking_time_slots ORDER BY sort_order ASC, db_time ASC');
if ($slotsResult instanceof mysqli_result) {
    while ($row = $slotsResult->fetch_assoc()) {
        $slots[] = $row;
    }
}

$stylists = [];
$stylistsResult = $mysqli->query('SELECT * FROM booking_stylists ORDER BY sort_order ASC, stylist_name ASC');
if ($stylistsResult instanceof mysqli_result) {
    while ($row = $stylistsResult->fetch_assoc()) {
        $stylists[] = $row;
    }
}

$locations = [];
$locationsResult = $mysqli->query('SELECT * FROM booking_locations WHERE is_active = 1 ORDER BY sort_order ASC, location_name ASC');
if ($locationsResult instanceof mysqli_result) {
    while ($row = $locationsResult->fetch_assoc()) {
        $locations[] = $row;
    }
}

$mappingStylists = [];
$mappingResult = $mysqli->query('SELECT service_id, location_id, GROUP_CONCAT(st.stylist_key ORDER BY st.sort_order ASC SEPARATOR ",") AS stylist_keys FROM booking_service_stylists m INNER JOIN booking_stylists st ON st.id = m.stylist_id WHERE m.is_active = 1 GROUP BY service_id, location_id');
if ($mappingResult instanceof mysqli_result) {
    while ($row = $mappingResult->fetch_assoc()) {
        $serviceId = (int)$row['service_id'];
        $locationId = (int)$row['location_id'];
        $mappingStylists[$serviceId][$locationId] = (string)$row['stylist_keys'];
    }
}

$serviceSlotsMap = [];
$serviceSlotsResult = $mysqli->query('SELECT service_id, GROUP_CONCAT(ts.slot_key ORDER BY ts.sort_order ASC SEPARATOR ",") AS slot_keys FROM booking_service_slots ss INNER JOIN booking_time_slots ts ON ts.id = ss.slot_id WHERE ss.is_active = 1 GROUP BY service_id');
if ($serviceSlotsResult instanceof mysqli_result) {
    while ($row = $serviceSlotsResult->fetch_assoc()) {
        $serviceSlotsMap[(int)$row['service_id']] = (string)$row['slot_keys'];
    }
}

$mysqli->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | Bella</title>
    <style>
        :root {
            --bg:#f6f7fb;
            --text:#161a27;
            --muted:#5f6b8a;
            --card:#ffffff;
            --line:#e4e8f2;
            --gold:#b07a2d;
            --ok:#0f8a5f;
            --err:#bf1f44;
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family: "Segoe UI", Tahoma, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width: 1300px; margin: 1.2rem auto; padding: 0 1rem 2rem; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; }
        h1 { margin:0; font-size:1.4rem; }
        .links { display:flex; gap:0.6rem; flex-wrap:wrap; }
        .btn { border:1px solid var(--line); background:#fff; color:#222; padding:0.5rem 0.75rem; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.88rem; }
        .btn.gold { background:var(--gold); border-color:var(--gold); color:#fff; }
        .notice { margin-bottom:1rem; padding:0.65rem 0.8rem; border-radius:8px; font-size:0.9rem; }
        .notice.success { background:#e8f8f1; color:var(--ok); border:1px solid #b7e8d1; }
        .notice.error { background:#ffecef; color:var(--err); border:1px solid #ffc5d0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:1rem; margin-bottom:1rem; }
        .card h2 { margin:0 0 0.8rem; font-size:1.05rem; }
        .grid-2 { display:grid; grid-template-columns:repeat(2, minmax(200px,1fr)); gap:0.8rem; }
        label { display:block; font-size:0.78rem; color:var(--muted); margin-bottom:0.25rem; text-transform:uppercase; letter-spacing:0.04em; }
        input[type="text"], input[type="number"], textarea {
            width:100%; border:1px solid #d4daea; border-radius:8px; padding:0.55rem 0.6rem; font:inherit; font-size:0.9rem;
        }
        textarea { min-height:70px; resize:vertical; }
        table { width:100%; border-collapse:collapse; font-size:0.86rem; }
        th, td { border-bottom:1px solid #edf0f7; padding:0.45rem; vertical-align:top; }
        th { text-align:left; color:#465170; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; }
        .small { font-size:0.78rem; color:var(--muted); }
        .check-wrap { display:flex; gap:0.25rem; align-items:center; }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns:1fr; }
            table { display:block; overflow:auto; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>Booking Configuration & Business Settings</h1>
        <div class="links">
            <a class="btn" href="admin-dashboard.php">Dashboard</a>
            <a class="btn" href="health-check.php" target="_blank" rel="noopener">Health Check</a>
            <a class="btn" href="admin-logout.php" style="color:#bf1f44;">Logout</a>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="notice <?php echo htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_business">
        <h2>Business Info</h2>
        <div class="grid-2">
            <?php foreach ($businessKeys as $key): ?>
                <div>
                    <label><?php echo htmlspecialchars(str_replace('_', ' ', $key), ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="text" name="business[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?php echo htmlspecialchars((string)($businessInfo[$key] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:0.8rem;"><button class="btn gold" type="submit">Save Business Settings</button></div>
    </form>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_services">
        <h2>Services</h2>
        <table>
            <thead><tr><th>Key</th><th>Name</th><th>Category</th><th>Price</th><th>Subtype</th><th>Length</th><th>Subtype Label</th><th>Sort</th><th>Active</th></tr></thead>
            <tbody>
            <?php foreach ($services as $service): ?>
                <?php $sid = (int)$service['id']; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$service['service_key'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><input type="text" name="services[<?php echo $sid; ?>][service_name]" value="<?php echo htmlspecialchars((string)$service['service_name'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="text" name="services[<?php echo $sid; ?>][category_label]" value="<?php echo htmlspecialchars((string)$service['category_label'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="number" step="0.01" min="0" name="services[<?php echo $sid; ?>][base_price]" value="<?php echo htmlspecialchars((string)$service['base_price'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="checkbox" name="services[<?php echo $sid; ?>][requires_sub_type]" value="1" <?php echo !empty($service['requires_sub_type']) ? 'checked' : ''; ?>></td>
                    <td><input type="checkbox" name="services[<?php echo $sid; ?>][requires_hair_length]" value="1" <?php echo !empty($service['requires_hair_length']) ? 'checked' : ''; ?>></td>
                    <td><input type="text" name="services[<?php echo $sid; ?>][sub_type_label]" value="<?php echo htmlspecialchars((string)$service['sub_type_label'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="number" min="0" name="services[<?php echo $sid; ?>][sort_order]" value="<?php echo (int)$service['sort_order']; ?>" style="max-width:80px;"></td>
                    <td><input type="checkbox" name="services[<?php echo $sid; ?>][is_active]" value="1" <?php echo !empty($service['is_active']) ? 'checked' : ''; ?>></td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="8">
                        <label>Info Text</label>
                        <textarea name="services[<?php echo $sid; ?>][info_text]"><?php echo htmlspecialchars((string)$service['info_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:0.8rem;"><button class="btn gold" type="submit">Save Services</button></div>
    </form>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_slots">
        <h2>Time Slots</h2>
        <table>
            <thead><tr><th>Key</th><th>Label</th><th>DB Time</th><th>Sort</th><th>Active</th></tr></thead>
            <tbody>
            <?php foreach ($slots as $slot): ?>
                <?php $slotId = (int)$slot['id']; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$slot['slot_key'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><input type="text" name="slots[<?php echo $slotId; ?>][slot_label]" value="<?php echo htmlspecialchars((string)$slot['slot_label'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="text" name="slots[<?php echo $slotId; ?>][db_time]" value="<?php echo htmlspecialchars((string)$slot['db_time'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="number" min="0" name="slots[<?php echo $slotId; ?>][sort_order]" value="<?php echo (int)$slot['sort_order']; ?>" style="max-width:80px;"></td>
                    <td><input type="checkbox" name="slots[<?php echo $slotId; ?>][is_active]" value="1" <?php echo !empty($slot['is_active']) ? 'checked' : ''; ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:0.8rem;"><button class="btn gold" type="submit">Save Time Slots</button></div>
    </form>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="add_stylist">
        <h2>Add Staff Member</h2>
        <p class="small">Add a new stylist/braider/MUA. They’ll be created as <strong>Active</strong>; then tick them into the relevant services in the <strong>Mappings</strong> card below so they appear in the booking calendar.</p>
        <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;margin-top:0.6rem;">
            <input type="text" name="new_stylist_name" placeholder="Full name e.g. Itumeleng" maxlength="150" required style="flex:1;min-width:220px;">
            <button class="btn gold" type="submit">Add staff member</button>
        </div>
    </form>

    <!-- Shared target form for per-row deletes (avoids nested forms in the table). -->
    <form id="deleteStylistForm" method="post" style="display:none;">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="delete_stylist">
    </form>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_stylists">
        <h2>Stylists</h2>
        <p class="small">Edit names/order, or untick <strong>Active</strong> to retire a staff member (keeps their booking history). Use <strong>Remove</strong> only to delete someone with no bookings on record.</p>
        <table>
            <thead><tr><th>Key</th><th>Name</th><th>Sort</th><th>Active</th><th>Remove</th></tr></thead>
            <tbody>
            <?php foreach ($stylists as $stylist): ?>
                <?php $stylistId = (int)$stylist['id']; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$stylist['stylist_key'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><input type="text" name="stylists[<?php echo $stylistId; ?>][stylist_name]" value="<?php echo htmlspecialchars((string)$stylist['stylist_name'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                    <td><input type="number" min="0" name="stylists[<?php echo $stylistId; ?>][sort_order]" value="<?php echo (int)$stylist['sort_order']; ?>" style="max-width:80px;"></td>
                    <td><input type="checkbox" name="stylists[<?php echo $stylistId; ?>][is_active]" value="1" <?php echo !empty($stylist['is_active']) ? 'checked' : ''; ?>></td>
                    <td>
                        <button type="submit" form="deleteStylistForm" name="stylist_id" value="<?php echo $stylistId; ?>"
                                class="btn" style="background:#fbeaea;color:#a3302a;border:1px solid #e5b4b4;"
                                onclick="return confirm('Remove <?php echo htmlspecialchars(addslashes((string)$stylist['stylist_name']), ENT_QUOTES, 'UTF-8'); ?>? This only works if they have no bookings on record.');">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:0.8rem;"><button class="btn gold" type="submit">Save Stylists</button></div>
    </form>

    <form method="post" class="card">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_mappings">
        <h2>Mappings</h2>
        <p class="small">Use comma-separated keys. Examples: caro,emma,patience or 07:30,11:30,14:30</p>
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <?php foreach ($locations as $location): ?>
                        <th>Stylists @ <?php echo htmlspecialchars((string)$location['location_key'], ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                    <th>Allowed Slot Keys</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <?php $sid = (int)$service['id']; ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$service['service_key'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars((string)$service['service_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <?php foreach ($locations as $location): ?>
                            <?php $lid = (int)$location['id']; ?>
                            <td>
                                <textarea name="service_location_stylists[<?php echo $sid; ?>][<?php echo htmlspecialchars((string)$location['location_key'], ENT_QUOTES, 'UTF-8'); ?>]" style="min-height:52px;"><?php echo htmlspecialchars((string)($mappingStylists[$sid][$lid] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <textarea name="service_slots[<?php echo $sid; ?>]" style="min-height:52px;"><?php echo htmlspecialchars((string)($serviceSlotsMap[$sid] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:0.8rem;"><button class="btn gold" type="submit">Save Mappings</button></div>
    </form>
</div>
</body>
</html>
