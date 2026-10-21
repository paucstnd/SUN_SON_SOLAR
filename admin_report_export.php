<?php

session_start();
require_once 'auth.php';
require_once 'functions.php';

$auth = require_auth(['admin']);

$type = $_GET['type'] ?? '';
$from = valid_date_string($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01', strtotime('-5 months'));
$to = valid_date_string($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');

function csv_safe($v) {
    $v = (string) $v;
    
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

function q(mysqli $conn, string $sql, array $params): array {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_all(MYSQLI_NUM);
    $stmt->close();
    return $r;
}

switch ($type) {
    case 'revenue':
        $head = ['Receipt no', 'Paid at', 'Customer', 'Service', 'Method', 'Reference', 'Amount'];
        $data = q($conn, "SELECT p.receipt_no, p.paid_at, CONCAT(c.first_name, ' ', c.last_name), s.name, p.method, p.reference_no, p.amount
            FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN services s ON s.id = b.service_id JOIN users c ON c.id = p.user_id
            WHERE DATE(p.paid_at) BETWEEN ? AND ? ORDER BY p.paid_at", [$from, $to]);
        break;
    case 'services':
        $head = ['Service', 'Bookings'];
        $data = q($conn, "SELECT s.name, COUNT(*) FROM bookings b JOIN services s ON s.id = b.service_id
            WHERE b.scheduled_date BETWEEN ? AND ? AND b.status IN ('pending','approved','completed') GROUP BY s.id ORDER BY 2 DESC", [$from, $to]);
        break;
    case 'bookings':
        $head = ['Booking ID', 'Date', 'Time', 'Customer', 'Service', 'Status', 'Technician', 'Address'];
        $data = q($conn, "SELECT b.id, b.scheduled_date, b.scheduled_time, CONCAT(c.first_name, ' ', c.last_name), s.name, b.status, COALESCE(CONCAT(t.first_name, ' ', t.last_name), ''), b.address
            FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id LEFT JOIN users t ON t.id = b.technician_id
            WHERE b.scheduled_date BETWEEN ? AND ? ORDER BY b.scheduled_date, b.scheduled_time", [$from, $to]);
        break;
    case 'customers':
        $head = ['Customer', 'Bookings', 'Total paid'];
        $data = q($conn, "SELECT CONCAT(u.first_name, ' ', u.last_name), COUNT(DISTINCT b.id), COALESCE(SUM(p.amount),0)
            FROM users u JOIN bookings b ON b.user_id = u.id AND b.scheduled_date BETWEEN ? AND ? AND b.status <> 'cancelled'
            LEFT JOIN payments p ON p.booking_id = b.id GROUP BY u.id ORDER BY 2 DESC, 3 DESC", [$from, $to]);
        break;
    case 'attendance':
        $head = ['Name', 'Time in', 'Time out', 'Latitude', 'Longitude', 'Accuracy (m)'];
        $data = q($conn, "SELECT full_name, checked_in_at, COALESCE(checked_out_at, ''), latitude, longitude, accuracy_m FROM attendance WHERE DATE(checked_in_at) BETWEEN ? AND ? ORDER BY checked_in_at", [$from, $to]);
        break;
    default:
        http_response_code(400);
        exit('Unknown export.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sunson_' . $type . '_' . $from . '_to_' . $to . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); 
fputcsv($out, $head);
foreach ($data as $row) {
    fputcsv($out, array_map('csv_safe', $row));
}
fclose($out);
