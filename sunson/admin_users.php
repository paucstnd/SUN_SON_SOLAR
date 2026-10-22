<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

/* User management: add employee, view, edit, deactivate. Admin and HR. (Customers register themselves.) */
$auth = require_auth(['admin', 'hr']);
$isAdmin = $auth['role'] === 'admin';
$self = 'admin_users.php';
$tab = $_GET['tab'] ?? 'employees';
if (!in_array($tab, ['employees', 'customers', 'admins'], true) || ($tab === 'admins' && !$isAdmin)) {
    $tab = 'employees';
}

function person_from_post(): array {
    return [
        'first_name' => clean_text($_POST['first_name'] ?? '', 50), 'middle_name' => clean_text($_POST['middle_name'] ?? '', 50),
        'last_name' => clean_text($_POST['last_name'] ?? '', 50), 'birthdate' => trim($_POST['birthdate'] ?? ''),
        'gender' => $_POST['gender'] ?? '', 'email' => clean_text($_POST['email'] ?? '', 100),
        'phone' => trim($_POST['phone'] ?? ''), 'address' => clean_text($_POST['address'] ?? '', 255),
        'username' => clean_text($_POST['username'] ?? '', 50), 'password' => $_POST['password'] ?? '',
    ];
}

function username_taken(mysqli $conn, string $username, int $exceptUserId = 0, int $exceptAdminId = 0): bool {
    $stmt = $conn->prepare('SELECT (SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?) + (SELECT COUNT(*) FROM admins WHERE username = ? AND id <> ?) AS c');
    $stmt->bind_param('sisi', $username, $exceptUserId, $username, $exceptAdminId);
    $stmt->execute();
    $c = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c > 0;
}

function email_taken(mysqli $conn, string $email, int $exceptUserId = 0, int $exceptAdminId = 0): bool {
    $stmt = $conn->prepare('SELECT (SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?) + (SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?) AS c');
    $stmt->bind_param('sisi', $email, $exceptUserId, $email, $exceptAdminId);
    $stmt->execute();
    $c = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self . '?tab=' . $tab);
    $action = $_POST['action'] ?? '';
    $back = $self . '?tab=' . $tab;

    if ($action === 'add_user') {
        $p = person_from_post();
        $dept = $_POST['department'] ?? '';
        if ($err = validate_person($p, true)) {
            flash_error($err);
        } elseif (!in_array($dept, DEPARTMENTS, true)) {
            flash_error('Please choose a department.');
        } elseif (username_taken($conn, $p['username']) || email_taken($conn, $p['email'])) {
            flash_error('That username or email is already registered.');
        } else {
            $hash = password_hash($p['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (account_type, first_name, middle_name, last_name, birthdate, gender, email, phone, department, address, username, password, status, must_change_password)
                VALUES ('Employee', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 1)");
            $stmt->bind_param('sssssssssss', $p['first_name'], $p['middle_name'], $p['last_name'], $p['birthdate'], $p['gender'], $p['email'], $p['phone'], $dept, $p['address'], $p['username'], $hash);
            $stmt->execute();
            $stmt->close();
            flash_success('Employee account created. Share the temporary password with them; they should change it after logging in.');
        }
        redirect($self . '?tab=employees');
    }

    if ($action === 'edit_user') {
        $uid = (int) ($_POST['id'] ?? 0);
        $p = person_from_post();
        $p['username'] = 'x'; // not editable
        $stmt = $conn->prepare('SELECT account_type FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $dept = $_POST['department'] ?? '';
        if (!$row) {
            flash_error('User not found.');
        } elseif ($err = validate_person($p, false)) {
            flash_error($err);
        } elseif ($row['account_type'] === 'Employee' && !in_array($dept, DEPARTMENTS, true)) {
            flash_error('Please choose a department.');
        } elseif (email_taken($conn, $p['email'], $uid)) {
            flash_error('That email is already used by another account.');
        } else {
            $deptParam = $row['account_type'] === 'Employee' ? $dept : null;
            $stmt = $conn->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, birthdate=?, gender=?, email=?, phone=?, address=?, department=? WHERE id=?');
            $stmt->bind_param('sssssssssi', $p['first_name'], $p['middle_name'], $p['last_name'], $p['birthdate'], $p['gender'], $p['email'], $p['phone'], $p['address'], $deptParam, $uid);
            $stmt->execute();
            $stmt->close();
            flash_success('User updated.');
        }
        redirect($back);
    }

    if ($action === 'toggle_user') {
        $uid = (int) ($_POST['id'] ?? 0);
        if ($uid === $auth['id'] && $auth['role'] !== 'admin') {
            flash_error('You cannot deactivate your own account.');
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'approved', 'inactive', 'approved') WHERE id = ?");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            flash_success('Account status updated.');
        }
        redirect($back);
    }

    if ($action === 'reset_user_pw') {
        $uid = (int) ($_POST['id'] ?? 0);
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 8) {
            flash_error('Temporary password must be at least 8 characters long.');
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            flash_success('Temporary password set. The user will be asked to change it.');
        }
        redirect($back);
    }

    // ----- Administrator accounts (admin only) -----
    if (!$isAdmin) {
        flash_error('Only administrators can manage administrator accounts.');
        redirect($self);
    }

    if ($action === 'add_admin') {
        $first = clean_text($_POST['first_name'] ?? '', 50);
        $middle = clean_text($_POST['middle_name'] ?? '', 50);
        $last = clean_text($_POST['last_name'] ?? '', 50);
        $email = clean_text($_POST['email'] ?? '', 100);
        $phone = clean_text($_POST['phone'] ?? '', 20);
        $username = clean_text($_POST['username'] ?? '', 50);
        $pw = $_POST['password'] ?? '';
        if ($first === '' || $last === '' || $username === '' || $email === '') {
            flash_error('Please fill in all required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_error('Please enter a valid email address.');
        } elseif (strlen($pw) < 8) {
            flash_error('Password must be at least 8 characters long.');
        } elseif (username_taken($conn, $username) || email_taken($conn, $email)) {
            flash_error('That username or email is already registered.');
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (first_name, middle_name, last_name, email, phone_number, username, password_hash, status, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1)");
            $stmt->bind_param('sssssss', $first, $middle, $last, $email, $phone, $username, $hash);
            $stmt->execute();
            $stmt->close();
            flash_success('Administrator added.');
        }
        redirect($self . '?tab=admins');
    }

    if ($action === 'toggle_admin') {
        $aid = (int) ($_POST['id'] ?? 0);
        if ($aid === $auth['id']) {
            flash_error('You cannot deactivate your own account.');
        } else {
            $stmt = $conn->prepare("UPDATE admins SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            $stmt->bind_param('i', $aid);
            $stmt->execute();
            $stmt->close();
            flash_success('Administrator status updated.');
        }
        redirect($self . '?tab=admins');
    }

    if ($action === 'reset_admin_pw') {
        $aid = (int) ($_POST['id'] ?? 0);
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 8) {
            flash_error('Temporary password must be at least 8 characters long.');
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE admins SET password_hash = ?, must_change_password = 1 WHERE id = ?');
            $stmt->bind_param('si', $hash, $aid);
            $stmt->execute();
            $stmt->close();
            flash_success('Temporary password set.');
        }
        redirect($self . '?tab=admins');
    }
    redirect($self);
}

$q = clean_text($_GET['q'] ?? '', 60);
$rows = [];
if ($tab === 'admins') {
    $rows = $conn->query("SELECT id, first_name, middle_name, last_name, email, phone_number AS phone, username, status FROM admins ORDER BY id")->fetch_all(MYSQLI_ASSOC);
} else {
    $type = $tab === 'employees' ? 'Employee' : 'Customer';
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT * FROM users WHERE account_type = ? AND (CONCAT_WS(' ', first_name, last_name) LIKE ? OR username LIKE ? OR email LIKE ?) ORDER BY created_at DESC, id DESC");
    $stmt->bind_param('ssss', $type, $like, $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

render_header($auth, 'User management', 'admin_users.php', $isAdmin ? 'Add employees, edit details, and activate or deactivate accounts.' : 'Create employee accounts and manage their details.');
?>
<div class="tabs">
  <a class="tab <?= $tab === 'employees' ? 'active' : '' ?>" href="?tab=employees">Employees</a>
  <a class="tab <?= $tab === 'customers' ? 'active' : '' ?>" href="?tab=customers">Customers</a>
  <?php if ($isAdmin): ?><a class="tab <?= $tab === 'admins' ? 'active' : '' ?>" href="?tab=admins">Administrators</a><?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <?php if ($tab !== 'admins'): ?>
    <form method="get" class="filters" style="margin:0"><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div class="field"><label for="q" class="muted small">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Name, username or email"></div>
      <button class="button button-ghost" type="submit">Search</button>
    </form>
    <?php else: ?><h2>Administrators</h2><?php endif; ?>
    <?php if ($tab === 'employees'): ?><button class="button" type="button" data-open-modal="addUserModal">+ Add employee</button>
    <?php elseif ($tab === 'admins'): ?><button class="button" type="button" data-open-modal="addAdminModal">+ Add administrator</button><?php endif; ?>
  </div>

  <?php if (!$rows): ?><div class="empty">No <?= e($tab) ?> found.</div><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Username</th><th>Contact</th><?php if ($tab === 'employees'): ?><th>Department</th><?php endif; ?><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $isActive = $tab === 'admins' ? $r['status'] === 'active' : $r['status'] === 'approved';
        $statusLabel = $tab === 'admins' ? $r['status'] : ($r['status'] === 'approved' ? 'active' : ($r['status'] === 'inactive' ? 'inactive' : $r['status']));
        $isSelf = ($tab === 'admins' && (int) $r['id'] === $auth['id']) || ($tab !== 'admins' && (int) $r['id'] === $auth['id'] && !$isAdmin);
        $editData = $tab === 'admins' ? '' : e(json_encode([
            'id' => (int) $r['id'], 'first_name' => $r['first_name'], 'middle_name' => $r['middle_name'], 'last_name' => $r['last_name'],
            'birthdate' => $r['birthdate'], 'gender' => $r['gender'], 'email' => $r['email'], 'phone' => $r['phone'],
            'address' => $r['address'], 'department' => $r['department'], 'type' => $r['account_type'], 'username' => $r['username'],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
        ?>
      <tr>
        <td><strong><?= e(trim($r['first_name'] . ' ' . $r['middle_name'] . ' ' . $r['last_name'])) ?></strong><span class="sub">ID #<?= (int) $r['id'] ?></span></td>
        <td>@<?= e($r['username']) ?></td>
        <td><?= e($r['email']) ?><span class="sub"><?= e($r['phone']) ?></span></td>
        <?php if ($tab === 'employees'): ?><td><?= e($r['department'] ?: '—') ?></td><?php endif; ?>
        <td><?= badge($statusLabel) ?></td>
        <td><div class="actions">
          <?php if ($tab !== 'admins'): ?><button type="button" class="button button-ghost button-sm js-edit" data-user="<?= $editData ?>">Edit</button><?php endif; ?>
          <button type="button" class="button button-ghost button-sm js-reset" data-id="<?= (int) $r['id'] ?>" data-kind="<?= $tab === 'admins' ? 'admin' : 'user' ?>" data-name="<?= e($r['first_name']) ?>">Reset password</button>
          <?php if (!$isSelf): ?>
          <form method="post" class="inline-form" data-confirm="<?= $isActive ? 'Deactivate this account? They will no longer be able to log in.' : 'Activate this account?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="<?= $tab === 'admins' ? 'toggle_admin' : 'toggle_user' ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="button button-sm <?= $isActive ? 'button-danger' : 'button-ok' ?>"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<!-- Add employee -->
<div class="modal-overlay" id="addUserModal" role="dialog" aria-modal="true" aria-labelledby="auT">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-head"><div><h2 id="auT">Add employee</h2><p class="muted small">The employee logs in with the username and temporary password below.</p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_user">
      <div class="form-grid">
        <div class="field"><label>First name</label><input name="first_name" required></div>
        <div class="field"><label>Middle name</label><input name="middle_name"></div>
        <div class="field"><label>Last name</label><input name="last_name" required></div>
        <div class="field"><label>Birthdate</label><input type="date" name="birthdate" required></div>
        <div class="field"><label>Gender</label><select name="gender" required><option value="">Select gender</option><option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="field"><label>Department</label><select name="department" required><option value="">Select department</option><?php foreach (DEPARTMENTS as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Phone number</label><input name="phone" inputmode="numeric" maxlength="11" data-digits placeholder="09XXXXXXXXX" required></div>
        <div class="field full"><label>Address</label><input name="address" required></div>
        <div class="field"><label>Username</label><input name="username" autocomplete="off" required></div>
        <div class="field"><label>Temporary password</label><input name="password" type="text" autocomplete="off" minlength="8" required><span class="hint">At least 8 characters.</span></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Create account</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>

<!-- Edit user -->
<div class="modal-overlay" id="editUserModal" role="dialog" aria-modal="true" aria-labelledby="euT">
  <div class="modal-box" style="max-width:680px">
    <div class="modal-head"><div><h2 id="euT">Edit user</h2><p class="muted small" id="eu_sub"></p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="edit_user"><input type="hidden" name="id" id="eu_id">
      <div class="form-grid">
        <div class="field"><label>First name</label><input name="first_name" id="eu_first" required></div>
        <div class="field"><label>Middle name</label><input name="middle_name" id="eu_middle"></div>
        <div class="field"><label>Last name</label><input name="last_name" id="eu_last" required></div>
        <div class="field"><label>Birthdate</label><input type="date" name="birthdate" id="eu_birth" required></div>
        <div class="field"><label>Gender</label><select name="gender" id="eu_gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="field" id="eu_dept_wrap"><label>Department</label><select name="department" id="eu_dept"><?php foreach (DEPARTMENTS as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Email</label><input type="email" name="email" id="eu_email" required></div>
        <div class="field"><label>Phone number</label><input name="phone" id="eu_phone" inputmode="numeric" maxlength="11" data-digits required></div>
        <div class="field full"><label>Address</label><input name="address" id="eu_address" required></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save changes</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>

<!-- Reset password -->
<div class="modal-overlay" id="resetModal" role="dialog" aria-modal="true" aria-labelledby="rpT">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="rpT">Reset password</h2><p class="muted small" id="rp_sub"></p></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" id="rp_action"><input type="hidden" name="id" id="rp_id">
      <div class="field"><label for="rp_pw">New temporary password</label><input type="text" id="rp_pw" name="password" minlength="8" autocomplete="off" required><span class="hint">They will be reminded to change it after logging in.</span></div>
      <div class="form-actions"><button class="button" type="submit">Set password</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Add administrator -->
<div class="modal-overlay" id="addAdminModal" role="dialog" aria-modal="true" aria-labelledby="aaT">
  <div class="modal-box">
    <div class="modal-head"><div><h2 id="aaT">Add administrator</h2></div><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
      <div class="form-grid">
        <div class="field"><label>First name</label><input name="first_name" required></div>
        <div class="field"><label>Middle name</label><input name="middle_name"></div>
        <div class="field"><label>Last name</label><input name="last_name" required></div>
        <div class="field"><label>Phone number</label><input name="phone" inputmode="numeric" maxlength="11" data-digits></div>
        <div class="field full"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Username</label><input name="username" autocomplete="off" required></div>
        <div class="field"><label>Temporary password</label><input name="password" type="text" autocomplete="off" minlength="8" required></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Add administrator</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.js-edit').forEach(function (b) {
  b.addEventListener('click', function () {
    var u = JSON.parse(b.getAttribute('data-user'));
    document.getElementById('eu_id').value = u.id;
    document.getElementById('eu_sub').textContent = '@' + u.username + ' · ' + u.type;
    document.getElementById('eu_first').value = u.first_name; document.getElementById('eu_middle').value = u.middle_name;
    document.getElementById('eu_last').value = u.last_name; document.getElementById('eu_birth').value = u.birthdate;
    document.getElementById('eu_gender').value = u.gender; document.getElementById('eu_email').value = u.email;
    document.getElementById('eu_phone').value = u.phone; document.getElementById('eu_address').value = u.address;
    var isEmp = u.type === 'Employee';
    document.getElementById('eu_dept_wrap').style.display = isEmp ? '' : 'none';
    document.getElementById('eu_dept').disabled = !isEmp;
    if (isEmp) document.getElementById('eu_dept').value = u.department;
    SSS.openModal('editUserModal');
  });
});
document.querySelectorAll('.js-reset').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('rp_id').value = b.dataset.id;
    document.getElementById('rp_action').value = b.dataset.kind === 'admin' ? 'reset_admin_pw' : 'reset_user_pw';
    document.getElementById('rp_sub').textContent = 'For ' + b.dataset.name;
    document.getElementById('rp_pw').value = '';
    SSS.openModal('resetModal');
  });
});
</script>
<?php render_footer(); ?>
