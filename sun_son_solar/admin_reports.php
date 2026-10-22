<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);

$defaultFrom = date('Y-m-01', strtotime('-5 months'));
$from = valid_date_string($_GET['from'] ?? '') ? $_GET['from'] : $defaultFrom;
$to = valid_date_string($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
if ($from > $to) { [$from, $to] = [$to, $from]; }

function rows(mysqli $conn, string $sql, string $types, array $params): array {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $r;
}

$revenue = rows($conn, "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, SUM(amount) AS total FROM payments WHERE DATE(paid_at) BETWEEN ? AND ? GROUP BY m ORDER BY m", 'ss', [$from, $to]);
$usage = rows($conn, "SELECT s.name, COUNT(*) AS c FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.scheduled_date BETWEEN ? AND ? AND b.status IN ('pending','approved','completed') GROUP BY s.id ORDER BY c DESC", 'ss', [$from, $to]);
$status = rows($conn, "SELECT status, COUNT(*) AS c FROM bookings WHERE scheduled_date BETWEEN ? AND ? GROUP BY status", 'ss', [$from, $to]);
$signups = rows($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c FROM users WHERE account_type = 'Customer' AND DATE(created_at) BETWEEN ? AND ? GROUP BY m ORDER BY m", 'ss', [$from, $to]);
$top = rows($conn, "SELECT CONCAT(u.first_name, ' ', u.last_name) AS name, COUNT(DISTINCT b.id) AS bookings, COALESCE(SUM(p.amount),0) AS spent
    FROM users u JOIN bookings b ON b.user_id = u.id AND b.scheduled_date BETWEEN ? AND ? AND b.status <> 'cancelled'
    LEFT JOIN payments p ON p.booking_id = b.id GROUP BY u.id ORDER BY bookings DESC, spent DESC LIMIT 10", 'ss', [$from, $to]);

$totalRevenue = array_sum(array_map(fn($r) => (float) $r['total'], $revenue));
$totalBookings = array_sum(array_map(fn($r) => (int) $r['c'], $status));
$completed = 0;
foreach ($status as $s) { if ($s['status'] === 'completed') $completed = (int) $s['c']; }
$newCustomers = array_sum(array_map(fn($r) => (int) $r['c'], $signups));

$chart = [
    'revenue' => ['labels' => array_map(fn($r) => date('M Y', strtotime($r['m'] . '-01')), $revenue), 'data' => array_map(fn($r) => (float) $r['total'], $revenue)],
    'usage' => ['labels' => array_column($usage, 'name'), 'data' => array_map('intval', array_column($usage, 'c'))],
    'status' => ['labels' => array_map('ucfirst', array_column($status, 'status')), 'data' => array_map('intval', array_column($status, 'c'))],
    'signups' => ['labels' => array_map(fn($r) => date('M Y', strtotime($r['m'] . '-01')), $signups), 'data' => array_map('intval', array_column($signups, 'c'))],
];

render_header($auth, 'Reports & analytics', 'admin_reports.php', 'Customer activity, service usage and revenue.');
$qs = 'from=' . urlencode($from) . '&to=' . urlencode($to);
?>
<form method="get" class="filters no-print">
  <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <button class="button" type="submit">Apply</button>
  <button class="button button-ghost" type="button" onclick="window.print()">Print</button>
</form>

<div class="stats">
  <div class="stat hl"><div class="num"><?= e(peso($totalRevenue)) ?></div><div class="lbl">Revenue</div></div>
  <div class="stat"><div class="num"><?= (int) $totalBookings ?></div><div class="lbl">Bookings</div></div>
  <div class="stat"><div class="num"><?= (int) $completed ?></div><div class="lbl">Completed services</div></div>
  <div class="stat"><div class="num"><?= (int) $newCustomers ?></div><div class="lbl">New customers</div></div>
</div>

<div class="grid grid-2">
  <div class="card"><div class="card-head"><h2>Revenue by month</h2><a class="button button-ghost button-sm no-print" href="admin_report_export.php?type=revenue&amp;<?= e($qs) ?>">CSV</a></div>
    <?php if ($chart['revenue']['data']): ?><div class="chart-box"><canvas id="cRevenue" aria-label="Revenue by month" role="img"></canvas></div><?php else: ?><div class="empty">No payments in this period.</div><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>Service usage</h2><a class="button button-ghost button-sm no-print" href="admin_report_export.php?type=services&amp;<?= e($qs) ?>">CSV</a></div>
    <?php if ($chart['usage']['data']): ?><div class="chart-box"><canvas id="cUsage" aria-label="Bookings per service" role="img"></canvas></div><?php else: ?><div class="empty">No bookings in this period.</div><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>Bookings by status</h2><a class="button button-ghost button-sm no-print" href="admin_report_export.php?type=bookings&amp;<?= e($qs) ?>">CSV</a></div>
    <?php if ($chart['status']['data']): ?><div class="chart-box"><canvas id="cStatus" aria-label="Bookings by status" role="img"></canvas></div><?php else: ?><div class="empty">No bookings in this period.</div><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>New customer sign-ups</h2></div>
    <?php if ($chart['signups']['data']): ?><div class="chart-box"><canvas id="cSignups" aria-label="New customers per month" role="img"></canvas></div><?php else: ?><div class="empty">No new customers in this period.</div><?php endif; ?></div>
</div>

<div class="card">
  <div class="card-head"><h2>Customer activity — top customers</h2><a class="button button-ghost button-sm no-print" href="admin_report_export.php?type=customers&amp;<?= e($qs) ?>">CSV</a></div>
  <?php if (!$top): ?><div class="empty">No customer activity in this period.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Bookings</th><th>Total paid</th></tr></thead><tbody>
    <?php foreach ($top as $t): ?><tr><td><?= e($t['name']) ?></td><td><?= (int) $t['bookings'] ?></td><td><?= e(peso($t['spent'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<script>window.REPORT = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>
window.addEventListener('load', function () {
  if (!window.Chart) return;
  var gold = '#f2a900', navy = '#182a45', palette = ['#f2a900', '#182a45', '#ffc233', '#5b6472', '#1d6b34', '#23508f', '#8a2c1f'];
  Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
  function make(id, type, cfg, opts) {
    var el = document.getElementById(id); if (!el) return;
    new Chart(el, { type: type, data: cfg, options: Object.assign({ responsive: true, maintainAspectRatio: false }, opts || {}) });
  }
  var R = window.REPORT;
  make('cRevenue', 'bar', { labels: R.revenue.labels, datasets: [{ label: 'Revenue (₱)', data: R.revenue.data, backgroundColor: gold, borderRadius: 6 }] }, { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } });
  make('cUsage', 'bar', { labels: R.usage.labels, datasets: [{ label: 'Bookings', data: R.usage.data, backgroundColor: navy, borderRadius: 6 }] }, { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } });
  make('cStatus', 'doughnut', { labels: R.status.labels, datasets: [{ data: R.status.data, backgroundColor: palette }] }, { plugins: { legend: { position: 'bottom' } } });
  make('cSignups', 'line', { labels: R.signups.labels, datasets: [{ label: 'New customers', data: R.signups.data, borderColor: gold, backgroundColor: 'rgba(242,169,0,.2)', fill: true, tension: .3 }] }, { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } });
});
</script>
<?php render_footer(['https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js']); ?>
