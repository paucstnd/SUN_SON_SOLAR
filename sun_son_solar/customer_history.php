<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['customer']);
$uid = $auth['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf('customer_history.php');
    $msg = clean_text($_POST['message'] ?? '', 600);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $hasDone = (int) $stmt->get_result()->fetch_row()[0] > 0;
    $stmt->close();
    $stmt = $conn->prepare('SELECT first_name, last_name, address FROM users WHERE id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $display = $me['first_name'] . ' ' . mb_substr($me['last_name'], 0, 1) . '.';
    $stmt = $conn->prepare('SELECT COUNT(*) FROM testimonials WHERE customer_name = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $stmt->bind_param('s', $display);
    $stmt->execute();
    $recent = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if (!$hasDone) {
        flash_error('You can share your experience after a completed service.');
    } elseif (strlen($msg) < 10) {
        flash_error('Please write a short review (at least 10 characters).');
    } elseif ($recent > 0) {
        flash_error('Thank you! You already sent a review today.');
    } else {
        $loc = clean_text($_POST['location'] ?? '', 100);
        $stmt = $conn->prepare('INSERT INTO testimonials (customer_name, location, message, is_published) VALUES (?, ?, ?, 0)');
        $stmt->bind_param('sss', $display, $loc, $msg);
        $stmt->execute();
        $stmt->close();
        flash_success('Thank you! Your review will appear on our homepage once approved.');
    }
    redirect('customer_history.php');
}

$stmt = $conn->prepare("SELECT b.id, b.scheduled_date, b.scheduled_time, b.address, b.price, s.name AS service_name,
        p.id AS payment_id, p.amount, p.method, p.receipt_no, p.paid_at
    FROM bookings b JOIN services s ON s.id = b.service_id LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.user_id = ? AND b.status = 'completed' ORDER BY b.scheduled_date DESC, b.id DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$done = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT b.scheduled_date, b.scheduled_time, b.status, b.admin_note, s.name AS service_name
    FROM bookings b JOIN services s ON s.id = b.service_id
    WHERE b.user_id = ? AND b.status IN ('cancelled','declined') ORDER BY b.scheduled_date DESC LIMIT 30");
$stmt->bind_param('i', $uid);
$stmt->execute();
$other = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPaid = 0;
foreach ($done as $d) { $totalPaid += (float) ($d['amount'] ?? 0); }

render_header($auth, 'History & receipts', 'customer_history.php', 'Your completed services, payments and receipts.');
?>
<div class="stats">
  <div class="stat hl"><div class="num"><?= count($done) ?></div><div class="lbl">Completed services</div></div>
  <div class="stat"><div class="num"><?= e(peso($totalPaid)) ?></div><div class="lbl">Total paid</div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Completed services</h2></div>
  <?php if (!$done): ?><div class="empty">No completed services yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Service</th><th>Address</th><th>Payment</th><th>Receipt</th></tr></thead>
    <tbody>
    <?php foreach ($done as $d): ?>
      <tr>
        <td><?= e(fmt_date($d['scheduled_date'])) ?><span class="sub"><?= e(fmt_time($d['scheduled_time'])) ?></span></td>
        <td><?= e($d['service_name']) ?></td>
        <td><?= e($d['address']) ?></td>
        <td><?php if ($d['payment_id']): ?><strong><?= e(peso($d['amount'])) ?></strong><span class="sub"><?= e($d['method']) ?> · <?= e(fmt_date($d['paid_at'])) ?></span>
            <?php else: ?><span class="badge badge-warn">Awaiting payment record</span><?php endif; ?></td>
        <td><?php if ($d['payment_id']): ?><a href="receipt.php?id=<?= (int) $d['payment_id'] ?>" target="_blank"><?= e($d['receipt_no']) ?></a><?php else: ?>—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($done): ?>
<div class="card" style="max-width:640px">
  <div class="card-head"><h2>Share your experience</h2></div>
  <form method="post">
    <?= csrf_field() ?>
    <div style="display:grid;gap:14px">
      <div class="field"><label for="rv_loc">City / area (optional)</label><input id="rv_loc" name="location" maxlength="100" placeholder="e.g. Pasig City"></div>
      <div class="field"><label for="rv_msg">Your review</label><textarea id="rv_msg" name="message" maxlength="600" required placeholder="How was your Sun Son Solar service?"></textarea></div>
    </div>
    <p class="hint" style="margin-top:8px">Your first name and last initial may be shown on our homepage after approval.</p>
    <div class="form-actions"><button class="button" type="submit">Send review</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($other): ?>
<div class="card">
  <div class="card-head"><h2>Cancelled &amp; declined requests</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Service</th><th>Status</th><th>Note</th></tr></thead>
    <tbody><?php foreach ($other as $o): ?>
      <tr><td><?= e(fmt_date($o['scheduled_date'])) ?> <span class="sub"><?= e(fmt_time($o['scheduled_time'])) ?></span></td><td><?= e($o['service_name']) ?></td><td><?= badge($o['status']) ?></td><td><?= e($o['admin_note'] ?: '—') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
