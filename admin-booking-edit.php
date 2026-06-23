<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = requireAdminRole(['admin', 'manager']);

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(404);
    die('Booking not found.');
}

$booking = getBookingById($bookingId);
if (!$booking) {
    http_response_code(404);
    die('Booking not found.');
}

$errors = [];
$success = '';
$stylists = getAllStylists();

// Cashless system: 'pending_cash' is no longer selectable (legacy rows still display
// via the status badges). Online deposit bookings flow pending -> paid -> completed.
$statuses = ['pending', 'confirmed', 'paid', 'completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $newStatus = trim($_POST['status'] ?? '');
    $stylistKey = isset($_POST['stylist_key']) ? trim((string)$_POST['stylist_key']) : '';
    
    if ($newStatus !== '' && in_array($newStatus, $statuses, true)) {
        if (updateBookingStatus($bookingId, $newStatus)) {
            $success = 'Status updated successfully.';
        } else {
            $errors[] = 'Failed to update status.';
        }
    }
    
    if ($stylistKey !== '') {
        if (assignStylist($bookingId, $stylistKey)) {
            $success = $success ? $success . ' Stylist assigned.' : 'Stylist assigned successfully.';
        } else {
            $errors[] = 'Failed to assign stylist.';
        }
    }
    
    if ($success) {
        $booking = getBookingById($bookingId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking - Bella CRM</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Montserrat', sans-serif;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 250px;
            background: #1a1a1a;
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-sidebar h2 {
            padding: 0 1.5rem;
            font-size: 1.2rem;
            margin: 0 0 2rem 0;
            color: #c9a961;
        }
        
        .admin-nav a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #ccc;
            text-decoration: none;
        }
        
        .admin-nav a:hover {
            color: #c9a961;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 250px;
        }
        
        .admin-header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .admin-content {
            padding: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 600px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #721c24;
            color: #721c24;
        }
        
        .alert-error ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .alert-error li {
            margin-bottom: 0.5rem;
        }
        
        .alert-success {
            background: #c7f0d8;
            border: 1px solid #06a77d;
            color: #06a77d;
        }
        
        .current-info {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
            border-left: 3px solid #c9a961;
        }
        
        .current-info h3 {
            margin: 0 0 1rem 0;
            color: #666;
        }
        
        .current-info p {
            margin: 0.5rem 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        select:focus {
            outline: none;
            border-color: #c9a961;
            box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #c9a961;
            color: white;
        }
        
        .btn-primary:hover {
            background: #a88a4d;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .back-link {
            color: #c9a961;
            text-decoration: none;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <h2>Bella CRM</h2>
            <ul class="admin-nav">
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>">Back</a></li>
            </ul>
        </aside>
        
        <div class="admin-main">
            <header class="admin-header">
                <h1>Edit Booking #<?php echo $bookingId; ?></h1>
            </header>
            
            <div class="admin-content">
                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="back-link">← Back</a>
                
                <div class="card">
                    <div class="current-info">
                        <h3>Booking Information</h3>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Appointment:</strong> <?php echo date('F d, Y g:i A', strtotime($booking['appointment_date'] . ' ' . $booking['appointment_time'])); ?></p>
                        <p><strong>Amount:</strong> R<?php echo number_format($booking['amount'], 2); ?></p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label for="status">Booking Status</label>
                            <select id="status" name="status">
                                <option value="">No change</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($booking['status'] === $status) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #999;">Update the booking status (pending, confirmed, paid, completed)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="stylist_key">Assign Stylist</label>
                            <select id="stylist_key" name="stylist_key">
                                <option value="">No stylist assigned</option>
                                <?php foreach ($stylists as $stylist): ?>
                                    <option value="<?php echo htmlspecialchars((string)$stylist['stylist_key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($booking['preferred_stylist'] ?? '') === $stylist['stylist_key']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stylist['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
