<?php
session_start();
require_once 'security.php';
require_once 'auth.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit();
}
$flash = $_SESSION['error'] ?? '';
unset($_SESSION['error'], $_SESSION['active_form']);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Admin Login · Sun Son Solar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{--navy-950:#0c1524;--navy-900:#101d31;--navy-800:#182a45;--gold-500:#f2a900;--gold-400:#ffc233;--amber-100:#fff3d9;--paper:#f7f7f4;--ink:#151a22;--ink-soft:#5b6472;--line:#e4e5e1;--danger-bg:#fdece9;--danger-ink:#8a2c1f;font-family:'IBM Plex Sans',sans-serif;}
  *{margin:0;padding:0;box-sizing:border-box;}
  html,body{height:100%;}
  body{background:var(--paper);color:var(--ink);min-height:100vh;}
  .container{min-height:100vh;display:flex;}
  .left-side{position:relative;width:44%;min-height:100vh;background:var(--navy-950);display:flex;flex-direction:column;justify-content:space-between;padding:48px 44px;overflow:hidden;}
  .sun{position:relative;width:140px;margin-bottom:10px;}
  .sun img{width:75%;height:auto;margin-left:-5%;display:block;}
  .brand-word{font-family:'IBM Plex Sans',sans-serif;font-weight:700;font-size:30px;color:#fff;line-height:1.15;}
  .brand-word span{color:var(--gold-400);}
  .tagline{margin-top:10px;font-size:15px;color:#a9b6c9;max-width:30ch;line-height:1.5;}
  .back-link{position:relative;z-index:1;color:#a9b6c9;font-size:14px;text-decoration:none;}
  .back-link:hover{color:var(--gold-400);}
  .right-side{width:56%;min-height:100vh;display:flex;justify-content:center;align-items:center;padding:48px clamp(28px,6vw,84px);}
  .login-form{width:100%;max-width:400px;}
  .form-header{margin-bottom:30px;}
  .form-header h2{font-family:'IBM Plex Sans',sans-serif;font-size:28px;font-weight:600;margin-bottom:6px;}
  .form-header p{color:var(--ink-soft);font-size:14.5px;}
  .input-group{display:flex;flex-direction:column;margin-bottom:18px;}
  label{font-size:12.5px;font-weight:600;color:var(--ink-soft);margin-bottom:6px;}
  input{width:100%;height:46px;border:1px solid var(--line);border-radius:6px;padding:0 14px;font-size:14.5px;font-family:'IBM Plex Sans',sans-serif;background:#fff;color:var(--ink);outline:none;transition:border-color .15s,box-shadow .15s;}
  input:focus{border-color:var(--gold-500);box-shadow:0 0 0 2px rgba(242,169,0,.16);}
  .password-wrapper{position:relative;}
  .password-wrapper input{padding-right:64px;}
  .show-password{position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--ink-soft);font-size:12.5px;font-weight:600;cursor:pointer;padding:6px 8px;border-radius:6px;}
  .show-password:hover{background:#f1efe9;color:var(--ink);}
  .btn{width:100%;height:46px;border:none;border-radius:6px;background:var(--gold-500);color:#241900;font-size:14px;font-weight:700;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;margin-top:8px;}
  .btn:hover{filter:brightness(.97);}
  .btn[disabled]{opacity:.7;cursor:wait;}
  .show-password:focus-visible,input:focus-visible,.btn:focus-visible,a:focus-visible{outline:2px solid var(--gold-500);outline-offset:2px;}
  .message{display:none;padding:12px 14px;margin-top:16px;border-radius:8px;background:var(--amber-100);color:#7a5600;font-size:13px;border:1px solid #f0d68a;}
  .message.error{background:var(--danger-bg);color:var(--danger-ink);border-color:#f2c2b8;}
  .message.show{display:block;}
  .alt-link{text-align:center;margin-top:22px;font-size:13.5px;color:var(--ink-soft);}
  .alt-link a{color:#a26a00;font-weight:600;text-decoration:none;}
  @media (max-width:850px){
    .container{flex-direction:column;}
    .left-side{width:100%;min-height:auto;padding:36px 28px;gap:0;}
    .left-side::after{display:none;}
    .sun{width:64px;margin-bottom:12px;}
    .brand-word{font-size:22px;}
    .tagline{display:none;}
    .back-link{display:none;}
    .right-side{width:100%;min-height:auto;padding:36px 24px;}
  }
</style>
</head>
<body>
<div class="container">
  <div class="left-side">
    <div>
      <div class="sun" aria-hidden="true"><img src="transL.png" alt="" width="600" height="400"></div>
      <h1 class="brand-word">Sun Son<br><span>Solar</span></h1>
      <p class="tagline">Administrator portal</p>
    </div>
    <a class="back-link" href="index.php">&larr; Back to website</a>
  </div>

  <div class="right-side">
    <div class="login-form">
      <div class="form-header">
        <h2>Admin login</h2>
        <p>Sign in to manage Sun Son Solar.</p>
      </div>
      <form id="adminLogin" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="input-group">
          <label for="username">Admin username</label>
          <input type="text" name="username" id="username" placeholder="Enter your admin username" required autocomplete="username">
        </div>
        <div class="input-group">
          <label for="password">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="show-password" id="toggle" aria-label="Show password">Show</button>
          </div>
        </div>
        <button type="submit" class="btn" id="submitBtn">Log in</button>
        <div class="message <?= $flash ? 'error show' : '' ?>" id="msg" role="status"><?= e($flash) ?></div>
      </form>
      <div class="alt-link">Customer or employee? <a href="index.php#account">Log in here</a></div>
    </div>
  </div>
</div>

<script>
  var form = document.getElementById('adminLogin'), msg = document.getElementById('msg'), btn = document.getElementById('submitBtn');
  document.getElementById('toggle').addEventListener('click', function(){
    var p = document.getElementById('password'), show = p.type === 'password';
    p.type = show ? 'text' : 'password'; this.textContent = show ? 'Hide' : 'Show';
  });
  function show(text, isError){ msg.textContent = text; msg.className = 'message show' + (isError ? ' error' : ''); }
  var countdown = null;
  function startCountdown(text){
    var m = text.match(/try again in (\d+) seconds?/i);
    if(!m) return;
    var deadline = Date.now() + Number(m[1]) * 1000;
    clearInterval(countdown);
    countdown = setInterval(function(){
      var s = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      if(s === 0){ clearInterval(countdown); show('You can try logging in again.', false); return; }
      show('Too many failed attempts. Please try again in ' + s + ' second' + (s === 1 ? '' : 's') + '.', true);
    }, 250);
  }
  startCountdown(msg.textContent);
  form.addEventListener('submit', function(e){
    e.preventDefault();
    btn.disabled = true;
    fetch('admin_login.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function(r){ return r.json().catch(function(){ return { success:false, message:'Unexpected server response.' }; }); })
      .then(function(data){
        if(data.success){ show(data.message, false); window.location.href = data.redirect; return; }
        show(data.message, true); startCountdown(data.message); btn.disabled = false;
      })
      .catch(function(){ show('Could not reach the server. Please try again.', true); btn.disabled = false; });
  });
</script>
</body>
</html>
