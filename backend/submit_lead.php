<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Clean helper
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val ?? '')), ENT_QUOTES, 'UTF-8');
}

// Sanitize headers to prevent mail injection
function sanitize_header($val) {
    return preg_replace('/(\r|\n|%0a|%0d)/i', '', trim($val ?? ''));
}

// Device detection helper
function getDevice($ua) {
    $ua = strtolower($ua);
    if (strpos($ua, 'ipad') !== false) return 'iPad';
    if (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) return 'Android Tablet';
    if (strpos($ua, 'iphone') !== false) return 'iPhone';
    if (strpos($ua, 'android') !== false) return 'Android Phone';
    if (strpos($ua, 'windows') !== false) return 'Windows PC';
    if (strpos($ua, 'macintosh') !== false) return 'Mac';
    if (strpos($ua, 'linux') !== false) return 'Linux PC';
    return 'Other Device';
}

// Real IP helper
function getRealIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Geo & VPN detection helper
function getGeoFull($ip) {
    $ipLong = ip2long($ip);
    $isPrivate = ($ipLong !== false && (
        ($ipLong & 0xFF000000) === 0x0A000000 ||          // 10.0.0.0/8
        ($ipLong & 0xFFF00000) === 0xAC100000 ||          // 172.16.0.0/12
        ($ipLong & 0xFFFF0000) === 0xC0A80000 ||          // 192.168.0.0/16
        $ip === '127.0.0.1' || $ip === '::1'
    ));

    if ($isPrivate) {
        return ['country' => 'India', 'countryCode' => 'IN', 'city' => 'Local', 'proxy' => false, 'hosting' => false];
    }

    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,proxy,hosting";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        $data = json_decode($res, true);
        if ($data && $data['status'] === 'success') {
            return [
                'country'     => $data['country']     ?? 'Unknown',
                'countryCode' => $data['countryCode'] ?? 'XX',
                'city'        => $data['city']         ?? 'Unknown',
                'proxy'       => (bool)($data['proxy']   ?? false),
                'hosting'     => (bool)($data['hosting'] ?? false),
            ];
        }
    }

    return ['country' => 'India', 'countryCode' => 'IN', 'city' => 'Unknown', 'proxy' => false, 'hosting' => false];
}

$ip = getRealIP();
$ip = filter_var($ip, FILTER_VALIDATE_IP) ?: '';

// --- Honeypot Bot Check ---
if (!empty($_POST['website'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Spam block triggered.']);
    exit;
}

// --- Velocity Check ---
$loadTime = (int)($_POST['load_time'] ?? 0);
if ($loadTime > 0) {
    $nowMs = time() * 1000;
    if (($nowMs - $loadTime) < 2500) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Submission rate-limit triggered.']);
        exit;
    }
}

// --- Geo Security check ---
$geo = getGeoFull($ip);
if ($geo['proxy'] || $geo['hosting']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Submission blocked. Active proxy/VPN service detected. Please disable it to continue.']);
    exit;
}

// --- Inputs Sanitization ---
$name             = clean($_POST['name'] ?? '');
$phone            = clean($_POST['phone'] ?? '');
$email            = clean($_POST['email'] ?? '');
$project_interest = clean($_POST['project_interest'] ?? '1250 sq.ft Plot');
$source_item      = clean($_POST['source_item'] ?? 'General Form');
$referral_url     = clean($_POST['referral_url'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$userAgent        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$device           = getDevice($userAgent);

// Validate inputs
$errors = [];
if (strlen($name) < 2) $errors[] = 'Full name is required.';
if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) $errors[] = 'Please enter a valid phone number.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// --- Backup local CSV log ---
$csvFile = __DIR__ . '/leads.csv';
$csvExists = file_exists($csvFile);
$fh = fopen($csvFile, 'a');
if ($fh) {
    if (!$csvExists) {
        fputcsv($fh, ['Date', 'Name', 'Phone', 'Email', 'Interest', 'Source', 'Referral URL', 'IP', 'Country', 'Device']);
    }
    fputcsv($fh, [date('Y-m-d H:i:s'), $name, $phone, $email, $project_interest, $source_item, $referral_url, $ip, $geo['countryCode'], $device]);
    fclose($fh);
}

// --- Database storage & dispatching ---
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Insert lead
    $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, project_interest, source_item, referral_url, ip_address, country_code, vpn_blocked, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        $phone,
        $email,
        $project_interest,
        $source_item,
        $referral_url,
        $ip,
        $geo['countryCode'],
        $geo['proxy'] ? 1 : 0,
        date('Y-m-d H:i:s')
    ]);

    // Retrieve settings
    $settings = [];
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings");
    if ($stmtSettings) {
        while ($row = $stmtSettings->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $toEmail = !empty($settings['notify_to']) ? $settings['notify_to'] : LEAD_EMAIL_TO;
    $ccEmail = !empty($settings['notify_cc']) ? $settings['notify_cc'] : LEAD_EMAIL_CC;

    // --- Build Email Content ---
    $subject = "[HOABL AYODHYA] New Lead: $name";
    $body    = "HOABL Ayodhya - New Lead Notification\n"
             . "========================================\n\n"
             . "Name:             $name\n"
             . "Phone/WhatsApp:   $phone\n"
             . "Email:            $email\n"
             . "Interest:         $project_interest\n"
             . "Source:           $source_item\n\n"
             . "Extra Analytics:\n"
             . "----------------\n"
             . "Referral URL:     $referral_url\n"
             . "Location:         " . $geo['city'] . ", " . $geo['country'] . "\n"
             . "Device:           $device\n"
             . "IP Address:       $ip\n"
             . "Timestamp:        " . date('Y-m-d H:i:s') . "\n";

    // Attempt PHPMailer if present
    $mailSent = false;
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $useSmtp = (defined('USE_SMTP') && USE_SMTP) || (isset($settings['use_smtp']) && $settings['use_smtp'] == '1');
            if ($useSmtp) {
                $mail->isSMTP();
                if (defined('USE_SMTP') && USE_SMTP) {
                    $mail->Host       = SMTP_HOST;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->Port       = SMTP_PORT;
                } else {
                    $mail->Host       = !empty($settings['smtp_host']) ? $settings['smtp_host'] : SMTP_HOST;
                    $mail->Username   = !empty($settings['smtp_user']) ? $settings['smtp_user'] : SMTP_USER;
                    $mail->Password   = !empty($settings['smtp_pass']) ? $settings['smtp_pass'] : SMTP_PASS;
                    $mail->Port       = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : SMTP_PORT;
                }
                $mail->SMTPAuth   = true;
                $mail->SMTPSecure = ($mail->Port === 465) ? 'ssl' : 'tls';
            }
            
            $mail->setFrom(LEAD_EMAIL_FROM, LEAD_EMAIL_NAME);
            $mail->addAddress($toEmail);
            if (!empty($ccEmail)) {
                $mail->addCC(sanitize_header($ccEmail));
            }
            
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            $mailSent = true;
        } catch (Exception $e) {
            error_log("PHPMailer failed: " . $e->getMessage());
        }
    }

    // Standard PHP mail fallback
    if (!$mailSent) {
        $headers = "From: " . sanitize_header(LEAD_EMAIL_NAME) . " <" . sanitize_header(LEAD_EMAIL_FROM) . ">\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "X-Mailer: PHP/" . phpversion();

        mail($toEmail, $subject, $body, $headers);
        if (!empty($ccEmail)) {
            mail($ccEmail, $subject, $body, $headers);
        }
    }

    // --- Google Sheets Webhook ---
    $sheetWebhook = defined('GOOGLE_SHEETS_WEBHOOK') ? GOOGLE_SHEETS_WEBHOOK : '';
    if (isset($settings['google_sheets_webhook']) && !empty($settings['google_sheets_webhook'])) {
        $sheetWebhook = $settings['google_sheets_webhook'];
    }
    
    // Validate webhook URL
    if (!empty($sheetWebhook) && !filter_var($sheetWebhook, FILTER_VALIDATE_URL)) {
        error_log("Invalid Google Sheets webhook URL: " . $sheetWebhook);
        $sheetWebhook = '';
    }

    if (!empty($sheetWebhook)) {
        $sheetData = [
            'date' => date('Y-m-d H:i:s'),
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'project_interest' => $project_interest,
            'source_item' => $source_item,
            'referral_url' => $referral_url,
            'ip' => $ip,
            'country' => $geo['countryCode'],
            'device' => $device
        ];

        $chSheet = curl_init($sheetWebhook);
        curl_setopt($chSheet, CURLOPT_POST, 1);
        curl_setopt($chSheet, CURLOPT_POSTFIELDS, http_build_query($sheetData));
        curl_setopt($chSheet, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chSheet, CURLOPT_TIMEOUT, 3);
        curl_setopt($chSheet, CURLOPT_FOLLOWLOCATION, true);
        
        // Execute synchronous request (blocks up to 3s)
        $result = curl_exec($chSheet);
        $httpCode = curl_getinfo($chSheet, CURLINFO_HTTP_CODE);
        
        if ($result === false) {
            error_log("Google Sheets webhook curl error: " . curl_error($chSheet));
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            error_log("Google Sheets webhook failed with HTTP code $httpCode. Response: " . $result);
        }
        
        curl_close($chSheet);
    }

    echo json_encode(['success' => true, 'message' => 'Enquiry captured successfully!']);
} catch (Exception $e) {
    error_log("Lead processor exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server database integration saved to logs.']);
}
