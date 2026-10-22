<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['customer']);
$uid = $auth['id'];
$self = 'customer_notifications.php';
$prefs = customer_prefs($conn, $uid);
$rem = (int) $prefs['pref_reminders'];
$pro = (int) $prefs['pref_promotions'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    if (($_POST['action'] ?? '') === 'read_all') {
        $stmt = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id)
            SELECT n.id, ? FROM notifications n WHERE " . CUSTOMER_NOTIF_WHERE);
        $stmt->bind_param('iiiii', $uid, $uid, $rem, $pro, $uid);
        $stmt->execute();
        $stmt->close();
        flash_success('All notifications marked as read.');
    } elseif (($_POST['action'] ?? '') === 'read_one') {
        $nid = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id)
            SELECT n.id, ? FROM notifications n WHERE n.id = ? AND " . CUSTOMER_NOTIF_WHERE);
        $stmt->bind_param('iiiiii', $uid, $nid, $uid, $rem, $pro, $uid);
        $stmt->execute();
        $stmt->close();
    }
    redirect($self . (isset($_GET['type']) ? '?type=' . urlencode($_GET['type']) : ''));
}

$type = $_GET['type'] ?? '';
$typeSql = isset(NOTIFICATION_TYPES[$type]) ? ' AND n.type = ?' : '';
$stmt = $conn->prepare("SELECT n.*, (SELECT COUNT(*) FROM notification_reads r WHERE r.notification_id = n.id AND r.user_id = ?) AS is_read
    FROM notifications n WHERE " . CUSTOMER_NOTIF_WHERE . $typeSql . " ORDER BY n.created_at DESC, n.id DESC LIMIT 100");

$bindParams = array_merge([$uid, $uid, $rem, $pro, $uid], $typeSql ? [$type] : []);
$stmt->bind_param('iiiii' . ($typeSql ? 's' : ''), ...$bindParams);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unread = 0;
foreach ($items as $i) { if (!(int) $i['is_read']) $unread++; }

render_header($auth, 'Notifications', 'customer_notifications.php', 'Reminders, schedule updates and offers from Sun Son Solar.');
?>
<div class="card">
  <div class="card-head">
    <div class="tabs" style="border:none;margin:0">
      <a class="tab <?= $type === '' ? 'active' : '' ?>" href="<?= e($self) ?>">All</a>
      <?php foreach (NOTIFICATION_TYPES as $k => $label): ?><a class="tab <?= $type === $k ? 'active' : '' ?>" href="?type=<?= e($k) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <?php if ($unread): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="button button-ghost button-sm" type="submit">Mark all as read</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$items): ?>
    <div class="empty">No notifications yet.</div>
  <?php else: foreach ($items as $n): ?>
    <div class="notif <?= (int) $n['is_read'] ? '' : 'unread' ?>">
      <div style="flex:1">
        <div class="actions" style="gap:8px"><?= badge($n['type']) ?><h3><?= e($n['title']) ?></h3></div>
        <p style="margin-top:4px"><?= e($n['message']) ?></p>
        <span class="muted small"><?= e(fmt_datetime($n['created_at'])) ?></span>
      </div>
      <?php if (!(int) $n['is_read']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_one"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="button button-ghost button-sm" type="submit">Mark read</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
  <p class="hint" style="margin-top:14px">Choose which reminders and promotions you receive in <a href="customer_profile.php#prefs">My profile</a>.</p>
</div>
<?php render_footer(); ?>
