<?php
// Admin functions for authentication and CRM operations
require_once __DIR__ . '/config.php';

define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Detect HTTPS directly OR behind a TLS-terminating proxy/load balancer so
        // the Secure flag is not silently dropped on Xneelo's edge.
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

/**
 * ===== CSRF protection for admin forms =====
 * Generate a per-session token on GET, embed it as a hidden field in every admin
 * POST form, and verify it (constant-time) before processing any state change.
 */
function csrfToken(): string
{
    startAdminSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token on a POST. On failure, reject with 403 and stop —
 * a forged off-site form cannot carry the session token.
 */
function csrfVerify(): void
{
    startAdminSession();
    $sent = (string)($_POST['csrf_token'] ?? '');
    $known = (string)($_SESSION['csrf_token'] ?? '');
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
        http_response_code(403);
        die('Security check failed. Please go back, refresh the page, and try again.');
    }
}

/**
 * Require the logged-in admin to hold one of the given roles, else 403.
 * Centralizes role gating for destructive/management pages.
 */
function requireAdminRole(array $roles): array
{
    $admin = getAdminUser();
    if (!$admin || !in_array((string)($admin['role'] ?? ''), $roles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to perform this action.');
    }
    return $admin;
}

function isAdminLoggedIn(): bool
{
    startAdminSession();

    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ADMIN_SESSION_TIMEOUT) {
        adminLogout();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        http_response_code(302);
        header('Location: admin-login.php');
        exit;
    }
}

function getAdminUser(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, first_name, last_name, role FROM admin_users WHERE id = ? AND is_active = 1');
    $adminId = $_SESSION['admin_id'];
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    
    return $user ?: null;
}

// ===== Brute-force protection (server-side, DB-backed) =====
// Session-based counters were bypassable simply by discarding the session
// cookie. Failures are now persisted and rate-limited per source-IP OR username.
const ADMIN_LOGIN_MAX_ATTEMPTS = 10;
const ADMIN_LOGIN_WINDOW_SECONDS = 900; // 15 minutes

function loginClientIp(): string
{
    // REMOTE_ADDR only — never trust client-supplied X-Forwarded-* for a security control.
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function ensureLoginAttemptsTable(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip           VARCHAR(45) NOT NULL,
            username     VARCHAR(190) NOT NULL,
            attempted_at DATETIME NOT NULL,
            INDEX idx_ala_ip (ip),
            INDEX idx_ala_username (username),
            INDEX idx_ala_time (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function countRecentLoginFailures(mysqli $mysqli, string $ip, string $username): int
{
    $stmt = $mysqli->prepare(
        'SELECT COUNT(*) AS c FROM admin_login_attempts
         WHERE attempted_at > (NOW() - INTERVAL ? SECOND) AND (ip = ? OR username = ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $window = ADMIN_LOGIN_WINDOW_SECONDS;
    $stmt->bind_param('iss', $window, $ip, $username);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

function recordLoginFailure(mysqli $mysqli, string $ip, string $username): void
{
    $stmt = $mysqli->prepare('INSERT INTO admin_login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $ip, $username);
    $stmt->execute();
    $stmt->close();
}

function clearLoginFailures(mysqli $mysqli, string $ip, string $username): void
{
    $stmt = $mysqli->prepare('DELETE FROM admin_login_attempts WHERE ip = ? OR username = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ss', $ip, $username);
    $stmt->execute();
    $stmt->close();
}

/**
 * Authoritative lockout check used by the login page for the user-facing message.
 * The real enforcement also happens inside adminLogin().
 */
function adminLoginLockedOut(string $username): bool
{
    $mysqli = getDbConnection();
    ensureLoginAttemptsTable($mysqli);
    $locked = countRecentLoginFailures($mysqli, loginClientIp(), $username) >= ADMIN_LOGIN_MAX_ATTEMPTS;
    $mysqli->close();
    return $locked;
}

function adminLogin(string $username, string $password): bool
{
    startAdminSession();

    $mysqli = getDbConnection();
    ensureLoginAttemptsTable($mysqli);
    $ip = loginClientIp();

    // Hard stop if this IP or username is already over the limit in the window.
    if (countRecentLoginFailures($mysqli, $ip, $username) >= ADMIN_LOGIN_MAX_ATTEMPTS) {
        $mysqli->close();
        return false;
    }

    $stmt = $mysqli->prepare('SELECT id, username, email, password_hash, first_name, last_name, role FROM admin_users WHERE username = ? AND is_active = 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Always run a verify (dummy hash when the user is unknown) to keep response
    // timing constant and avoid leaking which usernames exist.
    $dummyHash = '$2y$10$usesomesillystringforsalttocreateaconstanttimehashx9y';
    $hashToCheck = $user['password_hash'] ?? $dummyHash;
    $passwordOk = password_verify($password, $hashToCheck);

    if (!$user || !$passwordOk) {
        recordLoginFailure($mysqli, $ip, $username);
        $mysqli->close();
        return false;
    }

    clearLoginFailures($mysqli, $ip, $username);
    $mysqli->close();

    // Successful login — regenerate session ID to prevent session fixation.
    session_regenerate_id(true);
    unset($_SESSION['login_attempts']); // retire any legacy session-based counter
    $_SESSION['admin_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    return true;
}

function adminLogout(): void
{
    startAdminSession();
    session_destroy();
}

function changeAdminPassword(int $adminId, string $currentPassword, string $newPassword): array
{
    $mysqli = getDbConnection();
    
    // Get current password hash
    $stmt = $mysqli->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $mysqli->close();
        return ['success' => false, 'message' => 'Admin user not found.'];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        $mysqli->close();
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }
    
    // Validate new password
    if (strlen($newPassword) < 8) {
        $mysqli->close();
        return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
    }
    
    // Hash and update new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
    $stmt->bind_param('si', $newPasswordHash, $adminId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
    return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
}

function countActiveAdminUsers(): int
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM admin_users WHERE is_active = 1');
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $mysqli->close();

    return $count;
}

function getAdminUsers(): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT id, username, email, first_name, last_name, role, is_active, created_at FROM admin_users ORDER BY created_at DESC');
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
    $mysqli->close();

    return $users;
}

function createAdminUser(array $payload, string $creatorRole = ''): array
{
    $firstName = trim((string)($payload['first_name'] ?? ''));
    $lastName = trim((string)($payload['last_name'] ?? ''));
    $username = trim((string)($payload['username'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $role = trim((string)($payload['role'] ?? 'staff'));
    $password = (string)($payload['password'] ?? '');

    if ($firstName === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'All user fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email address is not valid.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }

    $allowedRoles = ['admin', 'manager', 'staff'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'staff';
    }

    // Defense in depth: only an admin may grant the admin role. Prevents a lower
    // privileged caller from escalating by creating an admin account.
    if ($role === 'admin' && $creatorRole !== 'admin') {
        return ['success' => false, 'message' => 'You do not have permission to create an admin user.'];
    }

    $activeUsers = countActiveAdminUsers();
    if ($activeUsers >= getMaxAdminUsers()) {
        return ['success' => false, 'message' => 'User limit reached. Maximum ' . getMaxAdminUsers() . ' users allowed.'];
    }

    $mysqli = getDbConnection();
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('ssssss', $username, $email, $passwordHash, $firstName, $lastName, $role);
    $success = $stmt->execute();

    if (!$success) {
        $errorCode = $stmt->errno;
        $stmt->close();
        $mysqli->close();

        if ($errorCode === 1062) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }

        return ['success' => false, 'message' => 'Failed to create user.'];
    }

    $stmt->close();
    $mysqli->close();
    return ['success' => true, 'message' => 'User created successfully.'];
}

function getAllBookings(string $status = '', string $sortBy = 'appointment_date'): array
{
    $mysqli = getDbConnection();
    
    $query = 'SELECT b.*, bs.stylist_name AS stylist_name FROM salon_bookings b LEFT JOIN booking_stylists bs ON bs.stylist_key = b.preferred_stylist';
    $params = [];
    $types = '';
    
    if ($status !== '') {
        $query .= ' WHERE b.status = ?';
        $params[] = $status;
        $types .= 's';
    }
    
    $allowedSort = ['appointment_date', 'created_at', 'amount', 'status', 'name'];
    if (in_array($sortBy, $allowedSort, true)) {
        $query .= ' ORDER BY b.' . $sortBy . ' DESC';
    } else {
        $query .= ' ORDER BY b.appointment_date DESC';
    }
    
    $stmt = $mysqli->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $bookings;
}

function getBookingById(int $bookingId): ?array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT b.*, bs.stylist_name AS stylist_name FROM salon_bookings b LEFT JOIN booking_stylists bs ON bs.stylist_key = b.preferred_stylist WHERE b.id = ?');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    
    return $booking ?: null;
}

function updateBookingStatus(int $bookingId, string $newStatus): bool
{
    $validStatuses = ['pending', 'pending_cash', 'confirmed', 'paid', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        return false;
    }
    
    // Get booking details before update
    $booking = getBookingById($bookingId);
    if (!$booking) {
        return false;
    }
    
    $oldStatus = $booking['status'];
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET status = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $newStatus, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS && $newStatus !== $oldStatus) {
        require_once __DIR__ . '/mail-functions.php';
        sendStatusUpdateEmail($booking, $newStatus);
    }
    
    return $success;
}

function rescheduleBooking(int $bookingId, string $newDate, string $newTime): bool
{
    $mysqli = getDbConnection();

    $booking = getBookingById($bookingId);
    if (!$booking) {
        $mysqli->close();
        return false;
    }

    // Check total slot capacity for this service
    $bookingService = (string)($booking['service'] ?? '');
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND service = ? AND id != ? AND status = "paid"');
    $stmt->bind_param('sssi', $newDate, $newTime, $bookingService, $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ((int)$row['count'] >= getServiceCapacity($bookingService)) {
        $mysqli->close();
        return false; // Slot not available
    }

    // Check stylist-specific conflict if stylist selected
    $preferredStylist = trim((string)($booking['preferred_stylist'] ?? ''));
    if ($preferredStylist !== '' && $preferredStylist !== 'no-preference') {
        $stylistStmt = $mysqli->prepare('SELECT COUNT(*) AS count FROM salon_bookings WHERE appointment_date = ? AND appointment_time = ? AND preferred_stylist = ? AND id != ? AND status = "paid"');
        $stylistStmt->bind_param('sssi', $newDate, $newTime, $preferredStylist, $bookingId);
        $stylistStmt->execute();
        $stylistResult = $stylistStmt->get_result();
        $stylistRow = $stylistResult->fetch_assoc();
        $stylistStmt->close();

        if ((int)$stylistRow['count'] > 0) {
            $mysqli->close();
            return false;
        }
    }

    // Get old booking details before update
    $oldDate = $booking['appointment_date'];
    $oldTime = $booking['appointment_time'];
    
    // Update booking
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET appointment_date = ?, appointment_time = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssi', $newDate, $newTime, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS) {
        require_once __DIR__ . '/mail-functions.php';
        sendRescheduleEmail($booking, $oldDate, $oldTime);
    }
    
    return $success;
}

function cancelBooking(int $bookingId, string $reason = ''): bool
{
    // Get booking details before cancelling
    $booking = getBookingById($bookingId);
    if (!$booking) {
        return false;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET status = "cancelled", cancellation_reason = ?, status_updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $reason, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    if ($success && SEND_CLIENT_EMAILS) {
        require_once __DIR__ . '/mail-functions.php';
        sendCancellationEmail($booking, $reason);
    }
    
    return $success;
}

function assignStylist(int $bookingId, string $stylistKey): bool
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('UPDATE salon_bookings SET preferred_stylist = ? WHERE id = ?');
    $stmt->bind_param('si', $stylistKey, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();

    return $success;
}

function addBookingNote(int $bookingId, string $note): bool
{
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        return false;
    }
    
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('INSERT INTO booking_notes (booking_id, admin_id, note) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $bookingId, $adminId, $note);
    $success = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    
    return $success;
}

function getBookingNotes(int $bookingId): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('SELECT n.*, a.first_name, a.last_name FROM booking_notes n LEFT JOIN admin_users a ON n.admin_id = a.id WHERE n.booking_id = ? ORDER BY n.created_at DESC');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $notes;
}

function getAllStylists(): array
{
    $mysqli = getDbConnection();
    // Single source of truth: booking_stylists. (Legacy `stylists` table retired.)
    $stmt = $mysqli->prepare('SELECT id, stylist_key, stylist_name AS name, NULL AS specialization FROM booking_stylists WHERE is_active = 1 ORDER BY sort_order ASC, stylist_name ASC');
    $stmt->execute();
    $result = $stmt->get_result();
    $stylists = [];
    
    while ($row = $result->fetch_assoc()) {
        $stylists[] = $row;
    }
    
    $stmt->close();
    $mysqli->close();
    
    return $stylists;
}

function getBookingStats(): array
{
    $mysqli = getDbConnection();
    $stats = [];

    // Total bookings ever
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings');
    $stmt->execute();
    $stats['total_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Today's appointments (any status)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE DATE(appointment_date) = CURDATE()');
    $stmt->execute();
    $stats['today_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Upcoming paid bookings (future dates, status = paid)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status = "paid" AND appointment_date >= CURDATE()');
    $stmt->execute();
    $stats['upcoming_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Completed bookings
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status = "completed"');
    $stmt->execute();
    $stats['completed_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Total deposits collected (paid + completed)
    $stmt = $mysqli->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM salon_bookings WHERE status IN ("paid", "completed")');
    $stmt->execute();
    $stats['total_revenue'] = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // This week's paid bookings (Mon–Sun)
    $stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM salon_bookings WHERE status IN ("paid","completed") AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)');
    $stmt->execute();
    $stats['this_week_bookings'] = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $mysqli->close();
    return $stats;
}

function getTodaysBookings(): array
{
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare(
        'SELECT b.*, bs.stylist_name AS stylist_name FROM salon_bookings b
         LEFT JOIN booking_stylists bs ON bs.stylist_key = b.preferred_stylist
         WHERE DATE(b.appointment_date) = CURDATE()
         ORDER BY b.appointment_time ASC'
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $mysqli->close();
    return $rows;
}

/**
 * ===== Admin block-slot management (Phase 5) =====
 * Lets staff block a stylist/slot/whole day so the availability engine treats it as
 * unavailable (e.g. walk-ins, leave, breaks). The engine reads booking_slot_blocks.
 */
function createSlotBlock(string $location, ?string $stylist, string $date, ?string $time, string $reason, string $createdBy): array
{
    $location = trim($location);
    if ($location === '' || $date === '') {
        return ['success' => false, 'message' => 'Location and date are required.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['success' => false, 'message' => 'Invalid date.'];
    }
    $time = ($time === null || trim($time) === '') ? null : trim($time);
    if ($time !== null && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return ['success' => false, 'message' => 'Invalid time.'];
    }
    $stylist = ($stylist === null || trim($stylist) === '') ? null : trim($stylist);
    $reason = substr(trim($reason), 0, 255);

    $mysqli = getDbConnection();
    ensureAvailabilitySupportTables($mysqli);
    $stmt = $mysqli->prepare('INSERT INTO booking_slot_blocks (location, stylist, block_date, block_time, reason, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        $mysqli->close();
        return ['success' => false, 'message' => 'Could not prepare block.'];
    }
    $stmt->bind_param('ssssss', $location, $stylist, $date, $time, $reason, $createdBy);
    $ok = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    return $ok ? ['success' => true, 'message' => 'Block added.'] : ['success' => false, 'message' => 'Could not add block.'];
}

function deleteSlotBlock(int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $mysqli = getDbConnection();
    $stmt = $mysqli->prepare('DELETE FROM booking_slot_blocks WHERE id = ?');
    if (!$stmt) {
        $mysqli->close();
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    $mysqli->close();
    return $ok;
}

function getUpcomingSlotBlocks(): array
{
    $mysqli = getDbConnection();
    if (!tableExists($mysqli, 'booking_slot_blocks')) {
        $mysqli->close();
        return [];
    }
    $result = $mysqli->query("SELECT * FROM booking_slot_blocks WHERE block_date >= CURDATE() ORDER BY block_date ASC, block_time ASC, id ASC");
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $mysqli->close();
    return $rows;
}

function getSlotOverviewForDate(string $date): array
{
    $slotMap = [
        '06:45:00' => '06:45 AM',
        '07:00:00' => '07:00 AM',
        '07:30:00' => '07:30 AM',
        '08:00:00' => '08:00 AM',
        '09:00:00' => '09:00 AM',
        '09:15:00' => '09:15 AM',
        '10:00:00' => '10:00 AM',
        '10:30:00' => '10:30 AM',
        '11:00:00' => '11:00 AM',
        '11:30:00' => '11:30 AM',
        '11:45:00' => '11:45 AM',
        '12:00:00' => '12:00 PM',
        '13:00:00' => '01:00 PM',
        '14:00:00' => '02:00 PM',
        '14:15:00' => '02:15 PM',
        '14:30:00' => '02:30 PM',
        '15:00:00' => '03:00 PM',
        '15:30:00' => '03:30 PM',
        '16:00:00' => '04:00 PM',
        '16:30:00' => '04:30 PM',
        '16:45:00' => '04:45 PM',
        '17:00:00' => '05:00 PM',
    ];

    $overview = [];
    foreach ($slotMap as $dbTime => $label) {
        $overview[$dbTime] = [
            'time_db'  => $dbTime,
            'time_label' => $label,
            'booked'   => 0,
            'open'     => 0,
            'stylists' => [],
            'services' => [],
        ];
    }

    $mysqli = getDbConnection();
    $statusPaid = 'paid';
    $statusPendingCash = 'pending_cash';
    $stmt = $mysqli->prepare('SELECT appointment_time, service, preferred_stylist FROM salon_bookings WHERE appointment_date = ? AND status IN (?, ?) ORDER BY appointment_time ASC');
    $stmt->bind_param('sss', $date, $statusPaid, $statusPendingCash);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $time = (string)$row['appointment_time'];
        if (!isset($overview[$time])) {
            continue;
        }

        $svc = (string)($row['service'] ?? '');
        if ($svc !== '' && !isset($overview[$time]['services'][$svc])) {
            $overview[$time]['services'][$svc] = [
                'booked'   => 0,
                'capacity' => getServiceCapacity($svc),
                'stylists' => [],
            ];
        }

        $overview[$time]['booked']++;
        if ($svc !== '') {
            $overview[$time]['services'][$svc]['booked']++;
        }

        $stylist = trim((string)($row['preferred_stylist'] ?? ''));
        if ($stylist !== '') {
            $overview[$time]['stylists'][] = $stylist;
            if ($svc !== '') {
                $overview[$time]['services'][$svc]['stylists'][] = $stylist;
            }
        }
    }

    // Compute open slots as sum of remaining capacity per service in this time block
    foreach ($overview as $dbTime => &$slot) {
        $openCount = 0;
        foreach ($slot['services'] as $svcData) {
            $openCount += max(0, $svcData['capacity'] - $svcData['booked']);
        }
        $slot['open'] = $openCount;
    }
    unset($slot);

    $stmt->close();
    $mysqli->close();

    return array_values($overview);
}
