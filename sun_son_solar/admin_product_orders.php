<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['admin']);
$self = 'admin_product_orders.php';
$statuses = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($orderId < 1 || !in_array($status, $statuses, true)) {
        flash_error('Choose a valid product order and status.');
        redirect($self);
    }

    $stmt = $conn->prepare('SELECT user_id, status FROM product_orders WHERE id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        flash_error('That product order could not be found.');
    } else {
        $stmt = $conn->prepare('UPDATE product_orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        if ($order['status'] !== $status) {
            notify_user($conn, (int) $order['user_id'], 'system', 'Product order updated', 'Your product order #' . $orderId . ' is now ' . $status . '.', $auth['name']);
        }
        flash_success('Product order #' . $orderId . ' updated.');
    }
    redirect($self);
}

$rows = $conn->query("SELECT o.id, o.user_id, o.status, o.total_amount, o.delivery_address, o.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.email,
        i.product_name, i.category, i.quantity, i.unit_price
    FROM product_orders o JOIN users u ON u.id = o.user_id
    JOIN product_order_items i ON i.order_id = o.id
    ORDER BY o.created_at DESC, o.id DESC, i.id")->fetch_all(MYSQLI_ASSOC);
$orders = [];
foreach ($rows as $row) {
    $orderId = (int) $row['id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'user_id' => (int) $row['user_id'], 'status' => $row['status'],
            'total_amount' => $row['total_amount'], 'delivery_address' => $row['delivery_address'],
            'created_at' => $row['created_at'], 'customer_name' => $row['customer_name'],
            'email' => $row['email'], 'items' => [],
        ];
    }
    $orders[$orderId]['items'][] = $row;
}

render_header($auth, 'Product orders', 'admin_product_orders.php', 'Review customer orders and update their fulfillment status.');
?>
<div class="card">
  <div class="card-head"><h2>Customer product orders</h2></div>
  <?php if (!$orders): ?><div class="empty">No customer product orders yet.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Order</th><th>Customer</th><th>Products</th><th>Total</th><th>Delivery address</th><th>Status</th><th>Update</th></tr></thead>
      <tbody><?php foreach ($orders as $orderId => $order): ?>
        <tr>
          <td><strong>#<?= (int) $orderId ?></strong><span class="sub"><?= e(fmt_datetime($order['created_at'])) ?></span></td>
          <td><?= e($order['customer_name']) ?><span class="sub"><?= e($order['email']) ?></span></td>
          <td><?php foreach ($order['items'] as $item): ?>
            <?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?><span class="sub"><?= e($item['category']) ?> · <?= $item['unit_price'] !== null ? e(peso($item['unit_price'])) . ' each' : 'Quote required' ?></span>
          <?php endforeach; ?></td>
          <td><?= $order['total_amount'] !== null ? e(peso($order['total_amount'])) : '<span class="muted">Quote required</span>' ?></td>
          <td><?= e($order['delivery_address']) ?></td>
          <td><?= badge($order['status']) ?></td>
          <td>
            <form method="post" class="actions">
              <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
              <select name="status" aria-label="Status for product order #<?= (int) $orderId ?>">
                <?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?>
              </select>
              <button class="button button-sm" type="submit">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
