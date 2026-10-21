<?php

session_start();
require_once 'auth.php';
require_once 'functions.php';

$auth = require_auth(['admin', 'dispatcher']);
$return = ($_POST['return'] ?? '') === 'dispatcher_dashboard.php' ? 'dispatcher_dashboard.php' : 'admin_schedule.php';
if ($auth['role'] === 'dispatcher') {
    $return = 'dispatcher_dashboard.php';
}
$month = preg_match('/^\d{4}-\d{2}$/', $_POST['month'] ?? '') ? $_POST['month'] : date('Y-m');
$back = $return . '?month=' . $month;

require_post_csrf($back);

$id = (int) ($_POST['booking_id'] ?? 0);
$status = $_POST['status'] ?? '';
$techId = (int) ($_POST['technician_id'] ?? 0);
$date = trim($_POST['scheduled_date'] ?? '');
$time = trim($_POST['scheduled_time'] ?? '');
$note = clean_text($_POST['admin_note'] ?? '', 255);

$stmt = $conn->prepare("SELECT b.*, s.name AS service_name FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$b) {
    flash_error('Booking not found.');
    redirect($back);
}
if (!in_array($status, BOOKING_STATUSES, true)) {
    flash_error('Invalid status.');
    redirect($back);
}

$time5 = substr($time, 0, 5);
$changedSlot = ($date !== $b['scheduled_date'] || $time5 !== substr($b['scheduled_time'], 0, 5));
if ($changedSlot) {
    
    if ($err = availability_error($conn, $date, $time, $id, false)) {
        flash_error($err);
        redirect($back);
    }
} else {
    $date = $b['scheduled_date'];
    $time = $b['scheduled_time'];
}

$techParam = null;
if ($techId > 0) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND account_type = 'Employee' AND department = 'Technician' AND status = 'approved'");
    $stmt->bind_param('i', $techId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        flash_error('Please choose a valid technician.');
        redirect($back);
    }
    $stmt->close();
    $techParam = $techId;
}

$stmt = $conn->prepare('UPDATE bookings SET status = ?, technician_id = ?, scheduled_date = ?, scheduled_time = ?, admin_note = ? WHERE id = ?');
$stmt->bind_param('sisssi', $status, $techParam, $date, $time, $note, $id);
$stmt->execute();
$stmt->close();


$by = $auth['name'];
$when = fmt_date($date) . ' at ' . fmt_time($time);
if ($status !== $b['status']) {
    $msgs = [
        'approved' => ['Booking approved', 'Your ' . $b['service_name'] . ' is confirmed for ' . $when . '.'],
        'declined' => ['Booking declined', 'Sorry, we could not accept your ' . $b['service_name'] . ' request for ' . fmt_date($b['scheduled_date']) . '.' . ($note !== '' ? ' Note: ' . $note : '')],
        'completed' => ['Service completed', 'Your ' . $b['service_name'] . ' was marked completed. Thank you for choosing Sun Son Solar!'],
        'cancelled' => ['Booking cancelled', 'Your ' . $b['service_name'] . ' booking on ' . fmt_date($b['scheduled_date']) . ' was cancelled.'],
        'pending' => ['Booking under review', 'Your ' . $b['service_name'] . ' request is being reviewed.'],
    ];
    notify_user($conn, (int) $b['user_id'], 'schedule', $msgs[$status][0], mb_substr($msgs[$status][1], 0, 500), $by);
} elseif ($changedSlot) {
    notify_user($conn, (int) $b['user_id'], 'schedule', 'Schedule changed', 'Your ' . $b['service_name'] . ' was moved to ' . $when . '.', $by);
}

flash_success('Booking #' . $id . ' updated.');
redirect($back);
