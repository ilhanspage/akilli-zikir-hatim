<?php
require_once __DIR__ . '/../system/auth.php';
require_admin();
$pdo = db();
$page = $_GET['page'] ?? 'dashboard';
$notice = '';
$error = '';

// Admin panelde eski CSS'in geri gelmesini engelle.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

function is_active_page($p, $page) { return $p === $page ? 'active' : ''; }
function post($key, $default = '') { return trim((string)($_POST[$key] ?? $default)); }
function try_sql(PDO $pdo, string $sql): void { try { $pdo->exec($sql); } catch (Throwable $e) {} }

function admin_css_version(): string {
    $file = __DIR__ . '/assets/admin.css';
    $fileMtime = is_file($file) ? (string)@filemtime($file) : date('YmdHis');
    $saved = '';
    try { $saved = (string)(setting('admin_css_version', '') ?? ''); } catch (Throwable $e) { $saved = ''; }
    return preg_replace('/[^a-zA-Z0-9._-]+/', '-', $saved ?: $fileMtime) ?: date('YmdHis');
}

function admin_refresh_css_cache(array &$messages = []): string {
    $version = date('YmdHis');
    $file = __DIR__ . '/assets/admin.css';
    if (is_file($file)) @touch($file);
    try { upsert_setting('admin_css_version', $version); } catch (Throwable $e) {}
    try { upsert_setting('admin_cache_version', $version); } catch (Throwable $e) {}
    $messages[] = 'Admin CSS cache sürümü yenilendi: ' . $version;
    return $version;
}

function check_ok($label, $ok, $detail = '') {
    return ['label' => $label, 'ok' => (bool)$ok, 'detail' => $detail];
}

function admin_onoff_select(string $key, string $label, string $default = '1', string $help = ''): string {
    $value = (string)(setting($key, $default) ?? $default);
    $on = $value === '1' ? 'selected' : '';
    $off = $value === '0' ? 'selected' : '';
    $html = '<div><label>' . h($label) . '</label><select class="field admin-onoff" name="' . h($key) . '">';
    $html .= '<option value="1" ' . $on . '>Açık</option>';
    $html .= '<option value="0" ' . $off . '>Kapalı</option>';
    $html .= '</select>';
    if ($help !== '') $html .= '<p class="field-help">' . h($help) . '</p>';
    return $html . '</div>';
}

function admin_settings_title(string $title, string $desc = ''): string {
    $html = '<div class="settings-section-head"><h2>' . h($title) . '</h2>';
    if ($desc !== '') $html .= '<p class="muted">' . h($desc) . '</p>';
    return $html . '</div>';
}


function admin_build_status_label(string $status): string {
    return match($status) {
        'pending' => 'Bekliyor',
        'sent' => 'Gönderildi',
        'success', 'completed' => 'Başarılı',
        'failed' => 'Başarısız',
        'cancelled' => 'İptal',
        default => $status ?: 'Bilinmiyor'
    };
}

function admin_build_status_class(string $status): string {
    return match($status) {
        'sent' => 'info',
        'success', 'completed' => 'live',
        'failed', 'cancelled' => 'off',
        default => 'wait'
    };
}

function admin_apk_type_label(string $type): string {
    return match($type) {
        'debug_apk' => 'Test APK',
        'release_apk' => 'Release APK',
        'play_aab', 'unsigned_aab' => 'Google Play AAB',
        default => $type ?: 'Bilinmiyor'
    };
}

function admin_ios_type_label(string $type): string {
    return match($type) {
        'simulator_debug' => 'Simülatör Testi',
        'ios_project_zip' => 'iOS Proje ZIP',
        'testflight' => 'TestFlight',
        'app_store' => 'App Store Release',
        'adhoc' => 'Ad Hoc IPA',
        default => $type ?: 'Bilinmiyor'
    };
}

function admin_hatim_status_label(string $status): string {
    return match($status) {
        'active' => 'Aktif',
        'paused' => 'Beklemede',
        'completed' => 'Tamamlandı',
        default => $status ?: 'Bilinmiyor'
    };
}

function admin_hatim_status_class(string $status): string {
    return match($status) {
        'active' => 'live',
        'completed' => 'info',
        'paused' => 'wait',
        default => 'wait'
    };
}

function admin_juz_status_label(string $status): string {
    return match($status) {
        'empty' => 'Boş',
        'reserved' => 'Alındı',
        'completed' => 'Tamamlandı',
        default => $status ?: 'Bilinmiyor'
    };
}

function admin_juz_status_class(string $status): string {
    return match($status) {
        'empty' => 'wait',
        'reserved' => 'info',
        'completed' => 'live',
        default => 'wait'
    };
}

function admin_mode_label(string $mode): string {
    return match($mode) {
        'github_actions' => 'GitHub Actions',
        'external_webhook' => 'Harici Webhook / CI',
        'manual' => 'Manuel Takip',
        default => $mode ?: 'Belirtilmemiş'
    };
}


function admin_yes_no_badge(bool $ok, string $yes = 'Hazır', string $no = 'Kontrol Et'): string {
    return '<span class="badge ' . ($ok ? 'live' : 'wait') . '">' . h($ok ? $yes : $no) . '</span>';
}

function admin_status_url(string $path = ''): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'zikir.next-sosyal.com';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function admin_file_ok(string $relative): bool {
    return is_file(dirname(__DIR__) . '/' . ltrim($relative, '/'));
}

function pwa_icon_defs(): array {
    return [
        'icon_192' => ['label' => 'Android / PWA 192x192', 'file' => 'icon-192.png', 'size' => '192x192', 'recommended' => [192, 192]],
        'icon_384' => ['label' => 'Android / PWA 384x384', 'file' => 'icon-384.png', 'size' => '384x384', 'recommended' => [384, 384]],
        'icon_512' => ['label' => 'Android / PWA 512x512', 'file' => 'icon-512.png', 'size' => '512x512', 'recommended' => [512, 512]],
        'maskable_512' => ['label' => 'Android maskable 512x512', 'file' => 'maskable-512.png', 'size' => '512x512', 'recommended' => [512, 512]],
        'apple_touch' => ['label' => 'iPhone Ana Ekran 180x180', 'file' => 'apple-touch-icon.png', 'size' => '180x180', 'recommended' => [180, 180]],
        'favicon_32' => ['label' => 'Favicon 32x32', 'file' => 'favicon-32.png', 'size' => '32x32', 'recommended' => [32, 32]],
        'favicon_16' => ['label' => 'Favicon 16x16', 'file' => 'favicon-16.png', 'size' => '16x16', 'recommended' => [16, 16]],
    ];
}

function pwa_project_root(): string {
    return realpath(__DIR__ . '/..') ?: dirname(__DIR__);
}



function admin_bool_badge(bool $ok, string $yes = 'Tamam', string $no = 'Kontrol'): string {
    return '<span class="badge ' . ($ok ? 'live' : 'off') . '">' . h($ok ? $yes : $no) . '</span>';
}

function admin_maintenance_check(string $label, bool $ok, string $detail, string $level = ''): array {
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'level' => $level ?: ($ok ? 'ok' : 'danger')];
}

function admin_write_db_backup(PDO $pdo): string {
    $root = dirname(__DIR__);
    $dir = $root . '/storage/backups/db';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $deny = $dir . '/.htaccess';
    if (!is_file($deny)) @file_put_contents($deny, "Require all denied\nDeny from all\n", LOCK_EX);

    $file = $dir . '/zikir_db_' . date('Ymd_His') . '.sql';
    $fh = fopen($file, 'wb');
    if (!$fh) throw new RuntimeException('Yedek dosyası oluşturulamadı.');

    fwrite($fh, "-- Akıllı Zikir & Hatim DB Backup\n");
    fwrite($fh, "-- Date: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $tableRow) {
        $table = (string)$tableRow[0];
        fwrite($fh, "\n-- Table: `{$table}`\n");
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        if ($create && isset($create[1])) {
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fh, $create[1] . ";\n\n");
        }

        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(fn($c) => '`' . str_replace('`','``',$c) . '`', array_keys($row));
            $vals = [];
            foreach ($row as $val) {
                $vals[] = $val === null ? 'NULL' : $pdo->quote((string)$val);
            }
            fwrite($fh, "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
        }
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    @chmod($file, 0600);
    return $file;
}

function admin_recent_backup_files(int $limit = 10): array {
    $dir = dirname(__DIR__) . '/storage/backups/db';
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/zikir_db_*.sql') ?: [];
    usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
    return array_slice($files, 0, $limit);
}

function admin_tail_file(string $file, int $lines = 50): array {
    if (!is_file($file) || !is_readable($file)) return [];
    $data = @file($file, FILE_IGNORE_NEW_LINES);
    if (!$data) return [];
    return array_slice($data, -$lines);
}



function admin_effective_app_version(PDO $pdo): string {
    try {
        $settingVersion = trim((string)setting('app_release_version', ''));
        if ($settingVersion !== '') return $settingVersion;
    } catch (Throwable $e) {}

    try {
        $latest = $pdo->query("SELECT version FROM app_versions ORDER BY applied_at DESC, version DESC LIMIT 1")->fetchColumn();
        if ($latest) return (string)$latest;
    } catch (Throwable $e) {}

    return defined('APP_VERSION') ? (string)APP_VERSION : 'Bilinmiyor';
}


function admin_format_bytes(int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return number_format($bytes, 0, ',', '.') . ' B';
}

function pwa_icon_asset_dirs(): array {
    return [
        'app/assets/icons' => 'PWA / Ana Ekran ikonları',
        'app/assets/img' => 'Uygulama içi ikon ve görseller',
        'app/assets/splash' => 'Splash / Açılış görselleri',
    ];
}

function pwa_public_path(string $root, string $absolute): string {
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $absolute = str_replace('\\', '/', $absolute);
    if (str_starts_with($absolute, $root)) return '/' . ltrim(substr($absolute, strlen($root)), '/');
    return '/' . basename($absolute);
}

function pwa_svg_dimensions(string $file): string {
    $raw = @file_get_contents($file, false, null, 0, 4096);
    if (!$raw) return 'SVG';
    if (preg_match('/viewBox=["\']\s*[-0-9.]+\s+[-0-9.]+\s+([-0-9.]+)\s+([-0-9.]+)\s*["\']/i', $raw, $m)) return 'SVG · ' . (int)round((float)$m[1]) . 'x' . (int)round((float)$m[2]);
    if (preg_match('/width=["\']([^"\']+)["\']/i', $raw, $w) && preg_match('/height=["\']([^"\']+)["\']/i', $raw, $h)) return 'SVG · ' . $w[1] . 'x' . $h[1];
    return 'SVG';
}

function pwa_asset_referenced(string $root, string $publicPath): bool {
    $publicPath = strtok($publicPath, '?') ?: $publicPath;
    $needle = ltrim($publicPath, '/');
    $base = basename($publicPath);
    foreach ([$root.'/app/index.html',$root.'/app/assets/js/app.js',$root.'/app/assets/css/app.css',$root.'/manifest.json',$root.'/service-worker.js'] as $file) {
        if (!is_file($file)) continue;
        $content = (string)@file_get_contents($file);
        if (str_contains($content, $publicPath) || str_contains($content, $needle) || str_contains($content, $base)) return true;
    }
    return false;
}

function pwa_icon_asset_files(string $root): array {
    $items = [];
    $allowed = ['png','jpg','jpeg','webp','svg','ico'];
    foreach (pwa_icon_asset_dirs() as $relDir => $groupLabel) {
        $dir = rtrim($root, '/') . '/' . trim($relDir, '/');
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) continue;
            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $allowed, true)) continue;
            $absolute = $fileInfo->getPathname();
            $public = pwa_public_path($root, $absolute);
            $dim = strtoupper($ext);
            if ($ext === 'svg') $dim = pwa_svg_dimensions($absolute);
            else { $info = @getimagesize($absolute); if ($info) $dim = ($info[0] ?? '?') . 'x' . ($info[1] ?? '?'); }
            $items[] = [
                'group' => $groupLabel,
                'file' => $fileInfo->getFilename(),
                'path' => $public,
                'relative' => ltrim($public, '/'),
                'ext' => strtoupper($ext),
                'size' => admin_format_bytes((int)$fileInfo->getSize()),
                'dimensions' => $dim,
                'used' => pwa_asset_referenced($root, $public),
                'modified' => date('d.m.Y H:i', $fileInfo->getMTime()),
            ];
        }
    }
    usort($items, fn($a, $b) => [$a['group'], $a['file']] <=> [$b['group'], $b['file']]);
    return $items;
}

function pwa_cache_asset_urls(string $root, string $version): array {
    $urls = [];
    foreach (pwa_icon_asset_files($root) as $item) $urls[] = $item['path'] . '?v=' . rawurlencode($version);
    return array_values(array_unique($urls));
}

function pwa_refresh_asset_query_versions(string $root, string $version): void {
    foreach ([$root.'/app/assets/js/app.js', $root.'/app/assets/css/app.css', $root.'/app/index.html'] as $file) {
        if (!is_file($file) || !is_writable($file)) continue;
        $content = (string)file_get_contents($file);
        $content = preg_replace_callback('#(/app/assets/(?:img|icons|splash)/[^"\'\)\s?]+)(?:\?v=[^"\'\)\s]*)?#i', fn($m) => $m[1] . '?v=' . $version, $content);
        file_put_contents($file, $content, LOCK_EX);
    }
}

function pwa_validate_asset_upload(array $file, array $allowedExt, int $maxBytes = 5242880): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi. Hata kodu: ' . (int)$file['error']);
    if (($file['size'] ?? 0) > $maxBytes) throw new RuntimeException('Dosya çok büyük. En fazla ' . admin_format_bytes($maxBytes) . ' olmalı.');
    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) throw new RuntimeException('Bu dosya türü desteklenmiyor: .' . $ext);
    if ($ext === 'svg') { $raw = (string)@file_get_contents($file['tmp_name']); if (stripos($raw, '<svg') === false) throw new RuntimeException('SVG dosyası geçerli görünmüyor.'); }
    elseif (in_array($ext, ['png','jpg','jpeg','webp'], true) && !@getimagesize($file['tmp_name'])) throw new RuntimeException('Görsel dosyası geçerli görünmüyor.');
    return $ext;
}

function pwa_safe_asset_filename(string $name, string $fallback, string $ext): string {
    $name = trim($name) ?: $fallback;
    $name = preg_replace('/\.[a-zA-Z0-9]+$/', '', $name);
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?: 'asset';
    return strtolower($name) . '.' . strtolower($ext);
}

function pwa_sanitize_version(string $version): string {
    $version = trim($version);
    $version = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $version) ?: '';
    return $version ?: date('YmdHis');
}

function pwa_upload_icon(string $field, string $destDir, array $def, array &$messages): void {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($def['label'] . ' yüklenemedi. Hata kodu: ' . (int)$file['error']);
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException($def['label'] . ' dosyası çok büyük. En fazla 4 MB olmalı.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || ($info[2] ?? null) !== IMAGETYPE_PNG) {
        throw new RuntimeException($def['label'] . ' için sadece PNG dosyası yükleyin.');
    }
    [$w, $h] = $info;
    $rec = $def['recommended'];
    if ($w !== $rec[0] || $h !== $rec[1]) {
        $messages[] = $def['label'] . ' yüklendi; ancak önerilen ' . $def['size'] . ', yüklenen ' . $w . 'x' . $h . '.';
    } else {
        $messages[] = $def['label'] . ' yüklendi.';
    }
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $target = rtrim($destDir, '/') . '/' . $def['file'];
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException($def['label'] . ' hedef klasöre taşınamadı.');
    }
    @chmod($target, 0644);
}

function pwa_refresh_manifest(string $root, string $version): void {
    $file = $root . '/manifest.json';
    $manifest = [];
    if (is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) $manifest = $decoded;
    }
    $manifest = array_replace([
        'name' => 'Akıllı Zikir & Hatim',
        'short_name' => 'Zikir Hatim',
        'description' => 'Reklamsız offline kişisel zikir, toplu dua, online zikir halkası ve hatim takip uygulaması.',
        'start_url' => '/?source=pwa',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#031915',
        'theme_color' => '#07372f',
        'lang' => 'tr-TR',
    ], $manifest);
    $manifest['icons'] = [
        ['src' => '/app/assets/icons/icon-192.png?v=' . $version, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/app/assets/icons/icon-384.png?v=' . $version, 'sizes' => '384x384', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/app/assets/icons/icon-512.png?v=' . $version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/app/assets/icons/maskable-512.png?v=' . $version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ];
    file_put_contents($file, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

function pwa_refresh_app_index(string $root, string $version): void {
    $file = $root . '/app/index.html';
    if (!is_file($file)) return;
    $html = (string)file_get_contents($file);
    $html = preg_replace('/\s*<link\s+rel=["\']apple-touch-icon["\'][^>]*>\s*/i', "\n", $html);
    $html = preg_replace('/\s*<link\s+rel=["\']icon["\'][^>]*>\s*/i', "\n", $html);
    $html = preg_replace('/\s*<link\s+rel=["\']manifest["\'][^>]*>\s*/i', "\n", $html);
    $block = "  <link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"/app/assets/icons/apple-touch-icon.png?v={$version}\">\n" .
             "  <link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"/app/assets/icons/favicon-16.png?v={$version}\">\n" .
             "  <link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"/app/assets/icons/favicon-32.png?v={$version}\">\n" .
             "  <link rel=\"icon\" type=\"image/png\" sizes=\"192x192\" href=\"/app/assets/icons/icon-192.png?v={$version}\">\n" .
             "  <link rel=\"icon\" type=\"image/png\" sizes=\"512x512\" href=\"/app/assets/icons/icon-512.png?v={$version}\">\n" .
             "  <link rel=\"manifest\" href=\"/manifest.json?v={$version}\">\n";
    if (stripos($html, '<title>') !== false) {
        $html = preg_replace('/\s*<title>/i', "\n" . $block . "  <title>", $html, 1);
    } else {
        $html = str_replace('</head>', $block . '</head>', $html);
    }
    file_put_contents($file, $html, LOCK_EX);
}

function pwa_refresh_service_worker(string $root, string $version): void {
    $file = $root . '/service-worker.js';
    if (!is_file($file)) return;
    $sw = (string)file_get_contents($file);
    $index = is_file($root . '/app/index.html') ? (string)file_get_contents($root . '/app/index.html') : '';
    $css = '/app/assets/css/app.css?v=' . $version;
    $js = '/app/assets/js/app.js?v=' . $version;
    if (preg_match('/<link[^>]+href=["\']([^"\']*app\.css[^"\']*)["\']/i', $index, $m)) $css = $m[1];
    if (preg_match('/<script[^>]+src=["\']([^"\']*app\.js[^"\']*)["\']/i', $index, $m)) $js = $m[1];
    $cache = 'akilli-zikir-hatim-v' . str_replace(['.', '_'], '-', $version);
    $sw = preg_replace("/const\s+CACHE_NAME\s*=\s*'[^']+';/", "const CACHE_NAME = '{$cache}';", $sw, 1);
    $assets = ['/', '/index.php', '/manifest.json?v=' . $version, '/app/index.html', $css, $js, '/app/assets/splash/splash-720x1280.png', '/app/assets/splash/splash-1080x1920.png'];
    foreach (pwa_cache_asset_urls($root, $version) as $url) $assets[] = $url;
    $assets = array_values(array_unique(array_filter($assets)));
    $lines = [];
    foreach ($assets as $asset) $lines[] = "  '" . str_replace("'", "\\'", $asset) . "'";
    $shell = "const APP_SHELL = [\n" . implode(",\n", $lines) . "\n];";
    $sw = preg_replace('/const\s+APP_SHELL\s*=\s*\[[\s\S]*?\];/', $shell, $sw, 1);
    file_put_contents($file, $sw, LOCK_EX);
}

function pwa_ensure_icon_fallbacks(string $root): void {
    $dir = $root . '/app/assets/icons';
    $src = $dir . '/icon-192.png';
    if (is_file($src)) {
        foreach (['apple-touch-icon.png','favicon-32.png','favicon-16.png'] as $file) {
            $target = $dir . '/' . $file;
            if (!is_file($target)) @copy($src, $target);
        }
    }
}

function pwa_refresh_icon_cache(string $version, array &$messages = []): void {
    $root = pwa_project_root();
    pwa_ensure_icon_fallbacks($root);
    pwa_refresh_asset_query_versions($root, $version);
    pwa_refresh_manifest($root, $version);
    pwa_refresh_app_index($root, $version);
    pwa_refresh_service_worker($root, $version);
    upsert_setting('pwa_icon_version', $version);
    upsert_setting('pwa_cache_version', $version);
    $messages[] = 'Manifest, iPhone/Android ikon linkleri ve service worker cache sürümü yenilendi: ' . $version;
}


try {
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['act'] ?? '';


    if ($act === 'save_pwa_icons') {
        $messages = [];
        $version = pwa_sanitize_version(post('pwa_icon_version', setting('pwa_icon_version','1.0.80') ?? '1.0.80'));
        if (!empty($_POST['auto_bump'])) {
            $version = date('YmdHis');
        }
        $root = pwa_project_root();
        $iconDir = $root . '/app/assets/icons';
        foreach (pwa_icon_defs() as $field => $def) {
            pwa_upload_icon($field, $iconDir, $def, $messages);
        }
        pwa_refresh_icon_cache($version, $messages);
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }

    if ($act === 'bump_pwa_icon_cache') {
        $messages = [];
        $version = date('YmdHis');
        pwa_refresh_icon_cache($version, $messages);
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }


    if ($act === 'refresh_admin_css_cache') {
        $messages = [];
        admin_refresh_css_cache($messages);
        $messages[] = 'Admin panel için eski beyaz/bozuk CSS görünümünü kırmak üzere sürüm yenilendi.';
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }



    if ($act === 'refresh_all_runtime_cache') {
        $messages = [];
        $version = date('YmdHis');
        try { upsert_setting('admin_css_version', $version); } catch (Throwable $e) {}
        try { upsert_setting('admin_cache_version', $version); } catch (Throwable $e) {}
        try { upsert_setting('pwa_cache_version', $version); } catch (Throwable $e) {}
        try { upsert_setting('asset_cache_version', $version); } catch (Throwable $e) {}
        $root = dirname(__DIR__);
        foreach ([
            __DIR__ . '/assets/admin.css',
            $root . '/app/assets/css/app.css',
            $root . '/app/assets/js/app.js',
            $root . '/service-worker.js',
            $root . '/manifest.json',
            $root . '/app/index.html',
        ] as $file) {
            if (is_file($file)) @touch($file);
        }
        $messages[] = 'Admin + PWA + mobil CSS/JS cache birlikte yenilendi: ' . $version;
        $notice = implode(' ', $messages);
        $page = 'maintenance';
    }

    if ($act === 'refresh_all_icon_assets') {
        $messages = [];
        $version = date('YmdHis');
        pwa_refresh_icon_cache($version, $messages);
        $messages[] = 'Tüm ikon/görsel kütüphanesi yeniden tarandı ve cache kırıldı.';
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }


    if ($act === 'create_db_backup') {
        $backupFile = admin_write_db_backup($pdo);
        $notice = 'Veritabanı yedeği oluşturuldu: ' . basename($backupFile);
        $page = 'maintenance';
    }

    if ($act === 'upload_hatim_svg') {
        $messages = [];
        if (empty($_FILES['hatim_svg'])) throw new RuntimeException('Hatim SVG dosyası seçilmedi.');
        $ext = pwa_validate_asset_upload($_FILES['hatim_svg'], ['svg'], 5 * 1024 * 1024);
        if ($ext !== 'svg') throw new RuntimeException('Hatim ikonu için SVG dosyası gerekli.');
        $root = pwa_project_root();
        $targetDir = $root . '/app/assets/img';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $target = $targetDir . '/kuran-hatim-v1_2_8.svg';
        if (!move_uploaded_file($_FILES['hatim_svg']['tmp_name'], $target)) throw new RuntimeException('Hatim SVG hedef dosyaya yazılamadı.');
        @chmod($target, 0644);
        $version = date('YmdHis');
        pwa_refresh_icon_cache($version, $messages);
        $messages[] = 'Hatim Halkası SVG ikonu güncellendi.';
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }

    if ($act === 'upload_extra_icon_asset') {
        $messages = [];
        if (empty($_FILES['asset_file'])) throw new RuntimeException('Yüklenecek ikon/görsel seçilmedi.');
        $ext = pwa_validate_asset_upload($_FILES['asset_file'], ['svg','png','jpg','jpeg','webp','ico'], 6 * 1024 * 1024);
        $root = pwa_project_root();
        $targetDir = $root . '/app/assets/img';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $safeName = pwa_safe_asset_filename(post('asset_name'), pathinfo((string)$_FILES['asset_file']['name'], PATHINFO_FILENAME), $ext);
        $target = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($_FILES['asset_file']['tmp_name'], $target)) throw new RuntimeException('Dosya app/assets/img klasörüne yazılamadı.');
        @chmod($target, 0644);
        $version = date('YmdHis');
        pwa_refresh_icon_cache($version, $messages);
        $messages[] = 'Yeni ikon/görsel eklendi: ' . $safeName;
        $notice = implode(' ', $messages);
        $page = 'pwa_icons';
    }


    if ($act === 'save_legal_settings') {
        foreach (['privacy_policy_url','terms_url','support_url','data_deletion_url','store_short_description','store_full_description','store_keywords','store_privacy_notes'] as $key) {
            upsert_setting($key, post($key));
        }
        $notice = 'Yasal ve mağaza metinleri kaydedildi.'; $page = 'legal';
    }

    if ($act === 'save_ios_build_settings') {
        foreach (['ios_bundle_id','ios_team_id','ios_app_store_connect_app_id','ios_sku','ios_version_name','ios_build_number','ios_build_output_type','ios_testflight_enabled','ios_build_webhook_url','ios_build_webhook_token','ios_build_callback_token','ios_privacy_policy_url','ios_support_url','ios_marketing_url','ios_build_notes'] as $key) {
            upsert_setting($key, post($key));
        }
        $notice = 'iOS / App Store ayarları kaydedildi.'; $page = 'ios_build';
    }

    if ($act === 'create_ios_build_request') {
        try_sql($pdo, "CREATE TABLE IF NOT EXISTS ios_build_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_uid VARCHAR(80) NOT NULL UNIQUE,
            build_type VARCHAR(40) NOT NULL DEFAULT 'testflight',
            version_name VARCHAR(40) NOT NULL DEFAULT '1.0.39',
            build_number INT NOT NULL DEFAULT 39,
            bundle_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            response_text TEXT NULL,
            artifact_url TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $uid = 'ios_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $buildType = post('build_type', setting('ios_build_output_type','testflight'));
        $versionName = post('version_name', setting('ios_version_name','1.0.39'));
        $buildNumber = max(1, (int)post('build_number', setting('ios_build_number','39')));
        $bundleId = post('bundle_id', setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim'));
        $notes = post('notes', '');
        $status = 'pending';
        $responseText = '';

        $callbackToken = setting('ios_build_callback_token','');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $callbackUrl = $host ? ($scheme . '://' . $host . '/update/ios_build_callback.php') : '';
        $payload = [
            'request_uid' => $uid,
            'build_type' => $buildType,
            'version_name' => $versionName,
            'build_number' => $buildNumber,
            'bundle_id' => $bundleId,
            'notes' => $notes,
            'callback_url' => $callbackUrl,
            'callback_token' => $callbackToken,
            'requested_at' => date('c')
        ];

        $webhookUrl = setting('ios_build_webhook_url','');
        $webhookToken = setting('ios_build_webhook_token','');
        if ($webhookUrl) {
            $headers = "Content-Type: application/json\r\n";
            if ($webhookToken) $headers .= "Authorization: Bearer {$webhookToken}\r\n";
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'timeout' => 8
                ]
            ]);
            $result = @file_get_contents($webhookUrl, false, $context);
            if ($result !== false) {
                $status = 'sent';
                $responseText = mb_substr($result, 0, 2000);
            } else {
                $status = 'pending';
                $responseText = 'Webhook gönderilemedi; talep yerelde bekliyor.';
            }
        }

        $stmt = $pdo->prepare('INSERT INTO ios_build_requests (request_uid, build_type, version_name, build_number, bundle_id, status, notes, response_text, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$uid, $buildType, $versionName, $buildNumber, $bundleId, $status, $notes, $responseText]);
        $notice = $webhookUrl ? 'iOS build talebi oluşturuldu ve webhook denenmiş oldu.' : 'iOS build talebi oluşturuldu. Webhook ayarlanmadığı için yerelde bekliyor.';
        $page = 'ios_build';
    }

    if ($act === 'update_ios_build_result') {
        try_sql($pdo, "CREATE TABLE IF NOT EXISTS ios_build_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_uid VARCHAR(80) NOT NULL UNIQUE,
            build_type VARCHAR(40) NOT NULL DEFAULT 'testflight',
            version_name VARCHAR(40) NOT NULL DEFAULT '1.0.39',
            build_number INT NOT NULL DEFAULT 39,
            bundle_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            response_text TEXT NULL,
            artifact_url TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $id = (int)($_POST['id'] ?? 0);
        $status = post('status', 'pending');
        $artifact = post('artifact_url', '');
        $response = post('response_text', '');
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE ios_build_requests SET status=?, artifact_url=?, response_text=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$status, $artifact, $response, $id]);
            $notice = 'iOS build sonucu güncellendi.'; $page = 'ios_build';
        }
    }

    if ($act === 'save_apk_build_settings') {
        foreach (['apk_build_mode','apk_build_webhook_url','apk_build_webhook_token','apk_build_profile','apk_build_version_name','apk_build_version_code','apk_build_output_type','apk_build_notes','apk_build_callback_token'] as $key) {
            upsert_setting($key, post($key));
        }
        $notice = 'APK build ayarları kaydedildi.'; $page = 'apk_build';
    }


    if ($act === 'update_apk_build_result') {
        try_sql($pdo, "CREATE TABLE IF NOT EXISTS apk_build_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_uid VARCHAR(80) NOT NULL UNIQUE,
            build_type VARCHAR(40) NOT NULL DEFAULT 'debug_apk',
            version_name VARCHAR(40) NOT NULL DEFAULT '1.0.38',
            version_code INT NOT NULL DEFAULT 38,
            package_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            response_text TEXT NULL,
            artifact_url TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $id = (int)($_POST['id'] ?? 0);
        $status = post('status', 'pending');
        $artifact = post('artifact_url', '');
        $response = post('response_text', '');
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE apk_build_requests SET status=?, artifact_url=?, response_text=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$status, $artifact, $response, $id]);
            $notice = 'Build sonucu güncellendi.'; $page = 'apk_build';
        }
    }

    if ($act === 'create_apk_build_request') {
        try_sql($pdo, "CREATE TABLE IF NOT EXISTS apk_build_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_uid VARCHAR(80) NOT NULL UNIQUE,
            build_type VARCHAR(40) NOT NULL DEFAULT 'debug_apk',
            version_name VARCHAR(40) NOT NULL DEFAULT '1.0.37',
            version_code INT NOT NULL DEFAULT 37,
            package_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            response_text TEXT NULL,
            artifact_url TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $uid = 'apk_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $buildType = post('build_type', setting('apk_build_output_type','debug_apk'));
        $versionName = post('version_name', setting('apk_build_version_name','1.0.37'));
        $versionCode = max(1, (int)post('version_code', setting('apk_build_version_code','37')));
        $packageId = post('package_id', setting('android_package_id','com.ilhanbeluk.akillizikirhatim'));
        $notes = post('notes', '');
        $status = 'pending';
        $responseText = '';

        $callbackToken = setting('apk_build_callback_token','');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $callbackUrl = $host ? ($scheme . '://' . $host . '/update/apk_build_callback.php') : '';
        $payload = [
            'request_uid' => $uid,
            'build_type' => $buildType,
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'package_id' => $packageId,
            'notes' => $notes,
            'callback_url' => $callbackUrl,
            'callback_token' => $callbackToken,
            'requested_at' => date('c')
        ];

        $webhookUrl = setting('apk_build_webhook_url','');
        $webhookToken = setting('apk_build_webhook_token','');
        if ($webhookUrl) {
            $headers = "Content-Type: application/json\r\n";
            if ($webhookToken) $headers .= "Authorization: Bearer {$webhookToken}\r\n";
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'timeout' => 8
                ]
            ]);
            $result = @file_get_contents($webhookUrl, false, $context);
            if ($result !== false) {
                $status = 'sent';
                $responseText = mb_substr($result, 0, 2000);
            } else {
                $status = 'pending';
                $responseText = 'Webhook gönderilemedi; talep yerelde bekliyor.';
            }
        }

        $stmt = $pdo->prepare('INSERT INTO apk_build_requests (request_uid, build_type, version_name, version_code, package_id, status, notes, response_text, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$uid, $buildType, $versionName, $versionCode, $packageId, $status, $notes, $responseText]);
        $notice = $webhookUrl ? 'APK build talebi oluşturuldu ve webhook denenmiş oldu.' : 'APK build talebi oluşturuldu. Webhook ayarlanmadığı için yerelde bekliyor.';
        $page = 'apk_build';
    }


    if ($act === 'save_zikir') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [post('title'), post('arabic_text'), post('meaning'), max(1,(int)post('default_target',1000)), isset($_POST['is_favorite']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0, (int)post('sort_order',0)];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE zikirs SET title=?, arabic_text=?, meaning=?, default_target=?, is_favorite=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([...$data, $id]);
            $notice = 'Hazır zikir güncellendi.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO zikirs (title, arabic_text, meaning, default_target, is_favorite, is_active, sort_order, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            $stmt->execute($data);
            $notice = 'Yeni hazır zikir eklendi.';
        }
        $page = 'zikirs';
    }

    if ($act === 'toggle_zikir_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE zikirs SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=NOW() WHERE id=?');
            $stmt->execute([$id]);
            $notice = 'Hazır zikir durumu değiştirildi.';
        }
        $page = 'zikirs';
    }

    if ($act === 'delete_zikir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT title FROM zikirs WHERE id=?');
            $stmt->execute([$id]);
            $title = (string)($stmt->fetchColumn() ?: 'Hazır zikir');
            try_sql($pdo, 'UPDATE zikir_sessions SET zikir_id=NULL WHERE zikir_id=' . (int)$id);
            $stmt = $pdo->prepare('DELETE FROM zikirs WHERE id=?');
            $stmt->execute([$id]);
            $notice = 'Hazır zikir silindi: ' . $title;
        }
        $page = 'zikirs';
    }

    if ($act === 'save_session') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [post('title'), (int)post('zikir_id',0) ?: null, post('subtitle'), max(1,(int)post('target_count',100000)), max(0,(int)post('current_count',0)), max(0,(int)post('participant_count',0)), isset($_POST['is_live']) ? 1 : 0];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE zikir_sessions SET title=?, zikir_id=?, subtitle=?, target_count=?, current_count=?, participant_count=?, is_live=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([...$data, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO zikir_sessions (title, zikir_id, subtitle, target_count, current_count, participant_count, is_live, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            $stmt->execute($data);
        }
        $notice = 'Toplu zikir oturumu kaydedildi.'; $page = 'zikir_sessions';
    }


    if ($act === 'toggle_session_live') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE zikir_sessions SET is_live = 1 - is_live, updated_at=NOW() WHERE id=?')->execute([$id]);
        $notice = 'Toplu zikir oturumu canlı/kapalı durumu değiştirildi.'; $page = 'zikir_sessions';
    }

    if ($act === 'reset_session_counts') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE zikir_sessions SET current_count = 0, participant_count = 0, updated_at=NOW() WHERE id=?')->execute([$id]);
        $notice = 'Toplu zikir oturumu sayacı sıfırlandı.'; $page = 'zikir_sessions';
    }

    if ($act === 'delete_session') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT title FROM zikir_sessions WHERE id=?');
            $stmt->execute([$id]);
            $title = (string)($stmt->fetchColumn() ?: 'Toplu zikir oturumu');
            $pdo->prepare('DELETE FROM zikir_contributions WHERE session_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM zikir_sessions WHERE id=?')->execute([$id]);
            $notice = 'Toplu zikir oturumu silindi: ' . $title;
        }
        $page = 'zikir_sessions';
    }

    if ($act === 'save_dua_circle') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [post('title'), post('subtitle'), max(0,(int)post('participant_count',0)), isset($_POST['is_live']) ? 1 : 0];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE dua_circles SET title=?, subtitle=?, participant_count=?, is_live=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([...$data, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO dua_circles (title, subtitle, participant_count, is_live, created_at) VALUES (?,?,?,?,NOW())');
            $stmt->execute($data);
        }
        $notice = 'Dua halkası kaydedildi.'; $page = 'duas';
    }

    if ($act === 'toggle_dua_request') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE dua_requests SET is_approved = 1 - is_approved, updated_at=NOW() WHERE id=?')->execute([$id]);
        $notice = 'Dua isteği durumu değiştirildi.'; $page = 'duas';
    }

    if ($act === 'toggle_dua_circle_live') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE dua_circles SET is_live = 1 - is_live, updated_at=NOW() WHERE id=?')->execute([$id]);
            $notice = 'Dua halkası canlı/kapalı durumu değiştirildi.';
        }
        $page = 'duas';
    }

    if ($act === 'delete_dua_circle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT title FROM dua_circles WHERE id=?');
            $stmt->execute([$id]);
            $title = (string)($stmt->fetchColumn() ?: 'Dua halkası');
            $pdo->prepare('UPDATE dua_requests SET circle_id=NULL, updated_at=NOW() WHERE circle_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM dua_circles WHERE id=?')->execute([$id]);
            $notice = 'Dua halkası silindi: ' . $title . '. Bağlı dua istekleri korunup halkadan ayrıldı.';
        }
        $page = 'duas';
    }

    if ($act === 'save_dua_request') {
        $id = (int)($_POST['id'] ?? 0);
        $circleId = (int)($_POST['circle_id'] ?? 0) ?: null;
        $nickname = trim(post('nickname', 'Misafir')) ?: 'Misafir';
        $category = trim(post('category', 'Genel')) ?: 'Genel';
        $title = trim(post('title'));
        $body = trim(post('body'));
        $aminCount = max(0, (int)post('amin_count', 0));
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        if ($title === '' || $body === '') {
            $error = 'Dua isteği için başlık ve dua metni zorunludur.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE dua_requests SET circle_id=?, nickname=?, category=?, title=?, body=?, amin_count=?, is_approved=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$circleId, $nickname, $category, $title, $body, $aminCount, $isApproved, $id]);
                $notice = 'Dua isteği güncellendi.';
            } else {
                $clientId = 'admin_' . date('YmdHis');
                $stmt = $pdo->prepare('INSERT INTO dua_requests (circle_id, nickname, client_id, category, title, body, amin_count, is_approved, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
                $stmt->execute([$circleId, $nickname, $clientId, $category, $title, $body, $aminCount, $isApproved]);
                $notice = 'Dua isteği eklendi.';
            }
        }
        $page = 'duas';
    }

    if ($act === 'delete_dua_request') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT title FROM dua_requests WHERE id=?');
            $stmt->execute([$id]);
            $title = (string)($stmt->fetchColumn() ?: 'Dua isteği');
            $pdo->prepare('DELETE FROM dua_joins WHERE request_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM dua_requests WHERE id=?')->execute([$id]);
            $notice = 'Dua isteği silindi: ' . $title;
        }
        $page = 'duas';
    }

    if ($act === 'save_hatim') {
        $id = (int)($_POST['id'] ?? 0);
        $title = post('title'); $description = post('description'); $status = in_array(post('status'), ['active','completed','paused'], true) ? post('status') : 'active';
        if ($id) {
            $pdo->prepare('UPDATE hatims SET title=?, description=?, status=?, updated_at=NOW() WHERE id=?')->execute([$title,$description,$status,$id]);
        } else {
            $pdo->prepare('INSERT INTO hatims (title, description, status, participant_count, created_at) VALUES (?,?,?,0,NOW())')->execute([$title,$description,$status]);
            $id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO hatim_juz (hatim_id, juz_number, status, updated_at) VALUES (?, ?, "empty", NOW())');
            for ($i=1;$i<=30;$i++) { $stmt->execute([$id,$i]); }
        }
        $notice = 'Hatim kaydedildi.'; $page = 'hatims';
    }

    if ($act === 'reset_juz') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE hatim_juz SET status='empty', nickname=NULL, client_id=NULL, reserved_at=NULL, completed_at=NULL, updated_at=NOW() WHERE id=?")->execute([$id]);
        $notice = 'Cüz boşa çıkarıldı.'; $page = 'hatims';
    }


    if ($act === 'toggle_hatim_status') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare('SELECT status FROM hatims WHERE id=?');
        $stmt->execute([$id]);
        $current = (string)$stmt->fetchColumn();
        $next = $current === 'active' ? 'paused' : 'active';
        $pdo->prepare('UPDATE hatims SET status=?, updated_at=NOW() WHERE id=?')->execute([$next, $id]);
        $notice = 'Hatim durumu güncellendi.'; $page = 'hatims';
    }

    if ($act === 'complete_hatim') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE hatims SET status='completed', updated_at=NOW() WHERE id=?")->execute([$id]);
        $notice = 'Hatim tamamlandı olarak işaretlendi.'; $page = 'hatims';
    }

    if ($act === 'delete_hatim') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare('SELECT title FROM hatims WHERE id=?');
        $stmt->execute([$id]);
        $title = (string)$stmt->fetchColumn();
        if ($id > 0 && $title !== '') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM hatim_juz WHERE hatim_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM hatims WHERE id=?')->execute([$id]);
            $pdo->commit();
            $notice = 'Hatim ve bağlı cüz kayıtları silindi: ' . $title;
        } else {
            $error = 'Silinecek hatim bulunamadı.';
        }
        $page = 'hatims';
    }

    if ($act === 'save_daily') {
        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $title = post('title');
        $body = post('body');
        $reference = post('reference_text');
        if ($title === '' || $body === '') {
            throw new RuntimeException('Başlık ve metin boş bırakılamaz.');
        }
        if ($id) {
            $pdo->prepare('UPDATE daily_contents SET title=?, body=?, reference_text=?, is_active=?, updated_at=NOW() WHERE id=?')->execute([$title,$body,$reference,$active,$id]);
            $notice = 'Günlük içerik güncellendi.';
        } else {
            $pdo->prepare('INSERT INTO daily_contents (title, body, reference_text, is_active, created_at) VALUES (?,?,?,?,NOW())')->execute([$title,$body,$reference,$active]);
            $notice = 'Yeni günlük içerik eklendi.';
        }
        $page = 'daily';
    }

    if ($act === 'daily_toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE daily_contents SET is_active = 1 - is_active, updated_at=NOW() WHERE id=?')->execute([$id]);
        $notice = 'Günlük içerik durumu değiştirildi.'; $page = 'daily';
    }

    if ($act === 'daily_make_only_active') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->exec('UPDATE daily_contents SET is_active=0, updated_at=NOW()');
        $pdo->prepare('UPDATE daily_contents SET is_active=1, updated_at=NOW() WHERE id=?')->execute([$id]);
        $pdo->commit();
        $notice = 'Seçilen içerik tek aktif günlük içerik yapıldı.'; $page = 'daily';
    }

    if ($act === 'daily_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM daily_contents WHERE id=?')->execute([$id]);
        $notice = 'Günlük içerik silindi.'; $page = 'daily';
    }

    if ($act === 'save_settings') {
        foreach (['app_name','default_daily_target','community_enabled','offline_mode_enabled','duas_require_approval','public_dua_enabled','app_announcement_enabled','app_announcement_title','app_announcement_body','publisher_name','developer_name','voluntary_support_enabled','support_message','support_amounts','support_general_url','support_25_url','support_50_url','support_100_url','support_250_url','support_custom_url','google_play_support_enabled','google_play_support_url','about_text','android_package_id','apk_google_play_product_25','apk_google_play_product_50','apk_google_play_product_100','apk_google_play_product_250','apk_google_play_product_custom','apk_admin_notes','android_notification_channel_id','android_notification_channel_name','android_notification_permission_note','native_notifications_enabled'] as $key) {
            upsert_setting($key, post($key));
        }
        $notice = 'Ayarlar kaydedildi.'; $page = 'settings';
    }
}
} catch (Throwable $e) { $error = $e->getMessage(); }

function stat_count($sql) { return (int)db()->query($sql)->fetchColumn(); }
$zikirs = $pdo->query('SELECT * FROM zikirs ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Akıllı Zikir & Hatim Admin</title><link rel="stylesheet" href="/admin/assets/admin.css?v=<?=h(admin_css_version())?>"></head>
<body class="admin-premium-shell"><div class="layout"><aside class="sidebar"><div class="brand"><div class="moon">☾</div><div><strong>Akıllı Zikir & Hatim</strong><div class="muted">Yönetim Paneli</div></div></div><nav class="nav categorized-nav">
  <div class="nav-group"><div class="nav-title">Genel Bakış</div><a class="<?=is_active_page('dashboard',$page)?>" href="?page=dashboard">📊 Dashboard</a><a class="<?=is_active_page('reports',$page)?>" href="?page=reports">📈 Raporlar</a></div>
  <div class="nav-group"><div class="nav-title">İçerik Yönetimi</div><a class="<?=is_active_page('zikirs',$page)?>" href="?page=zikirs">☾ Hazır Zikirler</a><a class="<?=is_active_page('daily',$page)?>" href="?page=daily">✨ Günlük İçerik</a></div>
  <div class="nav-group"><div class="nav-title">Topluluk Alanları</div><a class="<?=is_active_page('zikir_sessions',$page)?>" href="?page=zikir_sessions">👥 Toplu Zikir</a><a class="<?=is_active_page('duas',$page)?>" href="?page=duas">♡ Dua Halkası</a><a class="<?=is_active_page('hatims',$page)?>" href="?page=hatims">📖 Hatim</a><a class="<?=is_active_page('users',$page)?>" href="?page=users">👤 Kullanıcılar</a></div>
  <div class="nav-group"><div class="nav-title">Yayın & Mağaza</div><a class="<?=is_active_page('release_checklist',$page)?>" href="?page=release_checklist">✅ Yayın Kontrol</a><a class="<?=is_active_page('legal',$page)?>" href="?page=legal">📜 Yasal Metinler</a><a class="<?=is_active_page('pwa_icons',$page)?>" href="?page=pwa_icons">🖼 İkon & Cache</a><a class="<?=is_active_page('apk_build',$page)?>" href="?page=apk_build">📦 APK Build</a><a class="<?=is_active_page('ios_build',$page)?>" href="?page=ios_build">🍎 iOS Build</a></div>
  <div class="nav-group"><div class="nav-title">Sistem</div><a class="<?=is_active_page('settings',$page)?>" href="?page=settings">⚙ Ayarlar</a><a class="<?=is_active_page('maintenance',$page)?>" href="?page=maintenance">🛡 Bakım & Sağlık</a><a class="<?=is_active_page('updates',$page)?>" href="?page=updates">⬆ Güncelleme</a></div>
  <div class="nav-group quick"><div class="nav-title">Hızlı Erişim</div><a href="/" target="_blank">📱 Mobil Uygulama</a><a href="/admin/logout.php">🚪 Çıkış</a></div>
</nav></aside><main class="main"><div class="top"><div><h1><?=h(match($page){'zikirs'=>'Hazır Zikirler','zikir_sessions'=>'Toplu Zikir Halkaları','duas'=>'Toplu Dua Halkası','hatims'=>'Hatim Halkası','daily'=>'Günlük İçerik','settings'=>'Sistem Ayarları','maintenance'=>'Bakım & Sistem Sağlığı','apk_build'=>'APK Build Merkezi','legal'=>'Yasal Metinler','release_checklist'=>'Yayın Öncesi Kontrol','pwa_icons'=>'İkon & Cache Merkezi','ios_build'=>'iOS / App Store Merkezi','users'=>'Kullanıcı Yönetimi','updates'=>'Güncelleme','reports'=>'Raporlar ve Analiz','dashboard'=>'Dashboard',default=>'Dashboard'})?></h1><p class="muted">Admin: <?=h(admin_user()['name'] ?? '')?></p></div><a class="btn" href="/" target="_blank">Mobil Önizleme</a></div><?php if($notice): ?><div class="alert ok"><?=h($notice)?></div><?php endif; ?><?php if($error): ?><div class="alert err"><?=h($error)?></div><?php endif; ?>
<?php if($page === 'dashboard'): ?>
  <?php
    $liveZikirCount = stat_count('SELECT COUNT(*) FROM zikir_sessions WHERE is_live=1');
    $approvedDuaCount = stat_count('SELECT COUNT(*) FROM dua_requests WHERE is_approved=1');
    $activeHatimCount = stat_count("SELECT COUNT(*) FROM hatims WHERE status='active'");
    $completedJuzCount = stat_count("SELECT COUNT(*) FROM hatim_juz WHERE status='completed'");
    $todayContribDash = stat_count("SELECT COALESCE(SUM(amount),0) FROM zikir_contributions WHERE DATE(created_at)=CURDATE()");
    $todayAminDash = stat_count("SELECT COUNT(*) FROM dua_joins WHERE DATE(created_at)=CURDATE()");
    $apiOk = admin_file_ok('api/app.php');
    $privacyOk = admin_file_ok('legal/privacy.php');
    $termsOk = admin_file_ok('legal/terms.php');
    $supportOk = admin_file_ok('legal/support.php');
    $httpsOk = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $iconRoot = dirname(__DIR__) . '/app/assets/icons/';
    $iconFiles = ['icon-192.png','icon-384.png','icon-512.png','maskable-512.png','apple-touch-icon.png'];
    $missingIcons = [];
    foreach ($iconFiles as $iconFile) { if (!is_file($iconRoot . $iconFile)) $missingIcons[] = $iconFile; }
    $iconsOk = count($missingIcons) === 0;
    $appVersion = admin_effective_app_version($pdo);
    $cacheVersion = setting('pwa_cache_version', $appVersion);
    $androidPackage = setting('android_package_id','com.ilhanbeluk.akillizikirhatim');
    $iosBundle = setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim');
    $releaseScore = 0;
    foreach ([$httpsOk,$apiOk,$privacyOk,$termsOk,$supportOk,$iconsOk,$liveZikirCount>0,$approvedDuaCount>0,$activeHatimCount>0] as $ok) { if ($ok) $releaseScore++; }
    $releaseTotal = 9;
  ?>
  <section class="panel dashboard-premium-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Admin Kontrol Merkezi</span>
        <h2>Akıllı Zikir & Hatim Yönetim Özeti</h2>
        <p class="muted">Zikir, dua, hatim, yayın, cache ve mağaza hazırlığını tek panelden takip et.</p>
      </div>
      <div class="dashboard-premium-score">
        <b>%<?=round($releaseScore / max(1,$releaseTotal) * 100)?></b>
        <span>yayın hazırlığı</span>
      </div>
    </div>
  </section>

  <div class="grid dashboard-top-stats"><div class="stat"><b><?=stat_count('SELECT COUNT(*) FROM zikirs')?></b><span>Zikir</span></div><div class="stat"><b><?=$liveZikirCount?></b><span>Canlı Zikir</span></div><div class="stat"><b><?=$approvedDuaCount?></b><span>Yayındaki Dua</span></div><div class="stat"><b><?=$completedJuzCount?></b><span>Tamamlanan Cüz</span></div></div>

  <section class="panel dashboard-command-panel">
    <div class="section-head-actions">
      <div>
        <span class="badge live">Komuta Özeti</span>
        <h2>Bugünkü Yönetim Odağı</h2>
        <p class="muted">Admin panelde en hızlı kontrol edilmesi gereken alanlar burada toplanır.</p>
      </div>
      <span class="badge info">Premium Dashboard</span>
    </div>
    <div class="dashboard-command-grid">
      <a href="?page=duas"><strong>Dua Yönetimi</strong><span>Yayındaki ve bekleyen duaları kontrol et</span></a>
      <a href="?page=hatims"><strong>Hatim Takibi</strong><span>Cüz dağılımı ve ilerleme durumunu gör</span></a>
      <a href="?page=reports"><strong>Raporlar</strong><span>Zikir, dua, âmin ve hatim hareketini analiz et</span></a>
      <a href="?page=pwa_icons"><strong>İkon & Cache</strong><span>Admin CSS ve PWA cache yenile</span></a>
    </div>
  </section>

  <section class="panel dashboard-release-panel">
    <div class="dashboard-release-head">
      <div>
        <span class="badge info">Yayın Durumu</span>
        <h2>Uygulama Sağlık ve Yayın Özeti</h2>
        <p class="muted">Play Store / PWA / iPhone kurulum öncesi kritik sistem durumlarını tek ekranda takip et.</p>
      </div>
      <div class="release-score"><b><?=$releaseScore?>/<?=$releaseTotal?></b><span>kritik kontrol hazır</span></div>
    </div>
    <div class="dashboard-health-grid">
      <div class="health-card"><strong>SSL / HTTPS</strong><?=admin_yes_no_badge($httpsOk, 'Güvenli', 'SSL Kontrol')?><p><?= $httpsOk ? 'Site güvenli bağlantı üzerinden çalışıyor.' : 'APK ve PWA veri çekimi için HTTPS/SSL aktif olmalı.' ?></p></div>
      <div class="health-card"><strong>API Bootstrap</strong><?=admin_yes_no_badge($apiOk, 'Dosya Var', 'Eksik')?><p><a href="<?=h(admin_status_url('api/app.php?action=bootstrap'))?>" target="_blank">API çıktısını kontrol et</a></p></div>
      <div class="health-card"><strong>PWA / iPhone İkonları</strong><?=admin_yes_no_badge($iconsOk, 'Tamam', 'Eksik')?><p><?= $iconsOk ? 'Android ve iPhone ikon dosyaları hazır.' : 'Eksik: ' . h(implode(', ', $missingIcons)) ?></p></div>
      <div class="health-card"><strong>Yasal Sayfalar</strong><?=admin_yes_no_badge($privacyOk && $termsOk && $supportOk, 'Hazır', 'Kontrol')?><p>Gizlilik, şartlar ve destek linkleri mağaza yayını için hazır olmalı.</p></div>
      <div class="health-card"><strong>Topluluk Verisi</strong><?=admin_yes_no_badge($liveZikirCount>0 && $approvedDuaCount>0 && $activeHatimCount>0, 'Dolu', 'Kontrol')?><p>Zikir: <?=$liveZikirCount?> · Dua: <?=$approvedDuaCount?> · Hatim: <?=$activeHatimCount?></p></div>
      <div class="health-card"><strong>Sürüm / Cache</strong><span class="badge live">v<?=h($appVersion)?></span><p>PWA cache: <?=h($cacheVersion)?> · Paket: <?=h($androidPackage)?></p></div>
    </div>
    <div class="dashboard-quick-actions">
      <a class="btn secondary" href="?page=release_checklist">Yayın Kontrolüne Git</a>
      <a class="btn secondary" href="?page=pwa_icons">İkon & Cache Yönet</a>
      <a class="btn secondary" href="?page=apk_build">APK / AAB Build</a>
      <a class="btn secondary" href="?page=ios_build">iOS / PWA Durumu</a>
    </div>
  </section>

  <div class="grid dashboard-mini-grid"><div class="stat"><b><?=number_format($todayContribDash,0,',','.')?></b><span>Bugünkü Online Zikir</span></div><div class="stat"><b><?=number_format($todayAminDash,0,',','.')?></b><span>Bugünkü Âmin</span></div><div class="stat"><b><?=h($androidPackage)?></b><span>Android Paket ID</span></div><div class="stat"><b><?=h($iosBundle)?></b><span>iOS Bundle ID</span></div></div>

  <section class="panel dashboard-recent-panel"><div class="section-head-actions"><div><h2>Son Hareketler</h2><p class="muted">Zikir ve dua tarafındaki son işlemler.</p></div><span class="badge live">Canlı Akış</span></div><?php $recent=$pdo->query('SELECT zc.*, zs.title AS session_title FROM zikir_contributions zc LEFT JOIN zikir_sessions zs ON zs.id=zc.session_id ORDER BY zc.id DESC LIMIT 10')->fetchAll(); $latestDuas=$pdo->query('SELECT * FROM dua_requests ORDER BY id DESC LIMIT 8')->fetchAll(); ?><div class="table-wrap"><table><tr><th>Tür</th><th>Kişi</th><th>Detay</th><th>Tarih</th></tr><?php foreach($recent as $r): ?><tr><td>Zikir</td><td><?=h($r['nickname'])?></td><td><?=h($r['session_title'])?> / <?=number_format($r['amount'],0,',','.')?> tekrar</td><td><?=h($r['created_at'])?></td></tr><?php endforeach; ?><?php foreach($latestDuas as $d): ?><tr><td>Dua</td><td><?=h($d['nickname'])?></td><td><?=h($d['title'])?> <span class="badge <?=$d['is_approved']?'live':'off'?>"><?=$d['is_approved']?'Yayında':'Onay bekliyor'?></span></td><td><?=h($d['created_at'])?></td></tr><?php endforeach; ?></table></div></section>

<?php elseif($page === 'reports'): ?>
  <?php
    $todayContrib=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM zikir_contributions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $weekContrib=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM zikir_contributions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $monthContrib=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM zikir_contributions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $totalContrib=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM zikir_contributions")->fetchColumn();
    $todayAmin=(int)$pdo->query("SELECT COUNT(*) FROM dua_joins WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $weekAmin=(int)$pdo->query("SELECT COUNT(*) FROM dua_joins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $monthAmin=(int)$pdo->query("SELECT COUNT(*) FROM dua_joins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $todayDua=(int)$pdo->query("SELECT COUNT(*) FROM dua_requests WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $approvedDuaTotal=(int)$pdo->query("SELECT COUNT(*) FROM dua_requests WHERE is_approved=1")->fetchColumn();
    $pendingDuaTotal=(int)$pdo->query("SELECT COUNT(*) FROM dua_requests WHERE is_approved=0")->fetchColumn();
    $activeHatimTotal=(int)$pdo->query("SELECT COUNT(*) FROM hatims WHERE status='active'")->fetchColumn();
    $completedJuzTotal=(int)$pdo->query("SELECT COUNT(*) FROM hatim_juz WHERE status='completed'")->fetchColumn();
    $reservedJuzTotal=(int)$pdo->query("SELECT COUNT(*) FROM hatim_juz WHERE status='reserved'")->fetchColumn();
    $emptyJuzTotal=(int)$pdo->query("SELECT COUNT(*) FROM hatim_juz WHERE status='empty'")->fetchColumn();
    $topContributors=$pdo->query("SELECT COALESCE(NULLIF(nickname,''),'Misafir') AS nickname, COALESCE(SUM(amount),0) AS total_amount, COUNT(*) AS entry_count, MAX(created_at) AS last_at FROM zikir_contributions GROUP BY COALESCE(NULLIF(nickname,''),'Misafir') ORDER BY total_amount DESC LIMIT 10")->fetchAll();
    $topSessions=$pdo->query("SELECT zs.title, zs.current_count, zs.target_count, zs.participant_count, zs.is_live FROM zikir_sessions zs ORDER BY zs.current_count DESC LIMIT 10")->fetchAll();
    $duasByCategory=$pdo->query("SELECT COALESCE(NULLIF(category,''),'Genel') AS category, COUNT(*) AS total_count, COALESCE(SUM(amin_count),0) AS amin_total, SUM(CASE WHEN is_approved=1 THEN 1 ELSE 0 END) AS approved_count FROM dua_requests GROUP BY COALESCE(NULLIF(category,''),'Genel') ORDER BY total_count DESC")->fetchAll();
    $hatimSummary=$pdo->query("SELECT h.title, h.status, SUM(CASE WHEN hj.status='completed' THEN 1 ELSE 0 END) AS completed_count, SUM(CASE WHEN hj.status='reserved' THEN 1 ELSE 0 END) AS reserved_count, SUM(CASE WHEN hj.status='empty' THEN 1 ELSE 0 END) AS empty_count, COUNT(hj.id) AS total_juz FROM hatims h LEFT JOIN hatim_juz hj ON hj.hatim_id=h.id GROUP BY h.id ORDER BY h.id DESC LIMIT 8")->fetchAll();
    $zikirByDay=$pdo->query("SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS total FROM zikir_contributions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
    $aminByDay=$pdo->query("SELECT DATE(created_at) AS d, COUNT(*) AS total FROM dua_joins WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
    $maxDaily=1;
    $lastSeven=[];
    for($i=6;$i>=0;$i--){
      $date=date('Y-m-d', strtotime('-'.$i.' day'));
      $z=(int)($zikirByDay[$date] ?? 0);
      $a=(int)($aminByDay[$date] ?? 0);
      $maxDaily=max($maxDaily,$z,$a);
      $lastSeven[]=['date'=>$date,'label'=>date('d.m', strtotime($date)),'zikir'=>$z,'amin'=>$a];
    }
  ?>
  <section class="panel reports-hero">
    <div class="reports-hero-head">
      <div>
        <span class="badge info">Yönetim Raporu</span>
        <h2>Topluluk ve Kullanım Özeti</h2>
        <p class="muted">Zikir katkıları, dua hareketi, hatim ilerlemesi ve canlı içerik durumunu tek ekranda takip et.</p>
      </div>
      <div class="reports-hero-score"><b><?=number_format($totalContrib,0,',','.')?></b><span>toplam online zikir</span></div>
    </div>
    <div class="reports-kpi-grid">
      <div class="report-kpi"><span>Bugünkü Zikir</span><b><?=number_format($todayContrib,0,',','.')?></b><small>Son 7 gün: <?=number_format($weekContrib,0,',','.')?></small></div>
      <div class="report-kpi"><span>Bugünkü Âmin</span><b><?=number_format($todayAmin,0,',','.')?></b><small>Son 30 gün: <?=number_format($monthAmin,0,',','.')?></small></div>
      <div class="report-kpi"><span>Dua İstekleri</span><b><?=number_format($approvedDuaTotal,0,',','.')?></b><small>Onay bekleyen: <?=number_format($pendingDuaTotal,0,',','.')?></small></div>
      <div class="report-kpi"><span>Hatim Durumu</span><b><?=number_format($completedJuzTotal,0,',','.')?> cüz</b><small>Okunuyor: <?=number_format($reservedJuzTotal,0,',','.')?> · Boş: <?=number_format($emptyJuzTotal,0,',','.')?></small></div>
    </div>
  </section>

  <section class="panel reports-premium-summary">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Hızlı Yönetici Özeti</span>
        <h2>Bugünkü Durum ve Topluluk Nabzı</h2>
        <p class="muted">Admin panel açıldığında en kritik hareketleri tek bakışta görmek için hazırlanmış premium özet alanı.</p>
      </div>
      <span class="badge live">Canlı özet</span>
    </div>
    <div class="reports-summary-grid">
      <div class="summary-tile gold"><small>Bugünkü Toplam Etkileşim</small><b><?=number_format($todayContrib + $todayAmin + $todayDua,0,',','.')?></b><span>Zikir + Âmin + Dua</span></div>
      <div class="summary-tile"><small>30 Günlük Zikir</small><b><?=number_format($monthContrib,0,',','.')?></b><span>Topluluk katkısı</span></div>
      <div class="summary-tile"><small>Onay Bekleyen Dua</small><b><?=number_format($pendingDuaTotal,0,',','.')?></b><span>Kontrol gerektiren kayıt</span></div>
      <div class="summary-tile"><small>Hatim Okunuyor</small><b><?=number_format($reservedJuzTotal,0,',','.')?></b><span>Alınmış cüz</span></div>
    </div>
  </section>

  <section class="panel reports-chart-panel">
    <div class="section-head-actions"><div><h2>Son 7 Gün Hareketi</h2><p class="muted">Zikir ve Âmin hareketini hızlı kontrol için özet grafik.</p></div><span class="badge live">7 gün</span></div>
    <div class="report-bars">
      <?php foreach($lastSeven as $day): $zp=max(3,round($day['zikir']/$maxDaily*100)); $ap=max(3,round($day['amin']/$maxDaily*100)); ?>
        <div class="report-day">
          <div class="bar-wrap"><span class="bar zikir" style="height:<?=$zp?>%"></span><span class="bar amin" style="height:<?=$ap?>%"></span></div>
          <strong><?=h($day['label'])?></strong>
          <small><?=number_format($day['zikir'],0,',','.')?> zikir · <?=number_format($day['amin'],0,',','.')?> âmin</small>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="report-legend"><span><i class="zikir-dot"></i>Zikir</span><span><i class="amin-dot"></i>Âmin</span></div>
  </section>

  <div class="reports-grid-2">
    <section class="panel"><div class="section-head-actions"><h2>En Çok Zikir Katkısı Yapanlar</h2><span class="badge info">İlk 10</span></div><div class="table-wrap"><table class="report-table"><tr><th>Kişi</th><th>Toplam</th><th>Katılım</th><th>Son İşlem</th></tr><?php foreach($topContributors as $r): ?><tr><td><strong><?=h($r['nickname'] ?: 'Misafir')?></strong></td><td><?=number_format($r['total_amount'],0,',','.')?></td><td><?=number_format($r['entry_count'],0,',','.')?> işlem</td><td><?=h($r['last_at'] ? date('d.m H:i', strtotime($r['last_at'])) : '-')?></td></tr><?php endforeach; ?></table></div></section>

    <section class="panel"><div class="section-head-actions"><h2>Toplu Zikir Oturumu Özeti</h2><span class="badge live"><?=number_format($monthContrib,0,',','.')?> / 30 gün</span></div><div class="table-wrap"><table class="report-table"><tr><th>Oturum</th><th>İlerleme</th><th>Katılımcı</th><th>Durum</th></tr><?php foreach($topSessions as $r): $p=$r['target_count']?min(100,round($r['current_count']/$r['target_count']*100)):0; ?><tr><td><?=h($r['title'])?></td><td><div class="mini-progress"><span style="width:<?=$p?>%"></span></div><small><?=number_format($r['current_count'],0,',','.')?> / <?=number_format($r['target_count'],0,',','.')?> · %<?=$p?></small></td><td><?=number_format($r['participant_count'],0,',','.')?></td><td><span class="badge <?=$r['is_live']?'live':'off'?>"><?=$r['is_live']?'Canlı':'Kapalı'?></span></td></tr><?php endforeach; ?></table></div></section>
  </div>

  <div class="reports-grid-2">
    <section class="panel"><div class="section-head-actions"><h2>Dua Kategorileri</h2><span class="badge wait">Bugün <?=number_format($todayDua,0,',','.')?> yeni</span></div><div class="table-wrap"><table class="report-table"><tr><th>Kategori</th><th>Dua</th><th>Yayında</th><th>Âmin</th></tr><?php foreach($duasByCategory as $r): ?><tr><td><span class="badge"><?=h($r['category'])?></span></td><td><?=number_format($r['total_count'],0,',','.')?></td><td><?=number_format($r['approved_count'],0,',','.')?></td><td><?=number_format($r['amin_total'],0,',','.')?></td></tr><?php endforeach; ?></table></div></section>

    <section class="panel"><div class="section-head-actions"><h2>Hatim Özetleri</h2><span class="badge live"><?=number_format($activeHatimTotal,0,',','.')?> aktif</span></div><div class="table-wrap"><table class="report-table"><tr><th>Hatim</th><th>Durum</th><th>İlerleme</th><th>Okunuyor</th><th>Boş</th></tr><?php foreach($hatimSummary as $r): $statusLabel=match((string)$r['status']){'active'=>'Aktif','paused'=>'Beklemede','completed'=>'Tamamlandı',default=>(string)$r['status']}; $total=max(1,(int)$r['total_juz']); $hp=round(((int)$r['completed_count'])/$total*100); ?><tr><td><?=h($r['title'])?></td><td><span class="badge <?=($r['status']==='active'?'live':($r['status']==='completed'?'info':'wait'))?>"><?=h($statusLabel)?></span></td><td><div class="mini-progress gold"><span style="width:<?=$hp?>%"></span></div><small><?=number_format($r['completed_count'],0,',','.')?> / <?=$total?> · %<?=$hp?></small></td><td><?=number_format($r['reserved_count'],0,',','.')?></td><td><?=number_format($r['empty_count'],0,',','.')?></td></tr><?php endforeach; ?></table></div></section>
  </div>

<?php elseif($page === 'users'): ?>
  <?php
    $userMap = [];
    $ensureCommunityUser = function(string $nickname) use (&$userMap): string {
        $nickname = trim($nickname) !== '' ? trim($nickname) : 'Misafir';
        $key = function_exists('mb_strtolower') ? mb_strtolower($nickname, 'UTF-8') : strtolower($nickname);
        if (!isset($userMap[$key])) {
            $userMap[$key] = [
                'nickname' => $nickname,
                'zikir_total' => 0,
                'zikir_entries' => 0,
                'dua_requests' => 0,
                'dua_approved' => 0,
                'dua_amins' => 0,
                'amin_given' => 0,
                'hatim_reserved' => 0,
                'hatim_completed' => 0,
                'last_at' => null,
                'score' => 0,
            ];
        }
        return $key;
    };
    $touchLast = function(string $key, $date) use (&$userMap): void {
        if (!$date) return;
        $ts = strtotime((string)$date);
        if (!$ts) return;
        if (empty($userMap[$key]['last_at']) || $ts > strtotime((string)$userMap[$key]['last_at'])) $userMap[$key]['last_at'] = date('Y-m-d H:i:s', $ts);
    };

    try {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(nickname,''),'Misafir') AS nickname, COALESCE(SUM(amount),0) AS total_amount, COUNT(*) AS entry_count, MAX(created_at) AS last_at FROM zikir_contributions GROUP BY COALESCE(NULLIF(nickname,''),'Misafir')")->fetchAll();
        foreach($rows as $r){ $k=$ensureCommunityUser((string)$r['nickname']); $userMap[$k]['zikir_total'] += (int)$r['total_amount']; $userMap[$k]['zikir_entries'] += (int)$r['entry_count']; $touchLast($k, $r['last_at'] ?? null); }
    } catch (Throwable $e) {}
    try {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(nickname,''),'Misafir') AS nickname, COUNT(*) AS request_count, SUM(CASE WHEN is_approved=1 THEN 1 ELSE 0 END) AS approved_count, COALESCE(SUM(amin_count),0) AS amin_total, MAX(created_at) AS last_at FROM dua_requests GROUP BY COALESCE(NULLIF(nickname,''),'Misafir')")->fetchAll();
        foreach($rows as $r){ $k=$ensureCommunityUser((string)$r['nickname']); $userMap[$k]['dua_requests'] += (int)$r['request_count']; $userMap[$k]['dua_approved'] += (int)$r['approved_count']; $userMap[$k]['dua_amins'] += (int)$r['amin_total']; $touchLast($k, $r['last_at'] ?? null); }
    } catch (Throwable $e) {}
    try {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(nickname,''),'Misafir') AS nickname, COUNT(*) AS amin_count, MAX(created_at) AS last_at FROM dua_joins GROUP BY COALESCE(NULLIF(nickname,''),'Misafir')")->fetchAll();
        foreach($rows as $r){ $k=$ensureCommunityUser((string)$r['nickname']); $userMap[$k]['amin_given'] += (int)$r['amin_count']; $touchLast($k, $r['last_at'] ?? null); }
    } catch (Throwable $e) {}
    try {
        $rows = $pdo->query("SELECT COALESCE(NULLIF(nickname,''),'Misafir') AS nickname, SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) AS reserved_count, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count, MAX(COALESCE(completed_at,reserved_at,updated_at)) AS last_at FROM hatim_juz WHERE nickname IS NOT NULL AND nickname<>'' GROUP BY COALESCE(NULLIF(nickname,''),'Misafir')")->fetchAll();
        foreach($rows as $r){ $k=$ensureCommunityUser((string)$r['nickname']); $userMap[$k]['hatim_reserved'] += (int)$r['reserved_count']; $userMap[$k]['hatim_completed'] += (int)$r['completed_count']; $touchLast($k, $r['last_at'] ?? null); }
    } catch (Throwable $e) {}

    foreach($userMap as $k => $u) {
        $userMap[$k]['score'] = (int)$u['zikir_total'] + ((int)$u['amin_given'] * 10) + ((int)$u['dua_requests'] * 25) + ((int)$u['hatim_reserved'] * 30) + ((int)$u['hatim_completed'] * 120);
    }
    $communityUsers = array_values($userMap);
    usort($communityUsers, fn($a,$b) => [$b['score'], strtotime((string)($b['last_at'] ?? '1970-01-01'))] <=> [$a['score'], strtotime((string)($a['last_at'] ?? '1970-01-01'))]);

    $scope = $_GET['scope'] ?? 'all';
    $userSearch = trim((string)($_GET['q'] ?? ''));
    $filteredUsers = array_values(array_filter($communityUsers, function($u) use ($scope, $userSearch) {
        if ($userSearch !== '') {
            $haystack = (string)($u['nickname'] ?? '');
            $match = function_exists('mb_stripos') ? (mb_stripos($haystack, $userSearch, 0, 'UTF-8') !== false) : (stripos($haystack, $userSearch) !== false);
            if (!$match) return false;
        }
        return match($scope) {
            'zikir' => (int)$u['zikir_total'] > 0,
            'dua' => (int)$u['dua_requests'] > 0 || (int)$u['amin_given'] > 0,
            'hatim' => (int)$u['hatim_reserved'] > 0 || (int)$u['hatim_completed'] > 0,
            'active7' => !empty($u['last_at']) && strtotime((string)$u['last_at']) >= strtotime('-7 days'),
            default => true,
        };
    }));
    $totalUsers = count($communityUsers);
    $activeSeven = count(array_filter($communityUsers, fn($u) => !empty($u['last_at']) && strtotime((string)$u['last_at']) >= strtotime('-7 days')));
    $zikirUsers = count(array_filter($communityUsers, fn($u) => (int)$u['zikir_total'] > 0));
    $duaUsers = count(array_filter($communityUsers, fn($u) => (int)$u['dua_requests'] > 0 || (int)$u['amin_given'] > 0));
    $hatimUsers = count(array_filter($communityUsers, fn($u) => (int)$u['hatim_reserved'] > 0 || (int)$u['hatim_completed'] > 0));
    $topUser = $communityUsers[0] ?? null;
    $scopeLink = fn($s) => '?page=users&scope=' . urlencode($s) . ($userSearch !== '' ? '&q=' . urlencode($userSearch) : '');
  ?>
  <section class="panel users-admin-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Topluluk Yönetimi</span>
        <h2>Kullanıcı ve Katılımcı Merkezi</h2>
        <p class="muted">Mobil uygulamadaki zikir, dua, âmin ve hatim katılımlarını rumuz bazlı tek ekranda takip et.</p>
      </div>
      <div class="users-hero-score"><b><?=number_format($totalUsers,0,',','.')?></b><span>toplam rumuz</span></div>
    </div>
    <div class="users-kpi-grid">
      <div><b><?=number_format($activeSeven,0,',','.')?></b><span>Son 7 Gün Aktif</span></div>
      <div><b><?=number_format($zikirUsers,0,',','.')?></b><span>Zikir Katılımcısı</span></div>
      <div><b><?=number_format($duaUsers,0,',','.')?></b><span>Dua / Âmin Katılımcısı</span></div>
      <div><b><?=number_format($hatimUsers,0,',','.')?></b><span>Hatim Katılımcısı</span></div>
    </div>
  </section>

  <section class="panel users-filter-panel">
    <div class="section-head-actions">
      <div><h2>Katılımcı Listesi</h2><p class="muted">Filtreleyerek en aktif kişileri, dua/hatim katılımcılarını ve son 7 gün aktiflerini görebilirsin.</p></div>
      <?php if($topUser): ?><span class="badge live">En aktif: <?=h($topUser['nickname'])?></span><?php endif; ?>
    </div>
    <div class="users-filter-tabs">
      <a class="<?=$scope==='all'?'active':''?>" href="<?=$scopeLink('all')?>">Tümü</a>
      <a class="<?=$scope==='active7'?'active':''?>" href="<?=$scopeLink('active7')?>">Son 7 Gün</a>
      <a class="<?=$scope==='zikir'?'active':''?>" href="<?=$scopeLink('zikir')?>">Zikir</a>
      <a class="<?=$scope==='dua'?'active':''?>" href="<?=$scopeLink('dua')?>">Dua / Âmin</a>
      <a class="<?=$scope==='hatim'?'active':''?>" href="<?=$scopeLink('hatim')?>">Hatim</a>
    </div>
    <form method="get" class="users-search-form">
      <input type="hidden" name="page" value="users">
      <input type="hidden" name="scope" value="<?=h($scope)?>">
      <input class="field" name="q" value="<?=h($userSearch)?>" placeholder="Rumuz ara...">
      <button class="btn">Ara</button>
      <?php if($userSearch !== ''): ?><a class="btn secondary" href="?page=users&scope=<?=h($scope)?>">Temizle</a><?php endif; ?>
    </form>
    <?php if(!$filteredUsers): ?>
      <div class="empty-state">Bu filtre/arama sonucunda katılımcı bulunamadı.</div>
    <?php else: ?>
      <div class="users-card-grid">
        <?php foreach(array_slice($filteredUsers, 0, 60) as $u):
          $lastAt = !empty($u['last_at']) ? date('d.m.Y H:i', strtotime((string)$u['last_at'])) : '-';
          $isActive = !empty($u['last_at']) && strtotime((string)$u['last_at']) >= strtotime('-7 days');
          $role = ((int)$u['hatim_completed'] > 0) ? 'Hatim Tamamlayan' : (((int)$u['zikir_total'] > 0) ? 'Zikir Katılımcısı' : (((int)$u['dua_requests'] + (int)$u['amin_given'] > 0) ? 'Dua Katılımcısı' : 'Katılımcı'));
          $initial = function_exists('mb_substr') ? mb_substr((string)$u['nickname'],0,1,'UTF-8') : substr((string)$u['nickname'],0,1);
        ?>
          <article class="user-community-card">
            <div class="user-card-head">
              <div class="user-avatar"><?=h($initial)?></div>
              <div><h3><?=h($u['nickname'])?></h3><p><?=h($role)?></p></div>
              <span class="badge <?=$isActive?'live':'wait'?>"><?=$isActive?'Aktif':'Pasif'?></span>
            </div>
            <div class="user-metric-grid">
              <span><b><?=number_format((int)$u['zikir_total'],0,',','.')?></b><small>Zikir</small></span>
              <span><b><?=number_format((int)$u['dua_requests'],0,',','.')?></b><small>Dua</small></span>
              <span><b><?=number_format((int)$u['amin_given'],0,',','.')?></b><small>Âmin</small></span>
              <span><b><?=number_format((int)$u['hatim_completed'],0,',','.')?></b><small>Cüz</small></span>
            </div>
            <div class="user-card-foot"><span>Son hareket <b><?=h($lastAt)?></b></span><span>Puan <b><?=number_format((int)$u['score'],0,',','.')?></b></span></div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel users-table-panel">
    <div class="section-head-actions"><h2>Detaylı Katılımcı Tablosu</h2><span class="badge info"><?=number_format(count($filteredUsers),0,',','.')?> kayıt</span></div>
    <div class="table-wrap"><table class="users-table"><tr><th>Rumuz</th><th>Zikir</th><th>Dua</th><th>Âmin</th><th>Hatim</th><th>Son Hareket</th><th>Durum</th></tr>
      <?php foreach($filteredUsers as $u): $isActive=!empty($u['last_at']) && strtotime((string)$u['last_at']) >= strtotime('-7 days'); ?>
        <tr>
          <td><strong><?=h($u['nickname'])?></strong><small>Puan: <?=number_format((int)$u['score'],0,',','.')?></small></td>
          <td><?=number_format((int)$u['zikir_total'],0,',','.')?><small><?=number_format((int)$u['zikir_entries'],0,',','.')?> işlem</small></td>
          <td><?=number_format((int)$u['dua_requests'],0,',','.')?><small><?=number_format((int)$u['dua_approved'],0,',','.')?> yayında</small></td>
          <td><?=number_format((int)$u['amin_given'],0,',','.')?><small>Alınan: <?=number_format((int)$u['dua_amins'],0,',','.')?></small></td>
          <td><?=number_format((int)$u['hatim_completed'],0,',','.')?><small>Alınan: <?=number_format((int)$u['hatim_reserved'],0,',','.')?></small></td>
          <td><?=h(!empty($u['last_at']) ? date('d.m.Y H:i', strtotime((string)$u['last_at'])) : '-')?></td>
          <td><span class="badge <?=$isActive?'live':'wait'?>"><?=$isActive?'Aktif':'Beklemede'?></span></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  </section>

<?php elseif($page === 'zikirs'):
  $editZikirId=(int)($_GET['edit_zikir'] ?? 0);
  $editZikir=null;
  if ($editZikirId) {
      $stmt=$pdo->prepare('SELECT * FROM zikirs WHERE id=?');
      $stmt->execute([$editZikirId]);
      $editZikir=$stmt->fetch();
  }
  $totalZikir=count($zikirs);
  $activeZikir=count(array_filter($zikirs, fn($z)=>(int)$z['is_active']===1));
  $favZikir=count(array_filter($zikirs, fn($z)=>(int)$z['is_favorite']===1));
  $targetSum=array_sum(array_map(fn($z)=>(int)$z['default_target'], $zikirs));
?>
  <section class="panel zikir-admin-hero zikir-premium-hero">
    <div class="section-head-actions"><div><span class="eyebrow">İçerik Yönetimi</span><h2>Hazır Zikir Kontrol Merkezi</h2><p class="muted">Mobil uygulamadaki hazır zikir listesini buradan yönet. Düzenleme ve silme işlemleri sadece admin panel içindir.</p></div><a class="btn secondary" href="?page=zikirs">Yeni Zikir Ekle</a></div>
    <div class="zikir-admin-stats"><div><b><?=number_format($totalZikir,0,',','.')?></b><span>Toplam Zikir</span></div><div><b><?=number_format($activeZikir,0,',','.')?></b><span>Aktif</span></div><div><b><?=number_format($favZikir,0,',','.')?></b><span>Favori</span></div><div><b><?=number_format($targetSum,0,',','.')?></b><span>Toplam Hedef</span></div></div>
  </section>
  <section class="panel zikir-editor-panel zikir-premium-editor"><div class="section-head-actions"><h2><?=$editZikir?'Hazır Zikri Düzenle':'Yeni Hazır Zikir Ekle'?></h2><?php if($editZikir): ?><a class="btn secondary" href="?page=zikirs">Vazgeç / Yeni Ekle</a><?php endif; ?></div><form method="post" class="form-grid"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="save_zikir"><input type="hidden" name="id" value="<?=h($editZikir['id'] ?? '')?>"><div><label>Zikir adı</label><input class="field" name="title" value="<?=h($editZikir['title'] ?? '')?>" placeholder="Örn. Sübhânallah" required></div><div><label>Arapça metin</label><input class="field arabic-field" name="arabic_text" value="<?=h($editZikir['arabic_text'] ?? '')?>" placeholder="سُبْحَانَ اللّٰهِ"></div><div><label>Varsayılan hedef</label><input class="field" name="default_target" type="number" min="1" value="<?=h($editZikir['default_target'] ?? 1000)?>"></div><div><label>Sıralama</label><input class="field" name="sort_order" type="number" value="<?=h($editZikir['sort_order'] ?? 100)?>"></div><div style="grid-column:1/-1"><label>Anlam / açıklama</label><textarea class="field" name="meaning" placeholder="Kısa açıklama veya anlam bilgisi"><?=h($editZikir['meaning'] ?? '')?></textarea></div><label class="admin-check"><input type="checkbox" name="is_favorite" <?=((int)($editZikir['is_favorite'] ?? 1)===1)?'checked':''?>> Favorilerde göster</label><label class="admin-check"><input type="checkbox" name="is_active" <?=((int)($editZikir['is_active'] ?? 1)===1)?'checked':''?>> Aktif yayınla</label><div class="zikir-form-actions"><button class="btn"> <?=$editZikir?'Değişiklikleri Kaydet':'Hazır Zikri Kaydet'?> </button><?php if($editZikir): ?><a class="btn secondary" href="?page=zikirs">Yeni kayıt moduna dön</a><?php endif; ?></div></form></section>
  <section class="panel zikir-list-panel zikir-premium-list"><div class="section-head-actions"><div><h2>Hazır Zikirler</h2><p class="muted">Aktif kayıtlar mobil uygulamada görünür. Favori kayıtlar ana sayfa ve hızlı seçimlerde öne çıkar.</p></div><span class="badge info"><?=number_format($totalZikir,0,',','.')?> kayıt</span></div><?php if(!$zikirs): ?><div class="empty-state">Henüz hazır zikir kaydı yok.</div><?php else: ?><div class="zikir-admin-grid"><?php foreach($zikirs as $z): ?><article class="zikir-admin-card <?=$z['is_active']?'is-live':'is-off'?>"><div class="zikir-card-top"><div><span class="zikir-card-id">#<?=$z['id']?></span><h3><?=h($z['title'])?></h3></div><div class="zikir-card-badges"><span class="badge <?=$z['is_active']?'live':'off'?>"><?=$z['is_active']?'Aktif':'Pasif'?></span><?php if($z['is_favorite']): ?><span class="badge wait">Favori</span><?php endif; ?></div></div><div class="zikir-arabic-preview"><?=h($z['arabic_text'] ?: '—')?></div><p class="zikir-meaning"><?=h($z['meaning'] ?: 'Açıklama girilmemiş.')?></p><div class="zikir-meta"><span>Hedef <b><?=number_format((int)$z['default_target'],0,',','.')?></b></span><span>Sıra <b><?=number_format((int)$z['sort_order'],0,',','.')?></b></span></div><div class="inline-actions zikir-actions"><a class="btn secondary" href="?page=zikirs&edit_zikir=<?=$z['id']?>">Düzenle</a><form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="toggle_zikir_active"><input type="hidden" name="id" value="<?=$z['id']?>"><button class="btn secondary" type="submit"><?=$z['is_active']?'Pasif Yap':'Aktif Yap'?></button></form><form method="post" data-admin-confirm="<?=h($z['title'])?> kaydı silinsin mi? Bu işlem geri alınamaz. Bu zikirle bağlı toplu zikir oturumlarında zikir seçimi boşaltılır."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="delete_zikir"><input type="hidden" name="id" value="<?=$z['id']?>"><button class="btn danger" type="submit">Sil</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif($page === 'zikir_sessions'):
  $sessions=$pdo->query("SELECT zs.*, z.title zikir_title, COALESCE(c.contribution_total,0) contribution_total, COALESCE(c.contribution_rows,0) contribution_rows FROM zikir_sessions zs LEFT JOIN zikirs z ON z.id=zs.zikir_id LEFT JOIN (SELECT session_id, SUM(amount) contribution_total, COUNT(*) contribution_rows FROM zikir_contributions GROUP BY session_id) c ON c.session_id=zs.id ORDER BY zs.id DESC")->fetchAll();
  $editSessionId=(int)($_GET['edit_session'] ?? 0);
  $editSession=null;
  if ($editSessionId) {
      $stmt=$pdo->prepare('SELECT * FROM zikir_sessions WHERE id=?');
      $stmt->execute([$editSessionId]);
      $editSession=$stmt->fetch();
  }
  $totalSessions=count($sessions);
  $liveSessions=count(array_filter($sessions, fn($s)=>(int)$s['is_live']===1));
  $closedSessions=$totalSessions-$liveSessions;
  $totalSessionCount=array_sum(array_map(fn($s)=>(int)$s['current_count'], $sessions));
  $totalSessionParticipants=array_sum(array_map(fn($s)=>(int)$s['participant_count'], $sessions));
  $formSessionTitle=$editSession ? (string)$editSession['title'] : 'Sübhânallah Zikri';
  $formSessionZikirId=$editSession ? (int)($editSession['zikir_id'] ?? 0) : ((int)($zikirs[0]['id'] ?? 0));
  $formSessionTarget=$editSession ? (int)$editSession['target_count'] : 100000;
  $formSessionCurrent=$editSession ? (int)$editSession['current_count'] : 0;
  $formSessionParticipants=$editSession ? (int)$editSession['participant_count'] : 0;
  $formSessionSubtitle=$editSession ? (string)($editSession['subtitle'] ?? '') : 'Beraber zikrediyor, bereketi paylaşıyoruz.';
  $formSessionLive=$editSession ? (int)$editSession['is_live'] : 1;
?>
  <section class="panel session-admin-hero session-premium-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Premium Toplu Zikir Yönetimi</span>
        <h2>Toplu Zikir Kontrol Merkezi</h2>
        <p class="muted">Canlı zikir halkalarını, hedefleri, katılımcı sayılarını ve güvenli düzenleme/silme işlemlerini tek ekrandan yönet.</p>
      </div>
      <div class="inline-actions"><?php if($editSession): ?><a class="btn secondary" href="?page=zikir_sessions">Yeni Oturum Modu</a><?php endif; ?></div>
    </div>
    <div class="session-admin-stats">
      <div><b><?=number_format($totalSessions,0,',','.')?></b><span>Toplam Oturum</span></div>
      <div><b><?=number_format($liveSessions,0,',','.')?></b><span>Canlı</span></div>
      <div><b><?=number_format($closedSessions,0,',','.')?></b><span>Kapalı</span></div>
      <div><b><?=number_format($totalSessionCount,0,',','.')?></b><span>Toplam Zikir</span></div>
    </div>
  </section>

  <section class="panel session-editor-panel session-premium-editor">
    <div class="section-head-actions">
      <div>
        <h2><?=$editSession ? 'Toplu Zikir Oturumunu Düzenle' : 'Yeni Toplu Zikir Oturumu Aç'?></h2>
        <p class="muted"><?=$editSession ? 'Seçili oturumun başlık, hedef, sayaç ve yayın durumunu güncelle.' : 'Mobil uygulamada yayınlanacak yeni bir toplu zikir halkası oluştur.'?></p>
      </div>
      <?php if($editSession): ?><span class="badge info">Düzenlenen ID: <?=number_format((int)$editSession['id'],0,',','.')?></span><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_session">
      <?php if($editSession): ?><input type="hidden" name="id" value="<?=(int)$editSession['id']?>"><?php endif; ?>
      <div><label>Başlık</label><input class="field" name="title" value="<?=h($formSessionTitle)?>" required></div>
      <div><label>Zikir</label><select class="field" name="zikir_id"><option value="">Zikir seçilmedi</option><?php foreach($zikirs as $z): ?><option value="<?=$z['id']?>" <?=((int)$z['id']===$formSessionZikirId)?'selected':''?>><?=h($z['title'])?></option><?php endforeach; ?></select></div>
      <div><label>Hedef sayı</label><input class="field" name="target_count" type="number" min="1" value="<?=h($formSessionTarget)?>"></div>
      <div><label>Mevcut sayı</label><input class="field" name="current_count" type="number" min="0" value="<?=h($formSessionCurrent)?>"></div>
      <div><label>Katılımcı</label><input class="field" name="participant_count" type="number" min="0" value="<?=h($formSessionParticipants)?>"></div>
      <div><label>Alt açıklama</label><input class="field" name="subtitle" value="<?=h($formSessionSubtitle)?>"></div>
      <label class="admin-check"><input type="checkbox" name="is_live" <?=$formSessionLive?'checked':''?>> Canlı yayınla</label>
      <div class="session-form-actions"><button class="btn"> <?=$editSession ? 'Oturumu Güncelle' : 'Oturumu Kaydet'?> </button><?php if($editSession): ?><a class="btn secondary" href="?page=zikir_sessions">Vazgeç / Yeni Oturum</a><?php endif; ?></div>
    </form>
  </section>

  <section class="panel session-list-panel session-premium-list">
    <div class="section-head-actions"><div><h2>Toplu Zikir Oturumları</h2><p class="muted">Canlı oturumlar mobil uygulamada görünür. Silme işlemi oturuma bağlı katkı kayıtlarını da temizler.</p></div><span class="badge live"><?=number_format($totalSessionParticipants,0,',','.')?> toplam katılımcı</span></div>
    <?php if(!$sessions): ?><div class="empty-state">Henüz toplu zikir oturumu yok.</div><?php else: ?><div class="session-admin-grid"><?php foreach($sessions as $s): $target=max(1,(int)$s['target_count']); $current=max(0,(int)$s['current_count']); $p=min(100,round($current/$target*100)); $missing=max(0,$target-$current); ?><article class="session-admin-card <?=$s['is_live']?'is-live':'is-off'?>"><div class="session-card-head"><div><span class="session-card-id">#<?=$s['id']?></span><h3><?=h($s['title'])?></h3><p><?=h($s['subtitle'] ?: 'Alt açıklama girilmemiş.')?></p></div><span class="badge <?=$s['is_live']?'live':'off'?>"><?=$s['is_live']?'Canlı':'Kapalı'?></span></div><div class="session-zikir-line"><span>Zikir</span><strong><?=h($s['zikir_title'] ?: 'Zikir seçilmedi')?></strong></div><div class="session-progress"><span style="width:<?=$p?>%"></span></div><div class="session-admin-stats compact"><div><b><?=number_format($current,0,',','.')?></b><span>Mevcut</span></div><div><b><?=number_format($target,0,',','.')?></b><span>Hedef</span></div><div><b>%<?=$p?></b><span>İlerleme</span></div><div><b><?=number_format((int)$s['participant_count'],0,',','.')?></b><span>Katılımcı</span></div></div><div class="session-meta"><span>Kalan <b><?=number_format($missing,0,',','.')?></b></span><span>Katkı kaydı <b><?=number_format((int)$s['contribution_rows'],0,',','.')?></b></span><span>Katkı toplamı <b><?=number_format((int)$s['contribution_total'],0,',','.')?></b></span></div><div class="inline-actions session-actions"><a class="btn secondary" href="?page=zikir_sessions&edit_session=<?=$s['id']?>">Düzenle</a><form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="toggle_session_live"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn secondary" type="submit"><?=$s['is_live']?'Yayından Al':'Yayına Al'?></button></form><form method="post" data-admin-confirm="Bu oturumun sayaç ve katılımcı sayısı sıfırlansın mı?"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="reset_session_counts"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn secondary" type="submit">Sayaçları Sıfırla</button></form><form method="post" data-admin-confirm="<?=h($s['title'])?> oturumu silinsin mi? Bu işlem oturuma bağlı katkı kayıtlarını da siler ve geri alınamaz."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="delete_session"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn danger" type="submit">Sil</button></form></div></article><?php endforeach; ?></div><?php endif; ?>
  </section>
<?php elseif($page === 'duas'):
  $circles=$pdo->query('SELECT dc.*, COUNT(dr.id) AS request_count, COALESCE(SUM(dr.amin_count),0) AS amin_total, SUM(CASE WHEN dr.is_approved=1 THEN 1 ELSE 0 END) AS approved_count FROM dua_circles dc LEFT JOIN dua_requests dr ON dr.circle_id=dc.id GROUP BY dc.id ORDER BY dc.id DESC')->fetchAll();
  $duaRequestFilter = (string)($_GET['dua_request_filter'] ?? 'all');
  if (!in_array($duaRequestFilter, ['all','published','pending'], true)) $duaRequestFilter = 'all';
  $duaRequestWhere = $duaRequestFilter === 'published' ? 'WHERE dr.is_approved=1' : ($duaRequestFilter === 'pending' ? 'WHERE dr.is_approved=0' : '');
  $requests=$pdo->query('SELECT dr.*, dc.title AS circle_title FROM dua_requests dr LEFT JOIN dua_circles dc ON dc.id=dr.circle_id ' . $duaRequestWhere . ' ORDER BY dr.id DESC LIMIT 120')->fetchAll();
  $requestStats=$pdo->query('SELECT COUNT(*) AS total_count, SUM(CASE WHEN is_approved=1 THEN 1 ELSE 0 END) AS approved_count, SUM(CASE WHEN is_approved=0 THEN 1 ELSE 0 END) AS pending_count, COALESCE(SUM(amin_count),0) AS amin_total FROM dua_requests')->fetch() ?: [];
  $editCircleId=(int)($_GET['edit_circle'] ?? 0);
  $editCircle=null;
  if ($editCircleId) { $stmt=$pdo->prepare('SELECT * FROM dua_circles WHERE id=?'); $stmt->execute([$editCircleId]); $editCircle=$stmt->fetch(); }
  $editRequestId=(int)($_GET['edit_request'] ?? 0);
  $editRequest=null;
  if ($editRequestId) { $stmt=$pdo->prepare('SELECT * FROM dua_requests WHERE id=?'); $stmt->execute([$editRequestId]); $editRequest=$stmt->fetch(); }
  $totalRequests=(int)($requestStats['total_count'] ?? 0);
  $approvedRequests=(int)($requestStats['approved_count'] ?? 0);
  $pendingRequests=(int)($requestStats['pending_count'] ?? 0);
  $totalAmin=(int)($requestStats['amin_total'] ?? 0);
  $shownRequests=count($requests);
  $pendingPreview=[];
  $highAminPreview=[];
  try { $pendingPreview=$pdo->query("SELECT dr.*, dc.title AS circle_title FROM dua_requests dr LEFT JOIN dua_circles dc ON dc.id=dr.circle_id WHERE dr.is_approved=0 ORDER BY dr.id DESC LIMIT 6")->fetchAll(); } catch(Throwable $e) {}
  try { $highAminPreview=$pdo->query("SELECT dr.*, dc.title AS circle_title FROM dua_requests dr LEFT JOIN dua_circles dc ON dc.id=dr.circle_id ORDER BY dr.amin_count DESC, dr.id DESC LIMIT 6")->fetchAll(); } catch(Throwable $e) {}
  $liveCircles=0;
  foreach($circles as $c){ if($c['is_live']) $liveCircles++; }
  $circleFormTitle=$editCircle ? (string)$editCircle['title'] : 'Toplu Dua Halkası';
  $circleFormSubtitle=$editCircle ? (string)($editCircle['subtitle'] ?? '') : 'Beraber duâ edelim, dualarımız kabul olsun.';
  $circleFormParticipants=$editCircle ? (int)$editCircle['participant_count'] : 0;
  $circleFormLive=$editCircle ? (int)$editCircle['is_live'] === 1 : true;
  $reqFormCircleId=$editRequest ? (int)($editRequest['circle_id'] ?? 0) : ((int)($circles[0]['id'] ?? 0));
  $reqFormNickname=$editRequest ? (string)$editRequest['nickname'] : 'Misafir';
  $reqFormCategory=$editRequest ? (string)$editRequest['category'] : 'Genel';
  $reqFormTitle=$editRequest ? (string)$editRequest['title'] : '';
  $reqFormBody=$editRequest ? (string)$editRequest['body'] : '';
  $reqFormAmin=$editRequest ? (int)$editRequest['amin_count'] : 0;
  $reqFormApproved=$editRequest ? (int)$editRequest['is_approved'] === 1 : true;
?>
  <section class="panel dua-admin-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Premium Dua Yönetimi</span>
        <h2>Toplu Dua Halkası Kontrol Merkezi</h2>
        <p class="muted">Canlı dua halkalarını, kullanıcı dua isteklerini, onay durumunu ve Âmin hareketini tek ekranda yönet.</p>
      </div>
      <a class="btn secondary" href="?page=duas">Yeni Kayıt Modu</a>
    </div>
    <div class="dua-admin-stats">
      <div><b><?=number_format(count($circles),0,',','.')?></b><span>Dua Halkası</span></div>
      <div><b><?=number_format($liveCircles,0,',','.')?></b><span>Canlı Halkalar</span></div>
      <div><b><?=number_format($approvedRequests,0,',','.')?></b><span>Yayındaki Dua</span></div>
      <div><b><?=number_format($pendingRequests,0,',','.')?></b><span>Onay Bekleyen</span></div>
      <div><b><?=number_format($totalAmin,0,',','.')?></b><span>Toplam Âmin</span></div>
    </div>
  </section>

  <section class="panel admin-moderation-panel dua-moderation-panel">
    <div class="section-head-actions">
      <div>
        <span class="badge <?=$pendingRequests>0?'wait':'live'?>"><?=$pendingRequests>0?'Moderasyon gerekli':'Dua kuyruğu temiz'?></span>
        <h2>Dua Moderasyon Özeti</h2>
        <p class="muted">Onay bekleyen duaları ve yüksek Âmin alan kayıtları hızlı kontrol et.</p>
      </div>
      <div class="inline-actions">
        <a class="btn secondary" href="?page=duas&dua_request_filter=pending">Onay Bekleyenler</a>
        <a class="btn secondary" href="?page=duas&dua_request_filter=published">Yayındakiler</a>
      </div>
    </div>
    <div class="moderation-split-grid">
      <div class="moderation-box">
        <div class="moderation-box-title"><strong>Son Onay Bekleyenler</strong><span class="badge wait"><?=number_format(count($pendingPreview),0,',','.')?></span></div>
        <?php if(!$pendingPreview): ?><p class="muted">Onay bekleyen dua bulunmuyor.</p><?php else: ?>
          <div class="moderation-mini-list">
          <?php foreach($pendingPreview as $pr): ?>
            <div class="moderation-mini-item"><div><b><?=h($pr['title'])?></b><span><?=h(($pr['nickname'] ?: 'Misafir') . ' · ' . ($pr['category'] ?: 'Genel'))?></span></div><a class="btn secondary" href="?page=duas&edit_request=<?=(int)$pr['id']?>">İncele</a></div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="moderation-box">
        <div class="moderation-box-title"><strong>Yüksek Âmin Alanlar</strong><span class="badge info">Kontrol</span></div>
        <?php if(!$highAminPreview): ?><p class="muted">Henüz dua kaydı yok.</p><?php else: ?>
          <div class="moderation-mini-list">
          <?php foreach($highAminPreview as $hr): ?>
            <div class="moderation-mini-item"><div><b><?=h($hr['title'])?></b><span><?=number_format((int)$hr['amin_count'],0,',','.')?> Âmin · <?=h($hr['is_approved']?'Yayında':'Gizli')?></span></div><a class="btn secondary" href="?page=duas&edit_request=<?=(int)$hr['id']?>">Aç</a></div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel dua-form-panel">
    <div class="section-head-actions">
      <div><h2><?=$editCircle ? 'Dua Halkasını Düzenle' : 'Yeni Dua Halkası Aç'?></h2><p class="muted">Mobil uygulamada görünen dua halkası başlığı, açıklaması ve canlı durumunu buradan yönet.</p></div>
      <?php if($editCircle): ?><span class="badge info">Düzenlenen Halka #<?=number_format((int)$editCircle['id'],0,',','.')?></span><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_dua_circle">
      <?php if($editCircle): ?><input type="hidden" name="id" value="<?=(int)$editCircle['id']?>"><?php endif; ?>
      <div><label>Başlık</label><input class="field" name="title" value="<?=h($circleFormTitle)?>" required></div>
      <div><label>Katılımcı</label><input class="field" name="participant_count" type="number" min="0" value="<?=h($circleFormParticipants)?>"></div>
      <div style="grid-column:1/-1"><label>Alt açıklama</label><input class="field" name="subtitle" value="<?=h($circleFormSubtitle)?>"></div>
      <label class="admin-check"><input type="checkbox" name="is_live" <?=$circleFormLive?'checked':''?>> Canlı yayınla</label>
      <div class="session-form-actions"><button class="btn"> <?=$editCircle ? 'Halkayı Güncelle' : 'Halkayı Kaydet'?> </button><?php if($editCircle): ?><a class="btn secondary" href="?page=duas">Vazgeç / Yeni Halka</a><?php endif; ?></div>
    </form>
  </section>

  <section class="panel dua-circle-list-panel">
    <div class="section-head-actions"><div><h2>Dua Halkaları</h2><p class="muted">Canlı halkalar mobil uygulamada öne çıkar. Halka silinirse bağlı dua istekleri korunur, sadece halkadan ayrılır.</p></div><span class="badge live"><?=number_format($liveCircles,0,',','.')?> canlı</span></div>
    <?php if(!$circles): ?><div class="empty-state">Henüz dua halkası yok.</div><?php else: ?><div class="dua-circle-grid"><?php foreach($circles as $c): ?><article class="dua-circle-card <?=$c['is_live']?'is-live':'is-off'?>"><div class="dua-card-head"><div><span class="dua-card-id">#<?=$c['id']?></span><h3><?=h($c['title'])?></h3><p><?=h($c['subtitle'] ?: 'Alt açıklama girilmemiş.')?></p></div><span class="badge <?=$c['is_live']?'live':'off'?>"><?=$c['is_live']?'Canlı':'Kapalı'?></span></div><div class="dua-admin-stats compact"><div><b><?=number_format((int)$c['participant_count'],0,',','.')?></b><span>Katılımcı</span></div><div><b><?=number_format((int)$c['request_count'],0,',','.')?></b><span>Dua</span></div><div><b><?=number_format((int)$c['approved_count'],0,',','.')?></b><span>Yayında</span></div><div><b><?=number_format((int)$c['amin_total'],0,',','.')?></b><span>Âmin</span></div></div><div class="inline-actions dua-actions"><a class="btn secondary" href="?page=duas&edit_circle=<?=$c['id']?>">Düzenle</a><form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="toggle_dua_circle_live"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn secondary" type="submit"><?=$c['is_live']?'Yayından Al':'Yayına Al'?></button></form><form method="post" data-admin-confirm="<?=h($c['title'])?> halkası silinsin mi? Bağlı dua istekleri silinmez, sadece halkadan ayrılır."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="delete_dua_circle"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn danger" type="submit">Sil</button></form></div></article><?php endforeach; ?></div><?php endif; ?>
  </section>

  <section class="panel dua-request-form-panel">
    <div class="section-head-actions">
      <div><h2><?=$editRequest ? 'Dua İsteğini Düzenle' : 'Admin Dua İsteği Ekle'?></h2><p class="muted">Kullanıcıdan gelen dua isteklerini düzenle, onayla, gizle veya güvenli şekilde sil.</p></div>
      <?php if($editRequest): ?><span class="badge info">Düzenlenen Dua #<?=number_format((int)$editRequest['id'],0,',','.')?></span><?php endif; ?>
    </div>
    <form method="post" class="form-grid dua-request-form">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_dua_request">
      <?php if($editRequest): ?><input type="hidden" name="id" value="<?=(int)$editRequest['id']?>"><?php endif; ?>
      <div><label>Halka</label><select class="field" name="circle_id"><option value="">Halka seçilmedi</option><?php foreach($circles as $c): ?><option value="<?=$c['id']?>" <?=((int)$c['id']===$reqFormCircleId)?'selected':''?>><?=h($c['title'])?></option><?php endforeach; ?></select></div>
      <div><label>İsim / Rumuz</label><input class="field" name="nickname" value="<?=h($reqFormNickname)?>"></div>
      <div><label>Kategori</label><input class="field" name="category" value="<?=h($reqFormCategory)?>"></div>
      <div><label>Âmin sayısı</label><input class="field" name="amin_count" type="number" min="0" value="<?=h($reqFormAmin)?>"></div>
      <div style="grid-column:1/-1"><label>Başlık</label><input class="field" name="title" value="<?=h($reqFormTitle)?>" required></div>
      <div style="grid-column:1/-1"><label>Dua metni</label><textarea class="field" name="body" required><?=h($reqFormBody)?></textarea></div>
      <label class="admin-check"><input type="checkbox" name="is_approved" <?=$reqFormApproved?'checked':''?>> Yayında / onaylı</label>
      <div class="session-form-actions"><button class="btn"> <?=$editRequest ? 'Dua İsteğini Güncelle' : 'Dua İsteğini Kaydet'?> </button><?php if($editRequest): ?><a class="btn secondary" href="?page=duas">Vazgeç / Yeni Dua</a><?php endif; ?></div>
    </form>
  </section>

  <section class="panel dua-request-list-panel dua-request-premium-panel">
    <div class="section-head-actions dua-request-list-head">
      <div>
        <span class="dua-section-kicker">Dua Yönetimi</span>
        <h2>Dua İstekleri</h2>
        <p class="muted">Son 120 dua isteği listelenir. Yayındaki, onay bekleyen/gizli ve yüksek Âmin alan duaları tek ekranda kontrol et.</p>
      </div>
      <div class="dua-request-head-badges">
        <span class="badge wait"><?=number_format($pendingRequests,0,',','.')?> onay bekliyor</span>
        <span class="badge info"><?=number_format($shownRequests,0,',','.')?> gösteriliyor</span>
      </div>
    </div>

    <div class="dua-request-toolbar">
      <a class="dua-filter-tab <?=$duaRequestFilter==='all'?'active':''?>" href="?page=duas&dua_request_filter=all"><strong>Tümü</strong><span><?=number_format($totalRequests,0,',','.')?></span></a>
      <a class="dua-filter-tab <?=$duaRequestFilter==='published'?'active':''?>" href="?page=duas&dua_request_filter=published"><strong>Yayında</strong><span><?=number_format($approvedRequests,0,',','.')?></span></a>
      <a class="dua-filter-tab <?=$duaRequestFilter==='pending'?'active':''?>" href="?page=duas&dua_request_filter=pending"><strong>Onay/Gizli</strong><span><?=number_format($pendingRequests,0,',','.')?></span></a>
    </div>

    <?php if(!$requests): ?>
      <div class="empty-state dua-empty-state">Bu filtrede dua isteği yok.</div>
    <?php else: ?>
      <div class="dua-request-grid dua-request-premium-grid">
        <?php foreach($requests as $r):
          $reqApproved = (int)$r['is_approved'] === 1;
          $reqStatusClass = $reqApproved ? 'is-approved' : 'is-pending';
          $reqBadgeClass = $reqApproved ? 'live' : 'wait';
          $reqStatusText = $reqApproved ? 'Yayında' : 'Onay / Gizli';
          $reqAmin = (int)$r['amin_count'];
        ?>
          <article class="dua-request-card dua-request-premium-card <?=$reqStatusClass?>">
            <div class="dua-card-head dua-request-card-head">
              <div class="dua-request-title-block">
                <span class="dua-card-id">#<?=$r['id']?> · <?=h($r['category'] ?: 'Genel')?></span>
                <h3><?=h($r['title'])?></h3>
              </div>
              <span class="badge <?=$reqBadgeClass?> dua-status-badge"><?=$reqStatusText?></span>
            </div>

            <div class="dua-request-body-box">
              <span>Dua metni</span>
              <p><?=h($r['body'])?></p>
            </div>

            <div class="dua-request-meta premium">
              <span>Rumuz <b><?=h($r['nickname'] ?: 'Misafir')?></b></span>
              <span>Halka <b><?=h($r['circle_title'] ?: 'Halka yok')?></b></span>
              <span>Âmin <b><?=number_format($reqAmin,0,',','.')?></b></span>
              <span>Tarih <b><?=h($r['created_at'] ? date('d.m H:i', strtotime($r['created_at'])) : '-')?></b></span>
            </div>

            <div class="inline-actions dua-actions dua-request-actions">
              <a class="btn secondary dua-action-edit" href="?page=duas&edit_request=<?=$r['id']?>">Düzenle</a>
              <form method="post">
                <input type="hidden" name="_token" value="<?=csrf_token()?>">
                <input type="hidden" name="act" value="toggle_dua_request">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn secondary dua-action-toggle" type="submit"><?=$reqApproved?'Gizle':'Yayına Al'?></button>
              </form>
              <form method="post" data-admin-confirm="<?=h($r['title'])?> dua isteği silinsin mi? Bu işlem ilgili Âmin kayıtlarını da siler ve geri alınamaz.">
                <input type="hidden" name="_token" value="<?=csrf_token()?>">
                <input type="hidden" name="act" value="delete_dua_request">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn danger dua-action-delete" type="submit">Sil</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php elseif($page === 'hatims'):
  $hatims=$pdo->query('SELECT * FROM hatims ORDER BY id DESC')->fetchAll();
  $editHatimId=(int)($_GET['edit_hatim'] ?? 0);
  $editHatim=null;
  if ($editHatimId) {
      $stmt=$pdo->prepare('SELECT * FROM hatims WHERE id=?');
      $stmt->execute([$editHatimId]);
      $editHatim=$stmt->fetch();
  }
  $totalHatims=count($hatims);
  $activeHatims=0; $completedHatims=0; $pausedHatims=0;
  foreach($hatims as $hr){
      if (($hr['status'] ?? '') === 'active') $activeHatims++;
      elseif (($hr['status'] ?? '') === 'completed') $completedHatims++;
      else $pausedHatims++;
  }
  $formHatimTitle=$editHatim ? (string)$editHatim['title'] : '30 Cüz Online Hatim';
  $formHatimDescription=$editHatim ? (string)($editHatim['description'] ?? '') : 'Beraber okuyalım, beraber tamamlayalım.';
  $formHatimStatus=$editHatim ? (string)$editHatim['status'] : 'active';
?>
  <section class="panel hatim-admin-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Premium Hatim Yönetimi</span>
        <h2>Hatim Halkası Kontrol Merkezi</h2>
        <p class="muted">Aktif hatimleri, cüz dağılımını, düzenleme ve güvenli silme işlemlerini tek ekrandan yönet.</p>
      </div>
      <div class="inline-actions">
        <?php if($editHatim): ?><a class="btn secondary" href="?page=hatims">Yeni Hatim Modu</a><?php endif; ?>
      </div>
    </div>
    <div class="hatim-admin-stats">
      <div><b><?=number_format($totalHatims,0,',','.')?></b><span>Toplam Hatim</span></div>
      <div><b><?=number_format($activeHatims,0,',','.')?></b><span>Aktif</span></div>
      <div><b><?=number_format($pausedHatims,0,',','.')?></b><span>Beklemede</span></div>
      <div><b><?=number_format($completedHatims,0,',','.')?></b><span>Tamamlandı</span></div>
    </div>
  </section>

  <section class="panel hatim-editor-panel">
    <div class="section-head-actions">
      <div>
        <h2><?=$editHatim ? 'Hatim Düzenle' : 'Yeni Hatim Başlat'?></h2>
        <p class="muted"><?=$editHatim ? 'Seçili hatimin başlık, açıklama ve durum bilgisini güncelle.' : 'Yeni bir 30 cüz online hatim halkası oluştur.'?></p>
      </div>
      <?php if($editHatim): ?><span class="badge info">Düzenlenen ID: <?=number_format((int)$editHatim['id'],0,',','.')?></span><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_hatim">
      <?php if($editHatim): ?><input type="hidden" name="id" value="<?=(int)$editHatim['id']?>"><?php endif; ?>
      <div><label>Başlık</label><input class="field" name="title" value="<?=h($formHatimTitle)?>" required></div>
      <div><label>Durum</label><select class="field" name="status"><option value="active" <?=$formHatimStatus==='active'?'selected':''?>>Aktif</option><option value="paused" <?=$formHatimStatus==='paused'?'selected':''?>>Beklemede</option><option value="completed" <?=$formHatimStatus==='completed'?'selected':''?>>Tamamlandı</option></select></div>
      <div style="grid-column:1/-1"><label>Açıklama</label><textarea class="field" name="description"><?=h($formHatimDescription)?></textarea></div>
      <div class="hatim-form-actions" style="grid-column:1/-1"><button class="btn"><?=$editHatim ? 'Hatimi Güncelle' : 'Hatim Oluştur'?></button><?php if($editHatim): ?><a class="btn secondary" href="?page=hatims">Vazgeç</a><?php endif; ?></div>
    </form>
  </section>

  <?php if(!$hatims): ?>
    <section class="panel"><h2>Kayıtlı Hatim Yok</h2><p class="muted">İlk hatim halkasını yukarıdaki formdan oluşturabilirsin.</p></section>
  <?php endif; ?>

  <?php foreach($hatims as $hrow):
      $juzStmt=$pdo->prepare('SELECT * FROM hatim_juz WHERE hatim_id=? ORDER BY juz_number');
      $juzStmt->execute([$hrow['id']]);
      $juz=$juzStmt->fetchAll();
      $completed=0; $reserved=0; $empty=0; $staleReserved=0;
      foreach($juz as $jj){
          if(($jj['status'] ?? '') === 'completed') $completed++;
          elseif(($jj['status'] ?? '') === 'reserved') {
              $reserved++;
              $reservedAt = !empty($jj['reserved_at']) ? strtotime((string)$jj['reserved_at']) : 0;
              if ($reservedAt && $reservedAt < strtotime('-72 hours')) $staleReserved++;
          } else $empty++;
      }
      $totalJuz=max(1,count($juz));
      $progress=round($completed/$totalJuz*100);
      $status=(string)($hrow['status'] ?? 'active');
  ?>
    <section class="panel hatim-card-panel">
      <div class="section-head-actions hatim-card-head">
        <div>
          <span class="badge <?=admin_hatim_status_class($status)?>"><?=h(admin_hatim_status_label($status))?></span>
          <h2><?=h($hrow['title'])?></h2>
          <p class="muted"><?=h($hrow['description'] ?: 'Açıklama girilmemiş.')?></p>
        </div>
        <div class="inline-actions">
          <a class="btn secondary" href="?page=hatims&edit_hatim=<?=(int)$hrow['id']?>">Düzenle</a>
          <form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="toggle_hatim_status"><input type="hidden" name="id" value="<?=(int)$hrow['id']?>"><button class="btn secondary">Aktif/Beklemede</button></form>
          <form method="post" data-admin-confirm="Bu hatim tamamlandı olarak işaretlensin mi?"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="complete_hatim"><input type="hidden" name="id" value="<?=(int)$hrow['id']?>"><button class="btn secondary">Tamamlandı Yap</button></form>
          <form method="post" data-admin-confirm="Bu hatim ve bağlı 30 cüz kaydı silinsin mi? Bu işlem geri alınamaz."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="delete_hatim"><input type="hidden" name="id" value="<?=(int)$hrow['id']?>"><button class="btn danger">Sil</button></form>
        </div>
      </div>
      <div class="hatim-admin-stats compact">
        <div><b><?=number_format($completed,0,',','.')?></b><span>Tamamlanan</span></div>
        <div><b><?=number_format($reserved,0,',','.')?></b><span>Alınan</span></div>
        <div><b><?=number_format($empty,0,',','.')?></b><span>Boş</span></div>
        <div><b>%<?=$progress?></b><span>İlerleme</span></div>
      </div>
      <div class="hatim-progress"><span style="width:<?=$progress?>%"></span></div>
      <div class="hatim-admin-warning-grid">
        <div class="<?=$staleReserved>0?'warn':'ok'?>"><span>Süresi Uzayan Cüz</span><b><?=number_format($staleReserved,0,',','.')?></b><small>72 saati geçen alınmış cüz</small></div>
        <div><span>Sonraki Aksiyon</span><b><?=$staleReserved>0?'Kontrol Et':($empty>0?'Boş Cüz Var':'Tamamlanmaya Yakın')?></b><small><?=$staleReserved>0?'Gerekirse cüzü boşa çıkar.':($empty>0?'Mobil kullanıcılar boş cüz alabilir.':'Tamamlandı durumunu kontrol et.')?></small></div>
      </div>
      <div class="juz-mini premium-juz"><?php foreach($juz as $j): ?><span class="<?=h($j['status'])?>" title="<?=h(admin_juz_status_label((string)$j['status']))?><?=($j['nickname'] ?? '') ? ' · '.h($j['nickname']) : ''?>"><?=$j['juz_number']?></span><?php endforeach; ?></div><div class="hatim-juz-legend"><span><i class="juz-dot empty"></i>Boş</span><span><i class="juz-dot reserved"></i>Alındı</span><span><i class="juz-dot completed"></i>Tamamlandı</span></div>
      <div class="table-wrap" style="margin-top:14px"><table class="hatim-juz-table"><tr><th>Cüz</th><th>Durum</th><th>Kişi</th><th>Tarih</th><th>İşlem</th></tr><?php foreach($juz as $j): $jStatus=(string)$j['status']; ?><tr><td><strong><?=$j['juz_number']?>. Cüz</strong></td><td><span class="badge <?=admin_juz_status_class($jStatus)?>"><?=h(admin_juz_status_label($jStatus))?></span></td><td><?=h($j['nickname'] ?? '-')?></td><td><?=h($j['completed_at'] ?: ($j['reserved_at'] ?: '-'))?></td><td><?php if($jStatus !== 'empty'): ?><form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="reset_juz"><input type="hidden" name="id" value="<?=$j['id']?>"><button class="btn secondary">Boşa Çıkar</button></form><?php else: ?><span class="muted">İşlem yok</span><?php endif; ?></td></tr><?php endforeach; ?></table></div>
    </section>
  <?php endforeach; ?>
<?php elseif($page === 'daily'):
  $daily=$pdo->query('SELECT * FROM daily_contents ORDER BY is_active DESC, id DESC')->fetchAll();
  $editId=(int)($_GET['edit_daily'] ?? 0);
  $editDaily=null;
  if ($editId) {
      $stmt=$pdo->prepare('SELECT * FROM daily_contents WHERE id=?');
      $stmt->execute([$editId]);
      $editDaily=$stmt->fetch();
  }
  $formTitle=$editDaily ? (string)$editDaily['title'] : 'Kalplerin Huzuru';
  $formBody=$editDaily ? (string)$editDaily['body'] : 'Bilesiniz ki, kalpler ancak Allah’ın zikriyle huzur bulur.';
  $formRef=$editDaily ? (string)($editDaily['reference_text'] ?? '') : 'Ra’d Suresi, 28. Ayet';
  $formActive=$editDaily ? (int)$editDaily['is_active'] === 1 : true;
  $totalDaily=count($daily);
  $activeDaily=count(array_filter($daily, fn($d)=>(int)$d['is_active']===1));
  $passiveDaily=max(0,$totalDaily-$activeDaily);
  $latestDaily=$daily[0] ?? null;
?>
  <section class="panel daily-premium-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Günlük İçerik Yönetimi</span>
        <h2>Günlük Mesaj / Ayet Kartı Merkezi</h2>
        <p class="muted">Mobil ana sayfada görünecek günlük ayet, hadis, dua veya kısa mesaj kartlarını premium şekilde yönet.</p>
      </div>
      <div class="daily-hero-score">
        <b><?=number_format($activeDaily,0,',','.')?></b>
        <span>aktif içerik</span>
      </div>
    </div>
    <div class="daily-kpi-grid">
      <div><b><?=number_format($totalDaily,0,',','.')?></b><span>Toplam İçerik</span></div>
      <div><b><?=number_format($activeDaily,0,',','.')?></b><span>Aktif</span></div>
      <div><b><?=number_format($passiveDaily,0,',','.')?></b><span>Pasif</span></div>
      <div><b><?=h($latestDaily['updated_at'] ?? ($latestDaily['created_at'] ?? '-'))?></b><span>Son Güncelleme</span></div>
    </div>
  </section>

  <section class="panel daily-editor-panel daily-premium-editor">
    <div class="section-head-actions">
      <div>
        <h2><?= $editDaily ? 'Günlük İçeriği Düzenle' : 'Yeni Günlük Mesaj / Ayet Kartı' ?></h2>
        <p class="muted">Mobil ana sayfada gösterilecek günlük mesajları buradan ekleyip yönetebilirsin.</p>
      </div>
      <?php if($editDaily): ?><a class="btn secondary" href="?page=daily">Yeni içerik ekle</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid full">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_daily">
      <?php if($editDaily): ?><input type="hidden" name="id" value="<?= (int)$editDaily['id'] ?>"><?php endif; ?>
      <div><label>Başlık</label><input class="field" name="title" value="<?=h($formTitle)?>" required></div>
      <div><label>Metin</label><textarea class="field" name="body" required><?=h($formBody)?></textarea></div>
      <div><label>Kaynak</label><input class="field" name="reference_text" value="<?=h($formRef)?>" placeholder="Örn: Ra’d Suresi, 28. Ayet"></div>
      <div class="daily-form-status">
        <label><input type="checkbox" name="is_active" <?= $formActive ? 'checked' : '' ?>> Aktif olarak yayında tut</label>
        <p class="field-help">Aktif içerikler mobil uygulamada günlük kart havuzunda kullanılabilir. Tek içerik göstermek istersen aşağıdaki listeden “Tek Aktif Yap” kullan.</p>
      </div>
      <button class="btn"><?= $editDaily ? 'Günlük İçeriği Güncelle' : 'Günlük İçeriği Kaydet' ?></button>
    </form>
  </section>

  <section class="panel daily-premium-list">
    <div class="section-head-actions">
      <div>
        <h2>İçerikler</h2>
        <p class="muted">Tekrarlayan kayıtları pasifleştirebilir, silebilir veya tek aktif içerik olarak seçebilirsin.</p>
      </div>
      <span class="badge live"><?=number_format($totalDaily,0,',','.')?> kayıt</span>
    </div>

    <?php if(!$daily): ?>
      <div class="empty-state">Henüz günlük içerik yok. İlk içeriği yukarıdaki formdan ekleyebilirsin.</div>
    <?php else: ?>
      <div class="daily-card-grid">
        <?php foreach($daily as $d): ?>
          <article class="daily-content-card <?=$d['is_active']?'is-active':'is-passive'?>">
            <div class="daily-card-head">
              <div>
                <span class="daily-card-id">#<?= (int)$d['id'] ?></span>
                <h3><?=h($d['title'])?></h3>
              </div>
              <span class="badge <?=$d['is_active']?'live':'off'?>"><?=$d['is_active']?'Aktif':'Pasif'?></span>
            </div>
            <blockquote><?=h($d['body'])?></blockquote>
            <div class="daily-card-meta">
              <span>Kaynak <b><?=h($d['reference_text'] ?: '-')?></b></span>
              <span>Tarih <b><?=h($d['updated_at'] ?: $d['created_at'])?></b></span>
            </div>
            <div class="inline-actions daily-actions">
              <a class="btn secondary" href="?page=daily&edit_daily=<?=(int)$d['id']?>">Düzenle</a>
              <form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_toggle_status"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn secondary"><?=$d['is_active']?'Pasif Yap':'Aktif Yap'?></button></form>
              <form method="post" data-admin-confirm="Sadece bu içerik aktif kalsın mı? Diğer günlük içerikler pasif yapılır."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_make_only_active"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn secondary">Tek Aktif Yap</button></form>
              <form method="post" data-admin-confirm="Bu günlük içerik silinsin mi?"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_delete"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn danger">Sil</button></form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap daily-table-wrap"><table class="daily-admin-table"><tr><th>ID</th><th>Başlık</th><th>Metin</th><th>Kaynak</th><th>Durum</th><th>İşlem</th></tr><?php foreach($daily as $d): ?><tr>
        <td>#<?= (int)$d['id'] ?></td>
        <td><strong><?=h($d['title'])?></strong><div class="muted"><?=h($d['updated_at'] ?: $d['created_at'])?></div></td>
        <td><?=h($d['body'])?></td>
        <td><?=h($d['reference_text'] ?: '-')?></td>
        <td><span class="badge <?=$d['is_active']?'live':'off'?>"><?=$d['is_active']?'Aktif':'Pasif'?></span></td>
        <td><div class="inline-actions daily-actions">
          <a class="btn secondary" href="?page=daily&edit_daily=<?=(int)$d['id']?>">Düzenle</a>
          <form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_toggle_status"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn secondary"><?=$d['is_active']?'Pasif Yap':'Aktif Yap'?></button></form>
          <form method="post" data-admin-confirm="Sadece bu içerik aktif kalsın mı? Diğer günlük içerikler pasif yapılır."><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_make_only_active"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn secondary">Tek Aktif Yap</button></form>
          <form method="post" data-admin-confirm="Bu günlük içerik silinsin mi?"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="daily_delete"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn danger">Sil</button></form>
        </div></td>
      </tr><?php endforeach; ?></table></div>
    <?php endif; ?>
  </section>
<?php elseif($page === 'settings'): ?>
  <form method="post" class="settings-admin-form premium-settings-form">
    <input type="hidden" name="_token" value="<?=csrf_token()?>">
    <input type="hidden" name="act" value="save_settings">

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Genel Uygulama', 'Uygulamanın adı, varsayılan hedefi ve temel çalışma modları.')?>
      <div class="form-grid">
        <div><label>Uygulama adı</label><input class="field" name="app_name" value="<?=h(setting('app_name','Akıllı Zikir & Hatim'))?>"></div>
        <div><label>Varsayılan günlük hedef</label><input class="field" name="default_daily_target" inputmode="numeric" value="<?=h(setting('default_daily_target','1000'))?>"><p class="field-help">Mobil tarafta yeni kişisel hedefler için önerilen başlangıç değeri.</p></div>
        <?=admin_onoff_select('offline_mode_enabled', 'Offline mod', '1', 'Kişisel sayaç ve bazı tercihler cihazda saklanır.')?>
        <?=admin_onoff_select('community_enabled', 'Topluluk alanları', '1', 'Toplu zikir, dua halkası ve hatim alanlarını etkin tutar.')?>
      </div>
    </section>

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Dua ve Topluluk Kuralları', 'Dua isteği gönderme ve onay davranışını buradan yönet.')?>
      <div class="form-grid">
        <?=admin_onoff_select('duas_require_approval', 'Dua istekleri admin onayı istesin', '0', 'Açık olursa yeni dua istekleri onaydan sonra yayınlanır.')?>
        <?=admin_onoff_select('public_dua_enabled', 'Herkes dua isteği gönderebilsin', '1', 'Kapalı olursa kullanıcıların yeni dua isteği göndermesi sınırlandırılır.')?>
      </div>
    </section>

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Mobil Duyuru', 'Mobil uygulamada gerektiğinde kısa duyuru göstermek için kullanılır.')?>
      <div class="form-grid">
        <?=admin_onoff_select('app_announcement_enabled', 'Mobil duyuru', '0', 'Açık olursa duyuru başlığı ve metni mobil tarafta gösterilir.')?>
        <div><label>Duyuru başlığı</label><input class="field" name="app_announcement_title" value="<?=h(setting('app_announcement_title','Duyuru'))?>"></div>
        <div style="grid-column:1/-1"><label>Mobil duyuru metni</label><textarea class="field" name="app_announcement_body"><?=h(setting('app_announcement_body',''))?></textarea></div>
      </div>
    </section>

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Yayımcı ve Hakkında', 'Uygulama içindeki yayımcı, geliştirici ve hakkında metinleri.')?>
      <div class="form-grid">
        <div><label>Yayımcı adı</label><input class="field" name="publisher_name" value="<?=h(setting('publisher_name','İlhan BELUK'))?>"></div>
        <div><label>Geliştirici adı</label><input class="field" name="developer_name" value="<?=h(setting('developer_name','İlhan BELUK'))?>"></div>
        <div style="grid-column:1/-1"><label>Hakkında metni</label><textarea class="field" name="about_text"><?=h(setting('about_text','Akıllı Zikir & Hatim; kişisel zikir takibi, toplu zikir halkaları, dua halkası ve hatim takibi için hazırlanmış mobil odaklı, reklamsız bir uygulamadır. Kişisel sayaç ve bazı veriler cihazınızda/offline saklanır.'))?></textarea></div>
      </div>
    </section>

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Gönüllü Katkı', 'Reklamsız yapıya uygun destek mesajları ve destek bağlantıları.')?>
      <div class="form-grid">
        <?=admin_onoff_select('voluntary_support_enabled', 'Gönüllü katkı bölümü', '1', 'Kapalı olursa mobil taraftaki gönüllü destek alanı pasifleştirilir.')?>
        <div><label>Destek tutarları</label><input class="field" name="support_amounts" value="<?=h(setting('support_amounts','25,50,100,250'))?>"><p class="field-help">Virgülle ayır: 25,50,100,250</p></div>
        <div style="grid-column:1/-1"><label>Gönüllü katkı açıklaması</label><textarea class="field" name="support_message"><?=h(setting('support_message','Bu uygulamada reklam bulunmamaktadır. Akıllı Zikir & Hatim’i beğendiyseniz ve geliştirilmesine destek olmak isterseniz gönüllü katkıda bulunabilirsiniz. Katkınız uygulamanın daha iyi, daha stabil ve daha faydalı hale gelmesine yardımcı olur.'))?></textarea></div>
        <div><label>Genel destek bağlantısı</label><input class="field" name="support_general_url" value="<?=h(setting('support_general_url',''))?>"></div>
        <div><label>25 TL bağlantısı</label><input class="field" name="support_25_url" value="<?=h(setting('support_25_url',''))?>"></div>
        <div><label>50 TL bağlantısı</label><input class="field" name="support_50_url" value="<?=h(setting('support_50_url',''))?>"></div>
        <div><label>100 TL bağlantısı</label><input class="field" name="support_100_url" value="<?=h(setting('support_100_url',''))?>"></div>
        <div><label>250 TL bağlantısı</label><input class="field" name="support_250_url" value="<?=h(setting('support_250_url',''))?>"></div>
        <div><label>Kendi tutarım bağlantısı</label><input class="field" name="support_custom_url" value="<?=h(setting('support_custom_url',''))?>" placeholder="İstersen {amount} kullan"></div>
        <?=admin_onoff_select('google_play_support_enabled', 'Google Play destek yönlendirmesi', '1', 'Google Play ürünleri hazır olana kadar kapalı tutulabilir.')?>
        <div><label>Google Play destek bağlantısı / ürün yönlendirme</label><input class="field" name="google_play_support_url" value="<?=h(setting('google_play_support_url',''))?>"></div>
      </div>
    </section>

    <section class="panel settings-panel premium-settings-panel">
      <?=admin_settings_title('Google Play ve Android Hazırlığı', 'Paket adı, ürün ID’leri ve Android bildirim hazırlıkları. Bu alan son kullanıcıya görünmez.')?>
      <div class="form-grid">
        <div><label>Android paket ID</label><input class="field" name="android_package_id" value="<?=h(setting('android_package_id','com.ilhanbeluk.akillizikirhatim'))?>"><p class="field-help">Play Store paket adı. Yayın sonrası değiştirilmemelidir.</p></div>
        <div><label>Google Play 25 TL ürün ID</label><input class="field" name="apk_google_play_product_25" value="<?=h(setting('apk_google_play_product_25','support_25'))?>"></div>
        <div><label>Google Play 50 TL ürün ID</label><input class="field" name="apk_google_play_product_50" value="<?=h(setting('apk_google_play_product_50','support_50'))?>"></div>
        <div><label>Google Play 100 TL ürün ID</label><input class="field" name="apk_google_play_product_100" value="<?=h(setting('apk_google_play_product_100','support_100'))?>"></div>
        <div><label>Google Play 250 TL ürün ID</label><input class="field" name="apk_google_play_product_250" value="<?=h(setting('apk_google_play_product_250','support_250'))?>"></div>
        <div><label>Google Play özel tutar ürün ID</label><input class="field" name="apk_google_play_product_custom" value="<?=h(setting('apk_google_play_product_custom','support_custom'))?>"></div>
        <?=admin_onoff_select('native_notifications_enabled', 'Native bildirim hazırlığı', '1', 'Android 13+ cihazlarda izin akışı ayrıca gerekir.')?>
        <div><label>Android bildirim kanal ID</label><input class="field" name="android_notification_channel_id" value="<?=h(setting('android_notification_channel_id','zikir_reminders'))?>"></div>
        <div><label>Android bildirim kanal adı</label><input class="field" name="android_notification_channel_name" value="<?=h(setting('android_notification_channel_name','Zikir Hatırlatıcıları'))?>"></div>
        <div style="grid-column:1/-1"><label>Android bildirim izin notu</label><textarea class="field" name="android_notification_permission_note"><?=h(setting('android_notification_permission_note','Android 13 ve üzeri cihazlarda POST_NOTIFICATIONS izni istenir. Bu alan sadece APK hazırlığı için admin tarafında tutulur.'))?></textarea></div>
        <div style="grid-column:1/-1"><label>APK admin notları</label><textarea class="field" name="apk_admin_notes"><?=h(setting('apk_admin_notes','APK hazırlık notları burada tutulur. Bu alan son kullanıcıya gösterilmez.'))?></textarea></div>
      </div>
    </section>

    <div class="settings-savebar premium-settings-savebar">
      <div>
        <strong>Ayarları güvenli şekilde kaydet</strong>
        <span class="muted">Bu sayfa sadece yönetim ayarlarını günceller; mobil uygulama dosyalarına dokunmaz.</span>
      </div>
      <button class="btn">Tüm Ayarları Kaydet</button>
    </div>
  </form>



<?php elseif($page === 'release_checklist'): ?>
  <?php
    $root = dirname(__DIR__);
    $appVersion = admin_effective_app_version($pdo);
    $cacheVersion = setting('pwa_cache_version', $appVersion);
    $androidPackage = setting('android_package_id','com.ilhanbeluk.akillizikirhatim');
    $iosBundle = setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim');
    $installFileExists = file_exists($root . '/install.php');
    $allowInstallExists = file_exists($root . '/system/ALLOW_INSTALL');
    $installLockedOk = (!$installFileExists || !$allowInstallExists);
    $configOk = file_exists($root . '/system/config.php');
    $adminOk = file_exists($root . '/admin/index.php');
    $httpsOk = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $apiFileOk = file_exists($root . '/api/app.php');
    $apiSecurityToday = 0;
    try { $apiSecurityToday = (int)$pdo->query("SELECT COUNT(*) FROM api_security_events WHERE DATE(created_at)=CURDATE()")->fetchColumn(); } catch (Throwable $e) {}
    $legalReady = file_exists($root . '/legal/privacy.php') && file_exists($root . '/legal/terms.php') && file_exists($root . '/legal/support.php') && file_exists($root . '/legal/data-deletion.php');
    $storeReady = file_exists($root . '/store/GOOGLE_PLAY_LISTING_TR.txt') && file_exists($root . '/store/APP_PRIVACY_ANSWERS_TR.txt') && file_exists($root . '/store/APP_STORE_LISTING_TR.txt');
    $iconFiles = [
      'Android 192px' => '/app/assets/icons/icon-192.png',
      'Android 384px' => '/app/assets/icons/icon-384.png',
      'Android 512px' => '/app/assets/icons/icon-512.png',
      'Maskable 512px' => '/app/assets/icons/maskable-512.png',
      'iPhone apple-touch-icon' => '/app/assets/icons/apple-touch-icon.png',
      'Favicon 32px' => '/app/assets/icons/favicon-32.png',
      'Favicon 16px' => '/app/assets/icons/favicon-16.png',
    ];
    $iconChecks = [];
    foreach ($iconFiles as $label => $path) $iconChecks[] = check_ok($label, file_exists($root . $path), $path);
    $iconsReady = count(array_filter($iconChecks, fn($c) => $c['ok'])) === count($iconChecks);
    $dataChecks = [
      check_ok('Aktif zikir oturumu', stat_count('SELECT COUNT(*) FROM zikir_sessions WHERE is_live=1') > 0, 'Toplu Zikir sayfasında canlı oturum'),
      check_ok('Onaylı dua isteği', stat_count('SELECT COUNT(*) FROM dua_requests WHERE is_approved=1') > 0, 'Dua Halkası sayfasında yayındaki dua'),
      check_ok('Aktif hatim', stat_count("SELECT COUNT(*) FROM hatims WHERE status='active'") > 0, 'Hatim Halkası sayfasında aktif hatim'),
      check_ok('Hazır zikir listesi', stat_count('SELECT COUNT(*) FROM zikirs WHERE is_active=1') > 0, 'Aktif hazır zikir kaydı'),
    ];
    $releaseGroups = [
      'Uygulama Çekirdeği' => [
        check_ok('Manifest dosyası', file_exists($root . '/manifest.json'), '/manifest.json'),
        check_ok('Service worker', file_exists($root . '/service-worker.js'), '/service-worker.js'),
        check_ok('Ana PWA ekranı', file_exists($root . '/app/index.html'), '/app/index.html'),
        check_ok('Mobil CSS', file_exists($root . '/app/assets/css/app.css'), '/app/assets/css/app.css'),
        check_ok('Mobil JS', file_exists($root . '/app/assets/js/app.js'), '/app/assets/js/app.js'),
        check_ok('API dosyası', $apiFileOk, '/api/app.php'),
      ],
      'İkon & Cache' => array_merge($iconChecks, [
        check_ok('PWA cache sürümü', trim($cacheVersion) !== '', 'pwa_cache_version: ' . $cacheVersion),
      ]),
      'Yasal & Mağaza Metinleri' => [
        check_ok('Gizlilik politikası', file_exists($root . '/legal/privacy.php'), '/legal/privacy.php'),
        check_ok('Kullanım şartları', file_exists($root . '/legal/terms.php'), '/legal/terms.php'),
        check_ok('Destek sayfası', file_exists($root . '/legal/support.php'), '/legal/support.php'),
        check_ok('Veri silme sayfası', file_exists($root . '/legal/data-deletion.php'), '/legal/data-deletion.php'),
        check_ok('Google Play mağaza metni', file_exists($root . '/store/GOOGLE_PLAY_LISTING_TR.txt'), '/store/GOOGLE_PLAY_LISTING_TR.txt'),
        check_ok('App privacy cevapları', file_exists($root . '/store/APP_PRIVACY_ANSWERS_TR.txt'), '/store/APP_PRIVACY_ANSWERS_TR.txt'),
      ],
      'Güvenlik & Canlı Kilit' => [
        check_ok('install.php kilitli', $installLockedOk, $installFileExists ? ($allowInstallExists ? 'ALLOW_INSTALL açık: canlı öncesi kapat' : 'install.php var ama kilitli') : 'install.php yok'),
        check_ok('system/config.php', $configOk, '/system/config.php'),
        check_ok('Admin panel dosyası', $adminOk, '/admin/index.php'),
        check_ok('HTTPS / SSL', $httpsOk, $httpsOk ? 'Güvenli bağlantı aktif' : 'SSL aktif değilse APK/PWA sorun yaşar'),
        check_ok('API güvenlik log tablosu', true, 'api_security_events aktif'),
      ],
      'Android / Play Store' => [
        check_ok('Android paket ID', trim($androidPackage) !== '', $androidPackage),
        check_ok('APK build callback', file_exists($root . '/update/apk_build_callback.php'), '/update/apk_build_callback.php'),
        check_ok('GitHub Android notları', is_dir($root . '/apk') || is_dir($root . '/.github'), 'GitHub Actions / apk klasörü'),
        check_ok('HTTPS / SSL', $httpsOk, $httpsOk ? 'Güvenli bağlantı aktif' : 'SSL aktif değilse APK veri çekemez'),
      ],
      'iPhone / PWA / TestFlight' => [
        check_ok('iOS Bundle ID', trim($iosBundle) !== '', $iosBundle),
        check_ok('iOS build callback', file_exists($root . '/update/ios_build_callback.php'), '/update/ios_build_callback.php'),
        check_ok('App Store mağaza metni', file_exists($root . '/store/APP_STORE_LISTING_TR.txt'), '/store/APP_STORE_LISTING_TR.txt'),
        check_ok('iPhone ana ekran ikonu', file_exists($root . '/app/assets/icons/apple-touch-icon.png'), 'apple-touch-icon.png'),
      ],
      'Canlı İçerik & Topluluk' => $dataChecks,
    ];
    $flatChecks = [];
    foreach ($releaseGroups as $items) foreach ($items as $item) $flatChecks[] = $item;
    $okCount = count(array_filter($flatChecks, fn($c) => $c['ok']));
    $totalCount = count($flatChecks);
    $percentReady = $totalCount ? round(($okCount / $totalCount) * 100) : 0;
    $missingCount = max(0, $totalCount - $okCount);
    $liveBlockers = [];
    if (!$installLockedOk) $liveBlockers[] = ['title' => 'Kurulum kilidi açık', 'detail' => 'system/ALLOW_INSTALL dosyası canlı öncesi kaldırılmalı veya install.php erişimi kapatılmalı.'];
    if (!$httpsOk) $liveBlockers[] = ['title' => 'SSL / HTTPS aktif değil', 'detail' => 'PWA ve APK online veri akışı için HTTPS zorunlu olmalı.'];
    if (!$apiFileOk) $liveBlockers[] = ['title' => 'API dosyası eksik', 'detail' => '/api/app.php çalışmadan mobil uygulama online veri alamaz.'];
    if (!$legalReady) $liveBlockers[] = ['title' => 'Yasal sayfalar eksik', 'detail' => 'Gizlilik, şartlar, destek ve veri silme sayfaları canlı öncesi hazır olmalı.'];
    if (!$iconsReady) $liveBlockers[] = ['title' => 'İkon seti eksik', 'detail' => 'Android/iPhone/PWA ikon dosyaları tamamlanmalı.'];
    $releaseState = count($liveBlockers) === 0 ? ($missingCount === 0 ? 'ready' : 'review') : 'blocked';
    $releaseStateText = $releaseState === 'ready' ? 'Canlı yayına hazır' : ($releaseState === 'review' ? 'Eksik kontrol var' : 'Canlı bloklayıcı var');
  ?>
  <section class="panel release-pro-panel">
    <div class="release-pro-head">
      <div>
        <span class="badge info">Yayın Öncesi Kontrol</span><span class="badge live">Final Etiket: <?=h($appVersion)?></span>
        <h2>Mağaza ve PWA Yayın Hazırlığı</h2>
        <p class="muted">Google Play, iPhone PWA/TestFlight ve web yayını için teknik, yasal ve içerik kontrollerini kategori bazlı takip et.</p>
      </div>
      <div class="release-pro-score"><b>%<?=$percentReady?></b><span><?=$okCount?> / <?=$totalCount?> hazır</span></div>
    </div>
    <div class="release-status-strip">
      <div><strong><?=h($appVersion)?></strong><span>Yayın Sürümü</span></div>
      <div><strong><?=h($cacheVersion)?></strong><span>PWA Cache</span></div>
      <div><strong><?=$missingCount?></strong><span>Kalan Eksik</span></div>
      <div><strong><?=h($androidPackage)?></strong><span>Android Paket ID</span></div>
    </div>
    <div class="dashboard-quick-actions release-pro-actions">
      <a class="btn secondary" href="<?=h(admin_status_url('api/app.php?action=bootstrap'))?>" target="_blank">API JSON Kontrol</a>
      <a class="btn secondary" href="?page=pwa_icons">İkon & Cache</a>
      <a class="btn secondary" href="?page=apk_build">Android Build</a>
      <a class="btn secondary" href="?page=ios_build">iPhone / iOS</a>
      <a class="btn secondary" href="?page=legal">Yasal Metinler</a>
    </div>
  </section>

  <section class="panel release-final-gate <?=$releaseState?>">
    <div class="section-head-actions">
      <div>
        <span class="badge <?=$releaseState === 'ready' ? 'live' : ($releaseState === 'review' ? 'wait' : 'off')?>"><?=h($releaseStateText)?></span>
        <h2>Canlı Yayın Karar Paneli</h2>
        <p class="muted">Bu panel canlıya çıkmadan önce özellikle güvenlik, API, yasal sayfalar, ikon ve cache durumunu özetler.</p>
      </div>
      <div class="release-gate-score"><b><?=count($liveBlockers)?></b><span>bloklayıcı</span></div>
    </div>
    <?php if(!$liveBlockers): ?>
      <div class="release-ready-box">
        <strong>Canlı bloklayıcı görünmüyor.</strong>
        <span>Son elle test: mobil ana sayfa, sayaç, dua, hatim, toplu zikir, ayarlar, PWA cache ve admin giriş kontrolü yapılmalı.</span>
      </div>
    <?php else: ?>
      <div class="release-blocker-grid">
        <?php foreach($liveBlockers as $b): ?>
          <div class="release-blocker-card"><strong><?=h($b['title'])?></strong><span><?=h($b['detail'])?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="release-final-actions">
      <a class="btn secondary" href="?page=maintenance">Bakım & Sağlık</a>
      <a class="btn secondary" href="?page=pwa_icons">İkon & Cache</a>
      <a class="btn secondary" href="?page=legal">Yasal Metinler</a>
      <a class="btn secondary" href="<?=h(admin_status_url('api/app.php?action=bootstrap'))?>" target="_blank">API JSON</a>
    </div>
  </section>

  <section class="panel release-live-test-panel">
    <div class="section-head-actions">
      <div><h2>Son Elle Test Sırası</h2><p class="muted">Bu maddeler otomatik değil; canlı öncesi telefondan manuel kontrol edilmeli.</p></div>
      <span class="badge info">8 adım</span>
    </div>
    <div class="release-manual-test-grid">
      <div><b>1</b><strong>Ana Sayfa</strong><span>Bugünkü görev kartı, sayaç geçişi, PWA açılışı.</span></div>
      <div><b>2</b><strong>Sayaç</strong><span>Artır, hedef değiştir, geçmişe kaydet.</span></div>
      <div><b>3</b><strong>Dua</strong><span>Dua gönder, Âmin ver, spam engelini kontrol et.</span></div>
      <div><b>4</b><strong>Hatim</strong><span>Boş cüz al, tamamla, bırakma/uyarı akışını kontrol et.</span></div>
      <div><b>5</b><strong>Toplu Zikir</strong><span>Sayacı halkaya aktar, offline kuyruğu kontrol et.</span></div>
      <div><b>6</b><strong>Ayarlar</strong><span>Bildirim, Verilerim, yedek alma/yükleme.</span></div>
      <div><b>7</b><strong>Admin</strong><span>Bakım & Sağlık, API Güvenlik Olayları, moderasyon.</span></div>
      <div><b>8</b><strong>Cache</strong><span>Ctrl+F5, PWA kapat/aç, eski görünüm kalmadığını kontrol et.</span></div>
    </div>
  </section>

  <section class="panel release-category-panel">
    <div class="section-head-actions"><div><h2>Kategori Bazlı Kontrol</h2><p class="muted">Eksik kalan yayın maddelerini kategori kategori kontrol et.</p></div><span class="badge <?= $missingCount===0 ? 'live' : 'wait' ?>"><?= $missingCount===0 ? 'Hazır' : number_format($missingCount,0,',','.') . ' eksik' ?></span></div>
    <div class="release-category-grid">
      <?php foreach($releaseGroups as $groupTitle => $items): $groupOk=count(array_filter($items, fn($c)=>$c['ok'])); $groupTotal=count($items); ?>
        <div class="release-category-card">
          <div class="release-category-title"><strong><?=h($groupTitle)?></strong><span class="badge <?=$groupOk===$groupTotal?'live':'wait'?>"><?=$groupOk?> / <?=$groupTotal?></span></div>
          <ul class="release-check-list">
            <?php foreach($items as $c): ?>
              <li class="<?=$c['ok']?'ok':'missing'?>"><span><?=$c['ok']?'✓':'!'?></span><div><b><?=h($c['label'])?></b><small><?=h($c['detail'])?></small></div></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel release-steps-panel">
    <div class="section-head-actions"><div><h2>Yayın Sırası</h2><p class="muted">Mağaza ve PWA yayını için izlenecek güvenli sıra.</p></div><span class="badge info">4 adım</span></div>
    <div class="release-step-grid">
      <div class="release-step"><b>1</b><strong>SSL ve API</strong><p>Önce API JSON çıktısı dolu dönmeli. SSL bozulursa APK/iPhone PWA online veri çekemez.</p></div>
      <div class="release-step"><b>2</b><strong>İkon & Cache</strong><p>Android ve iPhone ikonları yüklendikten sonra cache sürümü artırılmalı.</p></div>
      <div class="release-step"><b>3</b><strong>Test APK / PWA</strong><p>Android debug APK ve iPhone Safari “Ana Ekrana Ekle” akışı test edilmeli.</p></div>
      <div class="release-step"><b>4</b><strong>Mağaza Yayını</strong><p>Google Play için AAB, Apple tarafı için Developer/TestFlight süreci ayrıca yürütülmeli.</p></div>
    </div>
  </section>

<?php elseif($page === 'legal'): ?>
  <section class="panel legal-premium-panel">
    <div class="section-head-actions"><div><span class="badge info">Mağaza Hazırlığı</span><h2>Yasal Metinler ve Mağaza Yayını</h2>
    <p class="muted">Bu bölüm Google Play ve App Store yayın hazırlığı için admin tarafında tutulur. Mobil kullanıcı teknik alan görmez; sadece ilgili sayfalara ihtiyaç olduğunda erişir.</p></div><span class="badge live">Yasal Merkez</span></div>
    <form method="post" class="form-grid legal-premium-form">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_legal_settings">
      <div style="grid-column:1/-1"><label>Gizlilik Politikası URL</label><input class="field" name="privacy_policy_url" value="<?=h(setting('privacy_policy_url','/legal/privacy.php'))?>"></div>
      <div style="grid-column:1/-1"><label>Kullanım Şartları URL</label><input class="field" name="terms_url" value="<?=h(setting('terms_url','/legal/terms.php'))?>"></div>
      <div style="grid-column:1/-1"><label>Destek URL</label><input class="field" name="support_url" value="<?=h(setting('support_url','/legal/support.php'))?>"></div>
      <div style="grid-column:1/-1"><label>Veri Silme URL</label><input class="field" name="data_deletion_url" value="<?=h(setting('data_deletion_url','/legal/data-deletion.php'))?>"></div>
      <div style="grid-column:1/-1"><label>Mağaza kısa açıklama</label><input class="field" name="store_short_description" value="<?=h(setting('store_short_description','Reklamsız zikir sayacı, dua halkası ve hatim takibi.'))?>"></div>
      <div style="grid-column:1/-1"><label>Mağaza uzun açıklama</label><textarea class="field" name="store_full_description"><?=h(setting('store_full_description','Akıllı Zikir & Hatim; kişisel zikir sayacı, günlük hedef takibi, toplu zikir halkaları, dua halkası ve hatim takibi için hazırlanmış mobil odaklı, reklamsız bir uygulamadır.'))?></textarea></div>
      <div style="grid-column:1/-1"><label>Anahtar kelimeler</label><input class="field" name="store_keywords" value="<?=h(setting('store_keywords','zikir, hatim, dua, tesbihat, sayaç, vird'))?>"></div>
      <div style="grid-column:1/-1"><label>Gizlilik / veri notları</label><textarea class="field" name="store_privacy_notes"><?=h(setting('store_privacy_notes','Uygulamada reklam yoktur. Kişisel sayaç verileri cihazda saklanabilir. Topluluk özellikleri için takma ad ve katkı kayıtları işlenebilir.'))?></textarea></div>
      <button class="btn">Yasal Ayarları Kaydet</button>
    </form>
  </section>
  <section class="panel legal-links-panel">
    <div class="section-head-actions"><div><h2>Hızlı Bağlantılar</h2><p class="muted">Yasal sayfaları ve mağaza yayınına gerekli linkleri hızlı kontrol et.</p></div><span class="badge info">4 sayfa</span></div>
    <div class="table-wrap"><table>
      <tr><th>Sayfa</th><th>Bağlantı</th></tr>
      <tr><td>Gizlilik Politikası</td><td><a class="btn" href="/legal/privacy.php" target="_blank">Aç</a></td></tr>
      <tr><td>Kullanım Şartları</td><td><a class="btn" href="/legal/terms.php" target="_blank">Aç</a></td></tr>
      <tr><td>Destek</td><td><a class="btn" href="/legal/support.php" target="_blank">Aç</a></td></tr>
      <tr><td>Veri Silme</td><td><a class="btn" href="/legal/data-deletion.php" target="_blank">Aç</a></td></tr>
    </table></div>
  </section>


<?php elseif($page === 'pwa_icons'): ?>
  <?php
    $root = pwa_project_root();
    $iconVersion = setting('pwa_icon_version', setting('pwa_cache_version','1.0.80') ?? '1.0.80') ?? '1.0.80';
    $iconDefs = pwa_icon_defs();
    $assetItems = pwa_icon_asset_files($root);
    $hatimIconUrl = '/app/assets/img/kuran-hatim-v1_2_8.svg?v=' . rawurlencode($iconVersion);
    $adminCssVersion = admin_css_version();
    $adminCssFile = __DIR__ . '/assets/admin.css';
    $adminCssMtime = is_file($adminCssFile) ? date('d.m.Y H:i', @filemtime($adminCssFile)) : 'Bulunamadı';
  ?>
  <section class="panel icon-cache-hero">
    <div class="section-head-actions"><div><span class="eyebrow">Yayın & Mağaza</span><h2>İkon & Cache Merkezi</h2><p class="muted">PWA ikonları, Hatim Halkası ikonu ve uygulama içindeki tüm eklenen ikon/görselleri tek yerden gör. Dosya ekledikten sonra cache kırma işlemini buradan çalıştır.</p></div><a class="btn secondary" href="/app/" target="_blank">Mobil Önizleme</a></div>
    <div class="icon-cache-actions">
      <form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="refresh_all_icon_assets"><button class="btn">Tüm İkonları Tara & Cache Yenile</button></form>
      <form method="post"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="refresh_admin_css_cache"><button class="btn secondary">Admin CSS Cache Yenile</button></form>
      <a class="btn secondary" target="_blank" href="<?=h($hatimIconUrl)?>">Hatim İkonunu Aç</a>
      <span class="badge info">Mobil Cache: <?=h($iconVersion)?></span>
      <span class="badge wait">Admin CSS: <?=h($adminCssVersion)?></span>
    </div>
  </section>

  <section class="panel admin-css-cache-panel">
    <div class="section-head-actions">
      <div>
        <span class="eyebrow">Admin Görünüm Cache</span>
        <h2>CSS Cache Kontrolü</h2>
        <p class="muted">Admin panelde beyaz/eski görünüm geri gelirse bu buton admin CSS sürümünü yeniler. Dua Halkası gibi premium düzeltmelerin kalıcı görünmesi için kullanılır.</p>
      </div>
      <span class="badge live">admin.css</span>
    </div>
    <div class="admin-css-cache-card">
      <div>
        <strong>Aktif admin CSS sürümü</strong>
        <span><?=h($adminCssVersion)?></span>
      </div>
      <div>
        <strong>Dosya güncelleme zamanı</strong>
        <span><?=h($adminCssMtime)?></span>
      </div>
      <form method="post">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="refresh_admin_css_cache">
        <button class="btn">Admin CSS Cache Yenile</button>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="section-head-actions"><div><h2>Hatim Halkası İkonu</h2><p class="muted">Bu alan mobilde Hatim Halkası kartında görünen özel Kuran/Hatim ikonudur. Yeni SVG yüklediğinde dosya otomatik değiştirilir ve cache kırılır.</p></div><span class="badge live">/app/assets/img/kuran-hatim-v1_2_8.svg</span></div>
    <div class="hatim-icon-admin-card">
      <div class="hatim-icon-preview"><img src="<?=h($hatimIconUrl)?>" alt="Hatim ikonu" onerror="this.style.opacity=.18"></div>
      <form method="post" enctype="multipart/form-data" class="hatim-icon-upload">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="upload_hatim_svg">
        <label>Yeni Hatim SVG yükle</label>
        <input class="field" type="file" name="hatim_svg" accept=".svg,image/svg+xml" required>
        <p class="muted">Yüklenen dosya doğrudan <code>app/assets/img/kuran-hatim-v1_2_8.svg</code> üzerine yazılır. Ardından cache sürümü otomatik yenilenir.</p>
        <button class="btn">Hatim İkonunu Yükle & Cache Yenile</button>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="section-head-actions"><div><h2>Tüm Eklenen İkonlar / Görseller</h2><p class="muted">Aşağıda <code>app/assets/icons</code>, <code>app/assets/img</code> ve <code>app/assets/splash</code> klasörlerindeki tüm ikon/görsel dosyaları görünür. FTP/cPanel ile yeni dosya attığında bu sayfayı açman veya “Tüm İkonları Tara & Cache Yenile” demen yeterli.</p></div><span class="badge info"><?=number_format(count($assetItems),0,',','.')?> dosya</span></div>
    <?php if(!$assetItems): ?><div class="empty-state">Henüz ikon/görsel dosyası bulunamadı.</div><?php else: ?>
      <div class="asset-gallery-grid">
        <?php foreach($assetItems as $asset): $url=$asset['path'].'?v='.rawurlencode($iconVersion); ?>
          <article class="asset-gallery-card <?=$asset['used']?'is-used':'is-free'?>">
            <div class="asset-thumb"><img src="<?=h($url)?>" alt="<?=h($asset['file'])?>" onerror="this.style.opacity=.16"></div>
            <div class="asset-info">
              <strong><?=h($asset['file'])?></strong>
              <span><?=h($asset['group'])?></span>
              <small><?=h($asset['dimensions'])?> · <?=h($asset['size'])?> · <?=h($asset['modified'])?></small>
              <code><?=h($asset['relative'])?></code>
            </div>
            <span class="badge <?=$asset['used']?'live':'wait'?>"><?=$asset['used']?'Kullanılıyor':'Kütüphanede'?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="section-head-actions"><div><h2>Yeni İkon / Görsel Ekle</h2><p class="muted">Buradan eklediğin dosya <code>app/assets/img</code> klasörüne kaydedilir ve yukarıdaki listede görünür. Gerekirse sonra uygulama içinde bu dosyayı kullanırız.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="upload_extra_icon_asset">
      <div><label>Dosya adı</label><input class="field" name="asset_name" placeholder="Örn: yeni-hatim-ikon"></div>
      <div><label>İkon / Görsel dosyası</label><input class="field" type="file" name="asset_file" accept=".svg,.png,.jpg,.jpeg,.webp,.ico,image/*" required></div>
      <div style="grid-column:1/-1" class="actions"><button class="btn">Dosyayı Ekle & Cache Yenile</button></div>
    </form>
  </section>

  <section class="panel">
    <h2>PWA / Android / iPhone İkonları</h2>
    <p class="muted">Ana ekrana eklenen uygulama ikonları. Bunlar uygulama mağaza/PWA tarafı içindir; Hatim kart ikonu yukarıdaki özel alandan yönetilir.</p>
    <div class="icon-preview-grid">
      <?php foreach($iconDefs as $field => $def): $url='/app/assets/icons/'.$def['file'].'?v='.rawurlencode($iconVersion); ?>
        <div class="icon-preview-card">
          <img src="<?=h($url)?>" alt="<?=h($def['label'])?>" onerror="this.style.opacity=.18">
          <strong><?=h($def['label'])?></strong>
          <span><?=h($def['file'])?> · <?=h($def['size'])?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <h2>PWA İkon Yükle ve Cache Yenile</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_pwa_icons">
      <div><label>Yeni ikon/cache sürümü</label><input class="field" name="pwa_icon_version" value="<?=h($iconVersion)?>" placeholder="Örn: 1.0.80 veya 20260505"></div>
      <div><label>Otomatik yeni sürüm</label><select class="field" name="auto_bump"><option value="">Hayır, yukarıdaki sürümü kullan</option><option value="1">Evet, zamanı baz alarak yeni sürüm ver</option></select></div>
      <?php foreach($iconDefs as $field => $def): ?>
        <div>
          <label><?=h($def['label'])?> — <?=h($def['file'])?></label>
          <input class="field" type="file" name="<?=h($field)?>" accept="image/png">
          <div class="muted" style="font-size:12px;margin-top:5px">Önerilen: <?=h($def['size'])?> PNG</div>
        </div>
      <?php endforeach; ?>
      <div style="grid-column:1/-1" class="actions"><button class="btn">PWA İkonlarını Kaydet ve Cache Yenile</button></div>
    </form>
  </section>

  <section class="panel">
    <h2>Hızlı Cache Kırma</h2>
    <p class="muted">Dosyaları FTP/cPanel ile manuel değiştirdiysen, uygulamanın yeni görselleri görmesi için buradan cache sürümünü artır.</p>
    <form method="post" class="actions">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="bump_pwa_icon_cache">
      <button class="btn">Cache Sürümünü Artır</button>
    </form>
  </section>

<?php elseif($page === 'apk_build'): ?>
  <?php
    try_sql($pdo, "CREATE TABLE IF NOT EXISTS apk_build_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_uid VARCHAR(80) NOT NULL UNIQUE,
        build_type VARCHAR(40) NOT NULL DEFAULT 'debug_apk',
        version_name VARCHAR(40) NOT NULL DEFAULT '1.0.37',
        version_code INT NOT NULL DEFAULT 37,
        package_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
        status VARCHAR(40) NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        response_text TEXT NULL,
        artifact_url TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $builds = [];
    try { $builds = $pdo->query('SELECT * FROM apk_build_requests ORDER BY id DESC LIMIT 20')->fetchAll(); } catch(Throwable $e) {}
    $apiUrl = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/app.php?action=bootstrap');
    $callbackUrl = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/update/apk_build_callback.php');
  ?>
  <section class="panel build-hero build-premium-hero">
    <div>
      <span class="badge info">Android Yayın Hazırlığı</span>
      <h2>APK / AAB Build Merkezi</h2>
      <p class="muted">PHP hosting APK üretmez. Bu ekran GitHub Actions, harici build sistemi veya manuel takip için yayın hazırlığını düzenli tutar. Test için APK, Google Play için AAB kullanılır.</p>
    </div>
    <div class="build-hero-actions">
      <a class="btn secondary" href="https://github.com" target="_blank">GitHub Aç</a>
      <a class="btn secondary" href="https://play.google.com/console" target="_blank">Play Console Aç</a>
    </div>
  </section>

  <section class="panel build-guide-panel">
    <div class="section-head-actions"><div><h2>Android Build Yol Haritası</h2><p class="muted">APK/AAB üretim sürecini güvenli sırayla takip et.</p></div><span class="badge info">4 adım</span></div>
    <div class="build-guide-grid">
      <div class="build-guide-card"><strong>1. Test APK</strong><span>GitHub Actions içinde <b>debug_apk</b> çalıştırılır. Telefonda kurulum ve temel ekran testi yapılır.</span></div>
      <div class="build-guide-card"><strong>2. SSL / API Kontrol</strong><span>API adresi HTTPS ve güvenli olmalı. Dua, Hatim ve Toplu Zikir verileri sunucudan gelir.</span></div>
      <div class="build-guide-card"><strong>3. Play AAB</strong><span>Google Play için <b>AAB</b> çıktısı hazırlanır. İmza/keystore aşaması Play Console hatasına göre tamamlanır.</span></div>
      <div class="build-guide-card"><strong>4. Dahili Test</strong><span>Önce Production değil, Play Console Dahili Test üzerinden sınırlı kullanıcıyla denenir.</span></div>
    </div>
  </section>

  <section class="panel build-settings-panel">
    <div class="section-head-actions"><div><h2>Build Ayarları</h2><p class="muted">Android build modu, sürüm, webhook ve callback ayarları.</p></div><span class="badge wait">Android</span></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_apk_build_settings">
      <div><label>Build modu</label><select class="field" name="apk_build_mode"><option value="github_actions" <?=setting('apk_build_mode','external_webhook')==='github_actions'?'selected':''?>>GitHub Actions</option><option value="external_webhook" <?=setting('apk_build_mode','external_webhook')==='external_webhook'?'selected':''?>>Harici Webhook / CI</option><option value="manual" <?=setting('apk_build_mode','external_webhook')==='manual'?'selected':''?>>Manuel Takip</option></select><p class="field-help">GitHub ile çalışıyorsan GitHub Actions seçebilirsin. Webhook yoksa kayıt sadece admin panelde bekler.</p></div>
      <div><label>Varsayılan çıktı tipi</label><select class="field" name="apk_build_output_type"><option value="debug_apk" <?=setting('apk_build_output_type','debug_apk')==='debug_apk'?'selected':''?>>Test APK</option><option value="release_apk" <?=setting('apk_build_output_type','debug_apk')==='release_apk'?'selected':''?>>Release APK</option><option value="play_aab" <?=setting('apk_build_output_type','debug_apk')==='play_aab'?'selected':''?>>Google Play AAB</option></select></div>
      <div><label>Versiyon adı</label><input class="field" name="apk_build_version_name" value="<?=h(setting('apk_build_version_name','1.0.37'))?>"></div>
      <div><label>Versiyon kodu</label><input class="field" name="apk_build_version_code" value="<?=h(setting('apk_build_version_code','37'))?>"></div>
      <div style="grid-column:1/-1"><label>Webhook URL / GitHub Actions tetikleyici</label><input class="field" name="apk_build_webhook_url" value="<?=h(setting('apk_build_webhook_url',''))?>" placeholder="https://..."><p class="field-help">Boş bırakılırsa build talebi yalnızca kayıt olarak tutulur. GitHub manuel çalıştırılıyorsa bu normaldir.</p></div>
      <div style="grid-column:1/-1"><label>Webhook token</label><input class="field" name="apk_build_webhook_token" value="<?=h(setting('apk_build_webhook_token',''))?>" placeholder="Gizli token"></div>
      <div style="grid-column:1/-1"><label>Callback token</label><input class="field" name="apk_build_callback_token" value="<?=h(setting('apk_build_callback_token',''))?>" placeholder="Build sonucu geri gönderirken kullanılır"></div>
      <div style="grid-column:1/-1"><label>Callback adresi</label><input class="field" readonly value="<?=h($callbackUrl)?>"><p class="field-help">Harici build sistemi çıktı linkini buraya callback olarak gönderebilir.</p></div>
      <div style="grid-column:1/-1"><label>Canlı API kontrol adresi</label><input class="field" readonly value="<?=h($apiUrl)?>"><p class="field-help">APK içindeki online Dua/Hatim verileri bu API hattından gelir. SSL güvenli olmalıdır.</p></div>
      <div style="grid-column:1/-1"><label>Build notları</label><textarea class="field" name="apk_build_notes"><?=h(setting('apk_build_notes','Build işlemi dış GitHub Actions veya build sunucusu üzerinden yapılır.'))?></textarea></div>
      <button class="btn">Build Ayarlarını Kaydet</button>
    </form>
  </section>

  <section class="panel build-request-panel">
    <div class="section-head-actions"><div><h2>Yeni Android Build Talebi</h2><p class="muted">Test APK, Release APK veya Google Play AAB için kayıt oluştur.</p></div><span class="badge live">Yeni Talep</span></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="create_apk_build_request">
      <div><label>Çıktı tipi</label><select class="field" name="build_type"><option value="debug_apk">Test APK</option><option value="release_apk">Release APK</option><option value="play_aab">Google Play AAB</option></select></div>
      <div><label>Versiyon adı</label><input class="field" name="version_name" value="<?=h(setting('apk_build_version_name','1.0.37'))?>"></div>
      <div><label>Versiyon kodu</label><input class="field" name="version_code" value="<?=h(setting('apk_build_version_code','37'))?>"></div>
      <div style="grid-column:1/-1"><label>Paket ID</label><input class="field" name="package_id" value="<?=h(setting('android_package_id','com.ilhanbeluk.akillizikirhatim'))?>"><p class="field-help">Yayınlandıktan sonra paket ID değiştirilemez.</p></div>
      <div style="grid-column:1/-1"><label>Talep notu</label><textarea class="field" name="notes" placeholder="Örn: debug APK üret, API/SSL ve ikon testini kontrol et"></textarea></div>
      <button class="btn">APK / AAB Build Talebi Oluştur</button>
    </form>
  </section>

  <section class="panel build-history-panel">
    <div class="section-head-actions"><div><h2>Son Android Build Talepleri</h2><p class="muted">Build geçmişi, çıktı linki ve durum takibi.</p></div><span class="badge info"><?=number_format(count($builds),0,',','.')?> kayıt</span></div>
    <div class="table-wrap"><table><tr><th>Talep</th><th>Tip</th><th>Versiyon</th><th>Durum</th><th>APK/AAB</th><th>Tarih</th></tr><?php foreach($builds as $b): ?><tr><td><strong><?=h($b['request_uid'])?></strong><?php if(!empty($b['notes'])): ?><div class="muted"><?=h($b['notes'])?></div><?php endif; ?></td><td><?=h(admin_apk_type_label($b['build_type']))?></td><td><?=h($b['version_name'])?> / <?=h($b['version_code'])?></td><td><span class="badge <?=h(admin_build_status_class($b['status']))?>"><?=h(admin_build_status_label($b['status']))?></span></td><td><?php if(!empty($b['artifact_url'])): ?><a class="btn" href="<?=h($b['artifact_url'])?>" target="_blank">İndir</a><?php else: ?><span class="muted">Henüz çıktı yok</span><?php endif; ?></td><td><?=h($b['created_at'])?></td></tr>
      <tr><td colspan="6"><form method="post" class="form-grid build-result-form"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="update_apk_build_result"><input type="hidden" name="id" value="<?=h($b['id'])?>"><select class="field" name="status"><option value="pending" <?=$b['status']==='pending'?'selected':''?>>Bekliyor</option><option value="sent" <?=$b['status']==='sent'?'selected':''?>>Gönderildi</option><option value="success" <?=$b['status']==='success'?'selected':''?>>Başarılı</option><option value="failed" <?=$b['status']==='failed'?'selected':''?>>Başarısız</option><option value="cancelled" <?=$b['status']==='cancelled'?'selected':''?>>İptal</option></select><input class="field" name="artifact_url" value="<?=h($b['artifact_url'] ?? '')?>" placeholder="APK/AAB indirme linki"><input class="field" name="response_text" value="<?=h($b['response_text'] ?? '')?>" placeholder="Build notu / hata özeti"><button class="btn">Güncelle</button></form></td></tr><?php endforeach; ?></table></div>
    <p class="muted">Webhook ayarlanmadıysa build talepleri burada kayıt olarak bekler. GitHub Actions manuel çalıştırılıyorsa çıktı linkini bu tabloya elle işleyebilirsin.</p>
  </section>


<?php elseif($page === 'ios_build'): ?>
  <?php
    try_sql($pdo, "CREATE TABLE IF NOT EXISTS ios_build_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_uid VARCHAR(80) NOT NULL UNIQUE,
        build_type VARCHAR(40) NOT NULL DEFAULT 'testflight',
        version_name VARCHAR(40) NOT NULL DEFAULT '1.0.39',
        build_number INT NOT NULL DEFAULT 39,
        bundle_id VARCHAR(160) NOT NULL DEFAULT 'com.ilhanbeluk.akillizikirhatim',
        status VARCHAR(40) NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        response_text TEXT NULL,
        artifact_url TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $iosBuilds = [];
    try { $iosBuilds = $pdo->query('SELECT * FROM ios_build_requests ORDER BY id DESC LIMIT 20')->fetchAll(); } catch(Throwable $e) {}
    $iosCallbackUrl = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/update/ios_build_callback.php');
  ?>
  <section class="panel build-hero build-premium-hero">
    <div>
      <span class="badge info">iPhone / iOS Hazırlığı</span>
      <h2>iOS / App Store Merkezi</h2>
      <p class="muted">iPhone kullanıcıları için iki yol var: ücretsiz PWA kurulum veya Apple Developer üyeliğiyle TestFlight / App Store dağıtımı. Bu ekran iOS hazırlığını ve build kayıtlarını düzenli tutar.</p>
    </div>
    <div class="build-hero-actions">
      <a class="btn secondary" href="https://developer.apple.com/account/" target="_blank">Apple Developer</a>
      <a class="btn secondary" href="https://appstoreconnect.apple.com/" target="_blank">App Store Connect</a>
    </div>
  </section>

  <section class="panel build-guide-panel ios-guide-panel">
    <div class="section-head-actions"><div><h2>iOS Dağıtım Yol Haritası</h2><p class="muted">PWA, TestFlight ve App Store dağıtım sürecini düzenli takip et.</p></div><span class="badge info">4 adım</span></div>
    <div class="build-guide-grid">
      <div class="build-guide-card"><strong>1. Ücretsiz PWA</strong><span>Apple üyeliği olmadan Safari > Paylaş > Ana Ekrana Ekle ile uygulama gibi kullanılabilir.</span></div>
      <div class="build-guide-card"><strong>2. iOS Proje ZIP</strong><span>GitHub Actions ile iOS proje ZIP’i alınır. Mac/Xcode veya imzalama aşamasında kullanılır.</span></div>
      <div class="build-guide-card"><strong>3. TestFlight</strong><span>Apple Developer üyeliği gerekir. Arkadaşlara e-posta veya public link ile test dağıtımı yapılır.</span></div>
      <div class="build-guide-card"><strong>4. App Store</strong><span>TestFlight doğrulandıktan sonra mağaza metinleri, gizlilik ve sürüm bilgileriyle yayınlanır.</span></div>
    </div>
  </section>

  <section class="panel build-settings-panel ios-settings-panel">
    <div class="section-head-actions"><div><h2>iOS Ayarları</h2><p class="muted">Bundle ID, Apple Team, TestFlight ve App Store hazırlık ayarları.</p></div><span class="badge wait">iOS</span></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="save_ios_build_settings">
      <div><label>iOS Bundle ID</label><input class="field" name="ios_bundle_id" value="<?=h(setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim'))?>"><p class="field-help">App Store tarafındaki uygulama kimliği. Yayınlandıktan sonra değiştirilmemelidir.</p></div>
      <div><label>Apple Team ID</label><input class="field" name="ios_team_id" value="<?=h(setting('ios_team_id',''))?>" placeholder="Apple Developer Team ID"></div>
      <div><label>App Store Connect App ID</label><input class="field" name="ios_app_store_connect_app_id" value="<?=h(setting('ios_app_store_connect_app_id',''))?>"></div>
      <div><label>SKU</label><input class="field" name="ios_sku" value="<?=h(setting('ios_sku','akilli-zikir-hatim'))?>"></div>
      <div><label>Versiyon adı</label><input class="field" name="ios_version_name" value="<?=h(setting('ios_version_name','1.0.39'))?>"></div>
      <div><label>Build numarası</label><input class="field" name="ios_build_number" value="<?=h(setting('ios_build_number','39'))?>"></div>
      <div><label>Varsayılan çıktı tipi</label><select class="field" name="ios_build_output_type"><option value="ios_project_zip" <?=setting('ios_build_output_type','testflight')==='ios_project_zip'?'selected':''?>>iOS Proje ZIP</option><option value="simulator_debug" <?=setting('ios_build_output_type','testflight')==='simulator_debug'?'selected':''?>>Simülatör Testi</option><option value="testflight" <?=setting('ios_build_output_type','testflight')==='testflight'?'selected':''?>>TestFlight</option><option value="app_store" <?=setting('ios_build_output_type','testflight')==='app_store'?'selected':''?>>App Store Release</option><option value="adhoc" <?=setting('ios_build_output_type','testflight')==='adhoc'?'selected':''?>>Ad Hoc IPA</option></select></div>
      <div><label>TestFlight durumu</label><select class="field admin-onoff" name="ios_testflight_enabled"><option value="1" <?=setting('ios_testflight_enabled','1')==='1'?'selected':''?>>Hazırlık açık</option><option value="0" <?=setting('ios_testflight_enabled','1')==='0'?'selected':''?>>Şimdilik kapalı</option></select><p class="field-help">Apple Developer üyeliği yoksa PWA paylaşım yolunu kullan.</p></div>
      <div style="grid-column:1/-1"><label>iOS webhook URL / CI tetikleyici</label><input class="field" name="ios_build_webhook_url" value="<?=h(setting('ios_build_webhook_url',''))?>" placeholder="https://..."></div>
      <div style="grid-column:1/-1"><label>Webhook token</label><input class="field" name="ios_build_webhook_token" value="<?=h(setting('ios_build_webhook_token',''))?>"></div>
      <div style="grid-column:1/-1"><label>Callback token</label><input class="field" name="ios_build_callback_token" value="<?=h(setting('ios_build_callback_token',''))?>"></div>
      <div style="grid-column:1/-1"><label>Callback adresi</label><input class="field" readonly value="<?=h($iosCallbackUrl)?>"></div>
      <div style="grid-column:1/-1"><label>Gizlilik politikası URL</label><input class="field" name="ios_privacy_policy_url" value="<?=h(setting('ios_privacy_policy_url',''))?>"></div>
      <div style="grid-column:1/-1"><label>Destek URL</label><input class="field" name="ios_support_url" value="<?=h(setting('ios_support_url',''))?>"></div>
      <div style="grid-column:1/-1"><label>Pazarlama URL</label><input class="field" name="ios_marketing_url" value="<?=h(setting('ios_marketing_url',''))?>"></div>
      <div style="grid-column:1/-1"><label>iOS build notları</label><textarea class="field" name="ios_build_notes"><?=h(setting('ios_build_notes','iOS build için Mac/Xcode ve Apple Developer hesabı gerekir. Önce TestFlight, sonra App Store release önerilir.'))?></textarea></div>
      <button class="btn">iOS Ayarlarını Kaydet</button>
    </form>
  </section>

  <section class="panel build-request-panel ios-request-panel">
    <div class="section-head-actions"><div><h2>Yeni iOS Build Talebi</h2><p class="muted">iOS proje ZIP, TestFlight veya App Store release kaydı oluştur.</p></div><span class="badge live">Yeni Talep</span></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?=csrf_token()?>">
      <input type="hidden" name="act" value="create_ios_build_request">
      <div><label>Çıktı tipi</label><select class="field" name="build_type"><option value="ios_project_zip">iOS Proje ZIP</option><option value="simulator_debug">Simülatör Testi</option><option value="testflight">TestFlight</option><option value="app_store">App Store Release</option><option value="adhoc">Ad Hoc IPA</option></select></div>
      <div><label>Versiyon adı</label><input class="field" name="version_name" value="<?=h(setting('ios_version_name','1.0.39'))?>"></div>
      <div><label>Build numarası</label><input class="field" name="build_number" value="<?=h(setting('ios_build_number','39'))?>"></div>
      <div style="grid-column:1/-1"><label>Bundle ID</label><input class="field" name="bundle_id" value="<?=h(setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim'))?>"></div>
      <div style="grid-column:1/-1"><label>Talep notu</label><textarea class="field" name="notes" placeholder="Örn: iOS proje ZIP üret veya TestFlight hazırlığı için kayıt aç"></textarea></div>
      <button class="btn">iOS Build Talebi Oluştur</button>
    </form>
  </section>

  <section class="panel build-check-panel ios-check-panel">
    <div class="section-head-actions"><div><h2>App Store / PWA Hazırlık Kontrolü</h2><p class="muted">iPhone tarafında PWA ve App Store için kritik alanlar.</p></div><span class="badge info">Kontrol</span></div>
    <div class="table-wrap"><table>
      <tr><th>Kontrol</th><th>Durum</th><th>Not</th></tr>
      <tr><td>iPhone PWA yolu</td><td><span class="badge live">Ücretsiz kullanılabilir</span></td><td>Safari > Paylaş > Ana Ekrana Ekle</td></tr>
      <tr><td>Apple Developer hesabı</td><td><span class="badge wait">Manuel kontrol</span></td><td>TestFlight / App Store için gerekir.</td></tr>
      <tr><td>Bundle ID</td><td><?=h(setting('ios_bundle_id','com.ilhanbeluk.akillizikirhatim'))?></td><td>App Store Connect ile aynı olmalıdır.</td></tr>
      <tr><td>Gizlilik Politikası URL</td><td><?=setting('ios_privacy_policy_url','') ? h(setting('ios_privacy_policy_url','')) : '<span class="badge off">Eksik</span>'?></td><td>App Store formunda gerekir.</td></tr>
      <tr><td>TestFlight</td><td><?=setting('ios_testflight_enabled','1')==='1' ? '<span class="badge live">Hazırlık açık</span>' : '<span class="badge off">Kapalı</span>'?></td><td>Apple Developer üyeliği aktif olunca kullanılır.</td></tr>
      <tr><td>Yayımcı/Geliştirici</td><td><?=h(setting('publisher_name','İlhan BELUK'))?> / <?=h(setting('developer_name','İlhan BELUK'))?></td><td>Mağaza bilgileriyle tutarlı olmalı.</td></tr>
    </table></div>
  </section>

  <section class="panel build-history-panel ios-history-panel">
    <div class="section-head-actions"><div><h2>Son iOS Build Talepleri</h2><p class="muted">iOS build kayıtları, çıktı linkleri ve durum notları.</p></div><span class="badge info"><?=number_format(count($iosBuilds),0,',','.')?> kayıt</span></div>
    <div class="table-wrap"><table><tr><th>Talep</th><th>Tip</th><th>Versiyon</th><th>Durum</th><th>Çıktı</th><th>Tarih</th></tr><?php foreach($iosBuilds as $b): ?><tr><td><strong><?=h($b['request_uid'])?></strong><?php if(!empty($b['notes'])): ?><div class="muted"><?=h($b['notes'])?></div><?php endif; ?></td><td><?=h(admin_ios_type_label($b['build_type']))?></td><td><?=h($b['version_name'])?> / <?=h($b['build_number'])?></td><td><span class="badge <?=h(admin_build_status_class($b['status']))?>"><?=h(admin_build_status_label($b['status']))?></span></td><td><?php if(!empty($b['artifact_url'])): ?><a class="btn" href="<?=h($b['artifact_url'])?>" target="_blank">Aç</a><?php else: ?><span class="muted">Henüz çıktı yok</span><?php endif; ?></td><td><?=h($b['created_at'])?></td></tr>
      <tr><td colspan="6"><form method="post" class="form-grid build-result-form"><input type="hidden" name="_token" value="<?=csrf_token()?>"><input type="hidden" name="act" value="update_ios_build_result"><input type="hidden" name="id" value="<?=h($b['id'])?>"><select class="field" name="status"><option value="pending" <?=$b['status']==='pending'?'selected':''?>>Bekliyor</option><option value="sent" <?=$b['status']==='sent'?'selected':''?>>Gönderildi</option><option value="success" <?=$b['status']==='success'?'selected':''?>>Başarılı</option><option value="failed" <?=$b['status']==='failed'?'selected':''?>>Başarısız</option><option value="cancelled" <?=$b['status']==='cancelled'?'selected':''?>>İptal</option></select><input class="field" name="artifact_url" value="<?=h($b['artifact_url'] ?? '')?>" placeholder="Çıktı / TestFlight linki"><input class="field" name="response_text" value="<?=h($b['response_text'] ?? '')?>" placeholder="Build notu / hata özeti"><button class="btn">Güncelle</button></form></td></tr><?php endforeach; ?></table></div>
  </section>


<?php elseif($page === 'maintenance'): ?>
  <?php
    $root = dirname(__DIR__);
    $configFile = $root . '/system/config.php';
    $installFile = $root . '/install.php';
    $allowInstall = $root . '/system/ALLOW_INSTALL';
    $adminCssFile = __DIR__ . '/assets/admin.css';
    $appCssFile = $root . '/app/assets/css/app.css';
    $appJsFile = $root . '/app/assets/js/app.js';
    $swFile = $root . '/service-worker.js';
    $manifestFile = $root . '/manifest.json';
    $apiFile = $root . '/api/app.php';
    $storageDir = $root . '/storage';
    $backupDir = $root . '/storage/backups/db';
    $appVersion = admin_effective_app_version($pdo);
    $pwaCache = (string)(setting('pwa_cache_version', '') ?? '');
    $adminCssVersion = admin_css_version();
    $dbOk = false; $dbInfo = 'Kontrol edilemedi';
    try {
        $dbOk = (bool)$pdo->query('SELECT 1')->fetchColumn();
        $dbInfo = $dbOk ? 'MySQL bağlantısı çalışıyor' : 'MySQL yanıt vermedi';
    } catch(Throwable $e) { $dbInfo = $e->getMessage(); }

    $checks = [
        admin_maintenance_check('install.php güvenliği', !is_file($installFile) || (is_file($installFile) && !is_file($allowInstall)), is_file($installFile) ? (is_file($allowInstall) ? 'ALLOW_INSTALL açık: bakım dışında kapatılmalı' : 'Dosya var ama kurulu sistemde kilitli olmalı') : 'install.php yok', is_file($allowInstall) ? 'danger' : 'ok'),
        admin_maintenance_check('Config dosyası', is_file($configFile), is_file($configFile) ? 'system/config.php mevcut' : 'system/config.php eksik', is_file($configFile) ? 'ok' : 'danger'),
        admin_maintenance_check('Config yazılabilirliği', is_file($configFile) && !is_writable($configFile), is_file($configFile) ? (is_writable($configFile) ? 'Yazılabilir: mümkünse 640/440 gibi daha kapalı izin kullan' : 'Yazılamaz durumda') : 'Config yok', is_file($configFile) && is_writable($configFile) ? 'warn' : 'ok'),
        admin_maintenance_check('APP_VERSION', version_compare((string)$appVersion, '1.2.37', '>='), 'Mevcut: ' . $appVersion, version_compare((string)$appVersion, '1.2.37', '>=') ? 'ok' : 'warn'),
        admin_maintenance_check('PWA cache ayarı', trim($pwaCache) !== '', $pwaCache !== '' ? $pwaCache : 'Boş görünüyor', $pwaCache !== '' ? 'ok' : 'warn'),
        admin_maintenance_check('Admin CSS cache', trim($adminCssVersion) !== '', $adminCssVersion !== '' ? $adminCssVersion : 'Boş görünüyor', $adminCssVersion !== '' ? 'ok' : 'warn'),
        admin_maintenance_check('Veritabanı bağlantısı', $dbOk, $dbInfo, $dbOk ? 'ok' : 'danger'),
        admin_maintenance_check('API dosyası', is_file($apiFile), '/api/app.php', is_file($apiFile) ? 'ok' : 'danger'),
        admin_maintenance_check('Service Worker', is_file($swFile), '/service-worker.js', is_file($swFile) ? 'ok' : 'warn'),
        admin_maintenance_check('Manifest', is_file($manifestFile), '/manifest.json', is_file($manifestFile) ? 'ok' : 'warn'),
        admin_maintenance_check('Mobil CSS/JS', is_file($appCssFile) && is_file($appJsFile), 'app.css / app.js', is_file($appCssFile) && is_file($appJsFile) ? 'ok' : 'danger'),
        admin_maintenance_check('Storage klasörü', is_dir($storageDir) || is_writable($root), is_dir($storageDir) ? (is_writable($storageDir) ? 'storage yazılabilir' : 'storage var ama yazılamıyor') : 'storage yok, gerekirse oluşturulacak', (is_dir($storageDir) && !is_writable($storageDir)) ? 'warn' : 'ok'),
        admin_maintenance_check('Yedek klasörü', is_dir($backupDir) || is_writable($root), is_dir($backupDir) ? (is_writable($backupDir) ? 'backup klasörü yazılabilir' : 'backup klasörü var ama yazılamıyor') : 'Henüz oluşturulmamış', (is_dir($backupDir) && !is_writable($backupDir)) ? 'warn' : 'ok'),
    ];
    $okCount = count(array_filter($checks, fn($c) => $c['level'] === 'ok'));
    $warnCount = count(array_filter($checks, fn($c) => $c['level'] === 'warn'));
    $dangerCount = count(array_filter($checks, fn($c) => $c['level'] === 'danger'));
    $totalChecks = count($checks);
    $score = $totalChecks ? round(($okCount / $totalChecks) * 100) : 0;
    $recentBackups = admin_recent_backup_files(8);
    $phpLogCandidates = array_filter([
        ini_get('error_log') ?: '',
        $root . '/error_log',
        $root . '/admin/error_log',
        $root . '/api/error_log',
    ]);
    $logLines = [];
    $logFileUsed = '';
    foreach ($phpLogCandidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $logFileUsed = $candidate;
            $logLines = admin_tail_file($candidate, 60);
            break;
        }
    }
    $dangerItems = array_values(array_filter($checks, fn($c) => $c['level'] === 'danger'));
    $warnItems = array_values(array_filter($checks, fn($c) => $c['level'] === 'warn'));
    $maintenanceStatusClass = $dangerCount ? 'danger' : ($warnCount ? 'warn' : 'ok');
    $maintenanceStatusText = $dangerCount ? 'Önce kritik uyarıları çöz' : ($warnCount ? 'Yayın öncesi kontrol gerekli' : 'Bakım durumu temiz');
    $fileStatusRows = [
        ['Admin CSS', $adminCssFile],
        ['Mobil CSS', $appCssFile],
        ['Mobil JS', $appJsFile],
        ['Service Worker', $swFile],
        ['Manifest', $manifestFile],
        ['API', $apiFile],
    ];
    $apiSecurityTotal = 0; $apiSecurityToday = 0; $apiSecurityRecent = [];
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_security_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            action_name VARCHAR(80) NOT NULL,
            client_key VARCHAR(160) NULL,
            ip_address VARCHAR(80) NULL,
            reason VARCHAR(160) NOT NULL,
            payload_digest CHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_created (action_name, created_at),
            INDEX idx_client_created (client_key, created_at),
            INDEX idx_reason_created (reason, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $apiSecurityTotal = (int)$pdo->query("SELECT COUNT(*) FROM api_security_events")->fetchColumn();
        $apiSecurityToday = (int)$pdo->query("SELECT COUNT(*) FROM api_security_events WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $apiSecurityRecent = $pdo->query("SELECT action_name, client_key, ip_address, reason, created_at FROM api_security_events ORDER BY id DESC LIMIT 8")->fetchAll();
    } catch (Throwable $e) {}
  ?>
  <section class="panel maintenance-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Sistem Bakımı</span>
        <h2>Bakım ve Sistem Sağlığı Merkezi</h2>
        <p class="muted">Kurulum güvenliği, cache, sürüm, API, veritabanı ve dosya yazılabilirlik durumlarını tek ekrandan kontrol et.</p>
      </div>
      <div class="maintenance-score"><b>%<?=$score?></b><span><?=$okCount?> / <?=$totalChecks?> sağlıklı</span></div>
    </div>
    <div class="maintenance-kpi-grid">
      <div><b><?=number_format($dangerCount,0,',','.')?></b><span>Kritik Uyarı</span></div>
      <div><b><?=number_format($warnCount,0,',','.')?></b><span>Kontrol Gerekli</span></div>
      <div><b><?=h($appVersion)?></b><span>APP_VERSION</span></div>
      <div><b><?=h(PHP_VERSION)?></b><span>PHP Sürümü</span></div>
    </div>
  </section>

  <section class="panel maintenance-priority-panel <?=$maintenanceStatusClass?>">
    <div class="section-head-actions">
      <div>
        <span class="badge <?=$dangerCount ? 'off' : ($warnCount ? 'wait' : 'live')?>"><?=h($maintenanceStatusText)?></span>
        <h2>Öncelikli Bakım Özeti</h2>
        <p class="muted">Bu alan, yayın öncesi önce bakılması gereken maddeleri sade şekilde gösterir.</p>
      </div>
      <form method="post">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="refresh_all_runtime_cache">
        <button class="btn">Tüm Cache Yenile</button>
      </form>
    </div>
    <?php if(!$dangerItems && !$warnItems): ?>
      <div class="maintenance-priority-clean">Kritik uyarı görünmüyor. Yine de final yayın öncesi mobil ve admin akışları elle test edilmelidir.</div>
    <?php else: ?>
      <div class="maintenance-priority-list">
        <?php foreach(array_slice(array_merge($dangerItems, $warnItems), 0, 6) as $item): ?>
          <div class="maintenance-priority-item <?=$item['level']?>">
            <b><?=$item['level']==='danger'?'Kritik':'Kontrol'?></b>
            <strong><?=h($item['label'])?></strong>
            <span><?=h($item['detail'])?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel maintenance-cache-panel">
    <div class="section-head-actions">
      <div><h2>Sürüm ve Cache Özeti</h2><p class="muted">APP_VERSION, PWA cache, admin cache ve dosya güncellik bilgileri tek tabloda.</p></div>
      <span class="badge info">v<?=h($appVersion)?></span>
    </div>
    <div class="maintenance-cache-grid">
      <div><span>Yayın Sürümü</span><b><?=h($appVersion)?></b></div>
      <div><span>PWA Cache</span><b><?=h($pwaCache ?: 'Boş')?></b></div>
      <div><span>Admin CSS Cache</span><b><?=h($adminCssVersion ?: 'Boş')?></b></div>
      <div><span>PHP</span><b><?=h(PHP_VERSION)?></b></div>
    </div>
    <div class="table-wrap maintenance-file-table"><table><tr><th>Dosya</th><th>Durum</th><th>Son Değişim</th></tr>
      <?php foreach($fileStatusRows as [$label, $file]): ?><tr><td><strong><?=h($label)?></strong><div class="muted"><?=h(str_replace($root . '/', '', $file))?></div></td><td><?=is_file($file) ? '<span class="badge live">Var</span>' : '<span class="badge off">Yok</span>'?></td><td><?=is_file($file) ? date('d.m.Y H:i:s', (int)@filemtime($file)) : '-'?></td></tr><?php endforeach; ?>
    </table></div>
  </section>

  <section class="panel maintenance-check-panel">
    <div class="section-head-actions">
      <div><h2>Sistem Sağlık Kontrolleri</h2><p class="muted">Kırmızı maddeler önce çözülmeli, sarı maddeler yayın öncesi kontrol edilmelidir.</p></div>
      <span class="badge <?=$dangerCount ? 'off' : ($warnCount ? 'wait' : 'live')?>"><?=$dangerCount ? 'Kritik var' : ($warnCount ? 'Kontrol var' : 'Temiz')?></span>
    </div>
    <div class="maintenance-check-grid">
      <?php foreach($checks as $c): ?>
        <div class="maintenance-check-card <?=$c['level']?>">
          <div class="maintenance-check-icon"><?=$c['level']==='ok'?'✓':($c['level']==='warn'?'!':'×')?></div>
          <div><strong><?=h($c['label'])?></strong><p><?=h($c['detail'])?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel maintenance-actions-panel">
    <div class="section-head-actions">
      <div><h2>Bakım İşlemleri</h2><p class="muted">Veritabanı yedeği alabilir, cache durumunu görebilir ve hızlı bakım adımlarını takip edebilirsin.</p></div>
      <span class="badge info">Güvenli Bakım</span>
    </div>
    <div class="maintenance-action-grid">
      <form method="post">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="refresh_all_runtime_cache">
        <button class="maintenance-action-card primary" type="submit"><strong>Tüm Cache Yenile</strong><span>Admin, PWA, mobil CSS/JS ve service worker cache sürümlerini birlikte yeniler.</span></button>
      </form>
      <form method="post" data-admin-confirm="Veritabanı yedeği oluşturulsun mu? Dosya storage/backups/db klasörüne yazılır.">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="create_db_backup">
        <button class="maintenance-action-card" type="submit"><strong>Veritabanı Yedeği Al</strong><span>Tüm tablolar için SQL yedeği oluşturur.</span></button>
      </form>
      <form method="post">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="refresh_admin_css_cache">
        <button class="maintenance-action-card" type="submit"><strong>Admin CSS Cache Yenile</strong><span>Eski admin görünümü/cache sorunlarını kırar.</span></button>
      </form>
      <form method="post">
        <input type="hidden" name="_token" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="bump_pwa_icon_cache">
        <button class="maintenance-action-card" type="submit"><strong>PWA Cache Yenile</strong><span>Mobil ikon/manifest/service worker cache sürümünü artırır.</span></button>
      </form>
      <a class="maintenance-action-card" href="?page=release_checklist"><strong>Yayın Kontrolüne Git</strong><span>Mağaza ve PWA yayın öncesi kontrolleri açar.</span></a>
    </div>
  </section>

  <section class="panel maintenance-api-security-panel">
    <div class="section-head-actions">
      <div><h2>API Güvenlik Olayları</h2><p class="muted">Spam, tekrar, yasaklı kelime ve rate-limit engelleri burada özetlenir.</p></div>
      <span class="badge <?=$apiSecurityToday>0?'wait':'live'?>"><?=number_format($apiSecurityToday,0,',','.')?> bugün</span>
    </div>
    <div class="maintenance-cache-grid">
      <div><span>Toplam Olay</span><b><?=number_format($apiSecurityTotal,0,',','.')?></b></div>
      <div><span>Bugün</span><b><?=number_format($apiSecurityToday,0,',','.')?></b></div>
      <div><span>Korunan Alan</span><b>Dua API</b></div>
      <div><span>Durum</span><b><?=$apiSecurityToday>0?'Kontrol Et':'Temiz'?></b></div>
    </div>
    <?php if(!$apiSecurityRecent): ?>
      <div class="empty-state">Henüz API güvenlik olayı kaydedilmemiş.</div>
    <?php else: ?>
      <div class="table-wrap"><table><tr><th>İşlem</th><th>Sebep</th><th>Client/IP</th><th>Tarih</th></tr>
        <?php foreach($apiSecurityRecent as $ev): ?><tr><td><?=h($ev['action_name'])?></td><td><span class="badge wait"><?=h($ev['reason'])?></span></td><td><?=h(($ev['client_key'] ?: '-') . ' / ' . ($ev['ip_address'] ?: '-'))?></td><td><?=h($ev['created_at'])?></td></tr><?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="panel maintenance-backup-panel">
    <div class="section-head-actions">
      <div><h2>Son Veritabanı Yedekleri</h2><p class="muted">Yedekler webden doğrudan indirilmemesi için storage/backups/db altında tutulur.</p></div>
      <span class="badge info"><?=number_format(count($recentBackups),0,',','.')?> kayıt</span>
    </div>
    <?php if(!$recentBackups): ?>
      <div class="empty-state">Henüz veritabanı yedeği oluşturulmamış.</div>
    <?php else: ?>
      <div class="table-wrap"><table><tr><th>Dosya</th><th>Boyut</th><th>Tarih</th><th>Konum</th></tr>
        <?php foreach($recentBackups as $file): ?><tr><td><strong><?=h(basename($file))?></strong></td><td><?=h(admin_format_bytes((int)@filesize($file)))?></td><td><?=date('d.m.Y H:i:s', (int)@filemtime($file))?></td><td><code>storage/backups/db</code></td></tr><?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="panel maintenance-log-panel">
    <div class="section-head-actions">
      <div><h2>Son PHP Hata Logları</h2><p class="muted">Sunucuda okunabilir hata logu bulunursa son satırlar burada görünür.</p></div>
      <span class="badge <?=$logLines ? 'wait' : 'info'?>"><?=$logLines ? 'Log bulundu' : 'Log yok'?></span>
    </div>
    <?php if(!$logLines): ?>
      <div class="empty-state">Okunabilir PHP hata logu bulunamadı. Bu her zaman hata olmadığı anlamına gelmez; hosting logları panelde tutuluyor olabilir.</div>
    <?php else: ?>
      <p class="muted">Kaynak: <?=h($logFileUsed)?></p>
      <pre class="maintenance-log-box"><?=h(implode("\n", $logLines))?></pre>
    <?php endif; ?>
  </section>


<?php elseif($page === 'updates'):
  $versions=$pdo->query('SELECT * FROM app_versions ORDER BY id DESC')->fetchAll();
  $latestVersion=$versions[0] ?? null;
  $totalVersions=count($versions);
  $adminCssVersion = admin_css_version();
  $pwaCacheVersion = (string)(setting('pwa_cache_version', defined('APP_VERSION') ? APP_VERSION : '') ?? '');
?>
  <section class="panel updates-premium-hero">
    <div class="section-head-actions">
      <div>
        <span class="badge info">Bakım Merkezi</span>
        <h2>Güncelleme ve Sürüm Takibi</h2>
        <p class="muted">Yüklenen update paketleri, uygulanan sürümler, admin CSS cache ve PWA cache durumunu tek ekrandan takip et.</p>
      </div>
      <div class="updates-score">
        <b><?=h($latestVersion['version'] ?? '—')?></b>
        <span>son uygulanan sürüm</span>
      </div>
    </div>
    <div class="updates-kpi-grid">
      <div><b><?=number_format($totalVersions,0,',','.')?></b><span>Toplam Update Kaydı</span></div>
      <div><b><?=h($adminCssVersion ?: '-')?></b><span>Admin CSS Cache</span></div>
      <div><b><?=h($pwaCacheVersion ?: '-')?></b><span>PWA Cache</span></div>
      <div><b><?=h($latestVersion['applied_at'] ?? '-')?></b><span>Son Uygulama Tarihi</span></div>
    </div>
  </section>

  <section class="panel updates-guide-panel">
    <div class="section-head-actions">
      <div>
        <h2>Güvenli Güncelleme Sırası</h2>
        <p class="muted">Her yeni update paketinde aynı sırayı kullan; böylece eski cache veya eksik dosya riski azalır.</p>
      </div>
      <span class="badge live">Önerilen sıra</span>
    </div>
    <div class="update-step-grid">
      <div class="update-step"><b>1</b><strong>ZIP’i yükle</strong><p>Update ZIP içeriğini site ana dizinine çıkar ve mevcut dosyaların üzerine yazdır.</p></div>
      <div class="update-step"><b>2</b><strong>Updater çalıştır</strong><p><code>/update/updater.php</code> dosyasını admin açıkken bir kez çalıştır.</p></div>
      <div class="update-step"><b>3</b><strong>CSS cache yenile</strong><p>İkon & Cache Merkezi üzerinden Admin CSS Cache Yenile butonuna bas.</p></div>
      <div class="update-step"><b>4</b><strong>Kontrol et</strong><p>Dashboard, ilgili sayfa ve mobil uygulama önizlemesini kontrol et.</p></div>
    </div>
  </section>

  <section class="panel updates-history-panel">
    <div class="section-head-actions">
      <div>
        <h2>Uygulanan Sürümler</h2>
        <p class="muted">Sisteme işlenmiş update geçmişi. En yeni kayıt en üsttedir.</p>
      </div>
      <span class="badge info"><?=number_format($totalVersions,0,',','.')?> kayıt</span>
    </div>
    <div class="table-wrap"><table class="updates-table"><tr><th>Sürüm</th><th>Açıklama</th><th>Tarih</th></tr><?php foreach($versions as $v): ?><tr><td><span class="badge live"><?=h($v['version'])?></span></td><td><?=h($v['description'])?></td><td><?=h($v['applied_at'])?></td></tr><?php endforeach; ?></table></div>
  </section>
<?php endif; ?></main></div>
<script>
(() => {
  function escAdmin(v){return String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
  function adminConfirm(message){
    return new Promise(resolve => {
      const overlay=document.createElement('div');
      overlay.className='admin-confirm-backdrop';
      overlay.setAttribute('role','dialog');
      overlay.setAttribute('aria-modal','true');
      overlay.innerHTML=`<div class="admin-confirm-card"><div class="admin-confirm-mark">☾</div><h2>İşlem Onayı</h2><p>${escAdmin(message)}</p><div class="admin-confirm-actions"><button type="button" class="admin-confirm-cancel">İptal</button><button type="button" class="admin-confirm-ok">Tamam</button></div></div>`;
      document.body.appendChild(overlay);
      const finish = value => { document.removeEventListener('keydown', onKey); overlay.classList.add('is-leaving'); setTimeout(()=>overlay.remove(),120); resolve(value); };
      const onKey = ev => { if(ev.key === 'Escape') finish(false); if(ev.key === 'Enter') finish(true); };
      overlay.querySelector('.admin-confirm-cancel')?.addEventListener('click',()=>finish(false));
      overlay.querySelector('.admin-confirm-ok')?.addEventListener('click',()=>finish(true));
      overlay.addEventListener('click', ev => { if(ev.target === overlay) finish(false); });
      document.addEventListener('keydown', onKey);
      requestAnimationFrame(()=>overlay.classList.add('is-visible'));
    });
  }
  document.addEventListener('submit', async ev => {
    const form = ev.target?.closest?.('form[data-admin-confirm]');
    if(!form || form.dataset.confirmed === '1') return;
    ev.preventDefault();
    ev.stopPropagation();
    const ok = await adminConfirm(form.dataset.adminConfirm || 'Bu işlem yapılsın mı?');
    if(!ok) return;
    form.dataset.confirmed = '1';
    HTMLFormElement.prototype.submit.call(form);
  }, true);
})();
</script>
</body></html>
