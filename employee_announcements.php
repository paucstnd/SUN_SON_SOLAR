<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['technician', 'dispatcher', 'hr', 'staff']);
$dept = $auth['department'] ?? '';

$stmt = $conn->prepare("SELECT * FROM announcements WHERE department IS NULL OR department = ?
    ORDER BY FIELD(priority,'urgent','safety','normal'), created_at DESC, id DESC LIMIT 60");
$stmt->bind_param('s', $dept);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Announcements', 'employee_announcements.php', 'Updates from Admin and HR: reminders, safety guidelines and urgent notices.');
?>
<?php if (!$list): ?><div class="card"><div class="empty">No announcements right now.</div></div><?php endif; ?>
<?php foreach ($list as $a): ?>
  <div class="card announce <?= e($a['priority']) ?>">
    <div class="card-head" style="margin-bottom:6px"><h2 style="font-size:17px"><?= e($a['title']) ?></h2><div class="actions"><?= badge($a['priority']) ?></div></div>
    <p style="white-space:pre-wrap"><?= e($a['body']) ?></p>
    <p class="muted small" style="margin-top:10px"><?= e($a['posted_by']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></p>
  </div>
<?php endforeach; ?>
<?php render_footer(); ?>
