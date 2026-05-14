<?php
require_once __DIR__ . '/../system/auth.php';
$error = '';
// Admin login eski CSS cache'ini engelle.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (admin_user()) { redirect('/admin/'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_user'] = ['id' => $admin['id'], 'username' => $admin['username'], 'name' => $admin['name']];
        redirect('/admin/');
    }
    $error = 'Kullanıcı adı veya şifre hatalı.';
}
$adminCssVersion = is_file(__DIR__ . '/assets/admin.css') ? (string)filemtime(__DIR__ . '/assets/admin.css') : date('YmdHis');
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Giriş</title><link rel="stylesheet" href="/admin/assets/admin.css?v=<?=h($adminCssVersion)?>"></head><body class="admin-login-premium"><div class="login-wrap"><form class="login-card" method="post"><div class="login-moon-mark">☾</div><h1>Admin Panel</h1><p class="muted">Akıllı Zikir & Hatim premium yönetim merkezi</p><?php if($error): ?><div class="alert err"><?=h($error)?></div><?php endif; ?><label>Kullanıcı adı</label><input class="field" name="username" value="admin" required><br><br><label>Şifre</label><input class="field" name="password" type="password" required><button class="btn" style="width:100%;margin-top:18px">Giriş Yap</button><p class="muted">Kurulumda belirlediğiniz admin şifresini kullanın.</p></form></div></body></html>
