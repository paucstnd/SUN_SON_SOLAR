<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

/* Employee time-ins (name, time and coordinates) */
$auth = require_auth(['admin']);

$from = valid_date_string($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$to = valid_date_string($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
if ($from > $to) { [$from, $to] = [$to, $from]; }

$stmt = $conn->prepare("SELECT a.*, b.id AS job_id, s.name AS service_name FROM attendance a LEFT JOIN bookings b ON b.id = a.booking_id LEFT JOIN services s ON s.id = b.service_id
    WHERE DATE(a.checked_in_at) BETWEEN ? AND ? ORDER BY a.checked_in_at DESC LIMIT 500");
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Attendance', 'admin_attendance.php', 'Employee time-ins recorded at the work site.');
?>
<form method="get" class="filters">
  <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <button class="button" type="submit">Apply</button>
  <a class="button button-ghost" href="admin_report_export.php?type=attendance&amp;from=<?= e($from) ?>&amp;to=<?= e($to) ?>">Export CSV</a>
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No time-ins in this period.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Employee</th><th>Date</th><th>Time in</th><th>Time out</th><th>Hours</th><th>Job</th><th>Location</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['full_name']) ?></strong></td>
        <td><?= e(fmt_date($r['checked_in_at'])) ?></td><td><?= e(date('g:i A', strtotime($r['checked_in_at']))) ?></td>
        <td><?= $r['checked_out_at'] ? e(date('g:i A', strtotime($r['checked_out_at']))) : '<span class="badge badge-warn">Not timed out</span>' ?></td>
        <td><?= $r['checked_out_at'] ? e(number_format((strtotime($r['checked_out_at']) - strtotime($r['checked_in_at'])) / 3600, 1)) . ' h' : '—' ?></td>
        <td><?= $r['job_id'] ? '#' . (int) $r['job_id'] . ' · ' . e($r['service_name']) : '<span class="muted">—</span>' ?></td>
        <td><?php if ($r['latitude'] !== null): ?>
          <?= e(number_format((float) $r['latitude'], 5)) ?>, <?= e(number_format((float) $r['longitude'], 5)) ?><?= $r['accuracy_m'] !== null ? ' <span class="muted small">(±' . (int) $r['accuracy_m'] . ' m)</span>' : '' ?>
          <span class="sub"><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e($r['latitude']) ?>&amp;mlon=<?= e($r['longitude']) ?>#map=17/<?= e($r['latitude']) ?>/<?= e($r['longitude']) ?>">View on map</a></span>
        <?php else: ?><span class="badge badge-warn">Location not shared</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php render_footer(); ?>
