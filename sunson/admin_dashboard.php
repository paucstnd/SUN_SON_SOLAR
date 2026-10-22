<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);

function scalar(mysqli $conn, string $sql) { return $conn->query($sql)->fetch_row()[0]; }

$stats = [
    'customers' => (int) scalar($conn, "SELECT COUNT(*) FROM users WHERE account_type = 'Customer' AND status = 'approved'"),
    'employees' => (int) scalar($conn, "SELECT COUNT(*) FROM users WHERE account_type = 'Employee' AND status = 'approved'"),
    'pending' => (int) scalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
    'revenue' => (float) scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'tickets' => (int) scalar($conn, "SELECT COUNT(*) FROM tickets WHERE status = 'open'"),
    'incidents' => (int) scalar($conn, "SELECT COUNT(*) FROM incident_reports WHERE status = 'new'"),
    'quotes' => (int) scalar($conn, "SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
    'unpaid' => (int) scalar($conn, "SELECT COUNT(*) FROM bookings b LEFT JOIN payments p ON p.booking_id = b.id WHERE b.status = 'completed' AND p.id IS NULL"),
];

$upcoming = $conn->query("SELECT b.scheduled_date, b.scheduled_time, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, CONCAT(t.first_name, ' ', t.last_name) AS tech_name
    FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id LEFT JOIN users t ON t.id = b.technician_id
    WHERE b.status = 'approved' AND b.scheduled_date >= CURDATE() ORDER BY b.scheduled_date, b.scheduled_time LIMIT 8")->fetch_all(MYSQLI_ASSOC);

$checkins = $conn->query("SELECT full_name, checked_in_at, latitude, longitude FROM attendance WHERE DATE(checked_in_at) = CURDATE() ORDER BY checked_in_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

$incidents = $conn->query("SELECT i.id, i.type, i.created_at, CONCAT(u.first_name, ' ', u.last_name) AS tech_name FROM incident_reports i JOIN users u ON u.id = i.user_id WHERE i.status = 'new' ORDER BY i.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

render_header($auth, 'Dashboard', 'admin_dashboard.php', 'Welcome back, ' . explode(' ', $auth['name'])[0] . '. Here is what is happening at Sun Son Solar.');
?>
<div class="stats">
  <a class="stat hl" href="admin_schedule.php" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['pending'] ?></div><div class="lbl">Pending bookings</div></a>
  <div class="stat"><div class="num"><?= e(peso($stats['revenue'])) ?></div><div class="lbl">Revenue this month</div></div>
  <a class="stat" href="admin_users.php?tab=customers" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['customers'] ?></div><div class="lbl">Active customers</div></a>
  <a class="stat" href="admin_users.php?tab=employees" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['employees'] ?></div><div class="lbl">Active employees</div></a>
  <a class="stat" href="admin_support.php" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['tickets'] ?></div><div class="lbl">Open support tickets</div></a>
  <a class="stat" href="admin_incidents.php" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['incidents'] ?></div><div class="lbl">New field reports</div></a>
  <a class="stat" href="admin_quotes.php" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['quotes'] ?></div><div class="lbl">New quote requests</div></a>
  <a class="stat" href="admin_payments.php" style="text-decoration:none;color:inherit"><div class="num"><?= $stats['unpaid'] ?></div><div class="lbl">Completed, awaiting payment</div></a>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Upcoming approved services</h2><a href="admin_schedule.php" class="button button-ghost button-sm">Open calendar</a></div>
    <?php if (!$upcoming): ?><div class="empty">No approved services coming up.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Customer</th><th>Service</th><th>Technician</th></tr></thead><tbody>
      <?php foreach ($upcoming as $u): ?><tr>
        <td><?= e(fmt_date($u['scheduled_date'])) ?><span class="sub"><?= e(fmt_time($u['scheduled_time'])) ?></span></td>
        <td><?= e($u['customer_name']) ?></td><td><?= e($u['service_name']) ?></td>
        <td><?= $u['tech_name'] ? e($u['tech_name']) : '<span class="badge badge-warn">Unassigned</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>Technician time-ins today</h2><a href="admin_attendance.php" class="button button-ghost button-sm">All attendance</a></div>
      <?php if (!$checkins): ?><div class="empty">No check-ins yet today.</div><?php else: foreach ($checkins as $c): ?>
        <div class="actions" style="justify-content:space-between;padding:8px 0;border-top:1px solid var(--line)">
          <span><strong><?= e($c['full_name']) ?></strong> <span class="muted small"><?= e(date('g:i A', strtotime($c['checked_in_at']))) ?></span></span>
          <?php if ($c['latitude'] !== null): ?><a class="small" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e($c['latitude']) ?>&amp;mlon=<?= e($c['longitude']) ?>#map=17/<?= e($c['latitude']) ?>/<?= e($c['longitude']) ?>">View map</a><?php else: ?><span class="muted small">No location</span><?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>New field reports</h2><a href="admin_incidents.php" class="button button-ghost button-sm">Review</a></div>
      <?php if (!$incidents): ?><div class="empty">Nothing new from the field.</div><?php else: foreach ($incidents as $i): ?>
        <div class="actions" style="justify-content:space-between;padding:8px 0;border-top:1px solid var(--line)"><span><strong><?= e($i['type']) ?></strong> <span class="muted small">by <?= e($i['tech_name']) ?></span></span><span class="muted small"><?= e(fmt_datetime($i['created_at'])) ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php render_footer(); ?>
