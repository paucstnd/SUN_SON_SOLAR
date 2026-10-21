<?php

session_start();
$pdo = new PDO('sqlite:' . __DIR__ . '/app.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("
CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, name, email UNIQUE, pw, role, active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS bookings(id INTEGER PRIMARY KEY, customer, address, service, date, status DEFAULT 'pending', tech_id INTEGER);
CREATE TABLE IF NOT EXISTS checkins(id INTEGER PRIMARY KEY, user_id INTEGER, ts, lat, lng);
CREATE TABLE IF NOT EXISTS reports(id INTEGER PRIMARY KEY, user_id INTEGER, kind, message, ts);
CREATE TABLE IF NOT EXISTS announcements(id INTEGER PRIMARY KEY, text, ts);
CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY, admin_id INTEGER, action, ts);");

function q($s, $a = []) { global $pdo; $st = $pdo->prepare($s); $st->execute($a); return $st->fetchAll(); }
function e($s) { return htmlspecialchars((string)$s); }
function now() { return date('Y-m-d H:i'); }
function flash($m) { $_SESSION['f'][] = $m; }
function go($p = '') { header("Location: ?p=$p"); exit; }
function audit($a) { q("INSERT INTO audit(admin_id,action,ts) VALUES(?,?,?)", [$_SESSION['uid'], $a, now()]); }
function need($r) { if (empty($_SESSION['uid'])) go('login'); if ($_SESSION['role'] !== $r) go('home'); }

if (!q("SELECT 1 FROM users")) {
  q("INSERT INTO users(name,email,pw,role) VALUES('Admin','admin@example.com',?,'admin')", [password_hash('admin123', PASSWORD_DEFAULT)]);
  q("INSERT INTO users(name,email,pw,role) VALUES('Tech One','tech@example.com',?,'tech')", [password_hash('tech123', PASSWORD_DEFAULT)]);
}
$STATUSES = ['pending', 'approved', 'assigned', 'completed', 'cancelled'];
$p = $_GET['p'] ?? 'home';
$P = $_POST;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $j = json_decode(file_get_contents('php://input'), true);
  if (is_array($j)) $P = $j;
  $a = $P['a'] ?? '';
  if ($a === 'login') {
    $u = q("SELECT * FROM users WHERE email=?", [$P['email']])[0] ?? null;
    if ($u && $u['active'] && password_verify($P['password'], $u['pw'])) {
      session_regenerate_id(true); $_SESSION['uid'] = $u['id']; $_SESSION['role'] = $u['role']; $_SESSION['name'] = $u['name']; go('home');
    }
    flash('Invalid login or deactivated account.'); go('login');
  }
  if ($a === 'checkin') {
    need('tech'); $d = $P;
    q("INSERT INTO checkins(user_id,ts,lat,lng) VALUES(?,?,?,?)", [$_SESSION['uid'], now(), $d['lat'], $d['lng']]);
    echo 'Checked in ' . now(); exit;
  }
  if ($a === 'report') {
    need('tech'); q("INSERT INTO reports(user_id,kind,message,ts) VALUES(?,?,?,?)", [$_SESSION['uid'], $P['kind'], $P['message'], now()]);
    flash('Report sent.'); go('home');
  }
  if ($a === 'booking_add') {
    need('admin'); q("INSERT INTO bookings(customer,address,service,date) VALUES(?,?,?,?)", [$P['customer'], $P['address'], $P['service'], $P['date']]);
    audit('Added booking for ' . $P['customer']); go('bookings');
  }
  if ($a === 'booking_act') {
    need('admin'); $i = (int)$P['id']; $act = $P['act'];
    if ($act === 'assign' && $P['tech_id']) q("UPDATE bookings SET tech_id=?,status='assigned' WHERE id=?", [$P['tech_id'], $i]);
    elseif ($act === 'resched' && $P['date']) q("UPDATE bookings SET date=? WHERE id=?", [$P['date'], $i]);
    elseif (in_array($act, $STATUSES)) q("UPDATE bookings SET status=? WHERE id=?", [$act, $i]);
    else flash('Missing technician or date.');
    audit("Booking $i: $act"); go('bookings');
  }
  if ($a === 'user_add') {
    need('admin');
    try { q("INSERT INTO users(name,email,pw,role) VALUES(?,?,?,?)", [$P['name'], $P['email'], password_hash($P['password'], PASSWORD_DEFAULT), $P['role']]); audit('Added user ' . $P['email']); }
    catch (Exception $ex) { flash('Email already exists.'); }
    go('users');
  }
  if ($a === 'user_toggle') {
    need('admin'); $i = (int)$P['id'];
    if ($i !== $_SESSION['uid']) { q("UPDATE users SET active=1-active WHERE id=?", [$i]); audit("Toggled user $i"); }
    go('users');
  }
  if ($a === 'announce') {
    need('admin'); q("INSERT INTO announcements(text,ts) VALUES(?,?)", [$P['text'], now()]); audit('Posted announcement'); go('announce');
  }
}
if ($p === 'logout') { session_destroy(); header('Location: ?p=login'); exit; }
if ($p === 'home' && !empty($_SESSION['uid']) && $_SESSION['role'] === 'admin') go('bookings');
if ($p !== 'login') { if (empty($_SESSION['uid'])) go('login'); }


function solar() {
  $r = '';
  for ($i = 0; $i < 12; $i++) { $a = deg2rad($i * 30);
    $r .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', 320 + 38 * cos($a), 60 + 38 * sin($a), 320 + 56 * cos($a), 60 + 56 * sin($a)); }
  return '<svg class="solar" viewBox="0 0 400 210" role="img" aria-label="Solar panel under the sun"><defs>
  <linearGradient id="pg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#3b82f6"/></linearGradient>
  <clipPath id="pc"><polygon points="70,60 290,60 250,160 30,160"/></clipPath></defs>
  <g class="rays" stroke="#fbbf24" stroke-width="4" stroke-linecap="round">' . $r . '</g><circle class="sun" cx="320" cy="60" r="28" fill="#fbbf24"/>
  <line x1="95" y1="160" x2="80" y2="195" stroke="#64748b" stroke-width="5"/><line x1="215" y1="160" x2="230" y2="195" stroke="#64748b" stroke-width="5"/>
  <polygon points="70,60 290,60 250,160 30,160" fill="url(#pg)" stroke="#cbd5e1" stroke-width="3"/>
  <g stroke="#bfdbfe" stroke-width="1.5" opacity=".7"><line x1="125" y1="60" x2="85" y2="160"/><line x1="180" y1="60" x2="140" y2="160"/><line x1="235" y1="60" x2="195" y2="160"/>
  <line x1="50" y1="93" x2="270" y2="93"/><line x1="40" y1="127" x2="260" y2="127"/></g>
  <g clip-path="url(#pc)"><polygon class="shine" points="0,60 40,60 0,160 -40,160" fill="#fff" opacity=".3"/></g></svg>';
}
function table($heads, $rows) {
  echo '<table><tr>'; foreach ($heads as $h) echo "<th>$h</th>"; echo '</tr>';
  foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo "<td>$c</td>"; echo '</tr>'; }
  echo '</table>';
}
?>
<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>SolarServe</title>
<style>
:root{--bg:#f4f6fb;--card:#fff;--text:#1f2937;--muted:#6b7280;--line:#e5e7eb;--pri:#4f46e5;--pri2:#4338ca;--bar:#111827;--ok:#16a34a;--bad:#dc2626}
@media(prefers-color-scheme:dark){:root{--bg:#0f172a;--card:#1e293b;--text:#e5e7eb;--muted:#94a3b8;--line:#334155;--bar:#020617}}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;background:var(--bg);color:var(--text)}
nav{display:flex;flex-wrap:wrap;align-items:center;gap:6px 16px;background:var(--bar);padding:12px 24px}
nav a{color:#cbd5e1;text-decoration:none;font-weight:500;padding:4px 0;margin:0}nav a:hover{color:#fff}
.brand{color:#fff;font-weight:700;font-size:17px;margin-right:12px}.who{color:#94a3b8;margin-right:auto}
main{max-width:1100px;margin:24px auto;padding:0 16px}
h2{margin:24px 0 10px}h3{margin:22px 0 8px}
table{border-collapse:collapse;width:100%;background:var(--card);border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1);margin:8px 0}
th{background:rgba(127,127,127,.12);font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
td,th{padding:10px 12px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle}tr:last-child td{border-bottom:0}
input,select,textarea{padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--text);font:inherit;margin:2px}
input:focus,select:focus,textarea:focus{outline:2px solid var(--pri);border-color:transparent}
button{padding:8px 14px;border:0;border-radius:8px;background:var(--pri);color:#fff;font:inherit;font-weight:600;cursor:pointer;margin:2px}
button:hover{background:var(--pri2)}button.danger{background:var(--bad)}button.ok{background:var(--ok)}
.big{font-size:20px;padding:16px 28px;background:var(--ok);border-radius:12px}.big:hover{background:#15803d}
.f{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;border:1px solid #fecaca}
.card{background:var(--card);border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.login{max-width:360px;margin:60px auto}.login input{width:100%;margin:6px 0}.login button{width:100%;margin-top:10px}
.act{display:flex;flex-wrap:wrap;gap:4px;align-items:center}.act button{padding:5px 10px;font-size:13px}.act input,.act select{padding:5px;font-size:13px}
.badge{padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;color:#fff;background:#6b7280}
.b-pending{background:#d97706}.b-approved{background:#2563eb}.b-assigned{background:#7c3aed}.b-completed{background:#16a34a}.b-cancelled{background:#dc2626}
@media(max-width:700px){nav{padding:10px 14px}td,th{padding:8px}table{display:block;overflow-x:auto}}

.hero{text-align:center;margin:30px auto 0;max-width:420px}.hero h1{margin:6px 0 0;font-size:30px;background:linear-gradient(90deg,#f59e0b,#4f46e5);-webkit-background-clip:text;background-clip:text;color:transparent}
.hero p{margin:4px 0 0;color:var(--muted)}.hero.sm{max-width:260px;margin:0 auto}.solar{width:100%;height:auto;animation:float 5s ease-in-out infinite}
.rays{transform-box:fill-box;transform-origin:center;animation:spin 18s linear infinite}.sun{animation:glow 2.5s ease-in-out infinite}
.shine{animation:shine 3.5s ease-in-out infinite}.sunicon{display:inline-block;color:#fbbf24;transition:transform .6s}.brand:hover .sunicon{transform:rotate(180deg)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:12px 0}
.stat{background:var(--card);border-radius:12px;padding:14px;box-shadow:0 1px 3px rgba(0,0,0,.1);transition:transform .2s,box-shadow .2s}
.stat:hover{transform:translateY(-4px);box-shadow:0 8px 18px rgba(0,0,0,.15)}.stat b{display:block;font-size:28px}
main>*{animation:fadeUp .5s ease both}main>*:nth-child(2){animation-delay:.08s}main>*:nth-child(3){animation-delay:.16s}main>*:nth-child(4){animation-delay:.24s}
tbody tr{transition:background .15s}tr:hover td{background:rgba(79,70,229,.07)}button{transition:transform .1s,background .2s}button:active{transform:scale(.95)}
nav a{position:relative}nav a::after{content:"";position:absolute;left:0;bottom:-2px;height:2px;width:0;background:#fbbf24;transition:width .25s}nav a:hover::after{width:100%}
.pulse{animation:pulse 2s infinite}.f{animation:shake .4s}
@keyframes spin{to{transform:rotate(360deg)}}@keyframes float{50%{transform:translateY(-8px)}}
@keyframes glow{50%{filter:drop-shadow(0 0 14px #fbbf24)}}@keyframes shine{from{transform:translateX(-60px)}to{transform:translateX(330px)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.5)}70%{box-shadow:0 0 0 18px rgba(22,163,74,0)}100%{box-shadow:0 0 0 0 rgba(22,163,74,0)}}
@keyframes shake{25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
<nav><span class="brand"><span class="sunicon">&#9728;</span> SolarServe</span><?php if (!empty($_SESSION['uid'])): ?><span class="who"><?= e($_SESSION['name']) ?></span>
<?php if ($_SESSION['role'] === 'admin'): ?><a href="?p=bookings">Bookings</a><a href="?p=users">Users</a><a href="?p=announce">Announce</a>
<a href="?p=reports">Problem reports</a><a href="?p=attendance">Attendance</a><a href="?p=audit">Audit</a>
<?php else: ?><a href="?p=home">Home</a><?php endif; ?><a href="?p=logout">Logout</a><?php endif; ?></nav><main>
<?php foreach ($_SESSION['f'] ?? [] as $m) echo '<p class="f">' . e($m) . '</p>'; unset($_SESSION['f']);


if ($p === 'login'): ?>
<div class="hero"><?= solar() ?><h1>SolarServe</h1><p>Solar panel installation, cleaning &amp; maintenance</p></div>
<div class="card login"><h2>Login</h2><form method="post"><input type="hidden" name="a" value="login"><input name="email" placeholder="Email">
<input name="password" type="password" placeholder="Password"><button>Login</button></form></div>
<?php elseif ($p === 'home'):
  $jobs = q("SELECT customer,address,service FROM bookings WHERE tech_id=? AND status IN ('assigned','approved')", [$_SESSION['uid']]); ?>
<div class="hero sm"><?= solar() ?></div><h2>Check-in</h2><button class="big pulse" onclick="checkin()">Check in now</button> <span id="msg"></span>
<h2>Announcements</h2>
<?php foreach (q("SELECT * FROM announcements ORDER BY id DESC LIMIT 5") as $r) echo '<p>' . e($r['ts']) . ': ' . e($r['text']) . '</p>'; ?>
<h2>My jobs</h2>
<?php table(['Customer', 'Address', 'Service'], array_map(fn($j) => [e($j['customer']), e($j['address']), e($j['service'])], $jobs)); ?>
<h2>Report a problem</h2><form method="post"><input type="hidden" name="a" value="report">
<select name="kind"><option>Equipment issue</option><option>Customer concern</option></select><br>
<textarea name="message" rows="3" cols="40" required></textarea><br><button>Send to admin</button></form>
<script>function checkin(){navigator.geolocation.getCurrentPosition(p=>{fetch('?p=home',{method:'POST',
headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'checkin',lat:p.coords.latitude,lng:p.coords.longitude})})
.then(r=>r.text()).then(t=>msg.textContent=t)},e=>msg.textContent='Location denied: '+e.message)}</script>
<?php elseif ($p === 'bookings'): need('admin');
  $b = q("SELECT b.*,u.name tech FROM bookings b LEFT JOIN users u ON u.id=b.tech_id ORDER BY date");
  $techs = q("SELECT id,name FROM users WHERE role='tech' AND active=1"); ?>
<h2>Bookings</h2><div class="stats"><?php foreach ($STATUSES as $s) echo '<div class="stat"><b data-n="' . count(array_filter($b, fn($r) => $r['status'] === $s)) . '">0</b><span class="badge b-' . $s . '">' . $s . '</span></div>'; ?></div>
<table><tr><th>Date</th><th>Customer</th><th>Address</th><th>Service</th><th>Status</th><th>Technician</th><th>Actions</th></tr>
<?php foreach ($b as $r): ?><tr><td><?= e($r['date']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['address']) ?></td>
<td><?= e($r['service']) ?></td><td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td><td><?= e($r['tech'] ?? '-') ?></td>
<td><form method="post" class="act"><input type="hidden" name="a" value="booking_act"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<select name="tech_id"><option value="">tech...</option><?php foreach ($techs as $t) echo '<option value="' . $t['id'] . '">' . e($t['name']) . '</option>'; ?></select>
<button name="act" value="assign">Assign</button><button name="act" value="approved">Approve</button>
<button class="danger" name="act" value="cancelled">Decline</button><button class="ok" name="act" value="completed">Done</button>
<input name="date" type="date"><button name="act" value="resched">Reschedule</button></form></td></tr><?php endforeach; ?></table>
<h3>New booking</h3><form method="post"><input type="hidden" name="a" value="booking_add">
<input name="customer" placeholder="Customer" required><input name="address" placeholder="Address" required>
<select name="service" required><option value="">Service...</option><option>Solar Panel Installation</option><option>Panel Cleaning</option><option>Maintenance and Repair</option><option>System Inspection</option><option>Inverter or Battery Service</option></select><input name="date" type="date" required><button>Add</button></form>
<?php elseif ($p === 'users'): need('admin'); ?>
<h2>Users</h2>
<?php table(['Name', 'Email', 'Role', 'Active', ''], array_map(fn($u) => [e($u['name']), e($u['email']), e($u['role']), $u['active'] ? 'yes' : 'no',
  '<form method="post"><input type="hidden" name="a" value="user_toggle"><input type="hidden" name="id" value="' . $u['id'] . '"><button>' . ($u['active'] ? 'Deactivate' : 'Activate') . '</button></form>'],
  q("SELECT * FROM users"))); ?>
<h3>Add user</h3><form method="post"><input type="hidden" name="a" value="user_add"><input name="name" placeholder="Name" required>
<input name="email" placeholder="Email" required><input name="password" placeholder="Password" required>
<select name="role"><option>tech</option><option>admin</option></select><button>Add</button></form>
<?php elseif ($p === 'announce'): need('admin'); ?>
<h2>Announcements</h2><form method="post"><input type="hidden" name="a" value="announce"><textarea name="text" rows="3" cols="50" required></textarea><br><button>Post</button></form>
<?php foreach (q("SELECT * FROM announcements ORDER BY id DESC") as $r) echo '<p>' . e($r['ts']) . ': ' . e($r['text']) . '</p>'; ?>
<?php elseif ($p === 'reports'): need('admin'); ?>
<h2>Problem reports</h2>
<?php table(['When', 'Technician', 'Type', 'Message'], array_map(fn($r) => [e($r['ts']), e($r['name']), e($r['kind']), e($r['message'])],
  q("SELECT r.*,u.name FROM reports r JOIN users u ON u.id=r.user_id ORDER BY r.id DESC"))); ?>
<?php elseif ($p === 'attendance'): need('admin'); ?>
<h2>Attendance log</h2>
<?php table(['When', 'Name', 'GPS'], array_map(fn($c) => [e($c['ts']), e($c['name']),
  '<a target="_blank" href="https://maps.google.com/?q=' . e($c['lat']) . ',' . e($c['lng']) . '">' . e($c['lat']) . ', ' . e($c['lng']) . '</a>'],
  q("SELECT c.*,u.name FROM checkins c JOIN users u ON u.id=c.user_id ORDER BY c.id DESC"))); ?>
<?php elseif ($p === 'audit'): need('admin'); ?>
<h2>Audit log</h2>
<?php table(['When', 'Admin', 'Action'], array_map(fn($a) => [e($a['ts']), e($a['name']), e($a['action'])],
  q("SELECT a.*,u.name FROM audit a JOIN users u ON u.id=a.admin_id ORDER BY a.id DESC"))); ?>
<?php endif; ?>
<script>document.querySelectorAll('[data-n]').forEach(el=>{let n=+el.dataset.n,i=0;const t=setInterval(()=>{i++;el.textContent=Math.round(n*i/20);if(i>=20)clearInterval(t)},30)})</script>
</main>
