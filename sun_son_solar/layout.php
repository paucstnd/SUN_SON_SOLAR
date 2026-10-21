<?php

require_once __DIR__ . '/functions.php';

function icon(string $name): string {
    static $paths = [
        'home' => 'M3 12l9-9 9 9M5 10v10h14V10',
        'calendar' => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
        'bell' => 'M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0',
        'user' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8',
        'users' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8M23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8',
        'history' => 'M12 8v4l3 2M3 12a9 9 0 109-9 9 9 0 00-6.4 2.6L3 8M3 3v5h5',
        'help' => 'M12 22a10 10 0 100-20 10 10 0 000 20M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01',
        'chart' => 'M18 20V10M12 20V4M6 20v-6',
        'cog' => 'M12 15a3 3 0 100-6 3 3 0 000 6M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z',
        'megaphone' => 'M3 11v2a2 2 0 002 2h1l5 4V5L6 9H5a2 2 0 00-2 2zM16 8a5 5 0 010 8',
        'alert' => 'M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0zM12 9v4M12 17h.01',
        'pin' => 'M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0zM12 13a3 3 0 100-6 3 3 0 000 6',
        'card' => 'M1 4h22v16H1zM1 10h22',
        'file' => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M8 13h8M8 17h8',
        'layout' => 'M3 3h18v18H3zM3 9h18M9 21V9',
        'clipboard' => 'M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2M9 2h6v4H9z',
        'logout' => 'M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9',
        'menu' => 'M3 12h18M3 6h18M3 18h18',
    ];
    $d = $paths[$name] ?? $paths['home'];
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

function nav_for_role(string $role): array {
    switch ($role) {
        case 'admin':
            return [
                'Overview' => [['admin_dashboard.php', 'Dashboard', 'home'], ['admin_schedule.php', 'Schedule', 'calendar'], ['admin_payments.php', 'Payments', 'card'], ['admin_product_orders.php', 'Product orders', 'card'], ['admin_reports.php', 'Reports', 'chart']],
                'People' => [['admin_users.php', 'User management', 'users'], ['admin_resets.php', 'Password resets', 'user'], ['admin_attendance.php', 'Attendance', 'pin'], ['admin_incidents.php', 'Field reports', 'alert']],
                'Communication' => [['admin_notifications.php', 'Customer alerts', 'bell'], ['admin_announcements.php', 'Announcements', 'megaphone'], ['admin_support.php', 'Support tickets', 'help'], ['admin_quotes.php', 'Quote requests', 'file']],
                'System' => [['admin_content.php', 'Website content', 'layout'], ['admin_settings.php', 'Settings', 'cog'], ['admin_activity.php', 'Activity log', 'clipboard'], ['admin_profile.php', 'My account', 'user']],
            ];
        case 'customer':
            return ['Menu' => [['customer_dashboard.php', 'My schedule', 'calendar'], ['customer_dashboard.php#products', 'Shop products', 'card'], ['customer_notifications.php', 'Notifications', 'bell'], ['customer_history.php', 'History & receipts', 'history'], ['customer_support.php', 'Support', 'help'], ['customer_profile.php', 'My profile', 'user']]];
        case 'technician':
            return ['Menu' => [['technician_dashboard.php', 'Time in & my jobs', 'pin'], ['technician_report.php', 'Report a problem', 'alert'], ['employee_announcements.php', 'Announcements', 'megaphone'], ['employee_profile.php', 'My account', 'user']]];
        case 'dispatcher':
            return ['Menu' => [['dispatcher_dashboard.php', 'Schedule', 'calendar'], ['employee_attendance.php', 'Attendance', 'pin'], ['employee_announcements.php', 'Announcements', 'megaphone'], ['employee_profile.php', 'My account', 'user']]];
        case 'hr':
            return ['Menu' => [['admin_users.php', 'User management', 'users'], ['admin_resets.php', 'Password resets', 'user'], ['employee_attendance.php', 'Attendance', 'pin'], ['admin_announcements.php', 'Announcements', 'megaphone'], ['employee_announcements.php', 'Announcement feed', 'bell'], ['employee_profile.php', 'My account', 'user']]];
        default:
            return ['Menu' => [['employee_attendance.php', 'Attendance', 'pin'], ['employee_announcements.php', 'Announcements', 'megaphone'], ['employee_profile.php', 'My account', 'user']]];
    }
}

function profile_page_for_role(string $role): string {
    if ($role === 'admin') return 'admin_profile.php';
    if ($role === 'customer') return 'customer_profile.php';
    return 'employee_profile.php';
}

function role_label(array $auth): string {
    switch ($auth['role']) {
        case 'admin': return 'Administrator';
        case 'customer': return 'Customer';
        default: return ($auth['department'] ?: 'Employee') . ' staff';
    }
}

function nav_badges(mysqli $conn, array $auth): array {
    $b = [];
    if ($auth['role'] === 'admin') {
        $b['admin_schedule.php'] = (int) $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetch_row()[0];
        $b['admin_product_orders.php'] = (int) $conn->query("SELECT COUNT(*) FROM product_orders WHERE status = 'pending'")->fetch_row()[0];
        $b['admin_support.php'] = (int) $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetch_row()[0];
        $b['admin_incidents.php'] = (int) $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status = 'new'")->fetch_row()[0];
        $b['admin_quotes.php'] = (int) $conn->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'")->fetch_row()[0];
        try { $b['admin_resets.php'] = (int) $conn->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'new'")->fetch_row()[0]; } catch (Throwable $e) {  }
    } elseif ($auth['role'] === 'customer') {
        $b['customer_notifications.php'] = customer_unread_count($conn, $auth['id']);
    } elseif ($auth['role'] === 'dispatcher') {
        $b['dispatcher_dashboard.php'] = (int) $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetch_row()[0];
    }
    return $b;
}

function render_header(array $auth, string $title, string $active, string $subtitle = ''): void {
    global $conn;
    $nav = nav_for_role($auth['role']);
    $badges = nav_badges($conn, $auth);
    $success = $_SESSION['success'] ?? '';
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['success'], $_SESSION['error'], $_SESSION['active_form']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · Sun Son Solar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
<script src="assets/app.js" defer></script>
</head>
<body class="app">
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand">
      <img src="transL.png" alt="" width="44" height="44">
      <div><div class="brand-word-sm">Sun Son <span>Solar</span></div><div class="role-chip"><?= e(role_label($auth)) ?></div></div>
    </div>
    <nav class="side-nav" aria-label="Main">
      <?php foreach ($nav as $group => $items): ?>
        <?php if (count($nav) > 1): ?><div class="nav-group"><?= e($group) ?></div><?php endif; ?>
        <?php foreach ($items as $item): ?>
          <a href="<?= e($item[0]) ?>" class="nav-link <?= $item[0] === $active ? 'active' : '' ?>">
            <?= icon($item[2]) ?><span><?= e($item[1]) ?></span>
            <?php if (!empty($badges[$item[0]])): ?><span class="nav-count"><?= (int) $badges[$item[0]] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-user">
      <div class="who"><strong><?= e($auth['name']) ?></strong><small>@<?= e($auth['username']) ?></small></div>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="logout-btn" title="Log out"><?= icon('logout') ?><span>Log out</span></button>
      </form>
    </div>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button type="button" class="menu-btn" id="menuBtn" aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <div>
        <h1 class="page-title"><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><p class="page-sub"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
    </header>
    <main class="content" id="content">
      <?php if (!empty($auth['must_change_password'])): ?>
        <div class="message show warn-banner" role="alert">For your security, please <a href="<?= e(profile_page_for_role($auth['role'])) ?>">change your temporary password</a>.</div>
      <?php endif; ?>
      <?php if ($success): ?><div class="message show toast" role="status"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="message error show toast" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php
}

function render_footer(array $scripts = []): void {
    ?>
    </main>
  </div>
</div>
<div class="scrim" id="scrim"></div>
<?php foreach ($scripts as $src): ?><script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}


function select_options(array $options, $selected = null): string {
    $html = '';
    foreach ($options as $value => $label) {
        $sel = ((string) $value === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}
