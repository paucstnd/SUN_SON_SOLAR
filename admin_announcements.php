<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

/* Announcements to employees (reminders, safety guidelines, urgent notices). Admin and HR. */
$auth = require_auth(['admin', 'hr']);
$self = 'admin_announcements.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'post') {
        $title = clean_text($_POST['title'] ?? '', 120);
        $body = clean_text($_POST['body'] ?? '', 3000);
        $priority = in_array($_POST['priority'] ?? '', ['normal', 'safety', 'urgent'], true) ? $_POST['priority'] : 'normal';
        $dept = $_POST['department'] ?? '';
        $deptParam = in_array($dept, DEPARTMENTS, true) ? $dept : null;
        if ($title === '' || $body === '') {
            flash_error('Please enter a title and a message.');
        } else {
            $by = $auth['name'] . ' (' . ($auth['role'] === 'admin' ? 'Admin' : 'HR') . ')';
            $stmt = $conn->prepare('INSERT INTO announcements (title, body, priority, department, posted_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $title, $body, $priority, $deptParam, $by);
            $stmt->execute();
            $stmt->close();
            flash_success('Announcement posted.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Announcement removed.');
    }
    redirect($self);
}

$list = $conn->query('SELECT * FROM announcements ORDER BY created_at DESC, id DESC LIMIT 50')->fetch_all(MYSQLI_ASSOC);
render_header($auth, 'Announcements', 'admin_announcements.php', 'Send updates to employees: reminders, safety guidelines or urgent notices.');
?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);align-items:start">
  <div class="card">
    <div class="card-head"><h2>New announcement</h2></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="post">
      <div class="form-grid">
        <div class="field"><label for="priority">Priority</label><select id="priority" name="priority"><option value="normal">Normal</option><option value="safety">Safety guideline</option><option value="urgent">Urgent</option></select></div>
        <div class="field"><label for="department">Send to</label><select id="department" name="department"><option value="">All employees</option><?php foreach (DEPARTMENTS as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?> only</option><?php endforeach; ?></select></div>
        <div class="field full"><label for="title">Title</label><input id="title" name="title" maxlength="120" required></div>
        <div class="field full"><label for="body">Message</label><textarea id="body" name="body" maxlength="3000" required></textarea></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Post announcement</button></div>
    </form>
  </div>
  <div>
    <?php if (!$list): ?><div class="card"><div class="empty">No announcements yet.</div></div><?php endif; ?>
    <?php foreach ($list as $a): ?>
      <div class="card announce <?= e($a['priority']) ?>" style="margin-bottom:14px">
        <div class="card-head" style="margin-bottom:6px"><h2 style="font-size:16px"><?= e($a['title']) ?></h2><div class="actions"><?= badge($a['priority']) ?><span class="badge badge-muted"><?= e($a['department'] ?: 'All employees') ?></span></div></div>
        <p style="white-space:pre-wrap"><?= e($a['body']) ?></p>
        <div class="actions" style="justify-content:space-between;margin-top:10px"><span class="muted small"><?= e($a['posted_by']) ?> · <?= e(fmt_datetime($a['created_at'])) ?></span>
          <form method="post" class="inline-form" data-confirm="Remove this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="button button-danger button-sm" type="submit">Remove</button></form></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.3fr"]{grid-template-columns:1fr !important;}}</style>
<?php render_footer(); ?>
