<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['customer']);
$uid = $auth['id'];
$self = 'customer_support.php';
const TICKET_CATEGORIES = ['General', 'Billing', 'Installation', 'Maintenance / Repair', 'Account', 'Feedback'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';

    if ($action === 'new') {
        $subject = clean_text($_POST['subject'] ?? '', 150);
        $category = in_array($_POST['category'] ?? '', TICKET_CATEGORIES, true) ? $_POST['category'] : 'General';
        $message = clean_text($_POST['message'] ?? '', 3000);
        if ($subject === '' || $message === '') {
            flash_error('Please enter a subject and a message.');
            redirect($self);
        }
        $stmt = $conn->prepare('INSERT INTO tickets (user_id, subject, category) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $uid, $subject, $category);
        $stmt->execute();
        $tid = $stmt->insert_id;
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message) VALUES (?, 'customer', ?, ?)");
        $stmt->bind_param('iss', $tid, $auth['name'], $message);
        $stmt->execute();
        $stmt->close();
        flash_success('Your ticket was sent. We will reply as soon as we can.');
        redirect($self . '?id=' . $tid);
    }

    $tid = (int) ($_POST['ticket_id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, status FROM tickets WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $tid, $uid);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) {
        flash_error('Ticket not found.');
        redirect($self);
    }

    if ($action === 'reply') {
        $message = clean_text($_POST['message'] ?? '', 3000);
        if ($message === '') {
            flash_error('Please type your message.');
        } else {
            $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_name, message) VALUES (?, 'customer', ?, ?)");
            $stmt->bind_param('iss', $tid, $auth['name'], $message);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE tickets SET status = 'open' WHERE id = " . (int) $tid);
            flash_success('Message sent.');
        }
    } elseif ($action === 'close') {
        $conn->query("UPDATE tickets SET status = 'closed' WHERE id = " . (int) $tid);
        flash_success('Ticket closed.');
    }
    redirect($self . '?id=' . $tid);
}

$viewId = (int) ($_GET['id'] ?? 0);
$ticket = null;
$messages = [];
if ($viewId) {
    $stmt = $conn->prepare('SELECT * FROM tickets WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $viewId, $uid);
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

$stmt = $conn->prepare('SELECT id, subject, category, status, updated_at FROM tickets WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Support', 'customer_support.php', 'Need help? Send us a message and we will get back to you.');
?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);align-items:start">
  <div>
    <div class="card">
      <div class="card-head"><h2>New ticket</h2></div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="new">
        <div style="display:grid;gap:14px">
          <div class="field"><label for="category">Topic</label><select id="category" name="category"><?= select_options(array_combine(TICKET_CATEGORIES, TICKET_CATEGORIES)) ?></select></div>
          <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" required></div>
          <div class="field"><label for="message">How can we help?</label><textarea id="message" name="message" maxlength="3000" required></textarea></div>
        </div>
        <div class="form-actions"><button class="button" type="submit">Send ticket</button></div>
      </form>
    </div>
    <div class="card">
      <div class="card-head"><h2>My tickets</h2></div>
      <?php if (!$tickets): ?><div class="empty">No tickets yet.</div><?php else: foreach ($tickets as $t): ?>
        <a href="?id=<?= (int) $t['id'] ?>" style="display:block;text-decoration:none;color:inherit;padding:12px 0;border-top:1px solid var(--line)">
          <div class="actions" style="justify-content:space-between"><strong><?= e($t['subject']) ?></strong><?= badge($t['status']) ?></div>
          <span class="muted small"><?= e($t['category']) ?> · <?= e(fmt_datetime($t['updated_at'])) ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <?php if (!$ticket): ?>
      <div class="empty">Select a ticket to read the conversation, or create a new one.<br><span class="small">You can also call us at 0929 123 0983.</span></div>
    <?php else: ?>
      <div class="card-head"><div><h2><?= e($ticket['subject']) ?></h2><span class="muted small"><?= e($ticket['category']) ?> · opened <?= e(fmt_datetime($ticket['created_at'])) ?></span></div><?= badge($ticket['status']) ?></div>
      <div class="thread">
        <?php foreach ($messages as $m): ?>
          <div class="bubble <?= $m['sender_type'] === 'customer' ? 'mine' : '' ?>"><small><?= e($m['sender_type'] === 'customer' ? 'You' : $m['sender_name'] . ' (Sun Son Solar)') ?> · <?= e(fmt_datetime($m['created_at'])) ?></small><?= e($m['message']) ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ($ticket['status'] !== 'closed'): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
        <div class="field"><label for="reply">Reply</label><textarea id="reply" name="message" maxlength="3000" required></textarea></div>
        <div class="form-actions"><button class="button" type="submit">Send reply</button></div>
      </form>
      <form method="post" data-confirm="Close this ticket?" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>"><button class="button button-ghost button-sm" type="submit">Mark as resolved</button></form>
      <?php else: ?><p class="muted">This ticket is closed. Start a new ticket if you need more help.</p><?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.5fr"]{grid-template-columns:1fr !important;}}</style>
<?php render_footer(); ?>
