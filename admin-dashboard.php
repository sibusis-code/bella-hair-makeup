<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin    = getAdminUser();
$stats    = getBookingStats();
$today    = getTodaysBookings();
$todaySlotOverview = getSlotOverviewForDate(date('Y-m-d'));

$view     = $_GET['view'] ?? 'upcoming';
$search   = trim($_GET['q'] ?? '');

$allowedViews = ['all', 'upcoming', 'today', 'completed', 'cancelled'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'upcoming';
}

// Build bookings list for current view
switch ($view) {
    case 'today':
        $bookings = $today;
        $viewLabel = "Today's Appointments";
        break;
    case 'upcoming':
        $all = array_merge(getAllBookings('paid'), getAllBookings('pending_cash'));
        $bookings = array_filter($all, fn($b) => $b['appointment_date'] >= date('Y-m-d'));
        usort($bookings, fn($a, $b) => strcmp($a['appointment_date'] . $a['appointment_time'], $b['appointment_date'] . $b['appointment_time']));
        $viewLabel = 'Upcoming Appointments';
        break;
    case 'completed':
        $bookings = getAllBookings('completed');
        $viewLabel = 'Completed Appointments';
        break;
    case 'cancelled':
        $bookings = getAllBookings('cancelled');
        $viewLabel = 'Cancelled Bookings';
        break;
    default:
        $bookings = getAllBookings();
        $viewLabel = 'All Bookings';
        break;
}

// Search filter
if ($search !== '') {
    $s = strtolower($search);
    $bookings = array_filter($bookings, function ($b) use ($s) {
        return str_contains(strtolower((string)($b['name'] ?? '')), $s)
            || str_contains(strtolower((string)($b['phone'] ?? '')), $s)
            || str_contains(strtolower((string)($b['email'] ?? '')), $s)
            || str_contains(strtolower((string)($b['m_payment_id'] ?? '')), $s);
    });
}

// Handle quick status update (mark complete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'])) {
    csrfVerify();
    $bid = (int)($_POST['booking_id'] ?? 0);
    if ($bid > 0) {
        updateBookingStatus($bid, 'completed');
    }
    $qs = http_build_query(['view' => $view, 'q' => $search]);
    header('Location: admin-dashboard.php?' . $qs);
    exit;
}

$exportUrl = 'admin-export-csv.php?' . http_build_query(['view' => $view, 'q' => $search]);

function statusBadge(string $status): string
{
    $map = [
        'paid'      => ['#d1fae5', '#065f46', 'Paid'],
        'pending_cash' => ['#ffedd5', '#9a3412', 'Pending Cash'],
        'completed' => ['#dbeafe', '#1e40af', 'Completed'],
        'cancelled' => ['#fee2e2', '#991b1b', 'Cancelled'],
        'pending'   => ['#fef9c3', '#92400e', 'Pending'],
        'confirmed' => ['#e0e7ff', '#3730a3', 'Confirmed'],
    ];
    $c = $map[$status] ?? ['#f3f4f6', '#374151', ucfirst($status)];
    return '<span style="background:' . $c[0] . ';color:' . $c[1] . ';padding:0.2rem 0.65rem;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;">' . htmlspecialchars($c[2], ENT_QUOTES, 'UTF-8') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Bella CRM</title>
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
        .sidebar-logo {
            padding: 1.4rem 1.2rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .sidebar-logo .brand {
            color: var(--gold);
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .sidebar-logo .sub {
            color: var(--muted);
            font-size: 0.8rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .sidebar-nav { flex: 1; padding: 1rem 0; }
        .nav-section {
            color: var(--muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 0.4rem 1.2rem;
        }
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
        .sidebar-nav a > span:first-child {
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
        .sidebar-nav a.active {
            color: var(--gold);
            background: var(--sidebar-active);
            border-left-color: var(--gold);
        }
        .sidebar-nav a .count {
            margin-left: auto;
            font-size: 0.75rem;
            background: var(--gold);
            color: #111;
            border-radius: 999px;
            padding: 0.08rem 0.5rem;
            font-weight: 700;
        }

        .sidebar-footer {
            border-top: 1px solid var(--sidebar-border);
            padding: 1rem 1.2rem;
        }
        .admin-chip {
            margin-bottom: 0.8rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .admin-chip .name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #f3f4f6;
        }
        .admin-chip .role {
            margin-top: 0.1rem;
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .sidebar-footer a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            padding: 0.35rem 0;
            font-size: 0.95rem;
        }
        .sidebar-footer a:hover { color: var(--gold); }

        .main {
            width: 100%;
            margin-left: 230px;
            padding: 1.6rem;
        }
        .content {
            max-width: 1180px;
            margin: 0 auto;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat {
            background: var(--card-bg);
            border-radius: 12px;
            border-top: 3px solid var(--gold);
            box-shadow: var(--card-shadow);
            padding: 1rem;
            min-height: 116px;
        }
        .stat .label {
            color: var(--muted);
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .stat .value {
            margin-top: 0.35rem;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .stat .value.gold { color: var(--gold); }
        .stat .sub {
            margin-top: 0.25rem;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .today-panel,
        .table-panel {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .today-panel { margin-bottom: 1.3rem; }
        .panel-header {
            padding: 0.95rem 1.2rem;
            border-bottom: 1px solid #edf0f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
        }
        .panel-header h2,
        .table-toolbar h2 { margin: 0; font-size: 1.5rem; }
        .panel-header .today-date { color: var(--muted); font-size: 0.85rem; }

        .today-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.95rem 1.2rem;
            border-bottom: 1px solid #f2f4f7;
        }
        .today-item:last-child { border-bottom: none; }
        .today-time {
            min-width: 70px;
            text-align: center;
            padding: 0.35rem 0.55rem;
            background: #fef9ec;
            color: #9c7b2f;
            border-radius: 6px;
            font-size: 0.84rem;
            font-weight: 700;
        }
        .today-client { flex: 1; }
        .today-client .cname { font-weight: 700; }
        .today-client .cdetail { font-size: 0.84rem; color: var(--muted); margin-top: 0.1rem; }
        .today-actions { display: flex; gap: 0.45rem; flex-wrap: wrap; }
        .empty-today,
        .empty-state {
            padding: 2rem 1.2rem;
            text-align: center;
            color: var(--muted);
        }

        .table-toolbar {
            padding: 0.95rem 1.2rem;
            border-bottom: 1px solid #edf0f4;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .toolbar-actions {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        .slots-panel {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .slots-panel .panel-header { border-bottom: 1px solid #edf0f4; }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.7rem;
            padding: 0.8rem;
        }
        .slot-item {
            border: 1px solid #edf0f4;
            border-radius: 8px;
            padding: 0.55rem 0.6rem;
        }
        .slot-time { font-size: 0.85rem; font-weight: 700; }
        .slot-meta { margin-top: 0.25rem; font-size: 0.75rem; color: var(--muted); }
        .slot-stylists { margin-top: 0.2rem; font-size: 0.74rem; color: #5b6472; }
        .table-toolbar h2 {
            font-size: 1.9rem;
            flex: 1;
            min-width: 160px;
        }

        .view-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; }
        .view-tab {
            text-decoration: none;
            color: #5b6472;
            background: #f2f4f7;
            padding: 0.4rem 0.85rem;
            border-radius: 7px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .view-tab.active {
            background: var(--gold);
            color: #fff;
        }

        .search-box {
            display: flex;
            align-items: center;
            border: 1px solid #d8dde5;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            min-width: 260px;
        }
        .search-box input {
            border: none;
            outline: none;
            padding: 0.52rem 0.7rem;
            width: 100%;
            font: inherit;
        }
        .search-icon {
            background: var(--gold);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.55rem 0.85rem;
            line-height: 1;
        }

        table { width: 100%; border-collapse: collapse; min-width: 860px; }
        th {
            text-align: left;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            padding: 0.82rem 1rem;
            border-bottom: 1px solid #edf0f4;
        }
        td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #f3f5f8;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #fafbfc; }
        .is-hidden { display: none !important; }

        .client-name { font-weight: 700; }
        .client-phone,
        .appt-time { color: var(--muted); font-size: 0.84rem; margin-top: 0.08rem; }
        .appt-date { font-weight: 700; }

        .btn-sm {
            border: none;
            border-radius: 6px;
            padding: 0.35rem 0.75rem;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            white-space: nowrap;
            display: inline-block;
        }
        .btn-view { background: #e0e7ff; color: #3730a3; }
        .btn-done { background: #d1fae5; color: #065f46; }
        .btn-wa { background: #dcfce7; color: #166534; }

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
            letter-spacing: 0.02em;
        }
        .bottom-nav a.active { color: var(--gold); }

        @media (max-width: 1100px) {
            .sidebar {
                width: 64px;
            }
            .sidebar-logo .sub,
            .sidebar-logo .brand,
            .nav-section,
            .sidebar-nav a span:last-child,
            .sidebar-nav a .count,
            .sidebar-footer {
                display: none;
            }
            .sidebar-nav a {
                justify-content: center;
                padding: 0.78rem 0;
                border-left: none;
            }
            .main {
                margin-left: 64px;
                padding: 1.2rem;
            }
            .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        @media (max-width: 900px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .table-toolbar h2 {
                width: 100%;
                font-size: 1.4rem;
            }
            .slots-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main {
                margin-left: 0;
                padding: 0.55rem 0.4rem 4.4rem;
            }
            .bottom-nav {
                display: flex;
            }
            .stats {
                display: flex;
                overflow-x: auto;
                gap: 0.6rem;
                padding-bottom: 0.2rem;
            }
            .stat {
                min-width: 220px;
                flex: 0 0 auto;
            }
            .table-panel,
            .today-panel {
                border-radius: 10px;
            }
            .search-box {
                min-width: 100%;
                width: 100%;
            }

            .table-panel table,
            .table-panel thead,
            .table-panel tbody,
            .table-panel tr,
            .table-panel th,
            .table-panel td {
                display: block;
                width: 100%;
            }
            .table-panel thead { display: none; }
            .table-panel tr {
                margin: 0.7rem;
                border-radius: 9px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.06);
                overflow: hidden;
                background: #fff;
            }
            .table-panel td {
                border: none;
                padding: 0.52rem 0.8rem;
            }
            .table-panel td:before {
                content: attr(data-label);
                display: block;
                font-size: 0.74rem;
                color: var(--muted);
                font-weight: 700;
                margin-bottom: 0.08rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .slots-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- Sidebar (desktop/tablet) -->
    <aside class="sidebar" aria-label="Sidebar">
        <div class="sidebar-logo">
            <div class="brand">Bella</div>
            <div class="sub">Hair | Makeup &mdash; CRM</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Views</div>
            <a href="?view=upcoming" class="<?php echo $view === 'upcoming' ? 'active' : ''; ?>">
                <span aria-hidden="true">U</span><span>Upcoming</span>
                <?php if ($stats['upcoming_bookings'] > 0): ?>
                    <span class="count"><?php echo $stats['upcoming_bookings']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?view=today" class="<?php echo $view === 'today' ? 'active' : ''; ?>">
                <span aria-hidden="true">T</span><span>Today</span>
                <?php if ($stats['today_bookings'] > 0): ?>
                    <span class="count"><?php echo $stats['today_bookings']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?view=completed" class="<?php echo $view === 'completed' ? 'active' : ''; ?>">
                <span aria-hidden="true">C</span><span>Completed</span>
            </a>
            <a href="?view=cancelled" class="<?php echo $view === 'cancelled' ? 'active' : ''; ?>">
                <span aria-hidden="true">X</span><span>Cancelled</span>
            </a>
            <a href="?view=all" class="<?php echo $view === 'all' ? 'active' : ''; ?>">
                <span aria-hidden="true">A</span><span>All Bookings</span>
            </a>
            <a href="admin-block-slots.php">
                <span aria-hidden="true">B</span><span>Block Times</span>
            </a>
            <a href="admin-users.php">
                <span aria-hidden="true">U</span><span>Users</span>
            </a>
            <a href="admin-settings.php">
                <span aria-hidden="true">S</span><span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="admin-chip">
                <div class="name"><?php echo htmlspecialchars(trim((string)(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="role"><?php echo htmlspecialchars(ucfirst((string)($admin['role'] ?? 'admin')), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a href="admin-change-password.php">Change Password</a>
            <a href="admin-logout.php" style="color:#ef4444;">Logout</a>
        </div>
    </aside>

    <!-- Bottom nav (mobile) -->
    <nav class="bottom-nav" aria-label="Mobile Navigation">
        <a href="?view=upcoming" class="<?php echo $view === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
        <a href="?view=today" class="<?php echo $view === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?view=completed" class="<?php echo $view === 'completed' ? 'active' : ''; ?>">Done</a>
        <a href="?view=cancelled" class="<?php echo $view === 'cancelled' ? 'active' : ''; ?>">Cancel</a>
        <a href="admin-block-slots.php">Block</a>
    </nav>

    <!-- Main -->
    <div class="main">

        <div class="content">

            <!-- Stats -->
            <div class="stats">
                <div class="stat">
                    <div class="label">Today's Appointments</div>
                    <div class="value"><?php echo $stats['today_bookings']; ?></div>
                    <div class="sub"><?php echo date('l, j M Y'); ?></div>
                </div>
                <div class="stat">
                    <div class="label">Upcoming (Paid)</div>
                    <div class="value"><?php echo $stats['upcoming_bookings']; ?></div>
                    <div class="sub">from today onwards</div>
                </div>
                <div class="stat">
                    <div class="label">This Week</div>
                    <div class="value"><?php echo $stats['this_week_bookings']; ?></div>
                    <div class="sub">paid &amp; completed</div>
                </div>
                <div class="stat">
                    <div class="label">Completed</div>
                    <div class="value"><?php echo $stats['completed_bookings']; ?></div>
                    <div class="sub">all time</div>
                </div>
                <div class="stat">
                    <div class="label">Deposits Collected</div>
                    <div class="value gold">R<?php echo number_format($stats['total_revenue'], 0); ?></div>
                    <div class="sub">paid + completed</div>
                </div>
            </div>

            <!-- Today's quick panel (only show on upcoming/today) -->
            <?php if (in_array($view, ['upcoming', 'today'], true) && !empty($today)): ?>
            <div class="today-panel">
                <div class="panel-header">
                    <h2>Today's Schedule</h2>
                    <span class="today-date"><?php echo date('l, j F Y'); ?></span>
                </div>
                <div class="today-list">
                    <?php foreach ($today as $t): ?>
                    <div class="today-item">
                        <div class="today-time"><?php echo date('g:i A', strtotime((string)$t['appointment_time'])); ?></div>
                        <div class="today-client">
                            <div class="cname"><?php echo htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="cdetail">
                                <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', (string)($t['service'] ?? 'N/A'))), ENT_QUOTES, 'UTF-8'); ?>
                                &nbsp;·&nbsp;
                                <?php echo htmlspecialchars((string)($t['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($t['stylist_name'])): ?>
                                    &nbsp;·&nbsp; <?php echo htmlspecialchars((string)$t['stylist_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="today-actions">
                            <?php echo statusBadge((string)$t['status']); ?>
                            <?php if (!empty($t['phone'])): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/\D/', '', (string)$t['phone']); ?>" target="_blank" rel="noopener" class="btn-sm btn-wa" title="WhatsApp">WhatsApp</a>
                            <?php endif; ?>
                            <?php if ($t['status'] === 'paid'): ?>
                                <form method="post" style="margin:0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="mark_complete" value="1">
                                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="btn-sm btn-done" onclick="return confirm('Mark this booking as completed?')">✓ Done</button>
                                </form>
                            <?php endif; ?>
                            <a href="admin-booking-detail.php?id=<?php echo (int)$t['id']; ?>" class="btn-sm btn-view">View</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif (in_array($view, ['upcoming', 'today'], true) && empty($today)): ?>
            <div class="today-panel">
                <div class="panel-header">
                    <h2>Today's Schedule</h2>
                    <span class="today-date"><?php echo date('l, j F Y'); ?></span>
                </div>
                <div class="empty-today">No appointments scheduled for today.</div>
            </div>
            <?php endif; ?>

            <div class="slots-panel">
                <div class="panel-header">
                    <h2>Slot Availability (Today)</h2>
                    <span class="today-date"><?php echo date('d M Y'); ?></span>
                </div>
                <div class="slots-grid">
                    <?php foreach ($todaySlotOverview as $slot): ?>
                        <?php if ((int)$slot['booked'] === 0) continue; ?>
                        <div class="slot-item">
                            <div class="slot-time"><?php echo htmlspecialchars((string)$slot['time_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (!empty($slot['services'])): ?>
                                <?php foreach ($slot['services'] as $svcKey => $svcData): ?>
                                    <div class="slot-meta"><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $svcKey)), ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int)$svcData['booked']; ?>/<?php echo (int)$svcData['capacity']; ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="slot-meta">Booked: <?php echo (int)$slot['booked']; ?></div>
                            <?php endif; ?>
                            <div class="slot-stylists">
                                <?php if (!empty($slot['stylists'])): ?>
                                    <?php echo htmlspecialchars(implode(', ', array_unique((array)$slot['stylists'])), ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    No stylist assigned
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bookings table -->
            <div class="table-panel">
                <div class="table-toolbar">
                    <h2>All Bookings
                        <span id="resultsCount" style="font-weight:400;color:#aaa;font-size:0.8rem;margin-left:0.5rem;">(<?php echo count($bookings); ?>)</span>
                    </h2>

                    <div class="view-tabs">
                        <a href="?view=upcoming<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="view-tab <?php echo $view === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                        <a href="?view=today<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="view-tab <?php echo $view === 'today' ? 'active' : ''; ?>">Today</a>
                        <a href="?view=completed<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="view-tab <?php echo $view === 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="?view=cancelled<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="view-tab <?php echo $view === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                        <a href="?view=all<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="view-tab <?php echo $view === 'all' ? 'active' : ''; ?>">All</a>
                    </div>

                    <div class="toolbar-actions">
                        <a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" class="view-tab">Export CSV</a>
                    </div>

                    <div class="search-box">
                        <input id="bookingSearch" type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search name, phone..." autocomplete="off">
                        <span class="search-icon">Go</span>
                    </div>
                </div>

                <?php if (!empty($bookings)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Date &amp; Time</th>
                            <th>Service</th>
                            <th>Location</th>
                            <th>Stylist</th>
                            <th>Deposit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr class="booking-row">
                            <td data-label="#" style="color:#aaa;font-size:0.78rem;"><?php echo (int)$b['id']; ?></td>
                            <td data-label="Client">
                                <div class="client-name"><?php echo htmlspecialchars((string)$b['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="client-phone">
                                    <?php if (!empty($b['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars((string)$b['phone'], ENT_QUOTES, 'UTF-8'); ?>" style="color:#888;text-decoration:none;"><?php echo htmlspecialchars((string)$b['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Date & Time">
                                <div class="appt-date"><?php echo date('d M Y', strtotime((string)$b['appointment_date'])); ?></div>
                                <div class="appt-time"><?php echo date('g:i A', strtotime((string)$b['appointment_time'])); ?></div>
                            </td>
                            <td data-label="Service">
                                <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', (string)($b['service'] ?? 'N/A'))), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-label="Location">
                                <?php echo !empty($b['location']) ? htmlspecialchars(ucfirst((string)$b['location']), ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                            </td>
                            <td data-label="Stylist">
                                <?php echo !empty($b['stylist_name']) ? htmlspecialchars((string)$b['stylist_name'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                            </td>
                            <td data-label="Deposit" style="font-weight:600;">R<?php echo number_format((float)$b['amount'], 0); ?></td>
                            <td data-label="Status"><?php echo statusBadge((string)$b['status']); ?></td>
                            <td data-label="Actions">
                                <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                                    <a href="admin-booking-detail.php?id=<?php echo (int)$b['id']; ?>" class="btn-sm btn-view">View</a>
                                    <?php if (!empty($b['phone'])): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', (string)$b['phone']); ?>" target="_blank" rel="noopener" class="btn-sm btn-wa">WA</a>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'paid'): ?>
                                        <form method="post" style="margin:0;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                                            <input type="hidden" name="mark_complete" value="1">
                                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn-sm btn-done" onclick="return confirm('Mark booking #<?php echo (int)$b['id']; ?> as completed?')">✓ Done</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="searchNoResults" class="empty-state is-hidden">No matching bookings found.</div>
                <?php else: ?>
                    <div class="empty-state">
                        <?php if ($search !== ''): ?>
                            No bookings match "<strong><?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></strong>".
                            <a href="?view=<?php echo urlencode($view); ?>" style="color:#c9a961;">Clear search</a>
                        <?php else: ?>
                            No bookings in this view yet.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /content -->
    </div><!-- /main -->
</div><!-- /layout -->
<script>
    (function () {
        var searchInput = document.getElementById('bookingSearch');
        var rows = Array.prototype.slice.call(document.querySelectorAll('.booking-row'));
        var countEl = document.getElementById('resultsCount');
        var noResultsEl = document.getElementById('searchNoResults');
        var tableEl = document.querySelector('.table-panel table');

        if (!searchInput || rows.length === 0) {
            return;
        }

        function applyFilter() {
            var q = searchInput.value.toLowerCase().trim();
            var visible = 0;

            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                var match = q === '' || text.indexOf(q) !== -1;
                row.classList.toggle('is-hidden', !match);
                if (match) {
                    visible += 1;
                }
            });

            if (countEl) {
                countEl.textContent = '(' + visible + ')';
            }

            if (noResultsEl && tableEl) {
                var showEmpty = visible === 0;
                noResultsEl.classList.toggle('is-hidden', !showEmpty);
                tableEl.classList.toggle('is-hidden', showEmpty);
            }
        }

        searchInput.addEventListener('input', applyFilter);
        applyFilter();
    })();
</script>
</body>
</html>