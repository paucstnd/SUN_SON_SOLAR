<?php
/* Calendar + pending queue, shared by admin_schedule.php and dispatcher_dashboard.php */

function render_schedule_view(mysqli $conn, string $baseFile): void {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $first = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if (!$first) { $first = new DateTime('first day of this month'); $month = $first->format('Y-m'); }
    $daysInMonth = (int) $first->format('t');
    $startDow = (int) $first->format('w');
    $prev = (clone $first)->modify('-1 month')->format('Y-m');
    $next = (clone $first)->modify('+1 month')->format('Y-m');
    $from = $first->format('Y-m-01');
    $to = $first->format('Y-m-t');

    $stmt = $conn->prepare("SELECT b.*, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.last_name, c.phone AS customer_phone,
            CONCAT(t.first_name, ' ', t.last_name) AS tech_name
        FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id LEFT JOIN users t ON t.id = b.technician_id
        WHERE b.scheduled_date BETWEEN ? AND ? ORDER BY b.scheduled_time");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $byDay = [];
    foreach ($rows as $r) { $byDay[$r['scheduled_date']][] = $r; }

    $open = [];
    foreach ($conn->query('SELECT day_of_week, is_open FROM availability')->fetch_all(MYSQLI_ASSOC) as $a) { $open[(int) $a['day_of_week']] = (int) $a['is_open']; }
    $blocked = [];
    $stmt = $conn->prepare('SELECT blocked_date, reason FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?');
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $bd) { $blocked[$bd['blocked_date']] = $bd['reason']; }
    $stmt->close();

    $pending = $conn->query("SELECT b.*, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.last_name, c.phone AS customer_phone,
            CONCAT(t.first_name, ' ', t.last_name) AS tech_name
        FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id LEFT JOIN users t ON t.id = b.technician_id
        WHERE b.status = 'pending' ORDER BY b.scheduled_date, b.scheduled_time LIMIT 50")->fetch_all(MYSQLI_ASSOC);

    $techs = technician_list($conn);
    $today = date('Y-m-d');

    $payload = function (array $r): string {
        return e(json_encode([
            'id' => (int) $r['id'], 'customer' => $r['customer_name'], 'phone' => $r['customer_phone'], 'service' => $r['service_name'],
            'address' => $r['address'], 'notes' => $r['notes'], 'status' => $r['status'], 'tech' => (int) $r['technician_id'],
            'date' => $r['scheduled_date'], 'time' => substr($r['scheduled_time'], 0, 5), 'note' => $r['admin_note'],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    };
    ?>
<div class="stats">
  <div class="stat hl"><div class="num"><?= count($pending) ?></div><div class="lbl">Pending requests</div></div>
  <div class="stat"><div class="num"><?= count(array_filter($rows, fn($r) => $r['status'] === 'approved')) ?></div><div class="lbl">Approved this month</div></div>
  <div class="stat"><div class="num"><?= count(array_filter($rows, fn($r) => $r['status'] === 'approved' && !$r['technician_id'])) ?></div><div class="lbl">Approved, no technician</div></div>
  <div class="stat"><div class="num"><?= count(array_filter($rows, fn($r) => $r['status'] === 'completed')) ?></div><div class="lbl">Completed this month</div></div>
</div>

<div class="card">
  <div class="cal-head">
    <h2><?= e($first->format('F Y')) ?></h2>
    <div class="actions">
      <a class="button button-ghost button-sm" href="<?= e($baseFile) ?>?month=<?= e($prev) ?>">← Prev</a>
      <a class="button button-ghost button-sm" href="<?= e($baseFile) ?>?month=<?= e(date('Y-m')) ?>">Today</a>
      <a class="button button-ghost button-sm" href="<?= e($baseFile) ?>?month=<?= e($next) ?>">Next →</a>
    </div>
  </div>
  <div class="cal" role="grid" aria-label="Bookings calendar">
    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?><div class="dow"><?= $d ?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $startDow; $i++): ?><div class="day off"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $date = $first->format('Y-m-') . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $dow = ($startDow + $d - 1) % 7;
        $closed = empty($open[$dow]) || isset($blocked[$date]);
        ?>
      <div class="day <?= $closed ? 'closed' : '' ?> <?= $date === $today ? 'today' : '' ?>" <?= isset($blocked[$date]) ? 'title="Blocked: ' . e($blocked[$date]) . '"' : '' ?>>
        <span class="dnum"><?= $d ?></span>
        <?php foreach ($byDay[$date] ?? [] as $r): ?>
          <button type="button" class="chip chip-<?= e($r['status']) ?> js-booking" data-booking="<?= $payload($r) ?>" title="<?= e($r['service_name'] . ' · ' . $r['customer_name']) ?>"><?= e(fmt_time($r['scheduled_time'])) ?> <?= e($r['last_name']) ?> · <?= e($r['service_name']) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
  <div class="legend">
    <span><i style="background:#fff3d9"></i>Pending</span><span><i style="background:#e7f5ea"></i>Approved</span><span><i style="background:#e6eefb"></i>Completed</span><span><i style="background:#eeece6"></i>Declined / cancelled</span><span><i style="background:repeating-linear-gradient(135deg,#faf9f5,#faf9f5 3px,#e4e1da 3px,#e4e1da 5px)"></i>Closed / blocked</span>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Pending requests</h2></div>
  <?php if (!$pending): ?><div class="empty">No pending requests. 🎉</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Requested</th><th>Customer</th><th>Service</th><th>Address</th><th></th></tr></thead>
    <tbody><?php foreach ($pending as $r): ?>
      <tr>
        <td><strong><?= e(fmt_date($r['scheduled_date'])) ?></strong><span class="sub"><?= e(fmt_time($r['scheduled_time'])) ?></span></td>
        <td><?= e($r['customer_name']) ?><span class="sub"><?= e($r['customer_phone']) ?></span></td>
        <td><?= e($r['service_name']) ?></td><td><?= e($r['address']) ?></td>
        <td><button type="button" class="button button-sm js-booking" data-booking="<?= $payload($r) ?>">Review</button></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="modal-overlay" id="bookingModal" role="dialog" aria-modal="true" aria-labelledby="bm_title">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="bm_title">Booking</h2><p class="muted small" id="bm_sub"></p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <dl class="kv">
      <dt>Customer</dt><dd id="bm_customer"></dd>
      <dt>Phone</dt><dd id="bm_phone"></dd>
      <dt>Service</dt><dd id="bm_service"></dd>
      <dt>Address</dt><dd id="bm_address"></dd>
      <dt>Customer notes</dt><dd id="bm_notes"></dd>
    </dl>
    <form method="post" action="booking_update.php" id="bm_form">
      <?= csrf_field() ?>
      <input type="hidden" name="booking_id" id="bm_id"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="return" value="<?= e($baseFile) ?>">
      <div class="form-grid">
        <div class="field"><label for="bm_status">Status</label>
          <select id="bm_status" name="status"><?php foreach (BOOKING_STATUSES as $s): ?><option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="bm_tech">Assigned technician</label>
          <select id="bm_tech" name="technician_id"><option value="0">— Unassigned —</option><?php foreach ($techs as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="bm_date">Date</label><input type="date" id="bm_date" name="scheduled_date" required></div>
        <div class="field"><label for="bm_time">Time</label><input type="time" id="bm_time" name="scheduled_time" required></div>
        <div class="field full"><label for="bm_note">Note to customer (shown with the booking)</label><input id="bm_note" name="admin_note" maxlength="255" placeholder="e.g. Reason for declining or arrival window"></div>
      </div>
      <div class="form-actions">
        <button type="button" class="button button-ok" id="bm_approve">Approve</button>
        <button type="button" class="button button-danger" id="bm_decline">Decline</button>
        <button type="submit" class="button">Save changes</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  var f = document.getElementById('bm_form');
  function set(id, v) { document.getElementById(id).textContent = v || '—'; }
  document.querySelectorAll('.js-booking').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = JSON.parse(b.getAttribute('data-booking'));
      document.getElementById('bm_title').textContent = 'Booking #' + d.id;
      set('bm_sub', d.service + ' · ' + d.status.toUpperCase());
      set('bm_customer', d.customer); set('bm_phone', d.phone); set('bm_service', d.service); set('bm_address', d.address); set('bm_notes', d.notes);
      document.getElementById('bm_id').value = d.id;
      document.getElementById('bm_status').value = d.status;
      document.getElementById('bm_tech').value = d.tech || 0;
      document.getElementById('bm_date').value = d.date;
      document.getElementById('bm_time').value = d.time;
      document.getElementById('bm_note').value = d.note || '';
      SSS.openModal('bookingModal');
    });
  });
  document.getElementById('bm_approve').addEventListener('click', function () { document.getElementById('bm_status').value = 'approved'; f.submit(); });
  document.getElementById('bm_decline').addEventListener('click', function () {
    var n = document.getElementById('bm_note');
    if (!n.value.trim()) { n.focus(); n.placeholder = 'Please add a short reason for declining'; n.style.borderColor = '#c0392b'; return; }
    document.getElementById('bm_status').value = 'declined'; f.submit();
  });
})();
</script>
<?php
}
