<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check session
if (!isset($_SESSION['hoabl_ayodhya_logged_in']) || $_SESSION['hoabl_ayodhya_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Default values matching config.php
$defaults = [
    'use_smtp'  => '0',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'notify_to' => LEAD_EMAIL_TO,
    'notify_cc' => LEAD_EMAIL_CC
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        foreach ($defaults as $key => $default_val) {
            $value = $_POST[$key] ?? $default_val;
            if ($key === 'use_smtp') {
                $value = isset($_POST['use_smtp']) ? '1' : '0';
            }
            $stmt->execute([$key, $value, $value]);
        }
        $success = 'Configuration saved successfully!';
    }

    // Retrieve settings
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Merge retrieved values with defaults
    $current = array_merge($defaults, $settings);

} catch (Exception $e) {
    $error = 'Database Connection Failed: ' . $e->getMessage() . ' (Settings cannot be saved dynamically)';
    $current = $defaults;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Settings | HOABL Ayodhya</title>
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
    .container{max-width:800px;margin:0 auto;padding:3rem 2rem}
    
    .btn-back{background:rgba(255,255,255,0.05);color:#fff;font-weight:600;padding:.65rem 1.4rem;border-radius:10px;text-decoration:none;font-size:.88rem;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;}
    .btn-back:hover{background:rgba(255,255,255,0.1);}
    
    .settings-card {
      background: rgba(255,255,255,0.02);
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 20px;
      padding: 2.5rem;
      backdrop-filter: blur(15px);
      margin-top: 1.5rem;
    }
    .settings-card h2 {
      font-size: 1.3rem;
      color: var(--saffron-light);
      margin-bottom: 1.8rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      padding-bottom: 0.8rem;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
      text-align: left;
    }
    .form-label {
      display: block;
      color: #8898b5;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 0.6rem;
    }
    .form-input {
      width: 100%;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      padding: 0.9rem 1.1rem;
      border-radius: 10px;
      color: #fff;
      font-size: 0.95rem;
      outline: none;
      transition: all 0.2s;
    }
    .form-input:focus {
      border-color: var(--saffron);
      background: rgba(242,110,34,0.04);
    }
    
    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin: 2rem 0;
    }
    .checkbox-group input {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }
    
    .btn-save {
      background: linear-gradient(135deg, var(--saffron), var(--saffron-light));
      color: #03070f;
      border: none;
      padding: 1rem 2rem;
      border-radius: 50px;
      font-weight: 800;
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      transition: all 0.25s;
      box-shadow: 0 5px 15px rgba(242,110,34,0.25);
    }
    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(242,110,34,0.45);
    }
    
    .msg-box {
      padding: 1rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      font-size: 0.9rem;
      font-weight: 600;
    }
    .msg-success {
      background: rgba(72,187,120,0.1);
      border: 1px solid rgba(72,187,120,0.25);
      color: #48bb78;
    }
    .msg-error {
      background: rgba(229,62,62,0.1);
      border: 1px solid rgba(229,62,62,0.25);
      color: #fc8181;
    }
  </style>
</head>
<body>
<div class="header">
  <h1>⚙️ System Config</h1>
  <div>
    <a href="index.php" class="btn-back">← Dashboard</a>
  </div>
</div>

<div class="container">
  <?php if ($success): ?>
    <div class="msg-box msg-success">✓ <?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="msg-box msg-error">⚠️ <?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="settings-card">
      <h2>Routing Configuration</h2>
      <div class="form-group">
        <label class="form-label">Notification recipient email (TO) *</label>
        <input type="email" name="notify_to" class="form-input" value="<?= htmlspecialchars($current['notify_to']) ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Notification copy email (CC)</label>
        <input type="email" name="notify_cc" class="form-input" value="<?= htmlspecialchars($current['notify_cc']) ?>" />
      </div>
    </div>

    <div class="settings-card">
      <h2>SMTP dispatching override</h2>
      <p style="color:#8898b5; font-size:0.82rem; margin-bottom:1.5rem; line-height:1.5;">Configuring SMTP settings allows you to bypass the standard PHP mail() daemon, sending routing emails securely via designated accounts (e.g. Gmail or SendGrid).</p>
      
      <div class="checkbox-group">
        <input type="checkbox" name="use_smtp" id="use_smtp" value="1" <?= $current['use_smtp'] === '1' ? 'checked' : '' ?> />
        <label for="use_smtp" style="font-weight:600; cursor:pointer;">Use Dynamic SMTP Dispatcher</label>
      </div>

      <div class="form-group">
        <label class="form-label">SMTP Hostname</label>
        <input type="text" name="smtp_host" class="form-input" value="<?= htmlspecialchars($current['smtp_host']) ?>" placeholder="e.g. smtp.gmail.com" />
      </div>

      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
          <label class="form-label">SMTP Port</label>
          <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($current['smtp_port']) ?>" placeholder="587" />
        </div>
        <div>
          <label class="form-label">Authentication Account (User)</label>
          <input type="text" name="smtp_user" class="form-input" value="<?= htmlspecialchars($current['smtp_user']) ?>" placeholder="your-email@domain.com" />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Authentication Password (Pass)</label>
        <input type="password" name="smtp_pass" class="form-input" value="<?= htmlspecialchars($current['smtp_pass']) ?>" placeholder="••••••••" />
      </div>
    </div>

    <div style="text-align:right; margin-top:2.5rem;">
      <button type="submit" class="btn-save">Save Settings</button>
    </div>
  </form>
</div>
</body>
</html>
