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

$timeSlots = [
    '08:00' => '08:00 AM',
    '09:00' => '09:00 AM',
    '10:00' => '10:00 AM',
    '11:00' => '11:00 AM',
    '12:00' => '12:00 PM',
    '13:00' => '01:00 PM',
    '14:00' => '02:00 PM',
    '15:00' => '03:00 PM',
    '16:00' => '04:00 PM',
    '17:00' => '05:00 PM',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $newDate = trim($_POST['new_date'] ?? '');
    $newTime = trim($_POST['new_time'] ?? '');
    
    if ($newDate === '') {
        $errors[] = 'New date is required.';
    }
    
    if ($newTime === '') {
        $errors[] = 'New time is required.';
    }
    
    if (empty($errors)) {
        // Convert UI time to database format
        $dbTime = $newTime . ':00';
        
        if (rescheduleBooking($bookingId, $newDate, $dbTime)) {
            $success = 'Booking rescheduled successfully.';
            $booking = getBookingById($bookingId);
        } else {
            $errors[] = 'Failed to reschedule booking. The time slot may already be taken.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Booking - Bella CRM</title>
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
        
        .alert-success {
            background: #c7f0d8;
            border: 1px solid #06a77d;
            color: #06a77d;
        }
        
        .alert ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .alert li {
            margin-bottom: 0.5rem;
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
        
        input[type="date"], select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        input[type="date"]:focus, select:focus {
            outline: none;
            border-color: #c9a961;
            box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
        }
        
        .current-booking {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
            border-left: 3px solid #c9a961;
        }
        
        .current-booking h3 {
            margin: 0 0 1rem 0;
            color: #666;
        }
        
        .current-booking p {
            margin: 0.5rem 0;
            color: #333;
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
                <h1>Reschedule Booking #<?php echo $bookingId; ?></h1>
            </header>
            
            <div class="admin-content">
                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="back-link">← Back</a>
                
                <div class="card">
                    <div class="current-booking">
                        <h3>Current Booking Details</h3>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Current Date:</strong> <?php echo date('F d, Y', strtotime($booking['appointment_date'])); ?></p>
                        <p><strong>Current Time:</strong> <?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></p>
                        <p><strong>Service:</strong> <?php echo htmlspecialchars($booking['service'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
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
                            <p style="margin: 1rem 0 0 0;">
                                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="btn btn-primary" style="font-size: 0.9rem;">View Updated Booking</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label for="new_date">New Date</label>
                                <input type="date" id="new_date" name="new_date" value="<?php echo date('Y-m-d', strtotime($booking['appointment_date'])); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_time">New Time</label>
                                <select id="new_time" name="new_time" required>
                                    <option value="">Select a time slot</option>
                                    <?php foreach ($timeSlots as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (date('H:i', strtotime($booking['appointment_time'])) === $key) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">Reschedule</button>
                                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
