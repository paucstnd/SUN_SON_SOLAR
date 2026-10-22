<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';


$auth = require_auth(['admin']);

$from = valid_date_string($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$to = valid_date_string($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
if ($from > $to) { [$from, $to] = [$to, $from]; }
$role = $_GET['role'] ?? '';
$roles = ['admin' => 'Administrator', 'customer' => 'Customer', 'technician' => 'Technician', 'dispatcher' => 'Dispatcher', 'hr' => 'HR', 'staff' => 'Other staff'];
$q = clean_text($_GET['q'] ?? '', 60);

$sql = 'SELECT * FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ?';
$types = 'ss'; $params = [$from, $to];
if (isset($roles[$role])) { $sql .= ' AND actor_role = ?'; $types .= 's'; $params[] = $role; }
if ($q !== '') { $sql .= ' AND (actor_name LIKE ? OR action LIKE ?)'; $types .= 'ss'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 300';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

render_header($auth, 'Activity log', 'admin_activity.php', 'A record of logins and changes made in the system (latest 300 matches).');
?>
<form method="get" class="filters">
  <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <div class="field"><label for="role">Who</label><select id="role" name="role"><option value="">Everyone</option><?= select_options($roles, $role) ?></select></div>
  <div class="field"><label for="q">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Name or action"></div>
  <button class="button" type="submit">Apply</button>
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty">No activity in this period.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Page</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
      <td><?= e(fmt_date($r['created_at'])) ?><span class="sub"><?= e(date('g:i:s A', strtotime($r['created_at']))) ?></span></td>
      <td><strong><?= e($r['actor_name']) ?></strong><span class="sub"><?= e($roles[$r['actor_role']] ?? $r['actor_role']) ?></span></td>
      <td><?= e($r['action']) ?></td><td class="muted small"><?= e($r['page']) ?></td><td class="muted small"><?= e($r['ip_address']) ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php render_footer(); ?>
