<?php
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$expected = setting('apk_build_callback_token', '');
$given = $_GET['token'] ?? '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$given && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
    $given = trim($m[1]);
}

if ($expected === '' || $given === '' || !hash_equals($expected, (string)$given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Callback token zorunlu veya geçersiz'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$uid = trim((string)($data['request_uid'] ?? ''));
if (!$uid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'request_uid gerekli'], JSON_UNESCAPED_UNICODE);
    exit;
}

$status = trim((string)($data['status'] ?? 'completed'));
$artifact = trim((string)($data['artifact_url'] ?? ''));
$response = trim((string)($data['response_text'] ?? $raw));

$pdo = db();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS apk_build_requests (
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
} catch (Throwable $e) {}

$stmt = $pdo->prepare('UPDATE apk_build_requests SET status=?, artifact_url=?, response_text=?, updated_at=NOW() WHERE request_uid=?');
$stmt->execute([$status, $artifact, mb_substr($response, 0, 2000), $uid]);

echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);
