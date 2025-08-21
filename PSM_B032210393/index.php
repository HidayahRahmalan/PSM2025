<?php
// index.php — landing/redirector
session_start();

$dest = 'login.php';
$role = $_SESSION['role'] ?? null;

if ($role === 'superadmin') {
    $dest = 'superadmin_home.php';
} elseif (!empty($role)) {
    // admin/staff etc.
    $dest = 'home.php';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Blood Donation Admin</title>
    <!-- Auto-redirect (fallback link shown below) -->
    <meta http-equiv="refresh" content="0; url=<?= htmlspecialchars($dest) ?>">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background:#f3f3f3; margin:0; }
        .navbar { background:#b7322c; color:#fff; padding:14px 24px; font-weight:bold; }
        .container {
            max-width: 520px; margin:60px auto; background:#fff; border-radius:8px;
            box-shadow:0 0 20px rgba(0,0,0,.08); padding:32px; text-align:center;
        }
        .logo { font-size:64px; color:#b7322c; }
        .title { font-size:22px; font-weight:700; margin:10px 0 4px; }
        .sub { color:#555; margin-bottom:22px; }
        a.btn {
            display:inline-block; background:#b7322c; color:#fff; padding:12px 18px;
            border-radius:6px; text-decoration:none; font-weight:600;
        }
        .muted { margin-top:12px; color:#777; font-size:14px; }
    </style>
</head>
<body>
    <div class="navbar">Blood Donation Management</div>

    <div class="container">
        <div class="logo">🩸</div>
        <div class="title">Redirecting…</div>
        <div class="sub">If you’re not redirected automatically, click the button below.</div>

        <a class="btn" href="<?= htmlspecialchars($dest) ?>">Continue</a>
        <div class="muted">Destination: <?= htmlspecialchars($dest) ?></div>
    </div>
</body>
</html>
