<?php
session_start();
require_once 'config.php';
require_once 'security.php';
require_once 'functions.php';


$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (!empty($_SESSION['last_reset']) && time() - (int) $_SESSION['last_reset'] < 30) {
        $error = 'Please wait a moment before sending another request.';
    } else {
        $username = clean_text($_POST['username'] ?? '', 50);
        $email = clean_text($_POST['email'] ?? '', 100);
        if ($username === '' || $email === '') {
            $error = 'Please enter your username and email.';
        } else {
            $_SESSION['last_reset'] = time();
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND email = ? AND status = 'approved'");
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($u) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE user_id = ? AND status = 'new'");
                $stmt->bind_param('i', $u['id']);
                $stmt->execute();
                $has = (int) $stmt->get_result()->fetch_row()[0];
                $stmt->close();
                if (!$has) {
                    $stmt = $conn->prepare('INSERT INTO password_reset_requests (user_id) VALUES (?)');
                    $stmt->bind_param('i', $u['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $done = true; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password · Sun Son Solar</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="app" style="display:flex;align-items:center;justify-content:center;padding:24px">
<div class="card" style="max-width:440px;width:100%">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><img src="transL.png" alt="" width="44" height="44" style="object-fit:contain;background:#101d31;border-radius:6px;padding:4px"><strong style="font-family:'IBM Plex Sans',sans-serif;font-size:18px">Sun Son Solar</strong></div>
  <h1 style="font-size:24px;margin-bottom:6px">Forgot your password?</h1>
  <?php if ($done): ?>
    <div class="message show">If those details match an account, our team has been notified. They will contact you to verify it is you and give you a temporary password.</div>
    <a class="button" href="index.php" style="display:inline-block;text-decoration:none;margin-top:6px">Back to home</a>
  <?php else: ?>
    <p class="muted" style="margin-bottom:16px">Enter your username and the email on your account. Our team will verify you and give you a temporary password.</p>
    <?php if ($error): ?><div class="message error show"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div style="display:grid;gap:14px">
        <div class="field"><label for="username">Username</label><input id="username" name="username" required autocomplete="username"></div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email"></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Send request</button><a class="button button-ghost" href="index.php" style="text-decoration:none">Cancel</a></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
