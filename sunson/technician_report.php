<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

/* Technicians log problems met during service (equipment issues, customer concerns). Goes straight to the admin dashboard. */
$auth = require_auth(['technician']);
$uid = $auth['id'];
$self = 'technician_report.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $type = $_POST['type'] ?? '';
    $desc = clean_text($_POST['description'] ?? '', 3000);
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if (!in_array($type, ['Equipment issue', 'Customer concern', 'Safety hazard', 'Other'], true)) {
        flash_error('Please choose what kind of problem it is.');
    } elseif (strlen($desc) < 10) {
        flash_error('Please describe the problem (at least a short sentence).');
    } else {
        $bookingParam = null;
        if ($bookingId > 0) {
            $stmt = $conn->prepare('SELECT id FROM bookings WHERE id = ? AND technician_id = ?');
            $stmt->bind_param('ii', $bookingId, $uid);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) { $bookingParam = $bookingId; }
            $stmt->close();
        }
        $stmt = $conn->prepare('INSERT INTO incident_reports (user_id, booking_id, type, description) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $uid, $bookingParam, $type, $desc);
        $stmt->execute();
        $stmt->close();
        flash_success('Report sent to the admin team. Thank you!');
    }
    redirect($self);
}

$stmt = $conn->prepare("SELECT b.id, b.scheduled_date, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id
    WHERE b.technician_id = ? AND b.status IN ('approved','completed') AND b.scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    ORDER BY b.scheduled_date DESC LIMIT 30");
$stmt->bind_param('i', $uid);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare('SELECT type, description, status, admin_note, created_at FROM incident_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$stmt->bind_param('i', $uid);
$stmt->execute();
$mine = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Report a problem', 'technician_report.php', 'Tell the admin team about equipment issues, customer concerns or safety hazards.');
?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);align-items:start">
  <div class="card">
    <div class="card-head"><h2>New report</h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <div style="display:grid;gap:14px">
        <div class="field"><label for="type">What happened?</label>
          <select id="type" name="type" required><option value="">Choose one</option><option>Equipment issue</option><option>Customer concern</option><option>Safety hazard</option><option>Other</option></select></div>
        <div class="field"><label for="booking_id">Related job (optional)</label>
          <select id="booking_id" name="booking_id"><option value="">Not linked to a job</option>
            <?php foreach ($jobs as $j): ?><option value="<?= (int) $j['id'] ?>"><?= e(fmt_date($j['scheduled_date']) . ' · ' . $j['service_name'] . ' · ' . $j['customer_name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="description">Details</label><textarea id="description" name="description" maxlength="3000" required placeholder="Describe the problem, where it happened and what you did."></textarea></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Send report</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h2>My recent reports</h2></div>
    <?php if (!$mine): ?><div class="empty">You have not sent any reports yet.</div><?php endif; ?>
    <?php foreach ($mine as $m): ?>
      <div style="padding:12px 0;border-top:1px solid var(--line)">
        <div class="actions" style="justify-content:space-between"><strong><?= e($m['type']) ?></strong><?= badge($m['status']) ?></div>
        <p style="white-space:pre-wrap;margin:4px 0"><?= e($m['description']) ?></p>
        <span class="muted small"><?= e(fmt_datetime($m['created_at'])) ?></span>
        <?php if ($m['admin_note'] !== ''): ?><p class="small" style="margin-top:4px"><strong>Admin note:</strong> <?= e($m['admin_note']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.2fr"]{grid-template-columns:1fr !important;}}</style>
<?php render_footer(); ?>
