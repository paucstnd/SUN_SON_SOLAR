<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['admin']);
$self = 'admin_notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';

    if ($action === 'settings') {
        foreach (array_keys(NOTIFICATION_TYPES) as $t) {
            $on = isset($_POST['enabled'][$t]) ? 1 : 0;
            $stmt = $conn->prepare('INSERT INTO notification_settings (type, is_enabled) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)');
            $stmt->bind_param('si', $t, $on);
            $stmt->execute();
            $stmt->close();
        }
        flash_success('Alert settings saved.');
    } elseif ($action === 'send') {
        $type = $_POST['type'] ?? '';
        $title = clean_text($_POST['title'] ?? '', 120);
        $message = clean_text($_POST['message'] ?? '', 500);
        $audience = $_POST['audience'] ?? 'all';
        $target = (int) ($_POST['user_id'] ?? 0);

        if (!isset(NOTIFICATION_TYPES[$type])) {
            flash_error('Please choose an alert type.');
        } elseif ($title === '' || $message === '') {
            flash_error('Please enter a title and a message.');
        } elseif (!notification_enabled($conn, $type)) {
            flash_error('"' . NOTIFICATION_TYPES[$type] . '" alerts are switched off. Turn them on first.');
        } else {
            $by = $auth['name'];
            if ($audience === 'one') {
                $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND account_type = 'Customer' AND status = 'approved'");
                $stmt->bind_param('i', $target);
                $stmt->execute();
                $ok = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$ok) {
                    flash_error('Please choose a customer.');
                } else {
                    notify_user($conn, $target, $type, $title, $message, $by);
                    flash_success('Alert sent to the customer.');
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO notifications (audience, user_id, type, title, message, created_by) VALUES ('customers', NULL, ?, ?, ?, ?)");
                $stmt->bind_param('ssss', $type, $title, $message, $by);
                $stmt->execute();
                $stmt->close();
                flash_success('Alert sent to all customers.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Alert removed.');
    }
    redirect($self);
}

$settings = [];
foreach ($conn->query('SELECT type, is_enabled FROM notification_settings')->fetch_all(MYSQLI_ASSOC) as $s) { $settings[$s['type']] = (int) $s['is_enabled']; }
$customers = $conn->query("SELECT id, first_name, last_name, username FROM users WHERE account_type = 'Customer' AND status = 'approved' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$recent = $conn->query("SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) AS to_name FROM notifications n LEFT JOIN users u ON u.id = n.user_id ORDER BY n.created_at DESC, n.id DESC LIMIT 40")->fetch_all(MYSQLI_ASSOC);
$descriptions = [
    'schedule' => 'Booking approved, declined, rescheduled or completed',
    'reminder' => 'Service reminders (customers can opt out)',
    'promotion' => 'Offers and promos (customers can opt out)',
    'system' => 'Payments received and system notices',
];

render_header($auth, 'Customer alerts', 'admin_notifications.php', 'Decide what kind of alerts go out to customers, and send reminders, promotions or system updates.');
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2>Alert types</h2></div>
    <p class="muted small" style="margin-bottom:12px">Switch an alert type off to stop it from being sent (automatic alerts too).</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <div style="display:grid;gap:14px">
        <?php foreach (NOTIFICATION_TYPES as $k => $label): ?>
          <label class="check" style="align-items:flex-start"><input type="checkbox" name="enabled[<?= e($k) ?>]" <?= ($settings[$k] ?? 1) ? 'checked' : '' ?>><span><strong><?= e($label) ?></strong><br><span class="muted small"><?= e($descriptions[$k]) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save settings</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2>Send an alert</h2></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="send">
      <div class="form-grid">
        <div class="field"><label for="type">Type</label><select id="type" name="type"><?php foreach (NOTIFICATION_TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'reminder' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="audience">Send to</label><select id="audience" name="audience"><option value="all">All customers</option><option value="one">One customer</option></select></div>
        <div class="field full" id="oneWrap" style="display:none"><label for="user_id">Customer</label><select id="user_id" name="user_id"><option value="0">Select customer</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name'] . ' (@' . $c['username'] . ')') ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="title">Title</label><input id="title" name="title" maxlength="120" required></div>
        <div class="field full"><label for="message">Message</label><textarea id="message" name="message" maxlength="500" required></textarea></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Send alert</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Recent alerts</h2></div>
  <?php if (!$recent): ?><div class="empty">No alerts yet.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Sent</th><th>Type</th><th>To</th><th>Message</th><th>By</th><th></th></tr></thead><tbody>
    <?php foreach ($recent as $n): ?>
      <tr><td><?= e(fmt_datetime($n['created_at'])) ?></td><td><?= badge($n['type']) ?></td><td><?= $n['audience'] === 'customers' ? 'All customers' : e($n['to_name'] ?: '—') ?></td>
      <td><strong><?= e($n['title']) ?></strong><span class="sub"><?= e($n['message']) ?></span></td><td><?= e($n['created_by']) ?></td>
      <td><form method="post" class="inline-form" data-confirm="Remove this alert?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="button button-danger button-sm" type="submit">Remove</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<script>
document.getElementById('audience').addEventListener('change', function () {
  document.getElementById('oneWrap').style.display = this.value === 'one' ? '' : 'none';
});
</script>
<?php render_footer(); ?>
