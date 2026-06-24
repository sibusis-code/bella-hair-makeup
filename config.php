<?php
// Database and PayFast configuration.
// Update these values to match your xneelo + PayFast account.

function loadDotEnv(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $separatorPos));
        $value = trim(substr($trimmed, $separatorPos + 1));

        if ($name === '') {
            continue;
        }

        if (
            (strlen($value) >= 2)
            && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === '\'' && substr($value, -1) === '\'')
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load secrets. Prefer a .env stored OUTSIDE the web root (one directory up, e.g.
// /home/<account>/.env above public_html) so a web-server misconfiguration can
// never serve it as plain text. Fall back to a local .env for development.
// loadDotEnv only sets variables that are currently unset, so:
//   1. real host/control-panel environment variables always win, then
//   2. the out-of-root .env, then
//   3. the local .env (dev only).
loadDotEnv(dirname(__DIR__) . '/.env');
loadDotEnv(__DIR__ . '/.env');

function envOrDefault(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string)$value;
}

function envToBool(string $name, bool $default): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function envToMoney(string $name, float $default): float
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    $amount = (float)$value;
    return $amount > 0 ? $amount : $default;
}

define('DB_HOST', envOrDefault('DB_HOST', ''));
define('DB_NAME', envOrDefault('DB_NAME', ''));
define('DB_USER', envOrDefault('DB_USER', ''));
// SECURITY: Database password MUST be set via environment variable
// Never commit secrets to code. Set on production server via xneelo control panel.
define('DB_PASS', envOrDefault('DB_PASS', ''));
define('DB_CONNECT_TIMEOUT_SECONDS', max(1, (int)envOrDefault('DB_CONNECT_TIMEOUT_SECONDS', '5')));
define('ALLOW_DB_OFFLINE_MODE', envToBool('ALLOW_DB_OFFLINE_MODE', true));
define('DB_OFFLINE_GRACE_SECONDS', max(0, (int)envOrDefault('DB_OFFLINE_GRACE_SECONDS', '60')));

define('PAYFAST_MERCHANT_ID', envOrDefault('PAYFAST_MERCHANT_ID', ''));
define('PAYFAST_MERCHANT_KEY', envOrDefault('PAYFAST_MERCHANT_KEY', ''));

// Keep true while testing in sandbox. Set to false for live payments.
define('PAYFAST_SANDBOX', envToBool('PAYFAST_SANDBOX', true));

// Optional: set your PayFast passphrase if configured in PayFast dashboard.
define('PAYFAST_PASSPHRASE', envOrDefault('PAYFAST_PASSPHRASE', ''));

// Optional override. Leave blank to auto-detect host and subfolder path
// (so canonical/og/PayFast URLs match whatever domain the app is served from).
define('SITE_URL', envOrDefault('SITE_URL', ''));

// Optional explicit notify URL for ITN (use a public HTTPS URL).
define('PAYFAST_NOTIFY_URL_OVERRIDE', envOrDefault('PAYFAST_NOTIFY_URL_OVERRIDE', 'https://bellahairandmakeup.co.za/itn.php'));

// Business rules
define('MAX_ADMIN_USERS', max(1, (int)envOrDefault('MAX_ADMIN_USERS', '3')));
define('MAX_STYLISTS_PER_SLOT', max(1, (int)envOrDefault('MAX_STYLISTS_PER_SLOT', '2')));
define('BOOKING_DEPOSIT_PERCENTAGE', 0.50); // Always 50%

// Fallback deposit amount only for unknown service mappings.
define('BOOKING_DEPOSIT_AMOUNT', envToMoney('BOOKING_DEPOSIT_AMOUNT', 500.00));

// Email configuration
define('EMAIL_FROM_NAME', envOrDefault('EMAIL_FROM_NAME', 'Bella Hair & Makeup'));
define('EMAIL_FROM_ADDRESS', envOrDefault('EMAIL_FROM_ADDRESS', 'bookings@bellahairandmakeup.co.za'));
define('EMAIL_ADMIN_ADDRESS', envOrDefault('EMAIL_ADMIN_ADDRESS', 'admin@bellahairandmakeup.co.za'));
define('EMAIL_USE_SMTP', envToBool('EMAIL_USE_SMTP', false));
define('EMAIL_SMTP_HOST', envOrDefault('EMAIL_SMTP_HOST', 'smtp.gmail.com'));
define('EMAIL_SMTP_PORT', (int)envOrDefault('EMAIL_SMTP_PORT', '587'));
define('EMAIL_SMTP_USER', envOrDefault('EMAIL_SMTP_USER', ''));
define('EMAIL_SMTP_PASS', envOrDefault('EMAIL_SMTP_PASS', ''));
define('SEND_CLIENT_EMAILS', envToBool('SEND_CLIENT_EMAILS', false));
define('SEND_ADMIN_EMAILS', envToBool('SEND_ADMIN_EMAILS', false));

define('APP_TIMEZONE', envOrDefault('APP_TIMEZONE', 'Africa/Johannesburg'));
date_default_timezone_set(APP_TIMEZONE);

// Google Analytics 4 — set GA4_MEASUREMENT_ID in .env (looks like "G-XXXXXXXXXX").
// Leave blank to disable analytics entirely (no tag is emitted).
define('GA4_MEASUREMENT_ID', envOrDefault('GA4_MEASUREMENT_ID', ''));

// Google Maps — accurate per-kilometre mobile travel pricing (owner spec 2026-06-18).
//   * BROWSER key: powers address autocomplete in book.php (exposed in the page, so
//     restrict it by HTTP referrer to the live domain in the Google Cloud console).
//   * SERVER key: powers the server-side Distance Matrix call (kept secret; restrict
//     it by server IP). Distance is measured by driving route from the Midrand studio.
// Leave BOTH blank to disable per-km pricing — the system then falls back to the
// existing zone-based travel surcharge (getTravelSurcharge), so the site keeps working.
define('GOOGLE_MAPS_BROWSER_KEY', envOrDefault('GOOGLE_MAPS_BROWSER_KEY', ''));
define('GOOGLE_MAPS_SERVER_KEY', envOrDefault('GOOGLE_MAPS_SERVER_KEY', ''));
define('TRAVEL_RATE_PER_KM', (float)envOrDefault('TRAVEL_RATE_PER_KM', '10'));
define('TRAVEL_ROUND_TRIP', envToBool('TRAVEL_ROUND_TRIP', true));
define('TRAVEL_ORIGIN_ADDRESS', envOrDefault('TRAVEL_ORIGIN_ADDRESS', '12 Demo Street, Sandton, Johannesburg, 2196, South Africa'));
// Optional safety cap: refuse auto-quotes beyond this many km (0 = no cap).
define('TRAVEL_MAX_KM', (float)envOrDefault('TRAVEL_MAX_KM', '0'));

/**
 * Returns the GA4 <script> tag when a valid measurement ID is configured, else ''.
 * Echo this in the <head> of public pages.
 */
function ga4Snippet(): string
{
    $id = trim((string)GA4_MEASUREMENT_ID);
    if ($id === '' || !preg_match('/^G-[A-Z0-9]+$/i', $id)) {
        return '';
    }
    $idJs = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$idJs}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$idJs}');
</script>
HTML;
}

// =========================================================================
// MAINTENANCE / "COMING SOON" MODE
// While testing the live site, the public sees a maintenance page; the team
// bypasses it with a secret preview link (?preview=KEY → cookie). Toggle ON by
// creating a `.maintenance` file in the web root, or MAINTENANCE_MODE=true.
// =========================================================================
define('MAINTENANCE_MODE', envToBool('MAINTENANCE_MODE', false));
define('MAINTENANCE_BYPASS_KEY', envOrDefault('MAINTENANCE_BYPASS_KEY', ''));

function maintenanceIsEnabled(): bool
{
    return MAINTENANCE_MODE || is_file(__DIR__ . '/.maintenance');
}

/**
 * True if this browser carries the valid preview cookie. Only meaningful when a
 * bypass key is configured (otherwise nobody could bypass).
 */
function maintenanceBypassActive(): bool
{
    $key = trim((string)MAINTENANCE_BYPASS_KEY);
    if ($key === '') {
        return false;
    }
    $cookie = (string)($_COOKIE['bella_preview'] ?? '');
    return $cookie !== '' && hash_equals(hash('sha256', $key), $cookie);
}

/**
 * Pages that must NEVER be blocked: the admin CRM + login (admin-*.php), the
 * PayFast callback (itn.php), and the maintenance page itself.
 */
function maintenanceScriptExempt(): bool
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    if (strncmp($script, 'admin-', 6) === 0) {
        return true;
    }
    return in_array($script, ['itn.php', 'maintenance.php'], true);
}

/**
 * Gate the request. Called once at the end of config.php (before any page
 * output, so setcookie()/headers are clean). Public visitors get a 503
 * maintenance page; the team bypasses via ?preview=KEY (or the cookie it sets).
 */
function enforceMaintenanceMode(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    // Handle the preview link first (works even when maintenance is currently off,
    // so the team can arm the cookie before going dark).
    if (isset($_GET['preview'])) {
        $preview = (string)$_GET['preview'];
        $key = trim((string)MAINTENANCE_BYPASS_KEY);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $cookieOpts = [
            'expires' => time() + 7 * 24 * 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($preview === 'off') {
            setcookie('bella_preview', '', ['expires' => time() - 3600, 'path' => '/']);
        } elseif ($key !== '' && hash_equals($key, $preview)) {
            setcookie('bella_preview', hash('sha256', $key), $cookieOpts);
        }
        // Redirect to the same path without the query string (clean URL).
        $path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
        header('Location: ' . ($path !== false ? $path : '/'));
        exit;
    }

    if (!maintenanceIsEnabled() || maintenanceScriptExempt() || maintenanceBypassActive()) {
        return;
    }

    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    include __DIR__ . '/maintenance.php';
    exit;
}

function getDbConnection(): mysqli
{
    // The mysqli extension is mandatory for pages that require the database
    // (admin, booking processing, ITN). Fail clearly instead of a cryptic
    // "undefined function" fatal if it isn't enabled.
    if (!function_exists('mysqli_init')) {
        error_log('[db] mysqli extension is not loaded.');
        http_response_code(503);
        die('Database driver unavailable. The mysqli PHP extension is not enabled on this server.');
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    // Prevent long hangs when remote DB host is unreachable from local dev.
    @ini_set('default_socket_timeout', (string)DB_CONNECT_TIMEOUT_SECONDS);

    $hostRaw = trim((string)DB_HOST);
    if ($hostRaw === '') {
        http_response_code(500);
        die('Database connection failed. DB host is not configured.');
    }

    $dbHost = $hostRaw;
    $dbPort = 3306;
    if (strpos($hostRaw, ':') !== false && substr_count($hostRaw, ':') === 1) {
        [$hostPart, $portPart] = explode(':', $hostRaw, 2);
        if ($hostPart !== '' && ctype_digit($portPart)) {
            $dbHost = $hostPart;
            $dbPort = (int)$portPart;
        }
    }

    // Quick network preflight so unreachable DB hosts fail in seconds, not at PHP max_execution_time.
    $probeErrNo = 0;
    $probeErrStr = '';
    $probe = @fsockopen($dbHost, $dbPort, $probeErrNo, $probeErrStr, DB_CONNECT_TIMEOUT_SECONDS);
    if ($probe === false) {
        error_log('[db] TCP preflight failed: ' . $dbHost . ':' . $dbPort . ' errno=' . (string)$probeErrNo . ' msg=' . $probeErrStr);
        http_response_code(500);
        die('Database connection failed. Cannot reach database server from this environment.');
    }

    // MySQL should send a greeting packet immediately after TCP accept.
    stream_set_timeout($probe, DB_CONNECT_TIMEOUT_SECONDS);
    $greeting = @fread($probe, 4);
    $meta = stream_get_meta_data($probe);
    fclose($probe);

    if (($meta['timed_out'] ?? false) || $greeting === false || strlen((string)$greeting) === 0) {
        error_log('[db] MySQL greeting preflight failed for ' . $dbHost . ':' . $dbPort . ' (TCP open but no greeting).');
        http_response_code(500);
        die('Database connection failed. Server is reachable, but MySQL handshake was not received.');
    }

    try {
        $mysqli = mysqli_init();
        if (!$mysqli) {
            throw new RuntimeException('Unable to initialize mysqli client.');
        }

        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_CONNECT_TIMEOUT_SECONDS);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, DB_CONNECT_TIMEOUT_SECONDS);
        }

        $connected = @$mysqli->real_connect($dbHost, DB_USER, DB_PASS, DB_NAME, $dbPort);
        if ($connected !== true || $mysqli->connect_errno) {
            throw new RuntimeException('Connect failed: ' . $mysqli->connect_error);
        }
    } catch (Throwable $e) {
        error_log('[db] Connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Database connection failed. Please verify DB host/network and credentials.');
    }

    $mysqli->set_charset('utf8mb4');
    // Pin DB session timezone to SAST so NOW()/DATE_ADD (hold expiry, availability)
    // stays consistent with PHP's Africa/Johannesburg regardless of server default.
    @$mysqli->query("SET time_zone = '+02:00'");
    return $mysqli;
}

function tryGetDbConnection(): ?mysqli
{
    // If the mysqli extension isn't loaded at all, degrade gracefully: callers
    // (public pages) fall back to the default catalog and still render.
    if (!function_exists('mysqli_init')) {
        return null;
    }

    if (ALLOW_DB_OFFLINE_MODE && DB_OFFLINE_GRACE_SECONDS > 0 && isDbProbeSuppressed()) {
        return null;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    @ini_set('default_socket_timeout', (string)DB_CONNECT_TIMEOUT_SECONDS);

    $hostRaw = trim((string)DB_HOST);
    if ($hostRaw === '') {
        return null;
    }

    $dbHost = $hostRaw;
    $dbPort = 3306;
    if (strpos($hostRaw, ':') !== false && substr_count($hostRaw, ':') === 1) {
        [$hostPart, $portPart] = explode(':', $hostRaw, 2);
        if ($hostPart !== '' && ctype_digit($portPart)) {
            $dbHost = $hostPart;
            $dbPort = (int)$portPart;
        }
    }

    $probeErrNo = 0;
    $probeErrStr = '';
    $probe = @fsockopen($dbHost, $dbPort, $probeErrNo, $probeErrStr, DB_CONNECT_TIMEOUT_SECONDS);
    if ($probe === false) {
        markDbOffline();
        return null;
    }

    // Read a larger greeting chunk to confirm MySQL auth handshake is responsive, not just TCP open.
    stream_set_timeout($probe, DB_CONNECT_TIMEOUT_SECONDS);
    $readStart = microtime(true);
    $greeting = @fread($probe, 64);
    $readElapsed = microtime(true) - $readStart;
    $meta = stream_get_meta_data($probe);
    fclose($probe);

    if (($meta['timed_out'] ?? false) || $greeting === false || strlen((string)$greeting) < 4) {
        markDbOffline();
        return null;
    }

    // If greeting was slow (close to timeout), treat as degraded and skip real_connect.
    // real_connect on Windows ignores MYSQLI_OPT_CONNECT_TIMEOUT for partially-open sockets.
    if ($readElapsed >= (DB_CONNECT_TIMEOUT_SECONDS * 0.8)) {
        markDbOffline();
        return null;
    }

    // Enforce a hard socket timeout so real_connect cannot hang beyond our threshold.
    @ini_set('default_socket_timeout', (string)DB_CONNECT_TIMEOUT_SECONDS);

    try {
        $mysqli = mysqli_init();
        if (!$mysqli) {
            markDbOffline();
            return null;
        }

        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_CONNECT_TIMEOUT_SECONDS);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, DB_CONNECT_TIMEOUT_SECONDS);
        }

        $connectStart = microtime(true);
        $connected = @$mysqli->real_connect($dbHost, DB_USER, DB_PASS, DB_NAME, $dbPort);
        $connectElapsed = microtime(true) - $connectStart;

        if ($connected !== true || $mysqli->connect_errno) {
            markDbOffline();
            return null;
        }

        // If connect succeeded but was suspiciously slow, still mark offline for future requests.
        if ($connectElapsed >= (DB_CONNECT_TIMEOUT_SECONDS * 0.8)) {
            // Connected but slow — use this connection but prime the breaker for next request.
            markDbOffline();
        } else {
            clearDbOfflineMark();
        }

        $mysqli->set_charset('utf8mb4');
        // Pin DB session timezone to SAST (see getDbConnection note).
        @$mysqli->query("SET time_zone = '+02:00'");
        return $mysqli;
    } catch (Throwable $e) {
        markDbOffline();
        return null;
    }
}

function getDbOfflineMarkerPath(): string
{
    $hostKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string)DB_HOST);
    if ($hostKey === '' || $hostKey === null) {
        $hostKey = 'default';
    }

    $fileName = 'db_offline_' . $hostKey . '.marker';
    $runtimeDir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';

    if (is_dir($runtimeDir) || @mkdir($runtimeDir, 0775, true)) {
        // Defense in depth: drop a deny-all .htaccess so the runtime dir is never
        // web-served even if the root .htaccess is not honored.
        $guard = $runtimeDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        if (is_writable($runtimeDir)) {
            return $runtimeDir . DIRECTORY_SEPARATOR . $fileName;
        }
    }

    // Fallback for hosts where project directory is read-only.
    return rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
}

function isDbProbeSuppressed(): bool
{
    $markerPath = getDbOfflineMarkerPath();
    if (!is_file($markerPath)) {
        return false;
    }

    $content = @file_get_contents($markerPath);
    $lastFailTs = is_string($content) && ctype_digit(trim($content))
        ? (int)trim($content)
        : (int)@filemtime($markerPath);

    if ($lastFailTs <= 0) {
        return false;
    }

    return (time() - $lastFailTs) < DB_OFFLINE_GRACE_SECONDS;
}

function markDbOffline(): void
{
    if (!ALLOW_DB_OFFLINE_MODE || DB_OFFLINE_GRACE_SECONDS <= 0) {
        return;
    }

    $markerPath = getDbOfflineMarkerPath();
    @file_put_contents($markerPath, (string)time(), LOCK_EX);
}

function clearDbOfflineMark(): void
{
    $markerPath = getDbOfflineMarkerPath();
    if (is_file($markerPath)) {
        @unlink($markerPath);
    }
}

function getPayFastProcessUrl(): string
{
    return PAYFAST_SANDBOX
        ? 'https://sandbox.payfast.co.za/eng/process'
        : 'https://www.payfast.co.za/eng/process';
}

function getPayFastValidateUrl(): string
{
    return PAYFAST_SANDBOX
        ? 'https://sandbox.payfast.co.za/eng/query/validate'
        : 'https://www.payfast.co.za/eng/query/validate';
}

function getSiteBaseUrl(): string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'https://bellahairandmakeup.co.za';
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'bellahairandmakeup.co.za';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $basePath = str_replace('\\', '/', dirname($scriptName));

    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . rtrim($basePath, '/');
}

function getPayFastReturnUrl(): string
{
    return getSiteBaseUrl() . '/success.php';
}

function getPayFastCancelUrl(): string
{
    return getSiteBaseUrl() . '/cancel.php';
}

function getPayFastNotifyUrl(): string
{
    if (PAYFAST_NOTIFY_URL_OVERRIDE !== '') {
        return rtrim(PAYFAST_NOTIFY_URL_OVERRIDE, '/');
    }

    return getSiteBaseUrl() . '/itn.php';
}

function buildPayFastSignature(array $data, string $passphrase = ''): string
{
    $payload = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature') {
            continue;
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            continue;
        }

        $payload[] = $key . '=' . urlencode($normalized);
    }

    $normalizedPassphrase = trim($passphrase);
    if ($normalizedPassphrase !== '') {
        $payload[] = 'passphrase=' . urlencode($normalizedPassphrase);
    }

    return md5(implode('&', $payload));
}

/**
 * Hair-length tier labels (data-driven pricing). Keys are used in booking_service_prices
 * and the price matrix below; '' means a single price with no length choice.
 */
function priceLengthLabel(string $lengthKey): string
{
    $map = [
        'bra-shoulder' => 'Bra/Shoulder Length',
        'shoulder'     => 'Shoulder Length',
        'bra'          => 'Bra Length',
        'waist'        => 'Waist Length',
        'bum'          => 'Bum Length',
    ];
    return $map[$lengthKey] ?? ucfirst(str_replace('-', ' ', $lengthKey));
}

/**
 * The owner's real price list (pricing.md), transcribed as a matrix keyed by
 * service → subtype → [ {length_key, length_label, price} ].
 *
 * This is the single source of truth used when the DB price table
 * (booking_service_prices) is empty/absent, so pricing works before the seed
 * migration runs and offline. The migration seeds the same data into the DB so
 * the owner can edit it. length_key '' = a single price (no length choice).
 * See getBookingItemPrice() / getServicePriceOptions().
 */
function getDefaultServicePriceMatrix(): array
{
    // Helper to expand [lengthKey => price] into the row shape.
    $rows = static function (array $byLength): array {
        $out = [];
        $sort = 10;
        foreach ($byLength as $lengthKey => $price) {
            $out[] = [
                'length_key'   => (string)$lengthKey,
                'length_label' => priceLengthLabel((string)$lengthKey),
                'price'        => (float)$price,
            ];
            $sort += 10;
        }
        return $out;
    };

    return [
        'braids' => [
            'knotless-braids'            => $rows(['bra-shoulder' => 650, 'waist' => 750, 'bum' => 950]),
            'normal-braids'              => $rows(['bra-shoulder' => 550, 'waist' => 650, 'bum' => 850]),
            'koroba-knotless-braids'     => $rows(['' => 850]),
            'koroba-normal-braids'       => $rows(['' => 750]),
            'koroba-tribal-braids'       => $rows(['' => 650]),
            'goddess-knotless-braids'    => $rows(['bra-shoulder' => 750, 'waist' => 850, 'bum' => 1050]),
            'goddess-normal-braids'      => $rows(['bra-shoulder' => 650, 'waist' => 750, 'bum' => 950]),
            'knotless-boho-french-curls' => $rows(['bra' => 950, 'waist' => 1200, 'bum' => 1400]),
            'french-curls'               => $rows(['bra-shoulder' => 950, 'waist' => 1200, 'bum' => 1400]),
            'boho-french-curls'          => $rows(['shoulder' => 950, 'waist' => 1300]),
            'tribal-french-curls'        => $rows(['' => 950]),
            'kinky-twist'                => $rows(['bra-shoulder' => 650, 'waist' => 800]),
            'jumbo-knotless-braids'      => $rows(['bra' => 950, 'waist' => 1200, 'bum' => 1500]),
            'jumbo-normal-braids'        => $rows(['bra' => 850, 'waist' => 1050, 'bum' => 1150]),
            'tribal-braids'              => $rows(['bra' => 450, 'waist' => 550, 'bum' => 750]),
            'boho-tribal-braids'         => $rows(['bra-shoulder' => 650, 'waist' => 750, 'bum' => 850]),
            'lemonade-braids'            => $rows(['shoulder' => 750, 'bra' => 850]),
            'jayda-wayda-sewin'          => $rows(['bra' => 550, 'waist' => 650]),
        ],
        'cornrows' => [
            'straight-back-cornrows' => $rows(['bra-shoulder' => 400, 'waist' => 450, 'bum' => 500]),
            'stitch-cornrows'        => $rows(['bra-shoulder' => 450, 'waist' => 500, 'bum' => 550]),
            'straight-up-cornrows'   => $rows(['waist' => 650, 'bum' => 750]),
            'wig-lines'              => $rows(['' => 250]),
            'freehand'               => $rows(['' => 350]),
        ],
        'locs' => [
            'invisible-locs' => $rows(['shoulder' => 550, 'waist' => 750]),
            'butterfly-locs' => $rows(['' => 1150]),
            'river-locs'     => $rows(['' => 1200]),
            'nana-locs'      => $rows(['' => 1400]),
        ],
        'wig-installation' => [
            'basic-wig-install'       => $rows(['' => 500]),
            'basic-wig-install-lines' => $rows(['' => 750]),
            '360-wig-install'         => $rows(['' => 800]),
            'wig-ponytail'            => $rows(['' => 150]),
            'wig-half-up-ponytail'    => $rows(['' => 150]),
            'wig-half-up-lines'       => $rows(['' => 200]),
            'wig-half-up-curls'       => $rows(['' => 250]),
            'wig-full-curls'          => $rows(['' => 350]),
            'wig-bridal-style'        => $rows(['' => 350]),
            'wig-making'              => $rows(['' => 600]),
            'lace-wash'               => $rows(['' => 50]),
            'lace-removal'            => $rows(['' => 100]),
            'wig-customisation'       => $rows(['' => 250]),
            'wig-treatment'           => $rows(['' => 350]),
        ],
        'sewin' => [
            'weave-sewin'           => $rows(['' => 650]),
            'weave-sewin-brazilian' => $rows(['' => 1800]),
        ],
        'frontal-ponytail' => [
            'hd-frontal-brazilian'    => $rows(['' => 3950]),
            'swiss-frontal-brazilian' => $rows(['' => 2800]),
            'swiss-frontal-synthetic' => $rows(['' => 1350]),
            'your-closure-bundles'    => $rows(['' => 650]),
        ],
        'ponytail' => [
            'curly'         => $rows(['' => 500]),
            'straight'      => $rows(['' => 450]),
            'half-up-sewin' => $rows(['' => 650]),
            'afro-twist'    => $rows(['' => 550]),
        ],
        'makeup' => [
            'full-soft-glam'     => $rows(['' => 750]),
            'eyebrow-shaping'    => $rows(['' => 100]),
            'lesson-daily'       => $rows(['' => 1400]),
            'lesson-daily-group' => $rows(['' => 1250]),
        ],
        'relaxer' => [
            'pure-royal'                 => $rows(['' => 350]),
            'mizani-moisture'            => $rows(['' => 350]),
            'mizani-strength'            => $rows(['' => 400]),
            'design-essential-anti-itchy'=> $rows(['' => 450]),
            'design-essential-moisture'  => $rows(['' => 350]),
            'native-child'               => $rows(['' => 350]),
            'dark-n-lovely-moisture'     => $rows(['' => 250]),
        ],
        'other-styling' => [
            'mizani-silk-press'  => $rows(['' => 550]),
            'moisture-mayonnaise'=> $rows(['' => 150]),
        ],
        'wash' => [
            'natural-hair' => $rows(['' => 200]),
            'relaxed-hair' => $rows(['' => 150]),
            'detangle'     => $rows(['' => 100]),
        ],
        'undo' => [
            'undo-braids-normal' => $rows(['' => 150]),
            'undo-braids-small'  => $rows(['' => 200]),
            'undo-cornrows'      => $rows(['' => 50]),
        ],
    ];
}

/**
 * Length/price options for a service + subtype, from the catalog's price matrix.
 * Returns [ {length_key, length_label, price}, ... ]. A single row with
 * length_key '' means "one flat price, no length choice".
 */
function getServicePriceOptions(array $catalog, string $service, string $subtype): array
{
    $matrix = $catalog['priceMatrix'] ?? [];
    return $matrix[$service][$subtype] ?? [];
}

/**
 * Authoritative FULL price for a booked item (owner price list). Resolves
 * (service, subtype, length) from the price matrix; falls back to the single
 * flat price, then to the service base_price for services not in the list
 * (nails, lashes, other). Deposit = 50% of this (BOOKING_DEPOSIT_PERCENTAGE).
 */
function getBookingItemPrice(array $catalog, string $service, string $subtype, string $length): float
{
    $rows = getServicePriceOptions($catalog, $service, $subtype);
    if ($rows) {
        foreach ($rows as $r) {
            if ((string)($r['length_key'] ?? '') === (string)$length) {
                return (float)$r['price'];
            }
        }
        // No exact length match but a single flat price exists → use it.
        if (count($rows) === 1) {
            return (float)$rows[0]['price'];
        }
    }
    return (float)($catalog['services'][$service]['base_price'] ?? 0);
}

function getDefaultBookingCatalog(): array
{
    $timeSlotMap = [
        '06:45' => ['label' => '06:45 AM', 'db' => '06:45:00'],
        '07:00' => ['label' => '07:00 AM', 'db' => '07:00:00'],
        '07:30' => ['label' => '07:30 AM', 'db' => '07:30:00'],
        '08:00' => ['label' => '08:00 AM', 'db' => '08:00:00'],
        '09:00' => ['label' => '09:00 AM', 'db' => '09:00:00'],
        '09:15' => ['label' => '09:15 AM', 'db' => '09:15:00'],
        '10:00' => ['label' => '10:00 AM', 'db' => '10:00:00'],
        '10:30' => ['label' => '10:30 AM', 'db' => '10:30:00'],
        '11:00' => ['label' => '11:00 AM', 'db' => '11:00:00'],
        '11:30' => ['label' => '11:30 AM', 'db' => '11:30:00'],
        '11:45' => ['label' => '11:45 AM', 'db' => '11:45:00'],
        '12:00' => ['label' => '12:00 PM', 'db' => '12:00:00'],
        '13:00' => ['label' => '01:00 PM', 'db' => '13:00:00'],
        '14:00' => ['label' => '02:00 PM', 'db' => '14:00:00'],
        '14:15' => ['label' => '02:15 PM', 'db' => '14:15:00'],
        '14:30' => ['label' => '02:30 PM', 'db' => '14:30:00'],
        '15:00' => ['label' => '03:00 PM', 'db' => '15:00:00'],
        '15:30' => ['label' => '03:30 PM', 'db' => '15:30:00'],
        '16:00' => ['label' => '04:00 PM', 'db' => '16:00:00'],
        '16:30' => ['label' => '04:30 PM', 'db' => '16:30:00'],
        '16:45' => ['label' => '04:45 PM', 'db' => '16:45:00'],
        '17:00' => ['label' => '05:00 PM', 'db' => '17:00:00'],
        'before-hours' => ['label' => 'Before Hours (extra R200)', 'db' => '07:00:00'],
        'after-hours'  => ['label' => 'After Hours (extra R200)',  'db' => '18:00:00'],
    ];

    $services = [
        'braids' => [
            'label' => 'Braids',
            'category' => 'Braiding Services',
            'base_price' => 1600.00,
            'requires_sub_type' => true,
            'requires_hair_length' => true,
            'sub_type_label' => 'Type of Braids',
            'subtypes' => [
                ['key' => 'knotless-braids', 'label' => 'Knotless Braids'],
                ['key' => 'normal-braids', 'label' => 'Normal Braids'],
                ['key' => 'goddess-knotless-braids', 'label' => 'Goddess Knotless Braids'],
                ['key' => 'goddess-normal-braids', 'label' => 'Goddess Normal Braids'],
                ['key' => 'koroba-knotless-braids', 'label' => 'Koroba Knotless Braids'],
                ['key' => 'koroba-normal-braids', 'label' => 'Koroba Normal Braids'],
                ['key' => 'koroba-tribal-braids', 'label' => 'Koroba Tribal Braids'],
                ['key' => 'french-curls', 'label' => 'French Curls'],
                ['key' => 'boho-french-curls', 'label' => 'Boho French Curls'],
                ['key' => 'tribal-french-curls', 'label' => 'Tribal French Curls'],
                ['key' => 'tribal-braids', 'label' => 'Tribal Braids'],
                ['key' => 'boho-tribal-braids', 'label' => 'Boho Tribal Braids'],
                ['key' => 'kinky-twist', 'label' => 'Kinky Twist'],
                ['key' => 'jumbo-knotless-braids', 'label' => 'Jumbo Knotless Braids'],
                ['key' => 'jumbo-normal-braids', 'label' => 'Jumbo Normal Braids'],
                ['key' => 'lemonade-braids', 'label' => 'Lemonade Braids'],
                ['key' => 'jayda-wayda-sewin', 'label' => 'Jayda Wayda Sewin'],
                ['key' => 'knotless-boho-french-curls', 'label' => 'Knotless Boho with French Curls'],
            ],
            'info' => '<strong>Duration:</strong> 3-4 hours &nbsp;·&nbsp; <strong>Team:</strong> 1 client / 2 Braiders',
            'slot_keys' => ['07:30', '11:30', '14:30'],
            'capacity' => 1,
        ],
        'cornrows' => [
            'label' => 'Cornrows',
            'category' => 'Braiding Services',
            'base_price' => 1200.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Cornrow Style',
            'subtypes' => [
                ['key' => 'straight-back-cornrows', 'label' => 'Straight-back Cornrows'],
                ['key' => 'stitch-cornrows', 'label' => 'Stitch Cornrows'],
                ['key' => 'fulani-cornrows', 'label' => 'Fulani Cornrows'],
                ['key' => 'cornrows-with-extensions', 'label' => 'Cornrows with Extensions'],
                ['key' => 'wig-lines', 'label' => 'Wig Lines'],
                ['key' => 'freehand', 'label' => 'Freehand'],
            ],
            'info' => '<strong>Duration:</strong> 2-3 hours &nbsp;·&nbsp; <strong>Team:</strong> 2 Stylists',
            'slot_keys' => ['07:30', '11:00', '14:00', '16:30'],
            'capacity' => 1,
        ],
        'ponytail' => [
            'label' => 'Ponytail',
            'category' => 'Hair Styling',
            'base_price' => 800.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Ponytail Style',
            'subtypes' => [
                ['key' => 'sleek-ponytail', 'label' => 'Sleek Ponytail'],
                ['key' => 'curly-ponytail', 'label' => 'Curly Ponytail'],
                ['key' => 'braided-ponytail', 'label' => 'Braided Ponytail'],
            ],
            'info' => '',
            'slot_keys' => ['07:00', '09:00', '11:00', '13:00', '15:00'],
            'capacity' => 2,
        ],
        'frontal-ponytail' => [
            'label' => 'Frontal Ponytail',
            'category' => 'Hair Styling',
            'base_price' => 1350.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Style',
            'subtypes' => [
                ['key' => 'hd-frontal-brazilian', 'label' => 'HD Frontal Closure + 24" Brazilian Bundles'],
                ['key' => 'swiss-frontal-brazilian', 'label' => 'Swiss Frontal Closure + 24" Brazilian Bundles'],
                ['key' => 'swiss-frontal-synthetic', 'label' => 'Swiss Frontal Closure + Synthetic Bundles'],
                ['key' => 'your-closure-bundles', 'label' => 'With Your Closure + Bundles'],
            ],
            'info' => '<strong>Duration:</strong> 2hrs 30min &nbsp;·&nbsp; Choose from HD, Swiss, or your own closure options.',
            'slot_keys' => ['07:00', '09:00', '11:00', '13:00', '15:00'],
            'capacity' => 2,
        ],
        'relaxer' => [
            'label' => 'Relaxer',
            'category' => 'Hair Styling',
            'base_price' => 300.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Relaxer Type',
            'subtypes' => [
                ['key' => 'dark-n-lovely', 'label' => 'Dark n Lovely'],
            ],
            'info' => 'Professional relaxer treatment.',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'wig-installation' => [
            'label' => 'Wig Installation',
            'category' => 'Hair Styling',
            'base_price' => 1500.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Wig Type',
            'subtypes' => [
                ['key' => 'basic-wig-install', 'label' => 'Basic Wig Installation'],
                ['key' => 'basic-wig-install-style', 'label' => 'Basic Wig Installation + Style'],
                ['key' => '360-wig-install', 'label' => '360 Basic Wig Installation'],
                ['key' => '360-wig-install-style', 'label' => '360 Basic Wig Installation + Style'],
                ['key' => 'frontal-install', 'label' => 'Frontal Install'],
                ['key' => 'glueless-install', 'label' => 'Glueless Install'],
                ['key' => 'wig-customisation', 'label' => 'Wig Customisation'],
                ['key' => 'wig-treatment', 'label' => 'Wig Treatment'],
                ['key' => 'lace-wash', 'label' => 'Lace Wash'],
                ['key' => 'wig-lines', 'label' => 'Wig Lines'],
            ],
            'info' => '<strong>Durations:</strong> Basic Install 1hr &nbsp;·&nbsp; Basic + Style 1hr 30min &nbsp;·&nbsp; 360 Install 2hrs &nbsp;·&nbsp; 360 + Style 2hrs 30min',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'hair-colour' => [
            'label' => 'Hair Colour',
            'category' => 'Hair Styling',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Hair Colour Service',
            'subtypes' => [
                ['key' => 'full-colour', 'label' => 'Full Colour'],
                ['key' => 'highlights', 'label' => 'Highlights'],
                ['key' => 'root-touch-up', 'label' => 'Root Touch-up'],
                ['key' => 'toner', 'label' => 'Toner'],
            ],
            'info' => '<strong>Duration:</strong> 1-2 hours',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'other-styling' => [
            'label' => 'Other Hair Styling',
            'category' => 'Hair Styling',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Styling Type',
            'subtypes' => [
                ['key' => 'silk-press', 'label' => 'Silk Press'],
                ['key' => 'updo', 'label' => 'Updo'],
                ['key' => 'treatment-style', 'label' => 'Treatment & Style'],
                ['key' => 'custom-styling', 'label' => 'Custom Styling'],
            ],
            'info' => '',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'makeup' => [
            'label' => 'Makeup Artistry',
            'category' => 'Makeup',
            'base_price' => 800.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Makeup Type',
            'subtypes' => [
                ['key' => 'soft-glam', 'label' => 'Soft Glam'],
                ['key' => 'full-glam', 'label' => 'Full Glam'],
                ['key' => 'photoshoot-makeup', 'label' => 'Photoshoot Makeup'],
            ],
            'info' => 'Makeup bookings are handled directly through our online booking form.',
            'slot_keys' => ['06:45', '08:00', '09:15', '10:30', '11:45', '13:00', '14:15', '15:30', '16:45'],
            'capacity' => 2,
        ],
        'bridal-makeup' => [
            'label' => 'Bridal Makeup',
            'category' => 'Makeup',
            'base_price' => 1350.00,
            'requires_sub_type' => false,
            'requires_hair_length' => false,
            'sub_type_label' => '',
            'subtypes' => [],
            'info' => '',
            'slot_keys' => ['06:45', '08:00', '09:15', '10:30', '11:45', '13:00', '14:15', '15:30', '16:45'],
            'capacity' => 2,
        ],
        'nails' => [
            'label' => 'Nails',
            'category' => 'Beauty Services',
            'base_price' => 200.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Type of Nails',
            'subtypes' => [
                ['key' => 'manicure', 'label' => 'Manicure'],
                ['key' => 'pedicure', 'label' => 'Pedicure'],
                ['key' => 'gel-nails', 'label' => 'Gel Nails'],
                ['key' => 'acrylic-nails', 'label' => 'Acrylic Nails'],
                ['key' => 'nail-art', 'label' => 'Nail Art'],
            ],
            'info' => '<strong>Mobile service</strong> &nbsp;·&nbsp; Nail technician comes to you. Travel fee added based on your area.',
            'slot_keys' => [],
            'capacity' => 1,
            'mobile_only' => true,
        ],
        'lashes' => [
            'label' => 'Lashes',
            'category' => 'Beauty Services',
            'base_price' => 200.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Type of Lashes',
            'subtypes' => [
                ['key' => 'classic-lashes', 'label' => 'Classic Lashes'],
                ['key' => 'volume-lashes', 'label' => 'Volume Lashes'],
                ['key' => 'mega-volume', 'label' => 'Mega Volume'],
                ['key' => 'lash-lift', 'label' => 'Lash Lift'],
                ['key' => 'lash-removal', 'label' => 'Lash Removal'],
            ],
            'info' => '<strong>Mobile service</strong> &nbsp;·&nbsp; Lash technician comes to you. Travel fee added based on your area.',
            'slot_keys' => [],
            'capacity' => 1,
            'mobile_only' => true,
        ],
        'undo' => [
            'label' => 'Undo',
            'category' => 'Hair Care',
            'base_price' => 50.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Undo Type',
            'subtypes' => [
                ['key' => 'undo-braids-normal', 'label' => 'Undo Braids (Normal)'],
                ['key' => 'undo-braids-small', 'label' => 'Undo Braids (Small)'],
                ['key' => 'undo-cornrows', 'label' => 'Undo Cornrows'],
            ],
            'info' => 'Removal of existing styles.',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'locs' => [
            'label' => 'Locs',
            'category' => 'Locs',
            'base_price' => 550.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Loc Style',
            'subtypes' => [
                ['key' => 'invisible-locs', 'label' => 'Invisible Locs'],
                ['key' => 'butterfly-locs', 'label' => 'Butterfly Locs'],
                ['key' => 'river-locs', 'label' => 'River Locs'],
                ['key' => 'nana-locs', 'label' => 'Nana Locs'],
            ],
            'info' => '',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'sewin' => [
            'label' => 'Sewin',
            'category' => 'Sewin',
            'base_price' => 650.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Sewin Type',
            'subtypes' => [
                ['key' => 'weave-sewin', 'label' => 'Weave Sewin'],
                ['key' => 'weave-sewin-brazilian', 'label' => 'Weave Sewin (Brazilian)'],
            ],
            'info' => '',
            'slot_keys' => [],
            'capacity' => 2,
        ],
        'wash' => [
            'label' => 'Wash',
            'category' => 'Hair Care',
            'base_price' => 200.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Wash Type',
            'subtypes' => [
                ['key' => 'natural-hair', 'label' => 'Natural Hair Wash'],
                ['key' => 'relaxed-hair', 'label' => 'Relaxed Hair Wash'],
                ['key' => 'detangle', 'label' => 'Detangle'],
            ],
            'info' => '',
            'slot_keys' => [],
            'capacity' => 2,
        ],
        'mobile' => [
            'label' => 'Mobile Service',
            'category' => 'Other',
            'base_price' => 400.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Mobile Service Type',
            'subtypes' => [
                ['key' => 'hair-service-at-home', 'label' => 'Hair Service at Home'],
                ['key' => 'makeup-at-home', 'label' => 'Makeup at Home'],
                ['key' => 'hair-makeup-at-home', 'label' => 'Hair + Makeup at Home'],
            ],
            'info' => '<strong>Travel fee:</strong> Additional R200',
            'slot_keys' => [],
            'capacity' => 1,
        ],
        'other' => [
            'label' => 'Other',
            'category' => 'Other',
            'base_price' => 1000.00,
            'requires_sub_type' => true,
            'requires_hair_length' => false,
            'sub_type_label' => 'Other Service',
            'subtypes' => [
                ['key' => 'other-service', 'label' => 'Other Service'],
            ],
            'info' => '',
            'slot_keys' => [],
            'capacity' => 1,
        ],
    ];

    $locations = [
        'midrand' => 'Midrand Studio',
        'copperleaf' => 'Copperleaf Studio',
        'mobile' => 'Mobile (come to me)',
    ];

    $stylists = [
        'caro' => 'Caro',
        'emma' => 'Emma',
        'patience' => 'Patience',
        'lincy' => 'Lincy',
        'charity' => 'Charity',
        'itumeleng' => 'Itumeleng',
        'pamela' => 'Pamela',
        'marlyn' => 'Marlyn',
        'ibongiwe' => 'Ibongiwe',
    ];

    $allStylists = array_keys($stylists);
    $serviceLocationStylists = [];
    foreach ($services as $serviceKey => $_serviceMeta) {
        $serviceLocationStylists[$serviceKey] = [
            'all' => $allStylists,
            'midrand' => ['caro', 'emma', 'patience'],
            'copperleaf' => ['lincy', 'charity'],
            'mobile' => $allStylists,
        ];

        if ($serviceKey === 'makeup') {
            $serviceLocationStylists[$serviceKey] = [
                'all' => ['itumeleng', 'pamela'],
                'midrand' => ['itumeleng', 'pamela'],
                'copperleaf' => ['itumeleng', 'pamela'],
                'mobile' => ['itumeleng', 'pamela'],
            ];
        }

        if ($serviceKey === 'wig-installation') {
            $serviceLocationStylists[$serviceKey] = [
                'all' => ['marlyn', 'ibongiwe'],
                'midrand' => ['marlyn', 'ibongiwe'],
                'copperleaf' => ['marlyn', 'ibongiwe'],
                'mobile' => ['marlyn', 'ibongiwe'],
            ];
        }

        if ($serviceKey === 'frontal-ponytail') {
            $serviceLocationStylists[$serviceKey] = [
                'all' => ['marlyn', 'ibongiwe'],
                'midrand' => ['marlyn', 'ibongiwe'],
                'copperleaf' => ['marlyn', 'ibongiwe'],
                'mobile' => ['marlyn', 'ibongiwe'],
            ];
        }

        if (in_array($serviceKey, ['nails', 'lashes'], true)) {
            $serviceLocationStylists[$serviceKey] = [
                'all'        => $allStylists,
                'midrand'    => [],
                'copperleaf' => [],
                'mobile'     => $allStylists,
            ];
        }
    }

    $serviceDepositMap = [];
    foreach ($services as $serviceKey => $serviceMeta) {
        $serviceDepositMap[$serviceKey] = round(((float)$serviceMeta['base_price']) * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }

    return [
        'services' => $services,
        'serviceOrder' => array_keys($services),
        'locations' => $locations,
        'stylists' => $stylists,
        'serviceLocationStylists' => $serviceLocationStylists,
        'timeSlotMap' => $timeSlotMap,
        'serviceDepositMap' => $serviceDepositMap,
        'priceMatrix' => getDefaultServicePriceMatrix(),
    ];
}

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $safeName = $mysqli->real_escape_string($tableName);
    $sql = "SHOW TABLES LIKE '" . $safeName . "'";
    $result = $mysqli->query($sql);
    if (!$result instanceof mysqli_result) {
        return false;
    }
    return $result->num_rows > 0;
}

function getBookingCatalog(mysqli $mysqli): array
{
    $defaults = getDefaultBookingCatalog();

    $requiredTables = [
        'booking_services',
        'booking_service_subtypes',
        'booking_locations',
        'booking_stylists',
        'booking_time_slots',
        'booking_service_stylists',
    ];

    foreach ($requiredTables as $tableName) {
        if (!tableExists($mysqli, $tableName)) {
            return $defaults;
        }
    }

    $services = [];
    $serviceOrder = [];

    $serviceResult = $mysqli->query(
        "SELECT id, service_key, service_name, category_label, base_price, capacity, requires_sub_type, requires_hair_length, sub_type_label, info_text
         FROM booking_services
         WHERE is_active = 1
         ORDER BY sort_order ASC, service_name ASC"
    );

    if (!$serviceResult instanceof mysqli_result || $serviceResult->num_rows === 0) {
        return $defaults;
    }

    $serviceIdByKey = [];
    while ($row = $serviceResult->fetch_assoc()) {
        $serviceKey = (string)$row['service_key'];
        $serviceIdByKey[$serviceKey] = (int)$row['id'];
        $serviceOrder[] = $serviceKey;
        $services[$serviceKey] = [
            'label' => (string)$row['service_name'],
            'category' => (string)$row['category_label'],
            'base_price' => (float)$row['base_price'],
            'requires_sub_type' => (bool)$row['requires_sub_type'],
            'requires_hair_length' => (bool)$row['requires_hair_length'],
            'sub_type_label' => trim((string)$row['sub_type_label']) !== '' ? (string)$row['sub_type_label'] : 'Style',
            'subtypes' => [],
            'info' => (string)$row['info_text'],
            'slot_keys' => [],
            'capacity' => (int)($row['capacity'] ?? 1),
        ];
    }

    $subtypeResult = $mysqli->query(
        "SELECT bs.service_key, s.subtype_key, s.subtype_label
         FROM booking_service_subtypes s
         INNER JOIN booking_services bs ON bs.id = s.service_id
         WHERE s.is_active = 1 AND bs.is_active = 1
         ORDER BY s.sort_order ASC, s.subtype_label ASC"
    );
    if ($subtypeResult instanceof mysqli_result) {
        while ($row = $subtypeResult->fetch_assoc()) {
            $serviceKey = (string)$row['service_key'];
            if (!isset($services[$serviceKey])) {
                continue;
            }
            $services[$serviceKey]['subtypes'][] = [
                'key' => (string)$row['subtype_key'],
                'label' => (string)$row['subtype_label'],
            ];
        }
    }

    $locations = [];
    $locationIdByKey = [];
    $locationResult = $mysqli->query(
        "SELECT id, location_key, location_name
         FROM booking_locations
         WHERE is_active = 1
         ORDER BY sort_order ASC, location_name ASC"
    );
    if ($locationResult instanceof mysqli_result) {
        while ($row = $locationResult->fetch_assoc()) {
            $locationKey = (string)$row['location_key'];
            $locationIdByKey[$locationKey] = (int)$row['id'];
            $locations[$locationKey] = (string)$row['location_name'];
        }
    }
    if (!$locations) {
        $locations = $defaults['locations'];
    }

    $stylists = [];
    $stylistResult = $mysqli->query(
        "SELECT id, stylist_key, stylist_name
         FROM booking_stylists
         WHERE is_active = 1
         ORDER BY sort_order ASC, stylist_name ASC"
    );
    if ($stylistResult instanceof mysqli_result) {
        while ($row = $stylistResult->fetch_assoc()) {
            $stylists[(string)$row['stylist_key']] = (string)$row['stylist_name'];
        }
    }
    if (!$stylists) {
        $stylists = $defaults['stylists'];
    }

    $timeSlotMap = [];
    $timeSlotIdByKey = [];
    $slotResult = $mysqli->query(
        "SELECT id, slot_key, slot_label, db_time
         FROM booking_time_slots
         WHERE is_active = 1
         ORDER BY sort_order ASC, db_time ASC"
    );
    if ($slotResult instanceof mysqli_result) {
        while ($row = $slotResult->fetch_assoc()) {
            $slotKey = (string)$row['slot_key'];
            $timeSlotIdByKey[$slotKey] = (int)$row['id'];
            $timeSlotMap[$slotKey] = [
                'label' => (string)$row['slot_label'],
                'db' => (string)$row['db_time'],
            ];
        }
    }
    if (!$timeSlotMap) {
        $timeSlotMap = $defaults['timeSlotMap'];
    }

    if (tableExists($mysqli, 'booking_service_slots')) {
        // Legacy flat slot list = weekday set only. Day-aware availability (incl. the
        // Sunday 'sun' day_group) is computed by the Phase 1 availability layer, not here.
        $slotMapResult = $mysqli->query(
            "SELECT bs.service_key, ts.slot_key
             FROM booking_service_slots bss
             INNER JOIN booking_services bs ON bs.id = bss.service_id
             INNER JOIN booking_time_slots ts ON ts.id = bss.slot_id
             WHERE bss.is_active = 1 AND bs.is_active = 1 AND ts.is_active = 1
               AND bss.day_group = 'weekday'
             ORDER BY ts.sort_order ASC, ts.db_time ASC"
        );
        if ($slotMapResult instanceof mysqli_result) {
            while ($row = $slotMapResult->fetch_assoc()) {
                $serviceKey = (string)$row['service_key'];
                $slotKey = (string)$row['slot_key'];
                if (!isset($services[$serviceKey])) {
                    continue;
                }
                if (isset($timeSlotMap[$slotKey])) {
                    $services[$serviceKey]['slot_keys'][] = $slotKey;
                }
            }
        }
    }

    $serviceLocationStylists = [];
    $allStylistKeys = array_keys($stylists);
    foreach ($services as $serviceKey => $_meta) {
        $serviceLocationStylists[$serviceKey] = ['all' => []];
        foreach (array_keys($locations) as $locationKey) {
            $serviceLocationStylists[$serviceKey][$locationKey] = [];
        }
    }

    $serviceStylistResult = $mysqli->query(
        "SELECT bs.service_key, bl.location_key, bst.stylist_key
         FROM booking_service_stylists bss
         INNER JOIN booking_services bs ON bs.id = bss.service_id
         INNER JOIN booking_stylists bst ON bst.id = bss.stylist_id
         LEFT JOIN booking_locations bl ON bl.id = bss.location_id
         WHERE bss.is_active = 1 AND bs.is_active = 1 AND bst.is_active = 1"
    );
    if ($serviceStylistResult instanceof mysqli_result) {
        while ($row = $serviceStylistResult->fetch_assoc()) {
            $serviceKey = (string)$row['service_key'];
            $stylistKey = (string)$row['stylist_key'];
            $locationKey = trim((string)$row['location_key']);

            if (!isset($serviceLocationStylists[$serviceKey])) {
                continue;
            }

            if (!in_array($stylistKey, $serviceLocationStylists[$serviceKey]['all'], true)) {
                $serviceLocationStylists[$serviceKey]['all'][] = $stylistKey;
            }

            if ($locationKey !== '' && isset($serviceLocationStylists[$serviceKey][$locationKey])) {
                if (!in_array($stylistKey, $serviceLocationStylists[$serviceKey][$locationKey], true)) {
                    $serviceLocationStylists[$serviceKey][$locationKey][] = $stylistKey;
                }
            }
        }
    }

    foreach ($serviceLocationStylists as $serviceKey => $locationMap) {
        if (!$locationMap['all']) {
            $serviceLocationStylists[$serviceKey]['all'] = $allStylistKeys;
        }

        foreach (array_keys($locations) as $locationKey) {
            if (empty($serviceLocationStylists[$serviceKey][$locationKey])) {
                $serviceLocationStylists[$serviceKey][$locationKey] = $serviceLocationStylists[$serviceKey]['all'];
            }
        }
    }

    $serviceDepositMap = [];
    foreach ($services as $serviceKey => $serviceMeta) {
        $serviceDepositMap[$serviceKey] = round(((float)$serviceMeta['base_price']) * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }

    // Keep core service options in sync with the public price list.
    if (isset($services['braids'])) {
        $services['braids']['subtypes'] = [
            ['key' => 'knotless-braids', 'label' => 'Knotless Braids'],
            ['key' => 'normal-braids', 'label' => 'Normal Braids'],
            ['key' => 'goddess-knotless-braids', 'label' => 'Goddess Knotless Braids'],
            ['key' => 'goddess-normal-braids', 'label' => 'Goddess Normal Braids'],
            ['key' => 'koroba-knotless-braids', 'label' => 'Koroba Knotless Braids'],
            ['key' => 'koroba-normal-braids', 'label' => 'Koroba Normal Braids'],
            ['key' => 'koroba-tribal-braids', 'label' => 'Koroba Tribal Braids'],
            ['key' => 'french-curls', 'label' => 'French Curls'],
            ['key' => 'boho-french-curls', 'label' => 'Boho French Curls'],
            ['key' => 'tribal-french-curls', 'label' => 'Tribal French Curls'],
            ['key' => 'tribal-braids', 'label' => 'Tribal Braids'],
            ['key' => 'boho-tribal-braids', 'label' => 'Boho Tribal Braids'],
            ['key' => 'kinky-twist', 'label' => 'Kinky Twist'],
            ['key' => 'jumbo-knotless-braids', 'label' => 'Jumbo Knotless Braids'],
            ['key' => 'jumbo-normal-braids', 'label' => 'Jumbo Normal Braids'],
            ['key' => 'lemonade-braids', 'label' => 'Lemonade Braids'],
            ['key' => 'jayda-wayda-sewin', 'label' => 'Jayda Wayda Sewin'],
            ['key' => 'knotless-boho-french-curls', 'label' => 'Knotless Boho with French Curls'],
        ];
    }

    if (isset($services['frontal-ponytail'])) {
        $services['frontal-ponytail']['subtypes'] = [
            ['key' => 'hd-frontal-brazilian', 'label' => 'HD Frontal Closure + 24" Brazilian Bundles'],
            ['key' => 'swiss-frontal-brazilian', 'label' => 'Swiss Frontal Closure + 24" Brazilian Bundles'],
            ['key' => 'swiss-frontal-synthetic', 'label' => 'Swiss Frontal Closure + Synthetic Bundles'],
            ['key' => 'your-closure-bundles', 'label' => 'With Your Closure + Bundles'],
        ];
    }

    if (isset($services['wig-installation']) && !empty($services['wig-installation']['subtypes'])) {
        $services['wig-installation']['subtypes'] = array_values(array_filter(
            $services['wig-installation']['subtypes'],
            static function (array $subType): bool {
                return strtolower((string)($subType['key'] ?? '')) !== 'closure-install';
            }
        ));
    }

    if (isset($serviceLocationStylists['frontal-ponytail'])) {
        $serviceLocationStylists['frontal-ponytail'] = [
            'all' => ['marlyn', 'ibongiwe'],
            'midrand' => ['marlyn', 'ibongiwe'],
            'copperleaf' => ['marlyn', 'ibongiwe'],
            'mobile' => ['marlyn', 'ibongiwe'],
        ];
    }

    // Price matrix: prefer DB rows (booking_service_prices, owner-editable, seeded by
    // the pricing migration); fall back to the code transcription so pricing works
    // before the migration runs. Shape: priceMatrix[service][subtype] = [ {length_key,length_label,price} ].
    $priceMatrix = [];
    if (tableExists($mysqli, 'booking_service_prices')) {
        $priceResult = $mysqli->query(
            "SELECT service_key, subtype_key, length_key, length_label, price
             FROM booking_service_prices WHERE is_active = 1
             ORDER BY service_key, subtype_key, sort_order, price"
        );
        if ($priceResult instanceof mysqli_result) {
            while ($row = $priceResult->fetch_assoc()) {
                $priceMatrix[(string)$row['service_key']][(string)$row['subtype_key']][] = [
                    'length_key'   => (string)$row['length_key'],
                    'length_label' => (string)$row['length_label'],
                    'price'        => (float)$row['price'],
                ];
            }
        }
    }
    if (!$priceMatrix) {
        $priceMatrix = getDefaultServicePriceMatrix();
    }

    return [
        'services' => $services,
        'serviceOrder' => $serviceOrder,
        'locations' => $locations,
        'stylists' => $stylists,
        'serviceLocationStylists' => $serviceLocationStylists,
        'timeSlotMap' => $timeSlotMap,
        'serviceDepositMap' => $serviceDepositMap,
        'priceMatrix' => $priceMatrix,
    ];
}

function getBusinessInfo(mysqli $mysqli): array
{
    $defaults = [
        'brand_name' => 'Bella Hair | Makeup',
        'phone_whatsapp' => '071 234 5678',
        'phone_call' => '071 234 5678',
        'phone_landline' => '010 500 7562',
        'whatsapp_url' => 'https://wa.me/27712345678',
        'hours_midrand' => 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: 11:00-16:00',
        'hours_copperleaf' => 'Mon-Wed: 09:00-17:30 | Thu-Fri: 08:00-18:00 | Sat: 08:00-17:00 | Sun: Closed',
        'address_midrand' => '12 Demo Street, Sandton',
        'address_copperleaf' => 'Copperleaf Golf & Country Estate (Appointment only)',
    ];

    if (!tableExists($mysqli, 'business_settings')) {
        return $defaults;
    }

    $result = $mysqli->query("SELECT setting_key, setting_value FROM business_settings WHERE is_active = 1");
    if (!$result instanceof mysqli_result) {
        return $defaults;
    }

    while ($row = $result->fetch_assoc()) {
        $key = (string)$row['setting_key'];
        if (array_key_exists($key, $defaults)) {
            $defaults[$key] = (string)$row['setting_value'];
        }
    }

    return $defaults;
}

function getServicePriceMap(?mysqli $mysqli = null): array
{
    if ($mysqli instanceof mysqli) {
        $catalog = getBookingCatalog($mysqli);
        $map = [];
        foreach ($catalog['services'] as $serviceKey => $serviceMeta) {
            $map[$serviceKey] = (float)$serviceMeta['base_price'];
        }
        if ($map) {
            return $map;
        }
    }

    $defaults = getDefaultBookingCatalog();
    $map = [];
    foreach ($defaults['services'] as $serviceKey => $serviceMeta) {
        $map[$serviceKey] = (float)$serviceMeta['base_price'];
    }
    return $map;
}

function getServiceDepositMap(?mysqli $mysqli = null): array
{
    $deposits = [];
    foreach (getServicePriceMap($mysqli) as $service => $basePrice) {
        $deposits[$service] = round($basePrice * BOOKING_DEPOSIT_PERCENTAGE, 2);
    }
    return $deposits;
}

/**
 * =========================================
 * LOGGING & MONITORING SYSTEM
 * =========================================
 * Track all errors, payments, emails, and system events
 * Critical for production monitoring and debugging
 */

/**
 * Log system events to database
 * 
 * @param string $level - 'debug', 'info', 'warning', 'error', 'critical'
 * @param string $type - 'booking', 'payment', 'email', 'auth', 'system', 'database'
 * @param string $message - Human-readable message
 * @param array|null $context - Additional data (will be JSON encoded)
 * @param string|null $userIdentifier - User email/phone/ID
 */
function logSystemEvent(
    string $level,
    string $type,
    string $message,
    ?array $context = null,
    ?string $userIdentifier = null
): void {
    // Logging must never crash the app. If the DB is unavailable, log to the
    // PHP error log instead of dying.
    $mysqli = tryGetDbConnection();
    if (!($mysqli instanceof mysqli)) {
        error_log('[' . $level . '][' . $type . '] ' . $message);
        return;
    }

    try {
        // Ensure table exists
        if (!tableExists($mysqli, 'system_logs')) {
            $mysqli->close();
            return; // Silently fail if table doesn't exist yet
        }
        
        $contextJson = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;
        
        $stmt = $mysqli->prepare("
            INSERT INTO system_logs (log_level, log_type, message, context_data, user_identifier, ip_address, user_agent, request_uri)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            $mysqli->close();
            return;
        }
        
        $stmt->bind_param('ssssssss', $level, $type, $message, $contextJson, $userIdentifier, $ipAddress, $userAgent, $requestUri);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    } catch (Throwable $e) {
        // Silently fail - don't break the app if logging fails
        error_log("Logging failed: " . $e->getMessage());
    }
}

/**
 * Log booking-related events
 */
function logBooking(string $level, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent($level, 'booking', $message, $context, $userIdentifier);
}

/**
 * Log payment-related events
 */
function logPayment(string $level, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent($level, 'payment', $message, $context, $userIdentifier);
}

/**
 * Log email-related events
 */
function logEmail(string $level, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent($level, 'email', $message, $context, $userIdentifier);
}

/**
 * Log authentication-related events
 */
function logAuth(string $level, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent($level, 'auth', $message, $context, $userIdentifier);
}

/**
 * Log database errors
 */
function logDatabase(string $level, string $message, array $context = []): void
{
    logSystemEvent($level, 'database', $message, $context);
}

/**
 * Log general system errors
 */
function logSystem(string $level, string $message, array $context = []): void
{
    logSystemEvent($level, 'system', $message, $context);
}

/**
 * Quick helper: Log error
 */
function logError(string $type, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent('error', $type, $message, $context, $userIdentifier);
}

/**
 * Quick helper: Log critical error (requires immediate attention)
 */
function logCritical(string $type, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent('critical', $type, $message, $context, $userIdentifier);
}

/**
 * Quick helper: Log warning
 */
function logWarning(string $type, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent('warning', $type, $message, $context, $userIdentifier);
}

/**
 * Quick helper: Log info
 */
function logInfo(string $type, string $message, array $context = [], ?string $userIdentifier = null): void
{
    logSystemEvent('info', $type, $message, $context, $userIdentifier);
}

function getBookingDepositAmount($serviceOrSlot = '', ?array $serviceDepositMap = null): string
{
    $priceMap = is_array($serviceDepositMap) ? $serviceDepositMap : getServiceDepositMap();
    
    // Handle both string service key and array slot
    if (is_array($serviceOrSlot)) {
        $slot = $serviceOrSlot;
        $normalizedService = strtolower(trim($slot['service'] ?? ''));
        
        // Mobile service: add mobile fee + actual service price
        if ($normalizedService === 'mobile' && !empty($slot['mobileActualService'])) {
            $mobileActualService = strtolower(trim($slot['mobileActualService']));
            $mobileFee = isset($priceMap['mobile']) ? (float)$priceMap['mobile'] : 0;
            $actualServiceDeposit = isset($priceMap[$mobileActualService]) ? (float)$priceMap[$mobileActualService] : 0;
            $amount = $mobileFee + $actualServiceDeposit;
        } else {
            $amount = isset($priceMap[$normalizedService]) ? $priceMap[$normalizedService] : BOOKING_DEPOSIT_AMOUNT;
        }
    } else {
        // Legacy: string service key
        $normalizedService = strtolower(trim($serviceOrSlot));
        $amount = ($normalizedService !== '' && isset($priceMap[$normalizedService])) 
            ? $priceMap[$normalizedService] 
            : BOOKING_DEPOSIT_AMOUNT;
    }

    return number_format($amount, 2, '.', '');
}

function getDepositPercentageLabel(): string
{
    return (string)round(BOOKING_DEPOSIT_PERCENTAGE * 100) . '%';
}

function getMaxAdminUsers(): int
{
    return MAX_ADMIN_USERS;
}

function getMaxStylistsPerSlot(): int
{
    return MAX_STYLISTS_PER_SLOT;
}

function getServiceCapacity(string $serviceKey): int
{
    $catalog = getDefaultBookingCatalog();
    $cap = (int)(($catalog['services'][$serviceKey] ?? [])['capacity'] ?? 0);
    return $cap > 0 ? $cap : 1;
}

/**
 * =========================================================================
 * AVAILABILITY ENGINE (Phase 1) — single source of truth for "what's free?"
 * Used by availability.php (calendar), booking.php (pre-pay check) and
 * itn.php (final re-check). Mirrors the rules in docs/BOOKING-BLUEPRINT.md §3/§3A.
 * =========================================================================
 */

/** Day group for a date: 'sun' on Sundays, else 'weekday'. (Holidays: future.) */
function availabilityDayGroup(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return 'weekday';
    }
    return ((int)date('w', $ts) === 0) ? 'sun' : 'weekday';
}

/** A "two-on-one" service needs 2 stylists on 1 client (Braids). */
function availabilityIsTwoOnOne(string $serviceKey): bool
{
    return $serviceKey === 'braids';
}

/** Per-service capacity from the (DB-driven) catalog; defaults to 1. */
function availabilityServiceCapacity(array $catalog, string $serviceKey): int
{
    $cap = (int)(($catalog['services'][$serviceKey] ?? [])['capacity'] ?? 0);
    return $cap > 0 ? $cap : 1;
}

/** Eligible stylists [key => name] for a service + location. */
function availabilityEligibleStylists(array $catalog, string $serviceKey, string $locationKey): array
{
    $map = $catalog['serviceLocationStylists'][$serviceKey] ?? [];
    $keys = $map[$locationKey] ?? ($map['all'] ?? []);
    $names = $catalog['stylists'] ?? [];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = $names[$k] ?? $k;
    }
    return $out;
}

/** Extra R200 for before/after-hours slots. */
function availabilitySlotSurcharge(string $slotKey): float
{
    return in_array($slotKey, ['before-hours', 'after-hours'], true) ? 200.00 : 0.00;
}

/** Day-aware slot keys for a service from booking_service_slots. */
function availabilitySlotKeys(mysqli $mysqli, string $serviceKey, string $dayGroup): array
{
    $keys = [];
    $stmt = $mysqli->prepare(
        "SELECT ts.slot_key
         FROM booking_service_slots bss
         INNER JOIN booking_services bs ON bs.id = bss.service_id
         INNER JOIN booking_time_slots ts ON ts.id = bss.slot_id
         WHERE bs.service_key = ? AND bss.day_group = ?
           AND bss.is_active = 1 AND bs.is_active = 1 AND ts.is_active = 1
         ORDER BY ts.db_time ASC"
    );
    if (!$stmt) {
        return $keys;
    }
    $stmt->bind_param('ss', $serviceKey, $dayGroup);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $keys[] = (string)$row['slot_key'];
    }
    $stmt->close();
    return $keys;
}

/**
 * Default time slots for services that have NO slots configured in
 * booking_service_slots, so every service is still bookable (mirrors the old
 * form, which offered standard hours for any service). Filtered to slot keys
 * that actually exist in the time-slot map.
 *   weekday: hourly 08:00–17:00   |   sun: 11:00–16:00 (Midrand Sunday hours)
 */
function availabilityDefaultSlotKeys(array $timeSlotMap, string $dayGroup): array
{
    $weekday = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
    $sun     = ['11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    $candidate = ($dayGroup === 'sun') ? $sun : $weekday;
    return array_values(array_filter($candidate, static fn($k) => isset($timeSlotMap[$k])));
}

/** Is the location operating on this date? (Copperleaf closed Sundays.) */
function availabilityLocationOpen(string $locationKey, string $date): bool
{
    $ts = strtotime($date);
    if ($ts === false) {
        return false;
    }
    $dow = (int)date('w', $ts); // 0 = Sunday
    if ($locationKey === 'copperleaf' && $dow === 0) {
        return false;
    }
    return true;
}

/**
 * Compute availability for a service + location across a date range.
 * Returns a structured array ready to JSON-encode for the calendar.
 */
function computeAvailability(mysqli $mysqli, string $serviceKey, string $locationKey, string $dateFrom, string $dateTo): array
{
    $catalog = getBookingCatalog($mysqli);
    $services = $catalog['services'] ?? [];
    $locations = $catalog['locations'] ?? [];
    if (!isset($services[$serviceKey])) {
        return ['error' => 'unknown_service'];
    }
    if (!isset($locations[$locationKey])) {
        return ['error' => 'unknown_location'];
    }

    $timeSlotMap = $catalog['timeSlotMap'] ?? [];
    $capacity = availabilityServiceCapacity($catalog, $serviceKey);
    $twoOnOne = availabilityIsTwoOnOne($serviceKey);
    $eligible = availabilityEligibleStylists($catalog, $serviceKey, $locationKey);

    $slotKeysByGroup = [
        'weekday' => availabilitySlotKeys($mysqli, $serviceKey, 'weekday'),
        'sun'     => availabilitySlotKeys($mysqli, $serviceKey, 'sun'),
    ];
    // Services with no configured slots fall back to a standard schedule so they
    // are still bookable (e.g. wig install, hair colour, relaxer, undo, mobile).
    if (empty($slotKeysByGroup['weekday']) && empty($slotKeysByGroup['sun'])) {
        $slotKeysByGroup['weekday'] = availabilityDefaultSlotKeys($timeSlotMap, 'weekday');
        $slotKeysByGroup['sun']     = availabilityDefaultSlotKeys($timeSlotMap, 'sun');
    }

    // ---- Range-load bookings, holds, blocks (3 queries) ----
    $busyStylist = [];   // [date][dbtime][stylistKeyLower] = true (any service)
    $svcCount = [];      // [date][dbtime] = count for THIS service
    $bStmt = $mysqli->prepare(
        "SELECT appointment_date, appointment_time, service, preferred_stylist, helper_stylist
         FROM salon_bookings
         WHERE appointment_date BETWEEN ? AND ? AND status IN ('paid','pending_cash')"
    );
    $bStmt->bind_param('ss', $dateFrom, $dateTo);
    $bStmt->execute();
    $bRes = $bStmt->get_result();
    while ($row = $bRes->fetch_assoc()) {
        $d = (string)$row['appointment_date'];
        $t = (string)$row['appointment_time'];
        foreach (['preferred_stylist', 'helper_stylist'] as $col) {
            $s = strtolower(trim((string)($row[$col] ?? '')));
            if ($s !== '' && $s !== 'no-preference') {
                $busyStylist[$d][$t][$s] = true;
            }
        }
        if ((string)$row['service'] === $serviceKey) {
            $svcCount[$d][$t] = ($svcCount[$d][$t] ?? 0) + 1;
        }
    }
    $bStmt->close();

    // Holds (live, non-expired). Holds count toward the service slot + busy the stylist.
    $hStmt = $mysqli->prepare(
        "SELECT preferred_date, preferred_time, service, stylist
         FROM booking_payment_attempts
         WHERE status = 'initiated' AND hold_expires_at IS NOT NULL AND hold_expires_at > NOW()
           AND preferred_date BETWEEN ? AND ?"
    );
    $hStmt->bind_param('ss', $dateFrom, $dateTo);
    $hStmt->execute();
    $hRes = $hStmt->get_result();
    while ($row = $hRes->fetch_assoc()) {
        $d = (string)$row['preferred_date'];
        $t = (string)$row['preferred_time'];
        $s = strtolower(trim((string)($row['stylist'] ?? '')));
        if ($s !== '' && $s !== 'no-preference') {
            $busyStylist[$d][$t][$s] = true;
        }
        if ((string)$row['service'] === $serviceKey) {
            $svcCount[$d][$t] = ($svcCount[$d][$t] ?? 0) + 1;
        }
    }
    $hStmt->close();

    // Blocks for this location.
    $dayBlocked = [];          // [date] = true
    $slotBlockedAll = [];      // [date][dbtime] = true
    $stylistBlocked = [];      // [date][dbtime][stylistLower] = true
    if (tableExists($mysqli, 'booking_slot_blocks')) {
        $kStmt = $mysqli->prepare(
            "SELECT block_date, block_time, stylist
             FROM booking_slot_blocks
             WHERE location = ? AND block_date BETWEEN ? AND ?"
        );
        $kStmt->bind_param('sss', $locationKey, $dateFrom, $dateTo);
        $kStmt->execute();
        $kRes = $kStmt->get_result();
        while ($row = $kRes->fetch_assoc()) {
            $d = (string)$row['block_date'];
            $t = $row['block_time'] !== null ? (string)$row['block_time'] : null;
            $s = strtolower(trim((string)($row['stylist'] ?? '')));
            if ($t === null && $s === '') {
                $dayBlocked[$d] = true;
            } elseif ($t !== null && $s === '') {
                $slotBlockedAll[$d][$t] = true;
            } elseif ($t !== null && $s !== '') {
                $stylistBlocked[$d][$t][$s] = true;
            }
        }
        $kStmt->close();
    }

    // ---- Walk the date range ----
    $tz = new DateTimeZone(APP_TIMEZONE);
    $today = new DateTimeImmutable('today', $tz);
    $bufferedNow = (new DateTimeImmutable('now', $tz))->modify('+1 hour');
    $cursor = DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom, $tz);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $dateTo, $tz);
    if ($cursor === false || $end === false) {
        return ['error' => 'bad_date_range'];
    }
    $cursor = $cursor->setTime(0, 0, 0);
    $end = $end->setTime(0, 0, 0);

    $days = [];
    $guard = 0;
    while ($cursor <= $end && $guard < 100) {
        $guard++;
        $dateStr = $cursor->format('Y-m-d');
        $dow = (int)$cursor->format('w');
        $dayGroup = ($dow === 0) ? 'sun' : 'weekday';
        $slotKeys = $slotKeysByGroup[$dayGroup] ?? [];

        $dayEntry = ['date' => $dateStr, 'weekday' => strtolower($cursor->format('D')), 'state' => 'open', 'slots' => []];

        if ($cursor < $today) {
            $dayEntry['state'] = 'past';
            $days[] = $dayEntry;
            $cursor = $cursor->modify('+1 day');
            continue;
        }
        if (!availabilityLocationOpen($locationKey, $dateStr) || !empty($dayBlocked[$dateStr]) || empty($slotKeys)) {
            $dayEntry['state'] = 'closed';
            $days[] = $dayEntry;
            $cursor = $cursor->modify('+1 day');
            continue;
        }

        $openCount = 0;
        $hasFutureSlot = false; // any slot still ahead of "now" today (for past-vs-full distinction)
        foreach ($slotKeys as $slotKey) {
            $dbTime = (string)($timeSlotMap[$slotKey]['db'] ?? '');
            if ($dbTime === '') {
                continue;
            }
            $label = (string)($timeSlotMap[$slotKey]['label'] ?? $slotKey);
            $surcharge = availabilitySlotSurcharge($slotKey);

            $blocked = !empty($slotBlockedAll[$dateStr][$dbTime]);

            // Past-time guard for today.
            $pastTime = false;
            if ($dateStr === $today->format('Y-m-d')) {
                $slotDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $dbTime, $tz);
                if ($slotDt !== false && $slotDt <= $bufferedNow) {
                    $pastTime = true;
                }
            }

            $stylistList = [];
            $freeCount = 0;
            foreach ($eligible as $key => $name) {
                $kl = strtolower($key);
                $free = empty($busyStylist[$dateStr][$dbTime][$kl])
                    && empty($stylistBlocked[$dateStr][$dbTime][$kl]);
                if ($free) {
                    $freeCount++;
                }
                $stylistList[] = ['key' => $key, 'name' => $name, 'free' => $free];
            }

            $clientsBooked = (int)($svcCount[$dateStr][$dbTime] ?? 0);
            $needed = $twoOnOne ? 2 : 1;
            $open = !$blocked && !$pastTime && $clientsBooked < $capacity && $freeCount >= $needed;
            if ($open) {
                $openCount++;
            }
            if (!$pastTime) {
                $hasFutureSlot = true;
            }

            $dayEntry['slots'][] = [
                'time' => $slotKey,
                'db_time' => $dbTime,
                'label' => $label,
                'open' => $open,
                'clients_booked' => $clientsBooked,
                'capacity' => $capacity,
                'surcharge' => $surcharge,
                'blocked' => $blocked,
                'stylists' => $stylistList,
            ];
        }

        $totalSlots = count($dayEntry['slots']);
        if ($totalSlots === 0) {
            $dayEntry['state'] = 'closed';
        } elseif ($openCount === 0) {
            // No open slots. If it's today and every remaining slot has simply elapsed
            // (none in the future, none actually booked out), it's "past" for today —
            // not a misleading red "Full".
            $dayEntry['state'] = (!$hasFutureSlot && $dateStr === $today->format('Y-m-d')) ? 'past' : 'full';
        } elseif ($openCount <= (int)ceil($totalSlots / 3)) {
            $dayEntry['state'] = 'nearly_full';
        } else {
            $dayEntry['state'] = 'open';
        }

        $days[] = $dayEntry;
        $cursor = $cursor->modify('+1 day');
    }

    // Add-ons for this service.
    $addons = [];
    if (tableExists($mysqli, 'booking_addons') && tableExists($mysqli, 'booking_addon_services')) {
        $aStmt = $mysqli->prepare(
            "SELECT a.addon_key, a.label, a.price
             FROM booking_addons a
             INNER JOIN booking_addon_services xs ON xs.addon_id = a.id
             INNER JOIN booking_services s ON s.id = xs.service_id
             WHERE s.service_key = ? AND a.is_active = 1 AND xs.is_active = 1
             ORDER BY a.sort_order ASC"
        );
        if ($aStmt) {
            $aStmt->bind_param('s', $serviceKey);
            $aStmt->execute();
            $aRes = $aStmt->get_result();
            while ($row = $aRes->fetch_assoc()) {
                $addons[] = ['key' => (string)$row['addon_key'], 'label' => (string)$row['label'], 'price' => (float)$row['price']];
            }
            $aStmt->close();
        }
    }

    return [
        'service' => $serviceKey,
        'service_label' => (string)($services[$serviceKey]['label'] ?? $serviceKey),
        'location' => $locationKey,
        'capacity' => $capacity,
        'model' => $twoOnOne ? 'two-on-one' : 'capacity-n',
        'deposit_percentage' => BOOKING_DEPOSIT_PERCENTAGE,
        'addons' => $addons,
        'days' => $days,
    ];
}

/**
 * DEMO availability — same output shape as computeAvailability(), but computed
 * entirely from the in-memory default catalog with NO database. Used when the DB
 * is offline (e.g. the Vercel serverless demo) so the calendar still works.
 * There are no real bookings/holds/blocks, so every slot is open except past
 * dates, elapsed times today, and days the location is closed.
 */
function computeAvailabilityDemo(string $serviceKey, string $locationKey, string $dateFrom, string $dateTo): array
{
    $catalog = getDefaultBookingCatalog();
    $services = $catalog['services'] ?? [];
    $locations = $catalog['locations'] ?? [];
    if (!isset($services[$serviceKey])) {
        return ['error' => 'unknown_service'];
    }
    if (!isset($locations[$locationKey])) {
        return ['error' => 'unknown_location'];
    }

    $timeSlotMap = $catalog['timeSlotMap'] ?? [];
    $capacity = availabilityServiceCapacity($catalog, $serviceKey);
    $twoOnOne = availabilityIsTwoOnOne($serviceKey);
    $eligible = availabilityEligibleStylists($catalog, $serviceKey, $locationKey);

    // Prefer the service's configured slot keys; fall back to the standard schedule.
    $svcSlotKeys = array_values(array_filter(
        $services[$serviceKey]['slot_keys'] ?? [],
        static fn($k) => isset($timeSlotMap[$k])
    ));
    $weekdayKeys = $svcSlotKeys ?: availabilityDefaultSlotKeys($timeSlotMap, 'weekday');
    $sunDefault = availabilityDefaultSlotKeys($timeSlotMap, 'sun');
    $sunKeys = $svcSlotKeys
        ? array_values(array_filter($svcSlotKeys, static fn($k) => in_array($k, $sunDefault, true)))
        : $sunDefault;
    if (empty($sunKeys)) {
        $sunKeys = $sunDefault;
    }
    $slotKeysByGroup = ['weekday' => $weekdayKeys, 'sun' => $sunKeys];

    $tz = new DateTimeZone(APP_TIMEZONE);
    $today = new DateTimeImmutable('today', $tz);
    $bufferedNow = (new DateTimeImmutable('now', $tz))->modify('+1 hour');
    $cursor = DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom, $tz);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $dateTo, $tz);
    if ($cursor === false || $end === false) {
        return ['error' => 'bad_date_range'];
    }
    $cursor = $cursor->setTime(0, 0, 0);
    $end = $end->setTime(0, 0, 0);

    $days = [];
    $guard = 0;
    while ($cursor <= $end && $guard < 100) {
        $guard++;
        $dateStr = $cursor->format('Y-m-d');
        $dow = (int)$cursor->format('w');
        $slotKeys = $slotKeysByGroup[($dow === 0) ? 'sun' : 'weekday'] ?? [];
        $dayEntry = ['date' => $dateStr, 'weekday' => strtolower($cursor->format('D')), 'state' => 'open', 'slots' => []];

        if ($cursor < $today) {
            $dayEntry['state'] = 'past';
            $days[] = $dayEntry;
            $cursor = $cursor->modify('+1 day');
            continue;
        }
        if (!availabilityLocationOpen($locationKey, $dateStr) || empty($slotKeys)) {
            $dayEntry['state'] = 'closed';
            $days[] = $dayEntry;
            $cursor = $cursor->modify('+1 day');
            continue;
        }

        $openCount = 0;
        $hasFutureSlot = false;
        foreach ($slotKeys as $slotKey) {
            $dbTime = (string)($timeSlotMap[$slotKey]['db'] ?? '');
            if ($dbTime === '') {
                continue;
            }
            $pastTime = false;
            if ($dateStr === $today->format('Y-m-d')) {
                $slotDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $dbTime, $tz);
                if ($slotDt !== false && $slotDt <= $bufferedNow) {
                    $pastTime = true;
                }
            }
            $stylistList = [];
            foreach ($eligible as $key => $name) {
                $stylistList[] = ['key' => $key, 'name' => $name, 'free' => true];
            }
            $needed = $twoOnOne ? 2 : 1;
            $open = !$pastTime && count($stylistList) >= $needed;
            if ($open) {
                $openCount++;
            }
            if (!$pastTime) {
                $hasFutureSlot = true;
            }
            $dayEntry['slots'][] = [
                'time' => $slotKey,
                'db_time' => $dbTime,
                'label' => (string)($timeSlotMap[$slotKey]['label'] ?? $slotKey),
                'open' => $open,
                'clients_booked' => 0,
                'capacity' => $capacity,
                'surcharge' => availabilitySlotSurcharge($slotKey),
                'blocked' => false,
                'stylists' => $stylistList,
            ];
        }

        $totalSlots = count($dayEntry['slots']);
        if ($totalSlots === 0) {
            $dayEntry['state'] = 'closed';
        } elseif ($openCount === 0) {
            $dayEntry['state'] = (!$hasFutureSlot && $dateStr === $today->format('Y-m-d')) ? 'past' : 'full';
        } elseif ($openCount <= (int)ceil($totalSlots / 3)) {
            $dayEntry['state'] = 'nearly_full';
        } else {
            $dayEntry['state'] = 'open';
        }

        $days[] = $dayEntry;
        $cursor = $cursor->modify('+1 day');
    }

    return [
        'service' => $serviceKey,
        'service_label' => (string)($services[$serviceKey]['label'] ?? $serviceKey),
        'location' => $locationKey,
        'capacity' => $capacity,
        'model' => $twoOnOne ? 'two-on-one' : 'capacity-n',
        'deposit_percentage' => BOOKING_DEPOSIT_PERCENTAGE,
        'addons' => [],
        'days' => $days,
        'demo' => true,
    ];
}

/**
 * Final re-check that a single slot is bookable for a chosen lead stylist.
 * Used by booking.php (pre-pay) and itn.php (post-pay). Returns:
 *   ['ok' => bool, 'reason' => string, 'helper' => ?string]
 * Options:
 *   'catalog'            => pre-fetched catalog (avoids re-query)
 *   'ignore_holds'       => true at ITN time (payer is finalizing)
 *   'exclude_payment_id' => ignore this payment's own holds
 *   'extra_busy'         => array of lowercased stylist keys already taken this submission at this slot
 *   'extra_slot_count'   => int clients already counted this submission for this service+slot
 */
function availabilityRecheckSlot(mysqli $mysqli, string $serviceKey, string $locationKey, string $date, string $dbTime, string $leadStylist, array $opts = []): array
{
    $catalog = $opts['catalog'] ?? getBookingCatalog($mysqli);
    $capacity = availabilityServiceCapacity($catalog, $serviceKey);
    $twoOnOne = availabilityIsTwoOnOne($serviceKey);
    $eligible = availabilityEligibleStylists($catalog, $serviceKey, $locationKey);
    $lead = strtolower(trim($leadStylist));
    $extraSlotCount = (int)($opts['extra_slot_count'] ?? 0);

    $hasBlocks = tableExists($mysqli, 'booking_slot_blocks');

    // Whole-day / whole-slot block?
    if ($hasBlocks) {
        $blkStmt = $mysqli->prepare(
            "SELECT COUNT(*) AS n FROM booking_slot_blocks
             WHERE location = ? AND block_date = ? AND stylist IS NULL
               AND (block_time IS NULL OR block_time = ?)"
        );
        $blkStmt->bind_param('sss', $locationKey, $date, $dbTime);
        $blkStmt->execute();
        if ((int)$blkStmt->get_result()->fetch_assoc()['n'] > 0) {
            $blkStmt->close();
            return ['ok' => false, 'reason' => 'blocked', 'helper' => null];
        }
        $blkStmt->close();
    }

    // Service-specific slot count.
    $cntStmt = $mysqli->prepare(
        "SELECT COUNT(*) AS n FROM salon_bookings
         WHERE appointment_date = ? AND appointment_time = ? AND service = ?
           AND status IN ('paid','pending_cash')"
    );
    $cntStmt->bind_param('sss', $date, $dbTime, $serviceKey);
    $cntStmt->execute();
    $slotCount = (int)$cntStmt->get_result()->fetch_assoc()['n'] + $extraSlotCount;
    $cntStmt->close();

    // Live holds (other payments) also consume the service slot, unless ignored (ITN).
    if (empty($opts['ignore_holds'])) {
        $excludePidCnt = (string)($opts['exclude_payment_id'] ?? '');
        $hcStmt = $mysqli->prepare(
            "SELECT COUNT(*) AS n FROM booking_payment_attempts
             WHERE preferred_date = ? AND preferred_time = ? AND service = ?
               AND status = 'initiated' AND hold_expires_at IS NOT NULL AND hold_expires_at > NOW()
               AND m_payment_id <> ?"
        );
        $hcStmt->bind_param('ssss', $date, $dbTime, $serviceKey, $excludePidCnt);
        $hcStmt->execute();
        $slotCount += (int)$hcStmt->get_result()->fetch_assoc()['n'];
        $hcStmt->close();
    }

    if ($slotCount >= $capacity) {
        return ['ok' => false, 'reason' => 'slot_full', 'helper' => null];
    }

    // Busy stylists at this slot (any service): bookings + (optionally) live holds.
    $busy = [];
    $bsStmt = $mysqli->prepare(
        "SELECT preferred_stylist, helper_stylist FROM salon_bookings
         WHERE appointment_date = ? AND appointment_time = ? AND status IN ('paid','pending_cash')"
    );
    $bsStmt->bind_param('ss', $date, $dbTime);
    $bsStmt->execute();
    $bsRes = $bsStmt->get_result();
    while ($row = $bsRes->fetch_assoc()) {
        foreach (['preferred_stylist', 'helper_stylist'] as $col) {
            $s = strtolower(trim((string)($row[$col] ?? '')));
            if ($s !== '' && $s !== 'no-preference') {
                $busy[$s] = true;
            }
        }
    }
    $bsStmt->close();

    if (empty($opts['ignore_holds'])) {
        $excludePid = (string)($opts['exclude_payment_id'] ?? '');
        $hsStmt = $mysqli->prepare(
            "SELECT stylist FROM booking_payment_attempts
             WHERE preferred_date = ? AND preferred_time = ?
               AND status = 'initiated' AND hold_expires_at IS NOT NULL AND hold_expires_at > NOW()
               AND m_payment_id <> ?"
        );
        $hsStmt->bind_param('sss', $date, $dbTime, $excludePid);
        $hsStmt->execute();
        $hsRes = $hsStmt->get_result();
        while ($row = $hsRes->fetch_assoc()) {
            $s = strtolower(trim((string)($row['stylist'] ?? '')));
            if ($s !== '' && $s !== 'no-preference') {
                $busy[$s] = true;
            }
        }
        $hsStmt->close();
    }

    foreach ((array)($opts['extra_busy'] ?? []) as $s) {
        $busy[strtolower(trim((string)$s))] = true;
    }

    // Stylist-specific blocks at this slot.
    if ($hasBlocks) {
        $sbStmt = $mysqli->prepare(
            "SELECT stylist FROM booking_slot_blocks
             WHERE location = ? AND block_date = ? AND block_time = ? AND stylist IS NOT NULL"
        );
        $sbStmt->bind_param('sss', $locationKey, $date, $dbTime);
        $sbStmt->execute();
        $sbRes = $sbStmt->get_result();
        while ($row = $sbRes->fetch_assoc()) {
            $busy[strtolower(trim((string)$row['stylist']))] = true;
        }
        $sbStmt->close();
    }

    // Free eligible stylists.
    $freeKeys = [];
    foreach (array_keys($eligible) as $key) {
        if (empty($busy[strtolower($key)])) {
            $freeKeys[] = strtolower($key);
        }
    }

    $leadGiven = ($lead !== '' && $lead !== 'no-preference');
    if ($leadGiven) {
        if (!isset($eligible[$lead]) && !in_array($lead, array_map('strtolower', array_keys($eligible)), true)) {
            return ['ok' => false, 'reason' => 'stylist_not_eligible', 'helper' => null];
        }
        if (!in_array($lead, $freeKeys, true)) {
            return ['ok' => false, 'reason' => 'stylist_taken', 'helper' => null];
        }
    }

    if ($twoOnOne) {
        // Need >= 2 free braiders; pick a helper distinct from the lead.
        if (count($freeKeys) < 2) {
            return ['ok' => false, 'reason' => 'need_two_braiders', 'helper' => null];
        }
        $leadResolved = $leadGiven ? $lead : $freeKeys[0];
        $helper = null;
        foreach ($freeKeys as $fk) {
            if ($fk !== $leadResolved) {
                $helper = $fk;
                break;
            }
        }
        if ($helper === null) {
            return ['ok' => false, 'reason' => 'need_two_braiders', 'helper' => null];
        }
        return ['ok' => true, 'reason' => '', 'helper' => $helper, 'lead' => $leadResolved];
    }

    // Capacity-n service: at least one free stylist must exist.
    if (count($freeKeys) < 1) {
        return ['ok' => false, 'reason' => 'no_stylist_free', 'helper' => null];
    }
    return ['ok' => true, 'reason' => '', 'helper' => null];
}

/**
 * Validate selected add-on keys against what's allowed for a service.
 * Returns ['keys' => [...valid], 'json' => string|null, 'total' => float (full price)].
 */
/**
 * Active add-ons available for a service (key/label/price), ordered by sort_order.
 * Used to show add-ons in Step 1 of book.php (before the calendar) without an
 * availability call. Mirrors the add-on query inside computeAvailability().
 */
function getServiceAddons(mysqli $mysqli, string $serviceKey): array
{
    $addons = [];
    if ($serviceKey === '' || !tableExists($mysqli, 'booking_addons') || !tableExists($mysqli, 'booking_addon_services')) {
        return $addons;
    }
    $stmt = $mysqli->prepare(
        "SELECT a.addon_key, a.label, a.price
         FROM booking_addons a
         INNER JOIN booking_addon_services xs ON xs.addon_id = a.id
         INNER JOIN booking_services s ON s.id = xs.service_id
         WHERE s.service_key = ? AND a.is_active = 1 AND xs.is_active = 1
         ORDER BY a.sort_order ASC"
    );
    if (!$stmt) {
        return $addons;
    }
    $stmt->bind_param('s', $serviceKey);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addons[] = ['key' => (string)$row['addon_key'], 'label' => (string)$row['label'], 'price' => (float)$row['price']];
    }
    $stmt->close();
    return $addons;
}

function resolveBookingAddons(mysqli $mysqli, string $serviceKey, array $selectedKeys): array
{
    $result = ['keys' => [], 'json' => null, 'total' => 0.0];
    $selectedKeys = array_values(array_unique(array_filter(array_map('strval', $selectedKeys))));
    if (!$selectedKeys || !tableExists($mysqli, 'booking_addons') || !tableExists($mysqli, 'booking_addon_services')) {
        return $result;
    }

    $allowed = [];
    $stmt = $mysqli->prepare(
        "SELECT a.addon_key, a.price FROM booking_addons a
         INNER JOIN booking_addon_services xs ON xs.addon_id = a.id
         INNER JOIN booking_services s ON s.id = xs.service_id
         WHERE s.service_key = ? AND a.is_active = 1 AND xs.is_active = 1"
    );
    if (!$stmt) {
        return $result;
    }
    $stmt->bind_param('s', $serviceKey);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $allowed[(string)$row['addon_key']] = (float)$row['price'];
    }
    $stmt->close();

    $validKeys = [];
    $total = 0.0;
    foreach ($selectedKeys as $k) {
        if (isset($allowed[$k])) {
            $validKeys[] = $k;
            $total += $allowed[$k];
        }
    }
    if (!$validKeys) {
        return $result;
    }
    return ['keys' => $validKeys, 'json' => json_encode($validKeys, JSON_UNESCAPED_SLASHES), 'total' => round($total, 2)];
}

/**
 * Validate + price the ADDITIONAL services in a "build your visit" multi-service
 * booking (owner spec 2026-06-18). These ride on the same date/time as the primary
 * (anchor) service; the salon arranges them. Each item's price is computed
 * server-side (never trusted from the client): base + hair-length surcharge + add-ons.
 *
 * @param array $rawItems  client items: [{service, subType, hairLength, braidSize,
 *                         cornrowLength, hairpieceColor, addons:[keys]}]
 * @return array ['items' => [...priced/clean...], 'total' => float, 'json' => string|null, 'errors' => []]
 */
function resolveAdditionalServices(mysqli $mysqli, array $rawItems, array $catalog): array
{
    $out = ['items' => [], 'total' => 0.0, 'json' => null, 'errors' => []];
    if (!$rawItems) {
        return $out;
    }
    if (count($rawItems) > 8) {
        $out['errors'][] = 'Too many additional services in one visit.';
        return $out;
    }

    $services = $catalog['services'] ?? [];
    $priceMap = [];
    foreach ($services as $key => $meta) {
        $priceMap[$key] = (float)($meta['base_price'] ?? 0);
    }

    $clean = [];
    $total = 0.0;
    foreach ($rawItems as $i => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $svc = strtolower(trim((string)($raw['service'] ?? '')));
        if ($svc === '' || !isset($services[$svc])) {
            $out['errors'][] = 'Additional service #' . ($i + 1) . ': invalid service.';
            continue;
        }
        $subType = substr(trim((string)($raw['subType'] ?? '')), 0, 100);
        $hairLength = substr(trim((string)($raw['hairLength'] ?? '')), 0, 100);
        $hairColour = substr(trim((string)($raw['hairpieceColor'] ?? '')), 0, 50);

        // Hair colour must be valid for the style (when that style carries colours).
        if (hairColourGroupFor($svc, $subType) !== '' && $hairColour !== ''
            && !in_array($hairColour, allowedHairColourValues($svc, $subType), true)) {
            $out['errors'][] = 'Additional service #' . ($i + 1) . ': invalid hair colour.';
            continue;
        }

        $addonKeys = [];
        if (!empty($raw['addons']) && is_array($raw['addons'])) {
            $addonKeys = array_map('strval', $raw['addons']);
        }
        $addonsResolved = resolveBookingAddons($mysqli, $svc, $addonKeys);

        // Full price from the owner price matrix (type + length), + add-ons.
        $itemPrice = getBookingItemPrice($catalog, $svc, $subType, $hairLength);
        $price = round($itemPrice + (float)$addonsResolved['total'], 2);
        $total += $price;

        $clean[] = [
            'service'        => $svc,
            'label'          => (string)($services[$svc]['label'] ?? $svc),
            'subType'        => $subType,
            'hairLength'     => $hairLength,
            'hairpieceColor' => $hairColour,
            'addons'         => $addonsResolved['keys'],
            'addons_total'   => (float)$addonsResolved['total'],
            'price'          => $price,
        ];
    }

    if ($out['errors']) {
        return $out;
    }

    $out['items'] = $clean;
    $out['total'] = round($total, 2);
    $out['json'] = $clean ? json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    return $out;
}

/**
 * Travel zones used for mobile-only services (nails, lashes).
 * Surcharge in ZAR added to the deposit for out-of-area bookings.
 * Edit suburb lists here to adjust zone boundaries.
 */
function getTravelZones(): array
{
    return [
        'zone_a' => [
            'label'    => 'Zone A (No travel fee)',
            'surcharge' => 0.00,
            'suburbs'  => [
                'midrand', 'centurion', 'halfway house', 'noordwyk', 'carlswald',
                'kyalami', 'waterfall', 'glen austin', 'vorna valley',
                'sunninghill', 'lonehill', 'rivonia', 'modderfontein',
            ],
        ],
        'zone_b' => [
            'label'    => 'Zone B (+R150 travel fee)',
            'surcharge' => 150.00,
            'suburbs'  => [
                'sandton', 'randburg', 'roodepoort', 'fourways', 'bromhof',
                'northgate', 'fairland', 'honeydew', 'kelvin', 'wynberg',
                'rosebank', 'parktown', 'westgate', 'florida', 'ruimsig',
            ],
        ],
        'zone_c' => [
            'label'    => 'Zone C (+R300 travel fee)',
            'surcharge' => 300.00,
            'suburbs'  => [
                'johannesburg', 'jburg', 'cbd', 'soweto', 'alexandra',
                'germiston', 'boksburg', 'benoni', 'krugersdorp', 'randfontein',
                'vanderbijlpark', 'vereeniging', 'springs', 'brakpan', 'nigel',
            ],
        ],
    ];
}

/**
 * Hair-extension colour ranges, grouped by the style the client is booking.
 * Single source of truth for both the booking picker (book.php) and the
 * server-side validation (booking.php). Owner spec (2026-06-18): Braids/Cornrows,
 * French Curl and Goddess Braids each carry their own colour range.
 *
 * Stored value = the salon's actual colour code (e.g. "1/30", "C14") so it reads
 * the same in the admin detail view and the CSV export. Column: hairpiece_color.
 */
function getHairColourGroups(): array
{
    return [
        'braids-cornrows' => [
            ['value' => '1',     'label' => '1 — Black'],
            ['value' => '2',     'label' => '2 — Natural Black'],
            ['value' => '4',     'label' => '4 — Natural Brown'],
            ['value' => '30',    'label' => '30 — Copper Brown'],
            ['value' => '33',    'label' => '33 — Dark Brown'],
            ['value' => '27',    'label' => '27 — Golden Blonde'],
            ['value' => '1/30',  'label' => '1/30 — Ombré Copper Brown'],
            ['value' => '1/27',  'label' => '1/27 — Ombré Golden Blonde'],
        ],
        'french-curl' => [
            ['value' => '1/30',  'label' => '1/30 — Ombré Black & Copper Brown'],
            ['value' => '1/27',  'label' => '1/27 — Ombré Black & Golden Brown'],
            ['value' => '1',     'label' => '1 — Black'],
            ['value' => 'C14',   'label' => 'C14 — 3 Toned'],
            ['value' => '27',    'label' => '27 — Golden Blonde'],
            ['value' => '30',    'label' => '30 — Copper Brown'],
        ],
        'goddess-braids' => [
            ['value' => '1',      'label' => 'Colour 1 — Black'],
            ['value' => '2',      'label' => 'Colour 2 — Natural Black'],
            ['value' => '4',      'label' => 'Colour 4 — Dark Brown'],
            ['value' => '30',     'label' => 'Colour 30 — Copper Brown'],
            ['value' => '27',     'label' => 'Colour 27 — Golden Blonde'],
            ['value' => '27/613', 'label' => 'Colour 27/613 — Colour Mix'],
            ['value' => '39',     'label' => 'Colour 39 — Burgundy'],
        ],
    ];
}

/**
 * Which colour range (if any) applies to a service + braids sub-type.
 * Returns '' when the service carries no hair-colour choice.
 */
function hairColourGroupFor(string $service, string $subType): string
{
    if ($service === 'cornrows') {
        return 'braids-cornrows';
    }
    if ($service === 'braids') {
        if (strpos($subType, 'goddess') !== false) {
            return 'goddess-braids';
        }
        if (strpos($subType, 'french-curl') !== false) {
            return 'french-curl';
        }
        return 'braids-cornrows';
    }
    return '';
}

/**
 * Flat list of valid colour codes for a service + sub-type (server-side validation).
 * Empty array means "no colour choice for this service".
 */
function allowedHairColourValues(string $service, string $subType): array
{
    $group = hairColourGroupFor($service, $subType);
    if ($group === '') {
        return [];
    }
    $values = [];
    foreach (getHairColourGroups()[$group] ?? [] as $opt) {
        $values[] = (string)$opt['value'];
    }
    return $values;
}

/**
 * Human label for a stored payment_method (admin views, emails).
 */
function paymentMethodLabel(string $method): string
{
    switch ($method) {
        case 'online_full':    return 'Online — Paid in full';
        case 'online_deposit': return 'Online — 50% deposit';
        case 'cash_50':        return 'Cash — 50% deposit (legacy)';
        default:               return $method !== '' ? $method : 'Online — 50% deposit';
    }
}

function getTravelSurcharge(string $address): float
{
    $lower = strtolower(trim($address));
    if ($lower === '') {
        return 0.00;
    }
    foreach (getTravelZones() as $zone) {
        foreach ($zone['suburbs'] as $suburb) {
            if (strpos($lower, $suburb) !== false) {
                return (float)$zone['surcharge'];
            }
        }
    }
    // Unknown zone — no automatic surcharge; admin can adjust
    return 0.00;
}

/**
 * True when address autocomplete can run in the browser (a browser key is set).
 */
function mapsBrowserEnabled(): bool
{
    return trim((string)GOOGLE_MAPS_BROWSER_KEY) !== '';
}

/**
 * True when the server can measure driving distance (a server key is set).
 */
function mapsServerEnabled(): bool
{
    return trim((string)GOOGLE_MAPS_SERVER_KEY) !== '';
}

/**
 * One-way driving distance (km) from the Midrand studio to a Google place_id,
 * via the Distance Matrix API. Returns null on any failure so callers can fall
 * back to the zone surcharge. Never throws.
 */
function googleDrivingDistanceKm(string $destinationPlaceId): ?float
{
    $destinationPlaceId = trim($destinationPlaceId);
    if ($destinationPlaceId === '' || !mapsServerEnabled() || !extension_loaded('curl')) {
        return null;
    }

    $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
        'origins'      => TRAVEL_ORIGIN_ADDRESS,
        'destinations' => 'place_id:' . $destinationPlaceId,
        'mode'         => 'driving',
        'units'        => 'metric',
        'region'       => 'za',
        'key'          => GOOGLE_MAPS_SERVER_KEY,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        error_log('[travel] Distance Matrix HTTP failure (' . $httpCode . ')');
        return null;
    }

    $data = json_decode((string)$body, true);
    $element = $data['rows'][0]['elements'][0] ?? null;
    if (($data['status'] ?? '') !== 'OK' || !is_array($element) || ($element['status'] ?? '') !== 'OK') {
        error_log('[travel] Distance Matrix status: ' . ($data['status'] ?? '?') . ' / ' . ($element['status'] ?? '?'));
        return null;
    }

    $metres = (float)($element['distance']['value'] ?? 0);
    return $metres > 0 ? $metres / 1000.0 : null;
}

/**
 * Authoritative mobile travel fee for a destination.
 *   - With a place_id + a working server key → accurate per-km driving fee
 *     (round trip if TRAVEL_ROUND_TRIP), e.g. R10/km from the Midrand studio.
 *   - Otherwise → the existing zone surcharge keyed on the typed address.
 * Returns ['fee' => float, 'km' => float|null, 'source' => 'gmaps'|'zone'].
 * The fee is the FULL travel cost (added to the deposit in full, like the old zones).
 */
function computeMobileTravelFee(string $placeId, string $fallbackAddress): array
{
    $oneWayKm = googleDrivingDistanceKm($placeId);
    if ($oneWayKm !== null) {
        if (TRAVEL_MAX_KM > 0 && $oneWayKm > TRAVEL_MAX_KM) {
            // Out of auto-quote range — let the zone logic / admin handle it.
            return ['fee' => getTravelSurcharge($fallbackAddress), 'km' => $oneWayKm, 'source' => 'zone'];
        }
        $billableKm = $oneWayKm * (TRAVEL_ROUND_TRIP ? 2 : 1);
        $fee = round($billableKm * TRAVEL_RATE_PER_KM, 2);
        return ['fee' => $fee, 'km' => $oneWayKm, 'source' => 'gmaps'];
    }
    return ['fee' => getTravelSurcharge($fallbackAddress), 'km' => null, 'source' => 'zone'];
}

function getPaymentConfigIssues(): array
{
    $issues = [];

    if (PAYFAST_MERCHANT_ID === '') {
        $issues[] = 'PayFast merchant ID is not configured.';
    }

    if (PAYFAST_MERCHANT_KEY === '') {
        $issues[] = 'PayFast merchant key is not configured.';
    }

    if (!extension_loaded('curl')) {
        $issues[] = 'PHP cURL extension is not enabled.';
    }

    // In LIVE mode a passphrase is mandatory: without it, ITN/return signatures are
    // computable by anyone who knows the field values, defeating signature checks.
    // Refuse to initiate live payments rather than process them insecurely. The
    // customer-facing text is generic; the technical reason is logged for ops.
    if (!PAYFAST_SANDBOX && trim((string)PAYFAST_PASSPHRASE) === '') {
        error_log('[payfast] LIVE mode with empty PAYFAST_PASSPHRASE — refusing to initiate payments.');
        $issues[] = 'Online payments are temporarily unavailable. Please choose the cash deposit option or contact us.';
    }

    return $issues;
}

function ensurePaymentAttemptsTable(mysqli $mysqli): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS booking_payment_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            m_payment_id VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(30) NOT NULL,
            service VARCHAR(100) NOT NULL,
            location VARCHAR(100) NOT NULL,
            stylist VARCHAR(100) NOT NULL,
            sub_type VARCHAR(100) NULL,
            hair_length VARCHAR(100) NULL,
            braid_size VARCHAR(50) NULL,
            cornrow_length VARCHAR(50) NULL,
            hairpiece_color VARCHAR(50) NULL,
            preferred_date DATE NOT NULL,
            preferred_time TIME NOT NULL,
            notes TEXT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'online_deposit',
            status VARCHAR(20) NOT NULL DEFAULT 'initiated',
            booking_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bpa_payment_id (m_payment_id),
            INDEX idx_bpa_status (status),
            INDEX idx_bpa_date_time (preferred_date, preferred_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if ($mysqli->query($sql) !== true) {
        return false;
    }

    $columnUpgrades = [
        "ALTER TABLE booking_payment_attempts ADD COLUMN braid_size VARCHAR(50) NULL AFTER hair_length",
        "ALTER TABLE booking_payment_attempts ADD COLUMN cornrow_length VARCHAR(50) NULL AFTER braid_size",
        "ALTER TABLE booking_payment_attempts ADD COLUMN hairpiece_color VARCHAR(50) NULL AFTER cornrow_length",
        "ALTER TABLE booking_payment_attempts ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'online_deposit' AFTER amount",
        "ALTER TABLE booking_payment_attempts ADD COLUMN mobile_actual_service VARCHAR(100) NULL AFTER hairpiece_color",
        "ALTER TABLE booking_payment_attempts ADD COLUMN mobile_person_count VARCHAR(20) NULL AFTER mobile_actual_service",
        "ALTER TABLE booking_payment_attempts ADD COLUMN mobile_address TEXT NULL AFTER mobile_person_count",
        "ALTER TABLE booking_payment_attempts ADD COLUMN travel_surcharge DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER mobile_address",
        // Phase 1: slot hold + add-ons + Braids helper.
        "ALTER TABLE booking_payment_attempts ADD COLUMN hold_expires_at DATETIME NULL AFTER status",
        "ALTER TABLE booking_payment_attempts ADD COLUMN helper_stylist VARCHAR(100) NULL AFTER stylist",
        "ALTER TABLE booking_payment_attempts ADD COLUMN addons TEXT NULL AFTER travel_surcharge",
        "ALTER TABLE booking_payment_attempts ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER addons",
        // Multi-service "build your visit": additional same-day services (JSON) + their combined price.
        "ALTER TABLE booking_payment_attempts ADD COLUMN additional_services TEXT NULL AFTER addons_total",
        "ALTER TABLE booking_payment_attempts ADD COLUMN additional_services_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER additional_services"
    ];

    foreach ($columnUpgrades as $alterSql) {
        $mysqli->query($alterSql);
    }

    return true;
}

function ensureSalonBookingsSchema(mysqli $mysqli): bool
{
    if (!tableExists($mysqli, 'salon_bookings')) {
        return false;
    }

    $columnUpgrades = [
        "ALTER TABLE salon_bookings ADD COLUMN braid_size VARCHAR(50) NULL AFTER hair_length",
        "ALTER TABLE salon_bookings ADD COLUMN cornrow_length VARCHAR(50) NULL AFTER braid_size",
        "ALTER TABLE salon_bookings ADD COLUMN hairpiece_color VARCHAR(50) NULL AFTER cornrow_length",
        "ALTER TABLE salon_bookings ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'online_deposit' AFTER amount",
        "ALTER TABLE salon_bookings ADD COLUMN mobile_address TEXT NULL AFTER mobile_person_count",
        "ALTER TABLE salon_bookings ADD COLUMN travel_surcharge DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER mobile_address",
        // Phase 1: add-ons + Braids helper. (status enum 'pending_cash' handled below.)
        "ALTER TABLE salon_bookings ADD COLUMN helper_stylist VARCHAR(100) NULL AFTER preferred_stylist",
        "ALTER TABLE salon_bookings ADD COLUMN addons TEXT NULL AFTER travel_surcharge",
        "ALTER TABLE salon_bookings ADD COLUMN addons_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER addons",
        // Multi-service "build your visit": additional same-day services (JSON) + their combined price.
        "ALTER TABLE salon_bookings ADD COLUMN additional_services TEXT NULL AFTER addons_total",
        "ALTER TABLE salon_bookings ADD COLUMN additional_services_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER additional_services"
    ];

    foreach ($columnUpgrades as $alterSql) {
        $mysqli->query($alterSql);
    }

    // Ensure the status enum allows cash bookings (idempotent MODIFY).
    $mysqli->query(
        "ALTER TABLE salon_bookings MODIFY COLUMN status " .
        "ENUM('pending','pending_cash','confirmed','paid','completed','cancelled') NOT NULL DEFAULT 'pending'"
    );

    ensureAvailabilitySupportTables($mysqli);

    return true;
}

/**
 * Create the block-slot + add-on tables if missing (self-healing across environments).
 */
function ensureAvailabilitySupportTables(mysqli $mysqli): void
{
    // Owner price list: per (service, subtype, length) pricing. Seeded by the pricing
    // migration; the code transcription (getDefaultServicePriceMatrix) is the fallback.
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS booking_service_prices (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_key  VARCHAR(100) NOT NULL,
            subtype_key  VARCHAR(120) NOT NULL DEFAULT '',
            length_key   VARCHAR(40)  NOT NULL DEFAULT '',
            length_label VARCHAR(60)  NOT NULL DEFAULT '',
            price        DECIMAL(10,2) NOT NULL,
            sort_order   INT UNSIGNED NOT NULL DEFAULT 100,
            is_active    TINYINT(1) NOT NULL DEFAULT 1,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_price (service_key, subtype_key, length_key),
            INDEX idx_price_lookup (service_key, subtype_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS booking_slot_blocks (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location    VARCHAR(100) NOT NULL,
            stylist     VARCHAR(100) NULL,
            block_date  DATE NOT NULL,
            block_time  TIME NULL,
            reason      VARCHAR(255) NULL,
            created_by  VARCHAR(100) NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_block_date_time (block_date, block_time),
            INDEX idx_block_stylist (stylist),
            INDEX idx_block_location (location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS booking_addons (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            addon_key   VARCHAR(60) NOT NULL UNIQUE,
            label       VARCHAR(120) NOT NULL,
            price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sort_order  INT UNSIGNED NOT NULL DEFAULT 100,
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS booking_addon_services (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            addon_id    INT UNSIGNED NOT NULL,
            service_id  INT UNSIGNED NOT NULL,
            is_active   TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_addon_service (addon_id, service_id),
            INDEX idx_addon_services_service (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// Gate every web request through maintenance mode last, once all helpers/defines
// above are available. Admin pages, itn.php and CLI are exempt (see the function).
enforceMaintenanceMode();
