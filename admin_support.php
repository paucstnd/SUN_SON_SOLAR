<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);
$self = 'admin_support.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';
    $tid = (int) ($_POST['ticket_id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, user_id, subject FROM tickets WHERE id = ?');
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) {
        flash_error('Ticket not found.');
        redirect($self);
    }
    if ($action === 'reply') {
        $msg = clean_text($_POST['message'] ?? '', 3000);
        if ($msg === '') {
            flash_error('Please type a reply.');
        } else {
            $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message) VALUES (?, 'admin', ?, ?)");
            $stmt->bind_param('iss', $tid, $auth['name'], $msg);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE tickets SET status = 'answered' WHERE id = " . (int) $tid);
            notify_user($conn, (int) $t['user_id'], 'system', 'New reply to your support ticket', 'Sun Son Solar replied to "' . mb_substr($t['subject'], 0, 80) . '". Open Support to read it.', $auth['name']);
            flash_success('Reply sent.');
        }
    } elseif ($action === 'close') {
        $conn->query("UPDATE tickets SET status = 'closed' WHERE id = " . (int) $tid);
        flash_success('Ticket closed.');
    } elseif ($action === 'reopen') {
        $conn->query("UPDATE tickets SET status = 'open' WHERE id = " . (int) $tid);
        flash_success('Ticket reopened.');
    }
    redirect($self . '?id=' . $tid);
}

$filter = $_GET['status'] ?? '';
$where = in_array($filter, ['open', 'answered', 'closed'], true) ? "WHERE t.status = '" . $filter . "'" : '';
$tickets = $conn->query("SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name FROM tickets t JOIN users u ON u.id = t.user_id $where ORDER BY FIELD(t.status,'open','answered','closed'), t.updated_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

$viewId = (int) ($_GET['id'] ?? 0);
$ticket = null;
$messages = [];
if ($viewId) {
    $stmt = $conn->prepare("SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.phone, u.email FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ticket) {
        $stmt = $conn->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id');
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

render_header($auth, 'Support tickets', 'admin_support.php', 'Reply to customer questions and concerns.');
?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);align-items:start">
  <div class="card">
    <div class="tabs">
      <a class="tab <?= $filter === '' ? 'active' : '' ?>" href="?">All</a>
      <a class="tab <?= $filter === 'open' ? 'active' : '' ?>" href="?status=open">Open</a>
      <a class="tab <?= $filter === 'answered' ? 'active' : '' ?>" href="?status=answered">Answered</a>
      <a class="tab <?= $filter === 'closed' ? 'active' : '' ?>" href="?status=closed">Closed</a>
    </div>
    <?php if (!$tickets): ?><div class="empty">No tickets.</div><?php else: foreach ($tickets as $t): ?>
      <a href="?id=<?= (int) $t['id'] ?><?= $filter ? '&amp;status=' . e($filter) : '' ?>" style="display:block;text-decoration:none;color:inherit;padding:12px 0;border-top:1px solid var(--line)">
        <div class="actions" style="justify-content:space-between"><strong><?= e($t['subject']) ?></strong><?= badge($t['status']) ?></div>
        <span class="muted small"><?= e($t['customer_name']) ?> · <?= e($t['category']) ?> · <?= e(fmt_datetime($t['updated_at'])) ?></span>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <?php if (!$ticket): ?><div class="empty">Select a ticket to read and reply.</div><?php else: ?>
      <div class="card-head"><div><h2><?= e($ticket['subject']) ?></h2><span class="muted small"><?= e($ticket['customer_name']) ?> · <?= e($ticket['phone']) ?> · <?= e($ticket['email']) ?> · <?= e($ticket['category']) ?></span></div><?= badge($ticket['status']) ?></div>
      <div class="thread">
        <?php foreach ($messages as $m): ?>
          <div class="bubble <?= $m['sender_type'] === 'admin' ? 'mine' : '' ?>"><small><?= e($m['sender_name']) ?> · <?= e(fmt_datetime($m['created_at'])) ?></small><?= e($m['message']) ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ($ticket['status'] !== 'closed'): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
        <div class="field"><label for="reply">Reply</label><textarea id="reply" name="message" maxlength="3000" required></textarea></div>
        <div class="form-actions"><button class="button" type="submit">Send reply</button></div>
      </form>
      <form method="post" style="margin-top:10px" data-confirm="Close this ticket?"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>"><button class="button button-ghost button-sm" type="submit">Close ticket</button></form>
      <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>"><button class="button button-ghost" type="submit">Reopen ticket</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.5fr"]{grid-template-columns:1fr !important;}}</style>
<?php render_footer(); ?>
