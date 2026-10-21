<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['dispatcher', 'hr', 'staff']);
$uid = $auth['id'];
$self = 'employee_attendance.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';
    $lat = $_POST['latitude'] ?? '';
    $lng = $_POST['longitude'] ?? '';
    $acc = $_POST['accuracy'] ?? '';
    $hasCoords = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
    $latP = $hasCoords ? round((float) $lat, 7) : null;
    $lngP = $hasCoords ? round((float) $lng, 7) : null;
    $accP = ($hasCoords && is_numeric($acc)) ? (int) min(max((float) $acc, 0), 99999) : null;

    if ($action === 'timeout') {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE attendance SET checked_out_at = ?, out_latitude = ?, out_longitude = ? WHERE user_id = ? AND checked_out_at IS NULL AND DATE(checked_in_at) = CURDATE() ORDER BY checked_in_at DESC LIMIT 1');
        $stmt->bind_param('sddi', $now, $latP, $lngP, $uid);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed > 0) {
            flash_success('Check-out recorded at ' . date('g:i A') . '.');
        } else {
            flash_error('You have no open check-in to close.');
        }
        redirect($self);
    }

    if ($action !== 'timein') {
        flash_error('Choose a valid attendance action.');
        redirect($self);
    }

    $stmt = $conn->prepare('SELECT id FROM attendance WHERE user_id = ? AND checked_out_at IS NULL AND DATE(checked_in_at) = CURDATE() LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $hasOpenCheckin = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($hasOpenCheckin) {
        flash_error('You already have an open check-in. Check out before checking in again.');
        redirect($self);
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM attendance WHERE user_id = ? AND checked_in_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recent = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    if ($recent > 0) {
        flash_error('You already checked in a moment ago.');
        redirect($self);
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO attendance (user_id, full_name, checked_in_at, latitude, longitude, accuracy_m) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issddi', $uid, $auth['name'], $now, $latP, $lngP, $accP);
    $stmt->execute();
    $stmt->close();
    flash_success('Check-in recorded at ' . date('g:i A') . ($hasCoords ? ' with your location.' : ' (location was not shared).'));
    redirect($self);
}

$stmt = $conn->prepare('SELECT checked_in_at, checked_out_at, latitude, longitude FROM attendance WHERE user_id = ? AND DATE(checked_in_at) = CURDATE() ORDER BY checked_in_at DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$today = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$isOpen = !empty($today) && $today[0]['checked_out_at'] === null;

$stmt = $conn->prepare('SELECT checked_in_at, checked_out_at, latitude FROM attendance WHERE user_id = ? ORDER BY checked_in_at DESC LIMIT 100');
$stmt->bind_param('i', $uid);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Attendance', 'employee_attendance.php', 'Record your workday check-in and check-out.');
?>
<div class="card checkin">
  <form method="post" id="attendanceForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isOpen ? 'timeout' : 'timein' ?>">
    <input type="hidden" name="latitude" id="lat"><input type="hidden" name="longitude" id="lng"><input type="hidden" name="accuracy" id="acc">
    <button type="button" class="checkin-btn" id="attendanceBtn" data-label="<?= $isOpen ? 'CHECK OUT' : 'CHECK IN' ?>"><?= $isOpen ? 'CHECK OUT' : 'CHECK IN' ?></button>
    <p class="checkin-note" id="attendanceNote"><?= $isOpen ? 'You are checked in. Tap when you are done for the day.' : 'Records your name, the time and your location.' ?></p>
  </form>
</div>
<div class="card" style="margin-top:18px">
  <div class="card-head"><h2>Attendance history</h2></div>
  <?php if (!$history): ?>
    <div class="empty">No attendance records yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Type</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($history as $entry): ?>
            <tr>
              <td><?= e(fmt_date($entry['checked_in_at'])) ?></td>
              <td>Workday</td>
              <td>
                <?php if ($entry['checked_out_at']): ?>
                  <span class="badge badge-ok">Completed</span>
                <?php else: ?>
                  <span class="badge badge-warn">Checked in</span>
                <?php endif; ?>
                <span class="sub"><?= $entry['latitude'] !== null ? 'Location saved' : 'No location shared' ?></span>
              </td>
              <td>
                In <?= e(date('g:i A', strtotime($entry['checked_in_at']))) ?>
                <?php if ($entry['checked_out_at']): ?>
                  <span class="sub">Out <?= e(date('g:i A', strtotime($entry['checked_out_at']))) ?></span>
                <?php else: ?>
                  <span class="sub">Not checked out</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script>
(function () {
  var btn = document.getElementById('attendanceBtn'), note = document.getElementById('attendanceNote'), form = document.getElementById('attendanceForm');
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
