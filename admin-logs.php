<?php
/**
 * Admin Logs Viewer
 * Monitor system health, errors, payments, and events
 */

require_once __DIR__ . '/admin-functions.php';
requireAdminLogin();

$mysqli = getDbConnection();

// Check if logs table exists
if (!tableExists($mysqli, 'system_logs')) {
    die('<h1>System Logs Table Not Created</h1><p>Run database-setup.php to create the system_logs table.</p>');
}

// Filters
$filterLevel = $_GET['level'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$filterDate = $_GET['date'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$whereClauses = [];
$params = [];
$types = '';

if ($filterLevel !== 'all') {
    $whereClauses[] = 'log_level = ?';
    $params[] = $filterLevel;
    $types .= 's';
}

if ($filterType !== 'all') {
    $whereClauses[] = 'log_type = ?';
    $params[] = $filterType;
    $types .= 's';
}

if ($filterDate === 'today') {
    $whereClauses[] = 'DATE(created_at) = CURDATE()';
} elseif ($filterDate === 'week') {
    $whereClauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($filterDate === 'month') {
    $whereClauses[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

if ($search !== '') {
    $whereClauses[] = '(message LIKE ? OR user_identifier LIKE ? OR context_data LIKE ?)';
    $searchPattern = '%' . $mysqli->real_escape_string($search) . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'sss';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total logs
$countSQL = "SELECT COUNT(*) as total FROM system_logs $whereSQL";
if ($params) {
    $countStmt = $mysqli->prepare($countSQL);
    if ($types) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalLogs = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $totalLogs = $mysqli->query($countSQL)->fetch_assoc()['total'];
}

// Get logs (most recent first)
$limit = 100;
$logsSQL = "SELECT * FROM system_logs $whereSQL ORDER BY created_at DESC LIMIT $limit";

if ($params) {
    $logsStmt = $mysqli->prepare($logsSQL);
    if ($types) {
        $logsStmt->bind_param($types, ...$params);
    }
    $logsStmt->execute();
    $logsResult = $logsStmt->get_result();
} else {
    $logsResult = $mysqli->query($logsSQL);
}

$logs = [];
while ($row = $logsResult->fetch_assoc()) {
    $logs[] = $row;
}

// Get stats
$statsSQL = "
    SELECT 
        log_level,
        COUNT(*) as count
    FROM system_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY log_level
";
$statsResult = $mysqli->query($statsSQL);
$stats = [];
while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['log_level']] = $row['count'];
}

$mysqli->close();

$pageTitle = 'System Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Bella Admin</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f5f5f5; color:#1a1a1a; line-height:1.6; }
        
        .header { background:#1a1a1a; color:#fff; padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .logo { font-size:1.5rem; font-weight:700; color:#d4af37; }
        .nav { display:flex; gap:1.5rem; align-items:center; }
        .nav a { color:#fff; text-decoration:none; transition:color 0.2s; }
        .nav a:hover { color:#d4af37; }
        .nav a.active { color:#d4af37; font-weight:600; }
        
        .container { max-width:1400px; margin:2rem auto; padding:0 1.5rem; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:2rem; }
        .stat-card { background:#fff; padding:1.5rem; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border-left:4px solid #ddd; }
        .stat-card.critical { border-left-color:#dc2626; }
        .stat-card.error { border-left-color:#ea580c; }
        .stat-card.warning { border-left-color:#f59e0b; }
        .stat-card.info { border-left-color:#3b82f6; }
        .stat-card.debug { border-left-color:#6b7280; }
        .stat-label { font-size:0.875rem; color:#666; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:2rem; font-weight:700; margin-top:0.25rem; }
        
        .filters { background:#fff; padding:1.5rem; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:1.5rem; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; align-items:end; }
        .filter-group { display:flex; flex-direction:column; gap:0.5rem; }
        .filter-group label { font-size:0.875rem; font-weight:600; color:#374151; }
        .filter-group select,
        .filter-group input { padding:0.5rem 0.75rem; border:1px solid #d1d5db; border-radius:6px; font-size:0.875rem; }
        .filter-actions { display:flex; gap:0.5rem; }
        .btn { padding:0.5rem 1rem; border:none; border-radius:6px; font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; display:inline-block; }
        .btn-primary { background:#d4af37; color:#1a1a1a; }
        .btn-primary:hover { background:#c19b2a; }
        .btn-secondary { background:#6b7280; color:#fff; }
        .btn-secondary:hover { background:#4b5563; }
        
        .logs-container { background:#fff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); overflow:hidden; }
        .logs-header { padding:1rem 1.5rem; background:#f9fafb; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .logs-count { font-weight:600; color:#374151; }
        
        .log-entry { padding:1rem 1.5rem; border-bottom:1px solid #e5e7eb; }
        .log-entry:hover { background:#f9fafb; }
        .log-entry:last-child { border-bottom:none; }
        
        .log-header { display:flex; justify-content:space-between; align-items:start; margin-bottom:0.5rem; }
        .log-meta { display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
        .log-badge { padding:0.25rem 0.75rem; border-radius:99px; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .log-badge.critical { background:#fecaca; color:#991b1b; }
        .log-badge.error { background:#fed7aa; color:#9a3412; }
        .log-badge.warning { background:#fef3c7; color:#92400e; }
        .log-badge.info { background:#dbeafe; color:#1e40af; }
        .log-badge.debug { background:#e5e7eb; color:#374151; }
        
        .log-type { font-size:0.75rem; color:#6b7280; font-weight:600; text-transform:uppercase; }
        .log-time { font-size:0.75rem; color:#9ca3af; }
        .log-user { font-size:0.75rem; color:#6b7280; }
        
        .log-message { color:#1f2937; margin-bottom:0.5rem; line-height:1.5; }
        .log-context { background:#f9fafb; padding:0.75rem; border-radius:4px; font-size:0.8125rem; font-family:monospace; color:#4b5563; white-space:pre-wrap; word-break:break-all; max-height:200px; overflow-y:auto; }
        
        .empty-state { text-align:center; padding:4rem 2rem; color:#9ca3af; }
        .empty-state svg { width:64px; height:64px; margin:0 auto 1rem; opacity:0.3; }
        
        @media (max-width: 768px) {
            .header { flex-direction:column; gap:1rem; }
            .filters-grid { grid-template-columns:1fr; }
            .stats-grid { grid-template-columns:repeat(2, 1fr); }
            .log-meta { flex-direction:column; align-items:start; gap:0.5rem; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">Bella Admin</div>
    <nav class="nav">
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="admin-logs.php" class="active">System Logs</a>
        <a href="admin-settings.php">Settings</a>
        <a href="admin-logout.php">Logout</a>
    </nav>
</div>

<div class="container">
    <h1 style="margin-bottom:1.5rem; font-size:1.75rem;">System Logs &amp; Monitoring</h1>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card critical">
            <div class="stat-label">Critical (24h)</div>
            <div class="stat-value"><?php echo $stats['critical'] ?? 0; ?></div>
        </div>
        <div class="stat-card error">
            <div class="stat-label">Errors (24h)</div>
            <div class="stat-value"><?php echo $stats['error'] ?? 0; ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Warnings (24h)</div>
            <div class="stat-value"><?php echo $stats['warning'] ?? 0; ?></div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Info (24h)</div>
            <div class="stat-value"><?php echo $stats['info'] ?? 0; ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <form method="get" class="filters">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Level</label>
                <select name="level">
                    <option value="all" <?php echo $filterLevel === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="critical" <?php echo $filterLevel === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    <option value="error" <?php echo $filterLevel === 'error' ? 'selected' : ''; ?>>Error</option>
                    <option value="warning" <?php echo $filterLevel === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="info" <?php echo $filterLevel === 'info' ? 'selected' : ''; ?>>Info</option>
                    <option value="debug" <?php echo $filterLevel === 'debug' ? 'selected' : ''; ?>>Debug</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Type</label>
                <select name="type">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="booking" <?php echo $filterType === 'booking' ? 'selected' : ''; ?>>Bookings</option>
                    <option value="payment" <?php echo $filterType === 'payment' ? 'selected' : ''; ?>>Payments</option>
                    <option value="email" <?php echo $filterType === 'email' ? 'selected' : ''; ?>>Emails</option>
                    <option value="auth" <?php echo $filterType === 'auth' ? 'selected' : ''; ?>>Authentication</option>
                    <option value="database" <?php echo $filterType === 'database' ? 'selected' : ''; ?>>Database</option>
                    <option value="system" <?php echo $filterType === 'system' ? 'selected' : ''; ?>>System</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date Range</label>
                <select name="date">
                    <option value="all" <?php echo $filterDate === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $filterDate === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $filterDate === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $filterDate === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search message, user, context..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="admin-logs.php" class="btn btn-secondary">Clear</a>
            </div>
        </div>
    </form>
    
    <!-- Logs -->
    <div class="logs-container">
        <div class="logs-header">
            <span class="logs-count"><?php echo number_format($totalLogs); ?> log<?php echo $totalLogs !== 1 ? 's' : ''; ?> found (showing last <?php echo min($limit, $totalLogs); ?>)</span>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>No logs found matching your filters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="log-entry">
                    <div class="log-header">
                        <div class="log-meta">
                            <span class="log-badge <?php echo htmlspecialchars($log['log_level'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($log['log_level'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="log-type"><?php echo htmlspecialchars($log['log_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($log['user_identifier']): ?>
                                <span class="log-user">👤 <?php echo htmlspecialchars($log['user_identifier'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="log-time"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></span>
                    </div>
                    
                    <div class="log-message">
                        <?php echo htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    
                    <?php if ($log['context_data']): ?>
                        <details style="margin-top:0.5rem;">
                            <summary style="cursor:pointer; color:#6b7280; font-size:0.875rem;">Show context data</summary>
                            <div class="log-context"><?php 
                                $contextData = json_decode($log['context_data'], true);
                                echo htmlspecialchars(json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); 
                            ?></div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
