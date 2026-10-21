<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['admin']);
$self = 'admin_incidents.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $note = clean_text($_POST['admin_note'] ?? '', 255);
    if (in_array($status, ['new', 'reviewed', 'resolved'], true)) {
        $stmt = $conn->prepare('UPDATE incident_reports SET status = ?, admin_note = ? WHERE id = ?');
        $stmt->bind_param('ssi', $status, $note, $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Report updated.');
    }
    redirect($self . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$filter = $_GET['status'] ?? '';
$where = in_array($filter, ['new', 'reviewed', 'resolved'], true) ? "WHERE i.status = '" . $filter . "'" : '';
$rows = $conn->query("SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) AS tech_name, b.scheduled_date, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM incident_reports i JOIN users u ON u.id = i.user_id
    LEFT JOIN bookings b ON b.id = i.booking_id LEFT JOIN services s ON s.id = b.service_id LEFT JOIN users c ON c.id = b.user_id
    $where ORDER BY FIELD(i.status,'new','reviewed','resolved'), i.created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

render_header($auth, 'Field reports', 'admin_incidents.php', 'Equipment issues, customer concerns and safety hazards reported by technicians.');
?>
<div class="tabs">
  <a class="tab <?= $filter === '' ? 'active' : '' ?>" href="?">All</a>
  <a class="tab <?= $filter === 'new' ? 'active' : '' ?>" href="?status=new">New</a>
  <a class="tab <?= $filter === 'reviewed' ? 'active' : '' ?>" href="?status=reviewed">Reviewed</a>
  <a class="tab <?= $filter === 'resolved' ? 'active' : '' ?>" href="?status=resolved">Resolved</a>
</div>
<?php if (!$rows): ?><div class="card"><div class="empty">No reports.</div></div><?php endif; ?>
<?php foreach ($rows as $r): ?>
  <div class="card">
    <div class="card-head" style="margin-bottom:8px">
      <div><h2><?= e($r['type']) ?></h2><span class="muted small">by <?= e($r['tech_name']) ?> · <?= e(fmt_datetime($r['created_at'])) ?><?php if ($r['booking_id']): ?> · Job #<?= (int) $r['booking_id'] ?> (<?= e($r['service_name']) ?>, <?= e($r['customer_name']) ?>, <?= e(fmt_date($r['scheduled_date'])) ?>)<?php endif; ?></span></div>
      <?= badge($r['status']) ?>
    </div>
    <p style="white-space:pre-wrap"><?= e($r['description']) ?></p>
    <form method="post" class="filters" style="margin:14px 0 0">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <div class="field"><label>Status</label><select name="status"><?= select_options(['new' => 'New', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved'], $r['status']) ?></select></div>
      <div class="field" style="flex:1;min-width:220px"><label>Admin note</label><input name="admin_note" value="<?= e($r['admin_note']) ?>" maxlength="255" placeholder="Action taken or follow-up"></div>
      <button class="button button-sm" type="submit" style="height:44px">Save</button>
    </form>
  </div>
<?php endforeach; ?>
<?php render_footer(); ?>
