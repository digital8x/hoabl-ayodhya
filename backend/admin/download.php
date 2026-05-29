<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check session
if (!isset($_SESSION['hoabl_ayodhya_logged_in']) || $_SESSION['hoabl_ayodhya_logged_in'] !== true) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$selected_interest = $_GET['interest'] ?? '';

// Headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=HOABL_Ayodhya_Leads_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output column headers
fputcsv($output, ['ID', 'Date Time', 'Name', 'Phone', 'Email', 'Interest', 'Source Form', 'Referral URL', 'IP Address', 'Country Code', 'VPN Blocked']);

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = "SELECT * FROM leads";
    $params = [];
    if (!empty($selected_interest)) {
        $query .= " WHERE project_interest = ?";
        $params[] = $selected_interest;
    }
    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['name'],
            $row['phone'],
            $row['email'],
            $row['project_interest'],
            $row['source_item'],
            $row['referral_url'],
            $row['ip_address'],
            $row['country_code'],
            $row['vpn_blocked']
        ]);
    }
} catch (Exception $e) {
    // If DB fails, fallback to local CSV file filtering
    $csvFile = __DIR__ . '/../leads.csv';
    if (file_exists($csvFile)) {
        $fp = fopen($csvFile, 'r');
        $headers = fgetcsv($fp);
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) === count($headers)) {
                $lead = array_combine($headers, $row);
                if (empty($selected_interest) || ($lead['Interest'] ?? '') === $selected_interest) {
                    fputcsv($output, [
                        'N/A',
                        $lead['Date'] ?? '',
                        $lead['Name'] ?? '',
                        $lead['Phone'] ?? '',
                        $lead['Email'] ?? '',
                        $lead['Interest'] ?? '',
                        $lead['Source'] ?? '',
                        $lead['Referral URL'] ?? '',
                        $lead['IP'] ?? '',
                        $lead['Country'] ?? '',
                        '0'
                    ]);
                }
            }
        }
        fclose($fp);
    }
}

fclose($output);
exit;
