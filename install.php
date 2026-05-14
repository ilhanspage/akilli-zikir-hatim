<?php
declare(strict_types=1);

$root = __DIR__;
$configFile = $root . '/system/config.php';
$maintenanceFlag = $root . '/system/ALLOW_INSTALL';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$installed = is_file($configFile);

if ($installed && !is_file($maintenanceFlag)) {
    http_response_code(403);
    ?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kurulum Kapalı</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#031915;color:#f5dc9a;font-family:Arial,sans-serif}
    .card{max-width:720px;margin:24px;padding:28px;border:1px solid rgba(245,220,154,.28);border-radius:24px;background:linear-gradient(145deg,#07372f,#021512);box-shadow:0 20px 60px rgba(0,0,0,.35)}
    h1{margin:0 0 12px;font-family:Georgia,serif;font-size:30px}
    p{color:#e8d8ad;line-height:1.6}
    code{background:rgba(255,255,255,.08);padding:3px 7px;border-radius:8px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Kurulum Güvenlik Nedeniyle Kapalı</h1>
    <p>Bu sistem zaten kurulu görünüyor. Canlı sistemde <code>install.php</code> çalıştırılması engellendi.</p>
    <p>Gerçekten yeniden kurulum gerekiyorsa önce tam dosya ve veritabanı yedeği alın, sonra sunucuda geçici olarak <code>system/ALLOW_INSTALL</code> dosyası oluşturun. İşlem bitince bu dosyayı ve <code>install.php</code> dosyasını kaldırın.</p>
  </div>
</body>
</html><?php
    exit;
}

$default = [
    'site_url' => '',
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'admin_user' => 'admin',
    'admin_name' => 'Yönetici'
];

$message = $installed ? 'Bakım modu açık. Kurulum ekranı geçici olarak erişilebilir.' : '';
$error = '';

function php_value($v) { return var_export((string)$v, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteUrl = rtrim(trim($_POST['site_url'] ?? $default['site_url']), '/');
    $dbHost = trim($_POST['db_host'] ?? $default['db_host']);
    $dbName = trim($_POST['db_name'] ?? $default['db_name']);
    $dbUser = trim($_POST['db_user'] ?? $default['db_user']);
    $dbPass = (string)($_POST['db_pass'] ?? $default['db_pass']);
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminName = trim($_POST['admin_name'] ?? 'Yönetici');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

    if ($siteUrl === '' || !preg_match('/^https?:\/\//', $siteUrl)) {
        $error = 'Site adresi https:// ile başlamalı.';
    } elseif ($dbName === '' || $dbUser === '') {
        $error = 'Veritabanı adı ve kullanıcı adı boş olamaz.';
    } elseif ($adminPass === '' || strlen($adminPass) < 8) {
        $error = 'Admin şifresi en az 8 karakter olmalı.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Admin şifreleri aynı değil.';
    } else {
        try {
            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            $schemaFile = $root . '/database/schema.sql';
            $seedFile = $root . '/database/seeds.sql';
            if (!is_file($schemaFile) || !is_file($seedFile)) {
                throw new RuntimeException('database/schema.sql veya database/seeds.sql bulunamadı.');
            }

            $pdo->exec(file_get_contents($schemaFile));
            $pdo->exec(file_get_contents($seedFile));

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, name, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)");
            $stmt->execute([$adminUser, $hash, $adminName]);

            $appKey = bin2hex(random_bytes(32));
            $config = "<?php\n"
                . "define('APP_INSTALLED', true);\n"
                . "define('APP_VERSION', " . php_value('1.2.37') . ");\n"
                . "define('SITE_URL', " . php_value($siteUrl) . ");\n"
                . "define('DB_HOST', " . php_value($dbHost) . ");\n"
                . "define('DB_NAME', " . php_value($dbName) . ");\n"
                . "define('DB_USER', " . php_value($dbUser) . ");\n"
                . "define('DB_PASS', " . php_value($dbPass) . ");\n"
                . "define('APP_KEY', " . php_value($appKey) . ");\n"
                . "date_default_timezone_set('Europe/Istanbul');\n";

            if (!is_dir($root . '/system')) mkdir($root . '/system', 0755, true);
            file_put_contents($configFile, $config, LOCK_EX);

            $message = 'Kurulum tamamlandı. Güvenlik için install.php ve system/ALLOW_INSTALL dosyasını kaldırın.';
        } catch (Throwable $e) {
            $error = 'Kurulum hatası: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Akıllı Zikir & Hatim Kurulum</title>
  <style>
    body{margin:0;background:#031915;color:#f5dc9a;font-family:Arial,sans-serif}
    .wrap{max-width:760px;margin:40px auto;padding:24px}
    .card{border:1px solid rgba(245,220,154,.24);border-radius:24px;background:linear-gradient(145deg,#07372f,#021512);padding:24px}
    label{display:block;margin:14px 0 6px;font-weight:700}
    input{width:100%;box-sizing:border-box;padding:12px;border-radius:12px;border:1px solid rgba(245,220,154,.25);background:#021512;color:#fff}
    button{margin-top:18px;padding:13px 18px;border:0;border-radius:14px;background:#d7af68;color:#10251f;font-weight:800;cursor:pointer}
    .ok{background:rgba(96,211,148,.12);border:1px solid rgba(96,211,148,.25);padding:12px;border-radius:12px}
    .err{background:rgba(232,107,97,.12);border:1px solid rgba(232,107,97,.25);padding:12px;border-radius:12px}
    p{color:#e8d8ad;line-height:1.55}
  </style>
</head>
<body>
<div class="wrap"><div class="card">
  <h1>Akıllı Zikir & Hatim Kurulum</h1>
  <p>Canlı sistemde kurulum varsayılan olarak kapalıdır. Bu ekran sadece ilk kurulum veya kontrollü bakım modu içindir.</p>
  <?php if($message): ?><div class="ok"><?=h($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>Site URL</label><input name="site_url" value="<?=h($_POST['site_url'] ?? $default['site_url'])?>" placeholder="https://siteadresiniz.com" required>
    <label>DB Host</label><input name="db_host" value="<?=h($_POST['db_host'] ?? $default['db_host'])?>" required>
    <label>DB Name</label><input name="db_name" value="<?=h($_POST['db_name'] ?? $default['db_name'])?>" required>
    <label>DB User</label><input name="db_user" value="<?=h($_POST['db_user'] ?? $default['db_user'])?>" required>
    <label>DB Pass</label><input name="db_pass" type="password" value="<?=h($_POST['db_pass'] ?? '')?>">
    <label>Admin Kullanıcı</label><input name="admin_user" value="<?=h($_POST['admin_user'] ?? $default['admin_user'])?>" required>
    <label>Admin Adı</label><input name="admin_name" value="<?=h($_POST['admin_name'] ?? $default['admin_name'])?>" required>
    <label>Admin Şifre</label><input name="admin_pass" type="password" required>
    <label>Admin Şifre Tekrar</label><input name="admin_pass2" type="password" required>
    <button>Kurulumu Başlat</button>
  </form>
</div></div>
</body>
</html>
