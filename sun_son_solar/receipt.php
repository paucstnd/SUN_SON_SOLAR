<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';

$auth = require_auth(['customer', 'admin']);
$id = (int) ($_GET['id'] ?? 0);

$sql = "SELECT p.*, b.scheduled_date, b.address, s.name AS service_name,
        CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.email, u.phone
    FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN services s ON s.id = b.service_id JOIN users u ON u.id = p.user_id
    WHERE p.id = ?" . ($auth['role'] === 'customer' ? ' AND p.user_id = ?' : '');
$stmt = $conn->prepare($sql);
if ($auth['role'] === 'customer') {
    $stmt->bind_param('ii', $id, $auth['id']);
} else {
    $stmt->bind_param('i', $id);
}
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    http_response_code(404);
    exit('Receipt not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt <?= e($p['receipt_no']) ?> · Sun Son Solar</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'IBM Plex Sans',sans-serif;background:#f7f7f4;color:#151a22;padding:30px 16px;}
  .receipt{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e4e5e1;border-radius:8px;padding:34px;}
  .top{display:flex;align-items:center;gap:12px;border-bottom:1px solid #e4e5e1;padding-bottom:16px;margin-bottom:20px;}
  .top img{width:48px;height:48px;object-fit:contain;}
  h1{font-family:'IBM Plex Sans',sans-serif;font-size:22px;}
  h1 span{color:#c68a00;}
  .top small{display:block;color:#5b6472;}
  dl{display:grid;grid-template-columns:140px 1fr;gap:8px 12px;font-size:14.5px;}
  dt{color:#5b6472;font-weight:600;}
  .total{margin-top:22px;padding:16px 0;border-top:1px solid #e4e5e1;border-radius:0;display:flex;justify-content:space-between;font-size:18px;font-weight:700;font-family:'IBM Plex Sans',sans-serif;}
  .foot{margin-top:22px;color:#5b6472;font-size:13px;text-align:center;}
  .actions{max-width:560px;margin:16px auto 0;text-align:center;}
  button{height:42px;padding:0 20px;border:none;border-radius:6px;background:#f2a900;font:700 14px 'IBM Plex Sans',sans-serif;cursor:pointer;}
  @media (max-width:520px){.receipt{padding:24px 18px;}dl{grid-template-columns:1fr;gap:3px;}dd{margin-bottom:9px;}}
  @media print{body{background:#fff;padding:0;}.receipt{border:none;}.actions{display:none;}}
</style>
</head>
<body>
<div class="receipt">
  <div class="top"><img src="transL.png" alt=""><div><h1>Sun Son <span>Solar</span></h1><small>Official receipt · Pasig City</small></div></div>
  <dl>
    <dt>Receipt no.</dt><dd><?= e($p['receipt_no']) ?></dd>
    <dt>Date paid</dt><dd><?= e(fmt_datetime($p['paid_at'])) ?></dd>
    <dt>Customer</dt><dd><?= e($p['customer_name']) ?></dd>
    <dt>Service</dt><dd><?= e($p['service_name']) ?> (<?= e(fmt_date($p['scheduled_date'])) ?>)</dd>
    <dt>Service address</dt><dd><?= e($p['address']) ?></dd>
    <dt>Payment method</dt><dd><?= e($p['method']) ?><?= $p['reference_no'] !== '' ? ' · Ref ' . e($p['reference_no']) : '' ?></dd>
    <dt>Received by</dt><dd><?= e($p['recorded_by']) ?></dd>
  </dl>
  <div class="total"><span>Amount paid</span><span><?= e(peso($p['amount'])) ?></span></div>
  <p class="foot">Thank you for choosing Sun Son Solar. Power Your Future with Solar.</p>
</div>
<div class="actions"><button type="button" onclick="window.print()">Print receipt</button></div>
</body>
</html>
