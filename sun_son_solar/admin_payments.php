<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);
$self = 'admin_payments.php';
const PAY_METHODS = ['Cash', 'GCash', 'Bank Transfer', 'Card', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $bid = (int) ($_POST['booking_id'] ?? 0);
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $method = in_array($_POST['method'] ?? '', PAY_METHODS, true) ? $_POST['method'] : 'Cash';
    $ref = clean_text($_POST['reference_no'] ?? '', 60);

    $stmt = $conn->prepare("SELECT b.id, b.user_id, s.name AS service_name FROM bookings b JOIN services s ON s.id = b.service_id
        LEFT JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.status = 'completed' AND p.id IS NULL");
    $stmt->bind_param('i', $bid);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$b) {
        flash_error('That booking is not available for payment (already paid or not completed).');
    } elseif ($amount <= 0 || $amount > 99999999) {
        flash_error('Please enter a valid amount.');
    } else {
        $receipt = next_receipt_no($conn);
        $by = $auth['name'];
        $stmt = $conn->prepare('INSERT INTO payments (booking_id, user_id, amount, method, reference_no, receipt_no, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iidssss', $bid, $b['user_id'], $amount, $method, $ref, $receipt, $by);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('UPDATE bookings SET price = ? WHERE id = ?');
        $stmt->bind_param('di', $amount, $bid);
        $stmt->execute();
        $stmt->close();
        notify_user($conn, (int) $b['user_id'], 'system', 'Payment received', 'We received your payment of ' . peso($amount) . ' for ' . $b['service_name'] . '. Receipt no. ' . $receipt . ' is in your History.', $by);
        flash_success('Payment recorded. Receipt ' . $receipt . ' is now available to the customer.');
    }
    redirect($self);
}

$unpaid = $conn->query("SELECT b.id, b.scheduled_date, b.price, s.name AS service_name, s.base_price, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.user_id LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.status = 'completed' AND p.id IS NULL ORDER BY b.scheduled_date DESC")->fetch_all(MYSQLI_ASSOC);
$paid = $conn->query("SELECT p.*, s.name AS service_name, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN services s ON s.id = b.service_id JOIN users c ON c.id = p.user_id
    ORDER BY p.paid_at DESC, p.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

render_header($auth, 'Payments', 'admin_payments.php', 'Record payments for completed services and issue receipts.');
?>
<div class="card">
  <div class="card-head"><h2>Awaiting payment record</h2></div>
  <?php if (!$unpaid): ?><div class="empty">All completed services have a payment recorded.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Service date</th><th>Customer</th><th>Service</th><th></th></tr></thead><tbody>
    <?php foreach ($unpaid as $u): $suggest = $u['price'] !== null ? $u['price'] : $u['base_price']; ?>
      <tr><td><?= e(fmt_date($u['scheduled_date'])) ?></td><td><?= e($u['customer_name']) ?></td><td><?= e($u['service_name']) ?></td>
      <td><button type="button" class="button button-sm js-pay" data-id="<?= (int) $u['id'] ?>" data-amount="<?= e((float) $suggest > 0 ? $suggest : '') ?>" data-label="<?= e('#' . $u['id'] . ' · ' . $u['customer_name'] . ' · ' . $u['service_name']) ?>">Record payment</button></td></tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2>Recent payments</h2><a class="button button-ghost button-sm" href="admin_report_export.php?type=revenue">Export CSV</a></div>
  <?php if (!$paid): ?><div class="empty">No payments recorded yet.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Paid</th><th>Customer</th><th>Service</th><th>Method</th><th>Amount</th></tr></thead><tbody>
    <?php foreach ($paid as $p): ?>
      <tr><td><a href="receipt.php?id=<?= (int) $p['id'] ?>" target="_blank"><?= e($p['receipt_no']) ?></a></td><td><?= e(fmt_datetime($p['paid_at'])) ?></td><td><?= e($p['customer_name']) ?></td><td><?= e($p['service_name']) ?></td>
      <td><?= e($p['method']) ?><?php if ($p['reference_no'] !== ''): ?><span class="sub">Ref <?= e($p['reference_no']) ?></span><?php endif; ?></td><td><strong><?= e(peso($p['amount'])) ?></strong></td></tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<div class="modal-overlay" id="payModal" role="dialog" aria-modal="true" aria-labelledby="pmT">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="pmT">Record payment</h2><p class="muted small" id="pm_label"></p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="booking_id" id="pm_id">
      <div class="form-grid">
        <div class="field"><label for="pm_amount">Amount (₱)</label><input type="number" step="0.01" min="0.01" id="pm_amount" name="amount" required></div>
        <div class="field"><label for="pm_method">Method</label><select id="pm_method" name="method"><?= select_options(array_combine(PAY_METHODS, PAY_METHODS)) ?></select></div>
        <div class="field full"><label for="pm_ref">Reference no. (optional)</label><input id="pm_ref" name="reference_no" maxlength="60"></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save &amp; issue receipt</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.js-pay').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('pm_id').value = b.dataset.id;
    document.getElementById('pm_amount').value = b.dataset.amount;
    document.getElementById('pm_label').textContent = b.dataset.label;
    SSS.openModal('payModal');
  });
});
</script>
<?php render_footer(); ?>
