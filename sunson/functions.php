<?php
/* Shared helpers for Sun Son Solar (bookings, notifications, formatting). */

const DEPARTMENTS = ['Administration', 'IT', 'Technician', 'Dispatch', 'Accounting', 'HR', 'Marketing', 'Sales', 'Customer Service'];
const PRODUCT_CATEGORIES = ['Solar Panels', 'Inverters', 'Batteries', 'Racking & Mounting', 'Wires & Cables'];
const BOOKING_STATUSES = ['pending', 'approved', 'declined', 'completed', 'cancelled'];
const NOTIFICATION_TYPES = ['schedule' => 'Schedule update', 'reminder' => 'Reminder', 'promotion' => 'Promotion', 'system' => 'System update'];

function peso($amount): string {
    return '₱' . number_format((float) $amount, 2);
}

function fmt_date($date): string {
    return $date ? date('M j, Y', strtotime($date)) : '—';
}

function fmt_time($time): string {
    return $time ? date('g:i A', strtotime($time)) : '—';
}

function fmt_datetime($dt): string {
    return $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
}

function badge(string $status): string {
    $map = [
        'pending' => 'warn', 'approved' => 'ok', 'declined' => 'bad', 'completed' => 'done', 'cancelled' => 'muted',
        'open' => 'warn', 'answered' => 'ok', 'closed' => 'muted',
        'new' => 'warn', 'reviewed' => 'ok', 'resolved' => 'done', 'contacted' => 'ok',
        'active' => 'ok', 'inactive' => 'muted', 'rejected' => 'bad',
        'normal' => 'muted', 'safety' => 'warn', 'urgent' => 'bad',
        'schedule' => 'ok', 'reminder' => 'warn', 'promotion' => 'done', 'system' => 'muted',
    ];
    $cls = $map[$status] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

function notification_enabled(mysqli $conn, string $type): bool {
    $stmt = $conn->prepare('SELECT is_enabled FROM notification_settings WHERE type = ?');
    $stmt->bind_param('s', $type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !$row || (int) $row['is_enabled'] === 1;
}

/** Send a notification to one customer. Respects the admin on/off switch for that alert type. */
function notify_user(mysqli $conn, int $userId, string $type, string $title, string $message, string $by = 'System'): void {
    if (!notification_enabled($conn, $type)) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO notifications (audience, user_id, type, title, message, created_by) VALUES ('user', ?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $userId, $type, $title, $message, $by);
    $stmt->execute();
    $stmt->close();
}

function active_services(mysqli $conn): array {
    return $conn->query('SELECT id, name, description, base_price FROM services WHERE is_active = 1 ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
}

function technician_list(mysqli $conn): array {
    return $conn->query("SELECT id, first_name, last_name FROM users WHERE account_type = 'Employee' AND department = 'Technician' AND status = 'approved' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check a requested date/time against opening days, hours, blocked dates and daily capacity.
 * Returns '' when OK, otherwise a message that can be shown to the user.
 */
function availability_error(mysqli $conn, string $date, string $time, int $excludeBookingId = 0, bool $requireAdvance = true): string {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return 'Please choose a valid date.';
    }
    $t = DateTime::createFromFormat('H:i', substr($time, 0, 5));
    if (!$t) {
        return 'Please choose a valid time.';
    }
    if ($requireAdvance && $date <= date('Y-m-d')) {
        return 'Please book at least one day in advance.';
    }

    $dow = (int) $d->format('w');
    $stmt = $conn->prepare('SELECT is_open, open_time, close_time, max_bookings FROM availability WHERE day_of_week = ?');
    $stmt->bind_param('i', $dow);
    $stmt->execute();
    $day = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$day || !(int) $day['is_open']) {
        return 'Sorry, we are closed on that day. Please pick another date.';
    }
    $hhmm = $t->format('H:i:00');
    if ($hhmm < $day['open_time'] || $hhmm >= $day['close_time']) {
        return 'We accept appointments between ' . fmt_time($day['open_time']) . ' and ' . fmt_time($day['close_time']) . ' on that day.';
    }

    $stmt = $conn->prepare('SELECT reason FROM blocked_dates WHERE blocked_date = ?');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $blocked = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($blocked) {
        return 'That date is unavailable' . ($blocked['reason'] !== '' ? ' (' . $blocked['reason'] . ')' : '') . '. Please pick another date.';
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE scheduled_date = ? AND status IN ('pending','approved','completed') AND id <> ?");
    $stmt->bind_param('si', $date, $excludeBookingId);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    if ($count >= (int) $day['max_bookings']) {
        return 'That date is fully booked. Please pick another date.';
    }
    return '';
}

function next_receipt_no(mysqli $conn): string {
    $prefix = 'SSS-' . date('Ymd') . '-';
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM payments WHERE receipt_no LIKE ?');
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['c'] + 1;
    $stmt->close();
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function clean_text($value, int $max = 255): string {
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function valid_date_string($d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', (string) $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Validate shared person fields; returns an error message or ''. */
function validate_person(array $in, bool $needPassword): string {
    foreach (['first_name', 'last_name', 'birthdate', 'gender', 'email', 'phone', 'address', 'username'] as $f) {
        if (trim($in[$f] ?? '') === '') {
            return 'Please fill in all required fields.';
        }
    }
    if (!valid_date_string($in['birthdate'])) {
        return 'Please enter a valid birthdate.';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    if (!preg_match('/^\d{11}$/', $in['phone'])) {
        return 'Phone number must contain exactly 11 digits.';
    }
    if (!in_array($in['gender'], ['Male', 'Female', 'Other'], true)) {
        return 'Please select a valid gender.';
    }
    if ($needPassword && strlen($in['password'] ?? '') < 8) {
        return 'Password must be at least 8 characters long.';
    }
    return '';
}

/* ---------- Customer notification helpers ---------- */

/** WHERE fragment (needs 4 params: user id, user id, reminders pref, promotions pref) for a customer's visible notifications. */
const CUSTOMER_NOTIF_WHERE = "(n.audience = 'customers' OR (n.audience = 'user' AND n.user_id = ?))
    AND (n.type <> 'reminder' OR ? = 1) AND (n.type <> 'promotion' OR ? = 1)
    AND n.created_at >= (SELECT created_at FROM users WHERE id = ?)";

function customer_prefs(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare('SELECT pref_reminders, pref_promotions FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: ['pref_reminders' => 1, 'pref_promotions' => 1];
}

function customer_unread_count(mysqli $conn, int $userId): int {
    $p = customer_prefs($conn, $userId);
    $rem = (int) $p['pref_reminders'];
    $pro = (int) $p['pref_promotions'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications n WHERE " . CUSTOMER_NOTIF_WHERE . "
        AND NOT EXISTS (SELECT 1 FROM notification_reads r WHERE r.notification_id = n.id AND r.user_id = ?)");
    $stmt->bind_param('iiiii', $userId, $rem, $pro, $userId, $userId);
    $stmt->execute();
    $c = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c;
}

/** Human-readable opening days, e.g. "Mon–Fri 8:00 AM – 5:00 PM · Sat 8:00 AM – 12:00 PM". */
function opening_summary(mysqli $conn): string {
    $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $rows = $conn->query('SELECT day_of_week, is_open, open_time, close_time FROM availability ORDER BY day_of_week')->fetch_all(MYSQLI_ASSOC);
    $groups = [];
    foreach ($rows as $r) {
        if (!(int) $r['is_open']) {
            continue;
        }
        $label = fmt_time($r['open_time']) . ' – ' . fmt_time($r['close_time']);
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last]['label'] === $label && $groups[$last]['end'] === (int) $r['day_of_week'] - 1) {
            $groups[$last]['end'] = (int) $r['day_of_week'];
        } else {
            $groups[] = ['start' => (int) $r['day_of_week'], 'end' => (int) $r['day_of_week'], 'label' => $label];
        }
    }
    $parts = [];
    foreach ($groups as $g) {
        $days = $g['start'] === $g['end'] ? $names[$g['start']] : $names[$g['start']] . '–' . $names[$g['end']];
        $parts[] = $days . ' ' . $g['label'];
    }
    return $parts ? implode(' · ', $parts) : 'Please contact us for available schedules';
}
