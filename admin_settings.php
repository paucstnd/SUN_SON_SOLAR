<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['admin']);
$self = 'admin_settings.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';

    if ($action === 'save_service') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = clean_text($_POST['name'] ?? '', 80);
        $desc = clean_text($_POST['description'] ?? '', 255);
        $priceRaw = trim($_POST['base_price'] ?? '0');
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($name === '') {
            flash_error('Please enter a service name.');
        } elseif (!is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 99999999) {
            flash_error('Please enter a valid price (0 means "price on quote").');
        } else {
            $price = round((float) $priceRaw, 2);
            try {
                if ($id > 0) {
                    $stmt = $conn->prepare('UPDATE services SET name = ?, description = ?, base_price = ?, sort_order = ? WHERE id = ?');
                    $stmt->bind_param('ssdii', $name, $desc, $price, $order, $id);
                } else {
                    $stmt = $conn->prepare('INSERT INTO services (name, description, base_price, sort_order) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('ssdi', $name, $desc, $price, $order);
                }
                $stmt->execute();
                $stmt->close();
                flash_success($id > 0 ? 'Service updated.' : 'Service added.');
            } catch (mysqli_sql_exception $ex) {
                flash_error('A service with that name already exists.');
            }
        }
    } elseif ($action === 'toggle_service') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Service availability changed.');
    } elseif ($action === 'save_hours') {
        for ($d = 0; $d <= 6; $d++) {
            $open = isset($_POST['open'][$d]) ? 1 : 0;
            $from = preg_match('/^\d{2}:\d{2}$/', $_POST['from'][$d] ?? '') ? $_POST['from'][$d] . ':00' : '08:00:00';
            $to = preg_match('/^\d{2}:\d{2}$/', $_POST['to'][$d] ?? '') ? $_POST['to'][$d] . ':00' : '17:00:00';
            $max = max(0, min(200, (int) ($_POST['max'][$d] ?? 5)));
            if ($open && $to <= $from) {
                flash_error('Closing time must be later than opening time (' . ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$d] . ').');
                redirect($self);
            }
            $stmt = $conn->prepare('INSERT INTO availability (day_of_week, is_open, open_time, close_time, max_bookings) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE is_open = VALUES(is_open), open_time = VALUES(open_time), close_time = VALUES(close_time), max_bookings = VALUES(max_bookings)');
            $stmt->bind_param('iissi', $d, $open, $from, $to, $max);
            $stmt->execute();
            $stmt->close();
        }
        flash_success('Weekly availability saved.');
    } elseif ($action === 'add_block') {
        $date = trim($_POST['blocked_date'] ?? '');
        $reason = clean_text($_POST['reason'] ?? '', 120);
        if (!valid_date_string($date)) {
            flash_error('Please choose a valid date.');
        } else {
            $stmt = $conn->prepare('INSERT INTO blocked_dates (blocked_date, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)');
            $stmt->bind_param('ss', $date, $reason);
            $stmt->execute();
            $stmt->close();
            flash_success('Date blocked. Customers cannot book it.');
        }
    } elseif ($action === 'del_block') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM blocked_dates WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Date is open for booking again.');
    }
    redirect($self);
}

$services = $conn->query('SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS uses FROM services s ORDER BY s.sort_order, s.name')->fetch_all(MYSQLI_ASSOC);
$hours = [];
foreach ($conn->query('SELECT * FROM availability')->fetch_all(MYSQLI_ASSOC) as $h) { $hours[(int) $h['day_of_week']] = $h; }
$blocks = $conn->query('SELECT * FROM blocked_dates WHERE blocked_date >= CURDATE() ORDER BY blocked_date LIMIT 100')->fetch_all(MYSQLI_ASSOC);
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

render_header($auth, 'Settings', 'admin_settings.php', 'Service categories, pricing and availability. Changes apply to customer booking right away.');
?>
<div class="card">
  <div class="card-head"><h2>Service categories &amp; pricing</h2><button class="button" type="button" data-open-modal="serviceModal" id="newServiceBtn">+ Add service</button></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Service</th><th>Starting price</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s): ?>
      <tr>
        <td><?= (int) $s['sort_order'] ?></td>
        <td><strong><?= e($s['name']) ?></strong><span class="sub"><?= e($s['description']) ?></span></td>
        <td><?= (float) $s['base_price'] > 0 ? e(peso($s['base_price'])) : '<span class="muted">Price on quote</span>' ?></td>
        <td><?= badge($s['is_active'] ? 'active' : 'inactive') ?></td>
        <td><div class="actions">
          <button type="button" class="button button-ghost button-sm js-edit-service" data-service="<?= e(json_encode(['id' => (int) $s['id'], 'name' => $s['name'], 'description' => $s['description'], 'base_price' => $s['base_price'], 'sort_order' => (int) $s['sort_order']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">Edit</button>
          <form method="post" class="inline-form" data-confirm="<?= $s['is_active'] ? 'Hide this service from customers? Existing bookings are kept.' : 'Show this service to customers again?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle_service"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="button button-sm <?= $s['is_active'] ? 'button-danger' : 'button-ok' ?>"><?= $s['is_active'] ? 'Hide' : 'Show' ?></button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint" style="margin-top:10px">Services are hidden instead of deleted so past bookings and receipts stay intact. A price of 0 shows as "Price on quote".</p>
</div>

<div class="card">
  <div class="card-head"><h2>Weekly availability</h2></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_hours">
    <div class="table-wrap"><table>
      <thead><tr><th>Day</th><th>Open</th><th>From</th><th>To</th><th>Max bookings / day</th></tr></thead>
      <tbody>
      <?php foreach ($days as $i => $dn): $h = $hours[$i] ?? ['is_open' => 0, 'open_time' => '08:00:00', 'close_time' => '17:00:00', 'max_bookings' => 5]; ?>
        <tr>
          <td><strong><?= e($dn) ?></strong></td>
          <td><label class="check"><input type="checkbox" name="open[<?= $i ?>]" <?= (int) $h['is_open'] ? 'checked' : '' ?>> Open</label></td>
          <td><input type="time" name="from[<?= $i ?>]" value="<?= e(substr($h['open_time'], 0, 5)) ?>" style="width:130px"></td>
          <td><input type="time" name="to[<?= $i ?>]" value="<?= e(substr($h['close_time'], 0, 5)) ?>" style="width:130px"></td>
          <td><input type="number" min="0" max="200" name="max[<?= $i ?>]" value="<?= (int) $h['max_bookings'] ?>" style="width:100px"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="form-actions"><button class="button" type="submit">Save availability</button></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>Blocked dates</h2></div>
  <form method="post" class="filters">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_block">
    <div class="field"><label for="blocked_date">Date</label><input type="date" id="blocked_date" name="blocked_date" required min="<?= e(date('Y-m-d')) ?>"></div>
    <div class="field" style="flex:1;min-width:220px"><label for="reason">Reason (optional)</label><input id="reason" name="reason" maxlength="120" placeholder="Holiday, company event…"></div>
    <button class="button" type="submit" style="height:46px">Block date</button>
  </form>
  <?php if (!$blocks): ?><div class="empty">No upcoming blocked dates.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead><tbody>
    <?php foreach ($blocks as $b): ?><tr>
      <td><?= e(fmt_date($b['blocked_date'])) ?></td><td><?= $b['reason'] !== '' ? e($b['reason']) : '<span class="muted">—</span>' ?></td>
      <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="del_block"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="button button-ghost button-sm" type="submit">Unblock</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<div class="modal-overlay" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="svT">
  <div class="modal-box">
    <div class="modal-head"><h2 id="svT">Add service</h2><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_service"><input type="hidden" name="id" id="sv_id" value="0">
      <div class="form-grid">
        <div class="field full"><label for="sv_name">Service name</label><input id="sv_name" name="name" maxlength="80" required></div>
        <div class="field full"><label for="sv_desc">Short description</label><input id="sv_desc" name="description" maxlength="255"></div>
        <div class="field"><label for="sv_price">Starting price (₱)</label><input id="sv_price" name="base_price" type="number" step="0.01" min="0" value="0"></div>
        <div class="field"><label for="sv_order">Display order</label><input id="sv_order" name="sort_order" type="number" value="0"></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save service</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>
<script>
(function () {
  function fill(d) {
    document.getElementById('svT').textContent = d ? 'Edit service' : 'Add service';
    document.getElementById('sv_id').value = d ? d.id : 0;
    document.getElementById('sv_name').value = d ? d.name : '';
    document.getElementById('sv_desc').value = d ? d.description : '';
    document.getElementById('sv_price').value = d ? d.base_price : 0;
    document.getElementById('sv_order').value = d ? d.sort_order : 0;
  }
  document.getElementById('newServiceBtn').addEventListener('click', function () { fill(null); });
  document.querySelectorAll('.js-edit-service').forEach(function (b) {
    b.addEventListener('click', function () { fill(JSON.parse(b.getAttribute('data-service'))); SSS.openModal('serviceModal'); });
  });
})();
</script>
<?php render_footer(); ?>
