<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

$auth = require_auth(['customer']);
$uid = $auth['id'];
$self = 'customer_profile.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($self);
    $action = $_POST['action'] ?? '';

    if ($action === 'info') {
        $in = [
            'first_name' => clean_text($_POST['first_name'] ?? '', 50), 'middle_name' => clean_text($_POST['middle_name'] ?? '', 50),
            'last_name' => clean_text($_POST['last_name'] ?? '', 50), 'birthdate' => trim($_POST['birthdate'] ?? ''),
            'gender' => $_POST['gender'] ?? '', 'email' => clean_text($_POST['email'] ?? '', 100),
            'phone' => trim($_POST['phone'] ?? ''), 'address' => clean_text($_POST['address'] ?? '', 255), 'username' => $auth['username'],
        ];
        if ($err = validate_person($in, false)) {
            flash_error($err);
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $stmt->bind_param('si', $in['email'], $uid);
            $stmt->execute();
            $taken = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($taken) {
                flash_error('That email is already used by another account.');
            } else {
                $stmt = $conn->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, birthdate=?, gender=?, email=?, phone=?, address=? WHERE id=?');
                $stmt->bind_param('ssssssssi', $in['first_name'], $in['middle_name'], $in['last_name'], $in['birthdate'], $in['gender'], $in['email'], $in['phone'], $in['address'], $uid);
                $stmt->execute();
                $stmt->close();
                flash_success('Your profile was updated.');
            }
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
        $stmt->close();
        if (!password_verify($current, $hash)) {
            flash_error('Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash_error('New password must be at least 8 characters long.');
        } elseif ($new !== $confirm) {
            flash_error('New passwords do not match.');
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?');
            $stmt->bind_param('si', $newHash, $uid);
            $stmt->execute();
            $stmt->close();
            session_regenerate_id(true);
            flash_success('Your password was changed.');
        }
    } elseif ($action === 'prefs') {
        $r = isset($_POST['pref_reminders']) ? 1 : 0;
        $p = isset($_POST['pref_promotions']) ? 1 : 0;
        $stmt = $conn->prepare('UPDATE users SET pref_reminders = ?, pref_promotions = ? WHERE id = ?');
        $stmt->bind_param('iii', $r, $p, $uid);
        $stmt->execute();
        $stmt->close();
        flash_success('Your preferences were saved.');
    }
    redirect($self);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

render_header($auth, 'My profile', 'customer_profile.php', 'Update your details, password and notification preferences.');
?>
<div class="card">
  <div class="card-head"><h2>Personal information</h2></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="info">
    <div class="form-grid">
      <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="<?= e($u['first_name']) ?>" required></div>
      <div class="field"><label for="middle_name">Middle name</label><input id="middle_name" name="middle_name" value="<?= e($u['middle_name']) ?>"></div>
      <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="<?= e($u['last_name']) ?>" required></div>
      <div class="field"><label for="birthdate">Birthdate</label><input type="date" id="birthdate" name="birthdate" value="<?= e($u['birthdate']) ?>" required></div>
      <div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><?= select_options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $u['gender']) ?></select></div>
      <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required></div>
      <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" value="<?= e($u['phone']) ?>" inputmode="numeric" maxlength="11" data-digits required></div>
      <div class="field"><label for="username">Username</label><input id="username" value="<?= e($u['username']) ?>" disabled><span class="hint">Usernames cannot be changed.</span></div>
      <div class="field full"><label for="address">Address</label><input id="address" name="address" value="<?= e($u['address']) ?>" required></div>
    </div>
    <div class="form-actions"><button class="button" type="submit">Save changes</button></div>
  </form>
</div>

<div class="grid grid-2">
  <div class="card" id="prefs">
    <div class="card-head"><h2>Preferences</h2></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="prefs">
      <div style="display:grid;gap:14px">
        <label class="check"><input type="checkbox" name="pref_reminders" <?= (int) $u['pref_reminders'] ? 'checked' : '' ?>> Service reminders</label>
        <label class="check"><input type="checkbox" name="pref_promotions" <?= (int) $u['pref_promotions'] ? 'checked' : '' ?>> Promotions and offers</label>
        <p class="hint">Schedule updates and important system notices are always sent.</p>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save preferences</button></div>
    </form>
  </div>

  <div class="card" id="password">
    <div class="card-head"><h2>Change password</h2></div>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div style="display:grid;gap:14px">
        <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required><span class="hint">At least 8 characters.</span></div>
        <div class="field"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Update password</button></div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
