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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $reason = trim($_POST['cancellation_reason'] ?? '');
    
    if ($reason === '') {
        $error = 'Please provide a cancellation reason.';
    } else {
        if (cancelBooking($bookingId, $reason)) {
            $success = 'Booking cancelled successfully.';
            $booking = getBookingById($bookingId);
        } else {
            $error = 'Failed to cancel booking.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - Bella CRM</title>
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
            color: #e74c3c;
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
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #856404;
        }
        
        .warning-box h3 {
            margin: 0 0 0.5rem 0;
        }
        
        .current-booking {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
            border-left: 3px solid #e74c3c;
        }
        
        .current-booking h3 {
            margin: 0 0 1rem 0;
            color: #666;
        }
        
        .current-booking p {
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
        
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
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
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .back-link {
            color: #e74c3c;
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
                <h1>⚠ Cancel Booking #<?php echo $bookingId; ?></h1>
            </header>
            
            <div class="admin-content">
                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="back-link">← Back</a>
                
                <div class="card">
                    <div class="current-booking">
                        <h3>Booking Details</h3>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($booking['appointment_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($booking['appointment_time'])); ?></p>
                        <p><strong>Amount:</strong> R<?php echo number_format($booking['amount'], 2); ?></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                            <p style="margin: 1rem 0 0 0;">
                                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="btn" style="background: #06a77d; color: white; font-size: 0.9rem;">View Cancelled Booking</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="warning-box">
                            <h3>⚠ Warning</h3>
                            <p>You are about to cancel this booking. This action cannot be undone. The client will be notified of the cancellation.</p>
                        </div>
                        
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label for="cancellation_reason">Cancellation Reason</label>
                                <textarea id="cancellation_reason" name="cancellation_reason" rows="4" placeholder="Explain why this booking is being cancelled..." required></textarea>
                                <small style="color: #999;">This reason will be recorded in the booking history and can be shared with the client.</small>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to cancel this booking?');">Confirm Cancellation</button>
                                <a href="admin-booking-detail.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">Keep Booking</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
