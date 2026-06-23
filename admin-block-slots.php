<?php
require_once __DIR__ . '/admin-functions.php';

requireAdminLogin();
$admin = requireAdminRole(['admin', 'manager']);

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    if (isset($_POST['add_block'])) {
        $res = createSlotBlock(
            (string)($_POST['location'] ?? ''),
            (string)($_POST['stylist'] ?? ''),
            (string)($_POST['block_date'] ?? ''),
            (string)($_POST['block_time'] ?? ''),
            (string)($_POST['reason'] ?? ''),
            (string)($admin['username'] ?? 'admin')
        );
        $flash = $res['message'];
        $flashOk = $res['success'];
    } elseif (isset($_POST['delete_block'])) {
        $ok = deleteSlotBlock((int)($_POST['block_id'] ?? 0));
        $flash = $ok ? 'Block removed.' : 'Could not remove block.';
        $flashOk = $ok;
    }
}

// Catalog for the form selects.
$mysqli = getDbConnection();
$catalog = getBookingCatalog($mysqli);
$mysqli->close();
$locations = $catalog['locations'] ?? [];
$stylists = $catalog['stylists'] ?? [];
$timeSlotMap = $catalog['timeSlotMap'] ?? [];

$blocks = getUpcomingSlotBlocks();

function bsTimeLabel(array $timeSlotMap, ?string $dbTime): string
{
    if ($dbTime === null || $dbTime === '') {
        return 'Whole day';
    }
    foreach ($timeSlotMap as $meta) {
        if (($meta['db'] ?? '') === $dbTime) {
            return (string)$meta['label'];
        }
    }
    return substr($dbTime, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Block Times — Bella CRM</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--gold:#c9a961;--bg:#f4f6fa;--card:#fff;--text:#1f2937;--muted:#8891a1;--border:#e5e8ef;}
    *{box-sizing:border-box;}
    body{margin:0;font-family:Montserrat,system-ui,sans-serif;background:var(--bg);color:var(--text);}
    .topbar{background:#181c24;color:#e5e7eb;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
    .topbar a{color:#c9a961;text-decoration:none;font-size:.85rem;}
    .topbar .brand{font-weight:700;letter-spacing:.05em;}
    .wrap{max-width:980px;margin:0 auto;padding:1.5rem;}
    h1{font-size:1.4rem;margin:.5rem 0 .25rem;}
    .lead{color:var(--muted);font-size:.9rem;margin:0 0 1.5rem;}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.4rem;margin-bottom:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.05);}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;}
    label{display:block;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:.35rem;}
    select,input{width:100%;padding:.65rem;border:1px solid var(--border);border-radius:8px;font-size:.95rem;background:#fff;color:var(--text);}
    .btn{background:var(--gold);color:#1a1a1a;border:none;border-radius:8px;padding:.7rem 1.3rem;font-weight:700;cursor:pointer;font-size:.9rem;}
    .btn-sm{background:#fff;border:1px solid #e0b4b4;color:#c0392b;padding:.4rem .8rem;border-radius:6px;cursor:pointer;font-size:.8rem;}
    .flash{padding:.8rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem;}
    .flash.ok{background:#e7f6ec;border:1px solid #aedcbf;color:#1d6f3f;}
    .flash.err{background:#fbeaea;border:1px solid #e5b4b4;color:#a3302a;}
    table{width:100%;border-collapse:collapse;font-size:.9rem;}
    th,td{text-align:left;padding:.7rem .6rem;border-bottom:1px solid var(--border);}
    th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
    .tag{display:inline-block;padding:.2rem .55rem;border-radius:999px;background:#eef1f7;color:#566;font-size:.75rem;}
    .empty{color:var(--muted);text-align:center;padding:2rem;}
    .hint{color:var(--muted);font-size:.8rem;margin-top:.4rem;}
    .actions{margin-top:1rem;display:flex;justify-content:flex-end;}
  </style>
</head>
<body>
  <div class="topbar">
    <span class="brand">Bella · Block Times</span>
    <span><a href="admin-dashboard.php">← Back to Dashboard</a> &nbsp; · &nbsp; <a href="admin-logout.php" style="color:#ef4444;">Logout</a></span>
  </div>

  <div class="wrap">
    <h1>Block Times</h1>
    <p class="lead">Block a stylist, a slot, or a whole day so it stops appearing as available (walk-ins, leave, breaks). The booking calendar updates instantly.</p>

    <?php if ($flash !== ''): ?>
      <div class="flash <?php echo $flashOk ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card">
      <h2 style="margin:0 0 1rem;font-size:1.05rem;">Add a block</h2>
      <form method="POST">
        <?php echo csrfField(); ?>
        <div class="grid">
          <div>
            <label>Location</label>
            <select name="location" required>
              <?php foreach ($locations as $key => $label): ?>
                <option value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Stylist</label>
            <select name="stylist">
              <option value="">All stylists (whole slot)</option>
              <?php foreach ($stylists as $key => $name): ?>
                <option value="<?php echo htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Date</label>
            <input type="date" name="block_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required />
          </div>
          <div>
            <label>Time</label>
            <select name="block_time">
              <option value="">Whole day</option>
              <?php foreach ($timeSlotMap as $slotKey => $meta): ?>
                <option value="<?php echo htmlspecialchars((string)$meta['db'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$meta['label'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-top:1rem;">
          <label>Reason (optional)</label>
          <input type="text" name="reason" maxlength="255" placeholder="e.g. Walk-in, staff leave, lunch break" />
          <p class="hint">Leave stylist as “All stylists” to block the whole slot. Leave time as “Whole day” to close the entire day at that location.</p>
        </div>
        <div class="actions">
          <button class="btn" type="submit" name="add_block" value="1">Add block</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2 style="margin:0 0 1rem;font-size:1.05rem;">Upcoming blocks (<?php echo count($blocks); ?>)</h2>
      <?php if (empty($blocks)): ?>
        <div class="empty">No upcoming blocks. Anything you add will appear here.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Date</th><th>Time</th><th>Location</th><th>Stylist</th><th>Reason</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($blocks as $b): ?>
              <tr>
                <td><?php echo htmlspecialchars(date('D, j M Y', strtotime((string)$b['block_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(bsTimeLabel($timeSlotMap, $b['block_time']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="tag"><?php echo htmlspecialchars((string)($locations[$b['location']] ?? $b['location']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars($b['stylist'] ? (string)($stylists[$b['stylist']] ?? $b['stylist']) : 'All stylists', ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="color:var(--muted);"><?php echo htmlspecialchars((string)($b['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align:right;">
                  <form method="POST" onsubmit="return confirm('Remove this block?');" style="display:inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="block_id" value="<?php echo (int)$b['id']; ?>" />
                    <button class="btn-sm" type="submit" name="delete_block" value="1">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
