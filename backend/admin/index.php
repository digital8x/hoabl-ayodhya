<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check session
if (!isset($_SESSION['hoabl_ayodhya_logged_in']) || $_SESSION['hoabl_ayodhya_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ===== FETCH LEADS & ANALYTICS =====
$leads = [];
$error = '';
$selected_interest = $_GET['interest'] ?? '';

// Analytics Counters
$stat_total = 0;
$stat_today = 0;
$stat_plots = 0;
$stat_visits = 0;
$stat_general = 0;

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Delete lead action
    if (isset($_GET['delete'])) {
        $delId = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$delId]);
        header("Location: index.php?msg=deleted" . (!empty($selected_interest) ? "&interest=" . urlencode($selected_interest) : ''));
        exit;
    }

    // Calculate dynamic analytics from DB
    $all_stats = $pdo->query("SELECT project_interest, created_at FROM leads")->fetchAll();
    $stat_total = count($all_stats);
    $stat_today = count(array_filter($all_stats, fn($l) => date('Y-m-d', strtotime($l['created_at'])) === date('Y-m-d')));
    $stat_plots = count(array_filter($all_stats, fn($l) => stripos($l['project_interest'], 'Plot') !== false));
    $stat_visits = count(array_filter($all_stats, fn($l) => stripos($l['project_interest'], 'Visit') !== false));
    $stat_general = count(array_filter($all_stats, fn($l) => stripos($l['project_interest'], 'Enquiry') !== false));

    // Build filtered leads query
    $query = "SELECT * FROM leads";
    $params = [];
    if (!empty($selected_interest)) {
        $query .= " WHERE project_interest = ?";
        $params[] = $selected_interest;
    }
    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'Database Connection Failed: ' . $e->getMessage();
    
    // Fallback to CSV lead log
    $csvFile = __DIR__ . '/../leads.csv';
    if (file_exists($csvFile)) {
        $fp = fopen($csvFile, 'r');
        $headers = fgetcsv($fp);
        $raw_leads = [];
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) === count($headers)) {
                $raw_leads[] = array_combine($headers, $row);
            }
        }
        fclose($fp);
        $raw_leads = array_reverse($raw_leads);

        $stat_total = count($raw_leads);
        $stat_today = count(array_filter($raw_leads, fn($l) => date('Y-m-d', strtotime($l['Date'] ?? '')) === date('Y-m-d')));
        $stat_plots = count(array_filter($raw_leads, fn($l) => stripos($l['Interest'] ?? '', 'Plot') !== false));
        $stat_visits = count(array_filter($raw_leads, fn($l) => stripos($l['Interest'] ?? '', 'Visit') !== false));
        $stat_general = count(array_filter($raw_leads, fn($l) => stripos($l['Interest'] ?? '', 'Enquiry') !== false));

        // Filter leads
        $leads = $raw_leads;
        if (!empty($selected_interest)) {
            $leads = array_filter($leads, fn($l) => ($l['Interest'] ?? '') === $selected_interest);
        }
        $leads = array_values($leads);
    }
}

// Unique categories list
$categories = [
    'HOABL Ayodhya - 1250 sq.ft Plot' => '1,250 Sq.Ft Plots',
    'HOABL Ayodhya - Site Visit' => 'VIP Site Visits',
    'HOABL Ayodhya - General Enquiry' => 'General Enquiries'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Leads Dashboard | HOABL Ayodhya</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --saffron: #f26e22;
      --saffron-light: #ff8c3a;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#03070f;color:#e8eaf0;min-height:100vh}
    .header{background:linear-gradient(135deg,#070e1b,#0c182d);padding:1.5rem 2rem;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
    .header h1{font-size:1.4rem;color:var(--saffron-light);display:flex;align-items:center;gap:0.6rem;font-weight:800;}
    .header-meta{color:#8898b5;font-size:0.85rem}
    .container{max-width:1400px;margin:0 auto;padding:2rem}
    
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.5rem;margin-bottom:2rem}
    .stat-card{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:1.5rem;text-align:center;backdrop-filter:blur(10px);}
    .stat-card .num{font-size:2rem;font-weight:800;color:var(--saffron-light)}
    .stat-card .lbl{font-size:0.75rem;color:#8898b5;text-transform:uppercase;letter-spacing:.08em;margin-top:.4rem}
    
    .actions{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center}
    .btn-download{background:linear-gradient(135deg,var(--saffron),var(--saffron-light));color:#03070f;font-weight:800;padding:.8rem 1.8rem;border-radius:50px;text-decoration:none;font-size:.9rem;transition:all .25s;border:none;cursor:pointer;box-shadow:0 5px 15px rgba(242,110,34,0.25);}
    .btn-download:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(242,110,34,0.45)}
    
    .search{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:.65rem 1rem;color:#e8eaf0;font-family:'Inter',sans-serif;font-size:.88rem;outline:none;flex:1;min-width:200px}
    .search:focus { border-color: var(--saffron); }
    .error{background:rgba(229,62,62,.1);border:1px solid rgba(229,62,62,.3);padding:.8rem 1.2rem;border-radius:10px;color:#fc8181;margin-bottom:1.5rem;font-size:.88rem}
    
    .table-wrap{overflow-x:auto;border-radius:16px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,0.01);}
    table{width:100%;border-collapse:collapse;min-width:1200px}
    thead{background:rgba(7,14,27,.9)}
    th{padding:1.1rem .9rem;text-align:left;font-size:.72rem;font-weight:700;color:var(--saffron-light);text-transform:uppercase;letter-spacing:.1em;white-space:nowrap}
    tbody tr{border-bottom:1px solid rgba(255,255,255,.03);transition:background .15s}
    tbody tr:hover{background:rgba(255,255,255,.02)}
    td{padding:.95rem .9rem;font-size:.84rem;color:#c0cce0;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    td.project{color:#f0d080;font-weight:600;max-width:260px}
    td.phone a{color:var(--saffron-light);text-decoration:none}
    td.phone a:hover{text-decoration:underline}
    td.msg{color:#8898b5;font-style:italic}
    
    .badge{display:inline-block;padding:.25rem .6rem;border-radius:20px;font-size:.67rem;font-weight:700;background:rgba(242,110,34,0.1);color:var(--saffron-light);border:1px solid rgba(242,110,34,0.2)}
    .empty{text-align:center;padding:4rem;color:#8898b5;font-size:0.95rem;}
    
    .filter-form {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1.5rem;
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      padding: 1.2rem;
      border-radius: 16px;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .filter-group {
      display: flex;
      align-items: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .filter-item {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }
    .filter-item label {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--saffron-light);
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .filter-item select {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      padding: .65rem 1rem;
      color: #e8eaf0;
      font-family: 'Inter', sans-serif;
      font-size: .88rem;
      outline: none;
      min-width: 200px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .filter-item select option {
      background: #070e1b;
      color: #e8eaf0;
    }
    .filter-item select:focus { border-color: var(--saffron); }
    
    .btn-clear {
      background: rgba(229, 62, 62, 0.1);
      color: #fc8181;
      font-weight: 600;
      padding: .65rem 1.2rem;
      border-radius: 10px;
      text-decoration: none;
      font-size: .88rem;
      border: 1px solid rgba(229, 62, 62, 0.15);
      transition: all 0.2s;
    }
    .btn-clear:hover { background: rgba(229, 62, 62, 0.2); }
    .search-wrap { flex: 1; min-width: 250px; display: flex; justify-content: flex-end; }
    
    .results-count { font-size: 0.84rem; color: #8898b5; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
    .results-count-badge { background: rgba(242,110,34,0.1); color: var(--saffron-light); padding: 0.2rem 0.6rem; border-radius: 20px; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(242,110,34,0.2); }
    
    footer { text-align: center; color: #404b5c; padding: 2rem 0; font-size: 0.8rem; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 4rem; }
  </style>
</head>
<body>
<div class="header">
  <h1>🦁 HOABL Ayodhya Leads Control</h1>
  <div style="display:flex; align-items:center; gap:1.5rem;">
    <span class="header-meta">Administrator Panel &nbsp;|&nbsp; <?= date('d M Y, h:i A') ?></span>
    <div style="display:flex; gap:0.5rem;">
      <a href="settings.php" style="background:rgba(242,110,34,0.1); color:var(--saffron-light); padding:0.4rem 1.2rem; border-radius:50px; text-decoration:none; font-size:0.75rem; font-weight:600; border:1px solid rgba(242,110,34,0.2);">Settings Config</a>
      <a href="index.php?logout=1" style="background:rgba(229,62,62,0.1); color:#fc8181; padding:0.4rem 1.2rem; border-radius:50px; text-decoration:none; font-size:0.75rem; font-weight:600; border:1px solid rgba(229,62,62,0.2);">Logout</a>
    </div>
  </div>
</div>

<div class="container">
  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?> (Operating fallback CSV storage records)</div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat-card"><div class="num"><?= $stat_total ?></div><div class="lbl">Total Enquiries</div></div>
    <div class="stat-card"><div class="num"><?= $stat_today ?></div><div class="lbl">Today's Leads</div></div>
    <div class="stat-card"><div class="num"><?= $stat_plots ?></div><div class="lbl">Villa Plots (1250)</div></div>
    <div class="stat-card"><div class="num"><?= $stat_visits ?></div><div class="lbl">Site Visits</div></div>
    <div class="stat-card"><div class="num"><?= $stat_general ?></div><div class="lbl">General Enquiries</div></div>
  </div>

  <form method="GET" action="index.php" class="filter-form">
    <div class="filter-group">
      <div class="filter-item">
        <label for="filter-interest">Filter by Interest</label>
        <select name="interest" id="filter-interest" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $val => $lbl): ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $selected_interest === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($selected_interest)): ?>
        <a href="index.php" class="btn-clear">Reset Filters</a>
      <?php endif; ?>
    </div>
    
    <div class="search-wrap">
      <input type="text" class="search" id="searchInput" placeholder="🔍 Search current logs..." oninput="filterTable()" />
    </div>
  </form>

  <?php
    $filtered_count = count($leads);
    $download_url = 'download.php' . (!empty($selected_interest) ? '?interest=' . urlencode($selected_interest) : '');
  ?>
  <div class="results-count">
    <span>Displaying</span>
    <span class="results-count-badge"><?= $filtered_count ?></span>
    <span>matching logs out of <?= $stat_total ?> total leads</span>
  </div>

  <div class="actions">
    <a href="<?= htmlspecialchars($download_url) ?>" class="btn-download">⬇️ Export Filtered Leads (CSV)</a>
  </div>

  <div class="table-wrap">
    <table id="leadsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Received Date</th>
          <th>Name</th>
          <th>Phone/WhatsApp</th>
          <th>Email Address</th>
          <th>Selected Interest</th>
          <th>Visitor Country</th>
          <th>Submit Device</th>
          <th>IP Coordinates</th>
          <th>Source Form</th>
          <th>Referrer</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
          <tr><td colspan="12" style="background:rgba(229,62,62,0.1); color:#fc8181; text-align:center; padding:0.6rem; font-weight: 600;">Lead record deleted successfully.</td></tr>
        <?php endif; ?>
        <?php if (empty($leads)): ?>
          <tr><td colspan="12" class="empty">No lead registrations logged. Launch marketing campaigns to start logging!</td></tr>
        <?php else: ?>
          <?php foreach ($leads as $i => $lead): ?>
          <?php 
            $dbFormat = isset($lead['created_at']);
            $dt = $dbFormat ? $lead['created_at'] : ($lead['Date'] ?? '');
            $nm = $dbFormat ? $lead['name'] : ($lead['Name'] ?? '');
            $ph = $dbFormat ? $lead['phone'] : ($lead['Phone'] ?? '');
            $em = $dbFormat ? $lead['email'] : ($lead['Email'] ?? '');
            $in = $dbFormat ? $lead['project_interest'] : ($lead['Interest'] ?? '');
            $sr = $dbFormat ? $lead['source_item'] : ($lead['Source'] ?? '');
            $rf = $dbFormat ? $lead['referral_url'] : ($lead['Referral URL'] ?? '');
            $cc = $dbFormat ? $lead['country_code'] : ($lead['Country'] ?? '');
            $ipAddr = $dbFormat ? $lead['ip_address'] : ($lead['IP'] ?? '');
            $dv = $dbFormat ? ($lead['device'] ?? 'Unknown') : ($lead['Device'] ?? 'Unknown');
          ?>
          <tr>
            <td><span class="badge"><?= $filtered_count - $i ?></span></td>
            <td>
              <?= htmlspecialchars(date('d M Y', strtotime($dt))) ?><br />
              <small style="color:#6b7a99"><?= htmlspecialchars(date('h:i A', strtotime($dt))) ?></small>
            </td>
            <td><strong><?= htmlspecialchars($nm) ?></strong></td>
            <td class="phone"><a href="tel:<?= htmlspecialchars($ph) ?>"><?= htmlspecialchars($ph) ?></a></td>
            <td><small><?= htmlspecialchars($em) ?></small></td>
            <td class="project"><?= htmlspecialchars($in) ?></td>
            <td>
              <span style="font-weight:bold; color:var(--saffron-light)"><?= htmlspecialchars($cc) ?></span>
            </td>
            <td><small><?= htmlspecialchars($dv) ?></small></td>
            <td><small style="color:#6b7a99"><?= htmlspecialchars($ipAddr) ?></small></td>
            <td><small><?= htmlspecialchars($sr) ?></small></td>
            <td>
              <?php if (!empty($rf)): ?>
                <a href="<?= htmlspecialchars($rf) ?>" target="_blank" style="color:var(--saffron-light); font-size:0.75rem;">Link</a>
              <?php else: ?>
                <small style="color:#4a5568">N/A</small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (isset($lead['id'])): ?>
                <a href="?delete=<?= $lead['id'] ?>" onclick="return confirm('Confirm permanent deletion of this lead?')" style="color:#fc8181; text-decoration:none; font-size:0.75rem; border:1px solid rgba(229,62,62,0.3); padding:3px 8px; border-radius:4px;">Delete</a>
              <?php else: ?>
                <small style="color:#4a5568">(CSV Record)</small>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<footer>HOABL Ayodhya – Admin Panel &copy; <?= date('Y') ?></footer>

<script>
function filterTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#leadsTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
