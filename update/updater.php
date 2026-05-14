<?php
require_once __DIR__ . '/../system/auth.php';
require_admin();
$pdo = db();

function try_sql(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch (Throwable $e) {}
}

try_sql($pdo, "CREATE TABLE IF NOT EXISTS app_versions (
    version VARCHAR(40) PRIMARY KEY,
    description TEXT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$root = dirname(__DIR__);
$versionStamp = date('YmdHis');

try { upsert_setting('app_release_version', '1.2.72'); } catch (Throwable $e) {}
try { upsert_setting('admin_css_version', $versionStamp); } catch (Throwable $e) {}
try { upsert_setting('admin_cache_version', $versionStamp); } catch (Throwable $e) {}
try { upsert_setting('pwa_cache_version', '20260512027200'); } catch (Throwable $e) {}
try { upsert_setting('asset_cache_version', '1.2.72'); } catch (Throwable $e) {}
try { upsert_setting('android_version_code', '1272'); } catch (Throwable $e) {}
try { upsert_setting('android_version_name', '1.2.72'); } catch (Throwable $e) {}
try { upsert_setting('android_build_mode', 'github_actions'); } catch (Throwable $e) {}

foreach ([
    $root . '/android/AkilliZikirHatim/app/build.gradle',
    $root . '/android/AkilliZikirHatim/gradle.properties',
    $root . '/.github/workflows/android-build.yml',
] as $file) {
    if (is_file($file)) @touch($file);
}

$config = $root . '/system/config.php';
if (is_file($config) && is_writable($config)) {
    $content = file_get_contents($config);
    if ($content !== false) {
        if (preg_match("/define\('APP_VERSION',\s*'[^']+'\);/", $content)) {
            $content = preg_replace("/define\('APP_VERSION',\s*'[^']+'\);/", "define('APP_VERSION', '1.2.72');", $content);
        } else {
            $content .= "\nif (!defined('APP_VERSION')) define('APP_VERSION', '1.2.72');\n";
        }
        file_put_contents($config, $content, LOCK_EX);
    }
}

$description = 'v1.2.72 ile GitHub Actions üzerinden APK/AAB üretim workflowu eklendi. Android build.gradle imzalı release AAB/APK üretecek şekilde hazırlandı; secrets yoksa ilk çalıştırmada upload keystore artifact olarak üretilir.';
$stmt = $pdo->prepare('INSERT INTO app_versions (version, description, applied_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE description = VALUES(description), applied_at = VALUES(applied_at)');
$stmt->execute(['1.2.72', $description]);

echo 'v1.2.72 güncellemesi tamamlandı. GitHub Actions APK/AAB build workflow eklendi.';
