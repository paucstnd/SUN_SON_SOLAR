<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);
$self = 'admin_quotes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['new', 'contacted', 'closed'], true)) {
        $stmt = $conn->prepare('UPDATE quote_requests SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Quote request updated.');
    }
    redirect($self);
}

$rows = $conn->query("SELECT q.*, s.name AS service_name FROM quote_requests q LEFT JOIN services s ON s.id = q.service_id ORDER BY FIELD(q.status,'new','contacted','closed'), q.created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
render_header($auth, 'Quote requests', 'admin_quotes.php', 'Free quote requests sent from the website.');
?>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No quote requests yet.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Received</th><th>Name</th><th>Contact</th><th>Interested in</th><th>Message</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(fmt_datetime($r['created_at'])) ?></td><td><strong><?= e($r['name']) ?></strong></td>
        <td><?= e($r['phone']) ?><span class="sub"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></span></td>
        <td><?= e($r['service_name'] ?: 'Not sure yet') ?></td><td><?= e($r['message'] ?: '—') ?></td>
        <td><form method="post" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <select name="status" onchange="this.form.submit()" style="height:34px;min-width:120px"><?= select_options(['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'], $r['status']) ?></select></form></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php render_footer(); ?>
