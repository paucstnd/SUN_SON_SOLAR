<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['admin', 'hr']);
$self = 'admin_resets.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';
    $rid = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT r.id, r.user_id, u.username FROM password_reset_requests r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.status = 'new'");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) {
        flash_error('That request was already handled.');
    } elseif ($action === 'reset') {
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 8) {
            flash_error('Temporary password must be at least 8 characters long.');
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
            $stmt->bind_param('si', $hash, $req['user_id']);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'done', handled_by = ? WHERE id = ?");
            $stmt->bind_param('si', $auth['name'], $rid);
            $stmt->execute();
            $stmt->close();
            flash_success('Temporary password set for @' . $req['username'] . '. Share it with them privately; they must change it on login.');
        }
    } elseif ($action === 'dismiss') {
        $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'dismissed', handled_by = ? WHERE id = ?");
        $stmt->bind_param('si', $auth['name'], $rid);
        $stmt->execute();
        $stmt->close();
        flash_success('Request dismissed.');
    }
    redirect($self);
}

$new = $conn->query("SELECT r.id, r.created_at, u.username, u.first_name, u.last_name, u.account_type, u.department, u.phone, u.email
    FROM password_reset_requests r JOIN users u ON u.id = r.user_id WHERE r.status = 'new' ORDER BY r.created_at")->fetch_all(MYSQLI_ASSOC);
$old = $conn->query("SELECT r.status, r.handled_by, r.created_at, u.username FROM password_reset_requests r JOIN users u ON u.id = r.user_id WHERE r.status <> 'new' ORDER BY r.id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

render_header($auth, 'Password resets', 'admin_resets.php', 'Verify the person (for example by phone), then set a temporary password.');
?>
<div class="card">
  <div class="card-head"><h2>Waiting requests</h2></div>
  <?php if (!$new): ?><div class="empty">No pending requests.</div><?php endif; ?>
  <?php foreach ($new as $r): ?>
    <form method="post" class="filters" style="padding:12px 0;border-top:1px solid var(--line);margin:0">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <div style="flex:1;min-width:220px"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> <span class="muted small">@<?= e($r['username']) ?> · <?= e($r['account_type'] === 'Customer' ? 'Customer' : ($r['department'] ?: 'Employee')) ?></span>
        <span class="sub"><?= e($r['phone']) ?> · <?= e($r['email']) ?> · requested <?= e(fmt_datetime($r['created_at'])) ?></span></div>
      <div class="field"><label>Temporary password</label><input name="password" type="text" minlength="8" autocomplete="off" placeholder="At least 8 characters"></div>
      <button class="button button-sm" type="submit" name="action" value="reset" style="height:44px">Set password</button>
      <button class="button button-ghost button-sm" type="submit" name="action" value="dismiss" style="height:44px" formnovalidate>Dismiss</button>
    </form>
  <?php endforeach; ?>
</div>
<?php if ($old): ?>
<div class="card"><div class="card-head"><h2>Recently handled</h2></div>
  <div class="table-wrap"><table><thead><tr><th>User</th><th>Requested</th><th>Result</th><th>Handled by</th></tr></thead><tbody>
    <?php foreach ($old as $o): ?><tr><td>@<?= e($o['username']) ?></td><td><?= e(fmt_datetime($o['created_at'])) ?></td><td><?= badge($o['status'] === 'done' ? 'completed' : 'cancelled') ?></td><td><?= e($o['handled_by']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php render_footer(); ?>
