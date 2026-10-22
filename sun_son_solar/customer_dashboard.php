<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['customer']);
$uid = $auth['id'];
$self = 'customer_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';

    if ($action === 'book') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $date = trim($_POST['scheduled_date'] ?? '');
        $time = trim($_POST['scheduled_time'] ?? '');
        $address = clean_text($_POST['address'] ?? '', 255);
        $notes = clean_text($_POST['notes'] ?? '', 500);

        $stmt = $conn->prepare('SELECT id, base_price FROM services WHERE id = ? AND is_active = 1');
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$service) {
            flash_error('Please choose a service.');
        } elseif ($address === '') {
            flash_error('Please enter the service address.');
        } elseif ($err = availability_error($conn, $date, $time)) {
            flash_error($err);
        } else {
            $price = (float) $service['base_price'] > 0 ? $service['base_price'] : null;
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, service_id, scheduled_date, scheduled_time, address, notes, status, price) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param('iissssd', $uid, $serviceId, $date, $time, $address, $notes, $price);
            $stmt->execute();
            $stmt->close();
            notify_user($conn, $uid, 'schedule', 'Request received', 'We received your service request for ' . fmt_date($date) . ' at ' . fmt_time($time) . '. We will confirm it shortly.');
            flash_success('Your request was sent. We will confirm your schedule soon.');
        }
        redirect($self);
    }

    if ($action === 'order_products') {
        $quantities = $_POST['quantity'] ?? [];
        if (!is_array($quantities)) {
            flash_error('Please choose valid product quantities.');
            redirect($self);
        }

        $catalog = $conn->query('SELECT id, category, name, price FROM products WHERE is_active = 1')->fetch_all(MYSQLI_ASSOC);
        $available = [];
        foreach ($catalog as $product) {
            $available[(int) $product['id']] = $product;
        }

        $items = [];
        $needsQuote = false;
        $total = 0.0;
        foreach ($quantities as $productId => $quantityRaw) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantityRaw, FILTER_VALIDATE_INT);
            if ($productId === false || $quantity === false || $quantity < 0 || $quantity > 99) {
                flash_error('Please enter valid quantities between 0 and 99.');
                redirect($self);
            }
            if ($quantity === 0) {
                continue;
            }
            if (!isset($available[$productId])) {
                flash_error('One of the selected products is no longer available.');
                redirect($self);
            }
            $product = $available[$productId];
            if ($product['price'] === null) {
                $needsQuote = true;
            } else {
                $total += (float) $product['price'] * $quantity;
            }
            $items[] = ['product' => $product, 'quantity' => $quantity];
        }

        if (!$items) {
            flash_error('Choose at least one product to place an order.');
            redirect($self);
        }

        $stmt = $conn->prepare('SELECT address FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deliveryAddress = $stmt->get_result()->fetch_assoc()['address'] ?? '';
        $stmt->close();
        if (trim($deliveryAddress) === '') {
            flash_error('Please add your delivery address to your profile before ordering.');
            redirect($self);
        }

        $totalAmount = $needsQuote ? null : round($total, 2);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO product_orders (user_id, total_amount, delivery_address) VALUES (?, ?, ?)");
            $stmt->bind_param('ids', $uid, $totalAmount, $deliveryAddress);
            $stmt->execute();
            $orderId = (int) $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare('INSERT INTO product_order_items (order_id, product_id, product_name, category, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $product = $item['product'];
                $productId = (int) $product['id'];
                $productName = $product['name'];
                $category = $product['category'];
                $quantity = $item['quantity'];
                $unitPrice = $product['price'] === null ? null : (float) $product['price'];
                $stmt->bind_param('iissid', $orderId, $productId, $productName, $category, $quantity, $unitPrice);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        notify_user($conn, $uid, 'system', 'Product order received', 'Your product order #' . $orderId . ' was received and is awaiting confirmation.', 'Sun Son Solar');
        flash_success('Order #' . $orderId . ' submitted. Sun Son Solar will confirm availability and payment details.');
        redirect($self);
    }

    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, status FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $bookingId, $uid);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking || !in_array($booking['status'], ['pending', 'approved'], true)) {
        flash_error('That booking can no longer be changed.');
        redirect($self);
    }

    if ($action === 'cancel') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $bookingId, $uid);
        $stmt->execute();
        $stmt->close();
        flash_success('Your booking was cancelled.');
    } elseif ($action === 'reschedule') {
        $date = trim($_POST['scheduled_date'] ?? '');
        $time = trim($_POST['scheduled_time'] ?? '');
        if ($err = availability_error($conn, $date, $time, $bookingId)) {
            flash_error($err);
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET scheduled_date = ?, scheduled_time = ?, status = 'pending', technician_id = NULL, admin_note = '' WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssii', $date, $time, $bookingId, $uid);
            $stmt->execute();
            $stmt->close();
            notify_user($conn, $uid, 'schedule', 'Reschedule requested', 'Your new requested schedule is ' . fmt_date($date) . ' at ' . fmt_time($time) . '. We will confirm it shortly.');
            flash_success('Reschedule request sent. We will confirm the new time.');
        }
    }
    redirect($self);
}

$stmt = $conn->prepare("SELECT b.*, s.name AS service_name, CONCAT(t.first_name, ' ', t.last_name) AS tech_name
    FROM bookings b JOIN services s ON s.id = b.service_id LEFT JOIN users t ON t.id = b.technician_id
    WHERE b.user_id = ? AND (b.status IN ('pending','approved') OR (b.status = 'declined' AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)))
    ORDER BY b.scheduled_date, b.scheduled_time");
$stmt->bind_param('i', $uid);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT
    SUM(status = 'pending') AS pending, SUM(status = 'approved' AND scheduled_date >= CURDATE()) AS upcoming, SUM(status = 'completed') AS completed
    FROM bookings WHERE user_id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT address FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$myAddress = $stmt->get_result()->fetch_assoc()['address'] ?? '';
$stmt->close();

$services = active_services($conn);
$products = $conn->query('SELECT id, category, name, description, price FROM products WHERE is_active = 1 ORDER BY category, name')->fetch_all(MYSQLI_ASSOC);
$ordersStmt = $conn->prepare("SELECT o.id, o.status, o.total_amount, o.delivery_address, o.created_at,
        i.product_name, i.category, i.quantity, i.unit_price
    FROM product_orders o JOIN product_order_items i ON i.order_id = o.id
    WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC, i.id");
$ordersStmt->bind_param('i', $uid);
$ordersStmt->execute();
$orderRows = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ordersStmt->close();
$orders = [];
foreach ($orderRows as $row) {
    $orderId = (int) $row['id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'status' => $row['status'], 'total_amount' => $row['total_amount'],
            'delivery_address' => $row['delivery_address'], 'created_at' => $row['created_at'], 'items' => [],
        ];
    }
    $orders[$orderId]['items'][] = $row;
}
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$unread = customer_unread_count($conn, $uid);

render_header($auth, 'My schedule', 'customer_dashboard.php', 'Welcome back, ' . explode(' ', $auth['name'])[0] . '. Book and manage your solar services here.');
?>
<div class="stats">
  <div class="stat hl"><div class="num"><?= (int) $counts['upcoming'] ?></div><div class="lbl">Upcoming confirmed</div></div>
  <div class="stat"><div class="num"><?= (int) $counts['pending'] ?></div><div class="lbl">Waiting for confirmation</div></div>
  <div class="stat"><div class="num"><?= (int) $counts['completed'] ?></div><div class="lbl">Completed services</div></div>
  <a class="stat" href="customer_notifications.php" style="text-decoration:none;color:inherit"><div class="num"><?= $unread ?></div><div class="lbl">Unread notifications</div></a>
</div>

<div class="card">
  <div class="card-head">
    <h2>Scheduled services</h2>
    <button type="button" class="button" data-open-modal="bookModal">+ Book a service</button>
  </div>
  <?php if (!$bookings): ?>
    <div class="empty">You have no scheduled services yet. Tap <strong>Book a service</strong> to request a consultation, installation or maintenance.</div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Date &amp; time</th><th>Service</th><th>Address</th><th>Status</th><th>Technician</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td><strong><?= e(fmt_date($b['scheduled_date'])) ?></strong><span class="sub"><?= e(fmt_time($b['scheduled_time'])) ?></span></td>
        <td><?= e($b['service_name']) ?><?php if ($b['notes'] !== ''): ?><span class="sub"><?= e($b['notes']) ?></span><?php endif; ?></td>
        <td><?= e($b['address']) ?></td>
        <td><?= badge($b['status']) ?><?php if ($b['admin_note'] !== ''): ?><span class="sub"><?= e($b['admin_note']) ?></span><?php endif; ?></td>
        <td><?= $b['tech_name'] ? e($b['tech_name']) : '<span class="muted">To be assigned</span>' ?></td>
        <td>
          <?php if (in_array($b['status'], ['pending', 'approved'], true)): ?>
          <div class="actions">
            <button type="button" class="button button-ghost button-sm js-resched" data-id="<?= (int) $b['id'] ?>" data-date="<?= e($b['scheduled_date']) ?>" data-time="<?= e(substr($b['scheduled_time'], 0, 5)) ?>">Reschedule</button>
            <form method="post" class="inline-form" data-confirm="Cancel this booking?">
              <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
              <button type="submit" class="button button-danger button-sm">Cancel</button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <p class="hint" style="margin-top:12px">Completed and cancelled services are in <a href="customer_history.php">History &amp; receipts</a>.</p>
</div>

<div class="card" id="products">
  <div class="card-head"><div><h2>Solar products</h2><p class="muted small">Choose quantities and submit a product order for confirmation.</p>
    <p class="hint">Delivery address: <?= e($myAddress) ?> · <a href="customer_profile.php">Update profile</a></p></div></div>
  <?php if (!$products): ?>
    <div class="empty">There are no products available right now. Please check back later.</div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="order_products">
      <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Description</th><th>Price</th><th style="width:130px">Quantity</th></tr></thead>
        <tbody><?php foreach ($products as $p): ?>
          <tr>
            <td><strong><?= e($p['name']) ?></strong><span class="sub"><?= e($p['category']) ?></span></td>
            <td><?= e($p['description']) ?></td>
            <td><?= $p['price'] !== null ? e(peso($p['price'])) : '<span class="muted">Request a quote</span>' ?></td>
            <td><input type="number" name="quantity[<?= (int) $p['id'] ?>]" min="0" max="99" value="0" aria-label="Quantity for <?= e($p['name']) ?>"></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <p class="hint">This submits an order request, not a payment. We’ll confirm availability, delivery, and payment details. Products marked “Request a quote” need a price confirmation.</p>
      <div class="form-actions"><button class="button" type="submit">Checkout / submit order</button></div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2>My product orders</h2></div>
  <?php if (!$orders): ?><div class="empty">You have not placed a product order yet.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Order</th><th>Items</th><th>Total</th><th>Delivery address</th><th>Status</th></tr></thead>
      <tbody><?php foreach ($orders as $orderId => $order): ?>
        <tr>
          <td><strong>#<?= (int) $orderId ?></strong><span class="sub"><?= e(fmt_datetime($order['created_at'])) ?></span></td>
          <td><?php foreach ($order['items'] as $item): ?>
            <?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?><span class="sub"><?= $item['unit_price'] !== null ? e(peso($item['unit_price'])) . ' each' : 'Price to be confirmed' ?></span>
          <?php endforeach; ?></td>
          <td><?= $order['total_amount'] !== null ? e(peso($order['total_amount'])) : '<span class="muted">Quote required</span>' ?></td>
          <td><?= e($order['delivery_address']) ?></td>
          <td><?= badge($order['status']) ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>


<div class="modal-overlay" id="bookModal" role="dialog" aria-modal="true" aria-labelledby="bookTitle">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="bookTitle">Book a service</h2><p class="muted small">We are open <?= e(opening_summary($conn)) ?>. Please book at least one day ahead.</p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post" action="<?= e($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="book">
      <div class="form-grid">
        <div class="field full"><label for="b_service">Service</label>
          <select id="b_service" name="service_id" required><option value="">Select a service</option>
            <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?><?= (float) $s['base_price'] > 0 ? ' — from ' . e(peso($s['base_price'])) : '' ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="b_date">Preferred date</label><input type="date" id="b_date" name="scheduled_date" min="<?= e($tomorrow) ?>" required></div>
        <div class="field"><label for="b_time">Preferred time</label><input type="time" id="b_time" name="scheduled_time" required></div>
        <div class="field full"><label for="b_addr">Service address</label><input type="text" id="b_addr" name="address" value="<?= e($myAddress) ?>" required maxlength="255"></div>
        <div class="field full"><label for="b_notes">Notes (optional)</label><textarea id="b_notes" name="notes" maxlength="500" placeholder="Anything our team should know?"></textarea></div>
      </div>
      <div class="form-actions"><button type="submit" class="button">Send request</button><button type="button" class="button button-ghost" data-close-modal>Close</button></div>
    </form>
  </div>
</div>


<div class="modal-overlay" id="reschedModal" role="dialog" aria-modal="true" aria-labelledby="reTitle">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="reTitle">Reschedule</h2><p class="muted small">Your new time will need to be confirmed again.</p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post" action="<?= e($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="reschedule"><input type="hidden" name="booking_id" id="re_id">
      <div class="form-grid">
        <div class="field"><label for="re_date">New date</label><input type="date" id="re_date" name="scheduled_date" min="<?= e($tomorrow) ?>" required></div>
        <div class="field"><label for="re_time">New time</label><input type="time" id="re_time" name="scheduled_time" required></div>
      </div>
      <div class="form-actions"><button type="submit" class="button">Request new time</button><button type="button" class="button button-ghost" data-close-modal>Close</button></div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.js-resched').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('re_id').value = b.dataset.id;
    document.getElementById('re_date').value = b.dataset.date;
    document.getElementById('re_time').value = b.dataset.time;
    SSS.openModal('reschedModal');
  });
});
</script>
<?php render_footer(); ?>
