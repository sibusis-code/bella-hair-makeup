<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = getAdminUser();

// User management (which can grant the admin role) is restricted to admins only.
// Allowing managers here let a manager mint a full admin account (privilege escalation).
if (!$admin || (string)($admin['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    csrfVerify();
    $result = createAdminUser([
        'first_name' => trim((string)($_POST['first_name'] ?? '')),
        'last_name' => trim((string)($_POST['last_name'] ?? '')),
        'username' => trim((string)($_POST['username'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'role' => trim((string)($_POST['role'] ?? 'staff')),
        'password' => (string)($_POST['password'] ?? '')
    ], (string)($admin['role'] ?? ''));

    $message = (string)$result['message'];
    $messageType = !empty($result['success']) ? 'success' : 'error';
}

$users = getAdminUsers();
$activeUsers = countActiveAdminUsers();
$maxUsers = getMaxAdminUsers();
$remainingUsers = max(0, $maxUsers - $activeUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Bella CRM</title>
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
        .sidebar-nav a:hover,
        .sidebar-nav a.active { color: var(--gold); background: var(--sidebar-active); border-left-color: var(--gold); }

        .sidebar-footer { border-top: 1px solid var(--sidebar-border); padding: 1rem 1.2rem; }
        .admin-chip { margin-bottom: 0.8rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--sidebar-border); }
        .admin-chip .name { font-size: 0.9rem; font-weight: 700; color: #f3f4f6; }
        .admin-chip .role { margin-top: 0.1rem; font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .sidebar-footer a { display: block; color: var(--muted); text-decoration: none; padding: 0.35rem 0; font-size: 0.95rem; }

        .main { width: 100%; margin-left: 230px; padding: 1.2rem; }
        .content { max-width: 1180px; margin: 0 auto; }
        .page-title { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 700; }
        .summary {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .message { margin-bottom: 1rem; border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.9rem; }
        .message.success { background: #d1fae5; color: #065f46; }
        .message.error { background: #fee2e2; color: #991b1b; }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 1rem;
            align-items: start;
        }
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .card-head {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #edf0f4;
            font-weight: 700;
        }
        .card-body { padding: 1rem; }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.7rem 0.5rem; border-bottom: 1px solid #f2f4f7; font-size: 0.88rem; }
        th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; }

        .form-group { margin-bottom: 0.8rem; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #5b6472; margin-bottom: 0.35rem; }
        .form-group input,
        .form-group select {
            width: 100%;
            border: 1px solid #d8dde5;
            border-radius: 8px;
            padding: 0.58rem 0.65rem;
            font: inherit;
        }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 0.66rem 1rem;
            background: var(--gold);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        .btn[disabled] {
            background: #d1d5db;
            cursor: not-allowed;
        }

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
            .main { margin-left: 64px; }
            .grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 0.55rem 0.4rem 4.4rem; }
            .bottom-nav { display: flex; }
            .card-body { padding: 0.75rem; }
            th, td { font-size: 0.82rem; }
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
            <li><a href="admin-users.php" class="active"><span class="icon">U</span><span class="label">Users</span></a></li>
        </ul>
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
        <a href="admin-dashboard.php?view=all">All</a>
    </nav>

    <main class="main">
        <div class="content">
            <h1 class="page-title">User Management</h1>

            <div class="summary">
                <div><strong>Active Users:</strong> <?php echo $activeUsers; ?> / <?php echo $maxUsers; ?></div>
                <div><strong>Remaining Slots:</strong> <?php echo $remainingUsers; ?></div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="grid">
                <section class="card">
                    <div class="card-head">Current Users</div>
                    <div class="card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(trim((string)(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst((string)($u['role'] ?? 'staff')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo !empty($u['is_active']) ? 'Active' : 'Inactive'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head">Create User (max <?php echo $maxUsers; ?>)</div>
                    <div class="card-body">
                        <form method="post">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="create_user" value="1">

                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" maxlength="100" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" maxlength="100" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" maxlength="100" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" maxlength="150" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select id="role" name="role" required>
                                    <option value="staff">Staff</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" minlength="8" required>
                            </div>

                            <button type="submit" class="btn" <?php echo $remainingUsers === 0 ? 'disabled' : ''; ?>>Create User</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
