<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['technician']);
$uid = $auth['id'];
$self = 'technician_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);

    if (($_POST['action'] ?? '') === 'complete') {
        $jid = (int) ($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT b.id, b.user_id, s.name AS service_name FROM bookings b JOIN services s ON s.id = b.service_id
            WHERE b.id = ? AND b.technician_id = ? AND b.status = 'approved' AND b.scheduled_date <= CURDATE()");
        $stmt->bind_param('ii', $jid, $uid);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$job) {
            flash_error('That job cannot be marked as done.');
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
            $stmt->bind_param('i', $jid);
            $stmt->execute();
            $stmt->close();
            notify_user($conn, (int) $job['user_id'], 'schedule', 'Service completed', 'Your ' . $job['service_name'] . ' was marked completed. Thank you for choosing Sun Son Solar!', $auth['name']);
            flash_success('Job #' . $jid . ' marked as done. The admin can now record the payment.');
        }
        redirect($self);
    }

    $lat = $_POST['latitude'] ?? '';
    $lng = $_POST['longitude'] ?? '';
    $acc = $_POST['accuracy'] ?? '';
    $hasCoords = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
    $latP = $hasCoords ? round((float) $lat, 7) : null;
    $lngP = $hasCoords ? round((float) $lng, 7) : null;
    $accP = ($hasCoords && is_numeric($acc)) ? (int) min(max((float) $acc, 0), 99999) : null;

    if (($_POST['action'] ?? '') === 'timeout') {
        $nowOut = date('Y-m-d H:i:s');
        $oLat = $hasCoords ? $latP : null;
        $oLng = $hasCoords ? $lngP : null;
        $stmt = $conn->prepare('UPDATE attendance SET checked_out_at = ?, out_latitude = ?, out_longitude = ? WHERE user_id = ? AND checked_out_at IS NULL AND DATE(checked_in_at) = CURDATE() ORDER BY checked_in_at DESC LIMIT 1');
        $stmt->bind_param('sddi', $nowOut, $oLat, $oLng, $uid);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed > 0) {
            flash_success('Time-out recorded at ' . date('g:i A') . '. Thank you for today!');
        } else {
            flash_error('You have no open time-in to close.');
        }
        redirect($self);
    }

    
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $bookingParam = null;
    if ($bookingId > 0) {
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND technician_id = ? AND status = 'approved'");
        $stmt->bind_param('ii', $bookingId, $uid);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $bookingParam = $bookingId;
        }
        $stmt->close();
    }

    
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM attendance WHERE user_id = ? AND checked_in_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recent = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    if ($recent > 0) {
        flash_error('You already timed in a moment ago.');
    } else {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('INSERT INTO attendance (user_id, full_name, checked_in_at, latitude, longitude, accuracy_m, booking_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issddii', $uid, $auth['name'], $now, $latP, $lngP, $accP, $bookingParam);
        $stmt->execute();
        $stmt->close();
        flash_success('Time-in recorded at ' . date('g:i A') . ($hasCoords ? ' with your location.' : ' (location was not shared).'));
    }
    redirect($self);
}


$stmt = $conn->prepare('SELECT checked_in_at, checked_out_at, latitude, longitude, booking_id FROM attendance WHERE user_id = ? AND DATE(checked_in_at) = CURDATE() ORDER BY checked_in_at DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$today = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$stmt = $conn->prepare("SELECT b.id, b.scheduled_date, b.scheduled_time, b.address, b.admin_note, s.name AS service_name,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id
    WHERE b.technician_id = ? AND b.status = 'approved' AND b.scheduled_date >= CURDATE()
    ORDER BY b.scheduled_date, b.scheduled_time LIMIT 30");
$stmt->bind_param('i', $uid);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$isOpen = !empty($today) && $today[0]['checked_out_at'] === null;
$todayJobs = array_values(array_filter($jobs, fn($j) => $j['scheduled_date'] === date('Y-m-d')));

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM announcements WHERE (department IS NULL OR department = 'Technician') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$recentNews = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

render_header($auth, 'Time in & my jobs', 'technician_dashboard.php', 'Tap the big button when you arrive at the site.');
?>
<div class="card checkin">
  <form method="post" id="timeinForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isOpen ? 'timeout' : 'timein' ?>">
    <input type="hidden" name="latitude" id="lat"><input type="hidden" name="longitude" id="lng"><input type="hidden" name="accuracy" id="acc">
    <?php if ($todayJobs && !$isOpen): ?>
      <div class="field" style="max-width:360px;margin:0 auto 18px;text-align:left">
        <label for="booking_id">Which job are you at? (optional)</label>
        <select id="booking_id" name="booking_id">
          <option value="">Not sure / other</option>
          <?php foreach ($todayJobs as $j): ?><option value="<?= (int) $j['id'] ?>"><?= e(fmt_time($j['scheduled_time']) . ' · ' . $j['service_name'] . ' · ' . $j['customer_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <button type="button" class="checkin-btn" id="timeinBtn" data-label="<?= $isOpen ? 'TIME OUT' : 'TIME IN' ?>"><?= $isOpen ? 'TIME OUT' : 'TIME IN' ?></button>
    <p class="checkin-note" id="timeinNote"><?= $isOpen ? 'You are timed in. Tap when you are done for the day.' : 'Records your name, the time and your location.' ?></p>
  </form>
  <?php if ($today): ?>
    <div style="margin-top:22px;text-align:left;max-width:420px;margin-left:auto;margin-right:auto">
      <strong>Your time-ins today</strong>
      <?php foreach ($today as $t): ?>
        <div class="actions" style="justify-content:space-between;padding:8px 0;border-top:1px solid var(--line)">
          <span>In <?= e(date('g:i A', strtotime($t['checked_in_at']))) ?><?= $t['checked_out_at'] ? ' · Out ' . e(date('g:i A', strtotime($t['checked_out_at']))) : '' ?><?= $t['booking_id'] ? ' <span class="muted small">· Job #' . (int) $t['booking_id'] . '</span>' : '' ?></span>
          <?= $t['latitude'] !== null ? '<span class="badge badge-ok">Location saved</span>' : '<span class="badge badge-warn">No location</span>' ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($recentNews): ?>
  <div class="message show"><a href="employee_announcements.php">You have <?= $recentNews ?> announcement<?= $recentNews === 1 ? '' : 's' ?> from the last 7 days.</a></div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>My assigned jobs</h2><a class="button button-ghost button-sm" href="technician_report.php">Report a problem</a></div>
  <?php if (!$jobs): ?><div class="empty">No upcoming jobs assigned to you yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>Service</th><th>Customer</th><th>Site address</th><th>Dispatcher note</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($jobs as $j): ?>
      <tr>
        <td><?= e(fmt_date($j['scheduled_date'])) ?><span class="sub"><?= e(fmt_time($j['scheduled_time'])) ?></span></td>
        <td><?= e($j['service_name']) ?></td>
        <td><?= e($j['customer_name']) ?></td>
        <td><?= e($j['address']) ?><span class="sub"><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/search?query=<?= urlencode($j['address']) ?>">Find on map</a></span></td>
        <td><?= $j['admin_note'] !== '' ? e($j['admin_note']) : '<span class="muted">—</span>' ?></td>
        <td><?php if ($j['scheduled_date'] <= date('Y-m-d')): ?>
          <form method="post" class="inline-form" data-confirm="Mark this job as done?"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><input type="hidden" name="booking_id" value="<?= (int) $j['id'] ?>"><button class="button button-ok button-sm" type="submit">Mark done</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint" style="margin-top:10px">For privacy, you only see the customer details needed for the job.</p>
  <?php endif; ?>
</div>

<script>
(function () {
  var btn = document.getElementById('timeinBtn'), note = document.getElementById('timeinNote'), form = document.getElementById('timeinForm');
  if (!btn) return;
  function send() { btn.disabled = true; btn.textContent = 'Saving…'; form.submit(); }
  btn.addEventListener('click', function () {
    btn.disabled = true; btn.textContent = 'Getting location…';
    note.textContent = 'Please allow location access if your phone asks.';
    if (!navigator.geolocation) {
      if (confirm('Your device cannot share location. Continue without location?')) { send(); } else { btn.disabled = false; btn.textContent = btn.dataset.label; }
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      document.getElementById('lat').value = pos.coords.latitude;
      document.getElementById('lng').value = pos.coords.longitude;
      document.getElementById('acc').value = Math.round(pos.coords.accuracy || 0);
      send();
    }, function () {
      if (confirm('We could not get your location. Continue without location?')) { send(); }
      else { btn.disabled = false; btn.textContent = btn.dataset.label; note.textContent = 'Turn on location for this site, then tap again.'; }
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  });
})();
</script>
<?php render_footer(); ?>
