<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if ($currentPassword === '') {
        $messageType = 'error';
        $message = 'Current password is required.';
    } elseif ($newPassword === '') {
        $messageType = 'error';
        $message = 'New password is required.';
    } elseif ($newPassword !== $confirmPassword) {
        $messageType = 'error';
        $message = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $messageType = 'error';
        $message = 'New password must be at least 8 characters long.';
    } else {
        // Change password
        $result = changeAdminPassword($_SESSION['admin_id'], $currentPassword, $newPassword);
        
        if ($result['success']) {
            $messageType = 'success';
            $message = $result['message'];
        } else {
            $messageType = 'error';
            $message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Bella CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body { font-family: 'Montserrat', sans-serif; background: var(--main-bg); color: var(--text); }

        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 230px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            background: var(--sidebar-bg);
            color: var(--sidebar-txt);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .sidebar-logo { padding: 1.4rem 1.2rem 1rem; border-bottom: 1px solid var(--sidebar-border); }
        .sidebar-logo .brand { color: var(--gold); font-size: 1.8rem; font-weight: 700; letter-spacing: 0.05em; }
        .sidebar-logo .sub { color: var(--muted); font-size: 0.8rem; letter-spacing: 0.1em; text-transform: uppercase; }

        .sidebar-nav { list-style: none; margin: 0; padding: 1rem 0; flex: 1; }
        .sidebar-nav li { margin: 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            color: var(--sidebar-txt);
            text-decoration: none;
            padding: 0.72rem 1.2rem;
            font-weight: 600;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover,
        .sidebar-nav a.active { color: var(--gold); background: var(--sidebar-active); border-left-color: var(--gold); }
        .sidebar-nav a .icon {
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

        .sidebar-footer { border-top: 1px solid var(--sidebar-border); padding: 1rem 1.2rem; }
        .admin-chip { margin-bottom: 0.8rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--sidebar-border); }
        .admin-chip .name { font-size: 0.9rem; font-weight: 700; color: #f3f4f6; }
        .admin-chip .role { margin-top: 0.1rem; font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .sidebar-footer a { display: block; color: var(--muted); text-decoration: none; padding: 0.35rem 0; font-size: 0.95rem; }
        .sidebar-footer a:hover { color: var(--gold); }

        .main { width: 100%; margin-left: 230px; padding: 1.6rem; }
        .content { max-width: 760px; margin: 0 auto; }
        .page-title { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 700; }

        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }
        .form-card h2 { margin: 0 0 1.2rem; font-size: 1.2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: #5b6472;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }
        .form-group input {
            width: 100%;
            border: 1px solid #d8dde5;
            border-radius: 8px;
            padding: 0.62rem 0.72rem;
            font: inherit;
            background: #fff;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,169,97,0.14);
        }

        .helper { color: var(--muted); font-size: 0.82rem; margin-top: 0.35rem; }
        .button-group { display: flex; gap: 0.7rem; margin-top: 1.2rem; }
        .btn {
            flex: 1;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
        }
        .btn-primary { background: var(--gold); color: #fff; }
        .btn-secondary { background: #dbe2ea; color: #425466; }

        .message { display: none; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .message.success { display: block; background: #d1fae5; color: #065f46; }
        .message.error { display: block; background: #fee2e2; color: #991b1b; }

        .back-link { margin-top: 1rem; text-align: center; }
        .back-link a { color: var(--gold); text-decoration: none; font-weight: 600; font-size: 0.9rem; }

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
            .sidebar-nav a span.label,
            .sidebar-footer { display: none; }
            .sidebar-nav a { justify-content: center; padding: 0.78rem 0; border-left: none; }
            .main { margin-left: 64px; padding: 1.2rem; }
        }

        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 0.8rem 0.5rem 4.4rem; }
            .content { max-width: none; }
            .button-group { flex-direction: column; }
            .bottom-nav { display: flex; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="brand">Bella</div>
                <div class="sub">Hair | Makeup &mdash; CRM</div>
            </div>
            <ul class="sidebar-nav">
                <li><a href="admin-dashboard.php?view=upcoming"><span class="icon">U</span><span class="label">Upcoming</span></a></li>
                <li><a href="admin-dashboard.php?view=today"><span class="icon">T</span><span class="label">Today</span></a></li>
                <li><a href="admin-dashboard.php?view=completed"><span class="icon">C</span><span class="label">Completed</span></a></li>
                <li><a href="admin-dashboard.php?view=cancelled"><span class="icon">X</span><span class="label">Cancelled</span></a></li>
                <li><a href="admin-dashboard.php?view=all"><span class="icon">A</span><span class="label">All Bookings</span></a></li>
                <li><a href="admin-users.php"><span class="icon">U</span><span class="label">Users</span></a></li>
                <li><a href="admin-change-password.php" class="active"><span class="icon">P</span><span class="label">Change Password</span></a></li>
            </ul>
            <div class="sidebar-footer">
                <div class="admin-chip">
                    <div class="name"><?php echo htmlspecialchars(trim((string)(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="role"><?php echo htmlspecialchars(ucfirst((string)($admin['role'] ?? 'admin')), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <a href="admin-logout.php" style="color:#ef4444;">Logout</a>
            </div>
        </aside>

        <nav class="bottom-nav" aria-label="Mobile Navigation">
            <a href="admin-dashboard.php?view=upcoming">Upcoming</a>
            <a href="admin-dashboard.php?view=today">Today</a>
            <a href="admin-dashboard.php?view=completed">Done</a>
            <a href="admin-dashboard.php?view=cancelled">Cancel</a>
            <a href="admin-dashboard.php?view=all">All</a>
        </nav>

        <main class="main">
            <div class="content">
                <h1 class="page-title">Change Password</h1>
                <div class="form-card">
                    <h2>Update Your Password</h2>
                    
                    <?php if ($message): ?>
                        <div class="message <?php echo $messageType; ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="8" required>
                            <div class="helper">Minimum 8 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                            <a href="admin-dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                    
                    <div class="back-link">
                        <a href="admin-dashboard.php?view=all">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
