<?php
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/helpers.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'bootstrap';

function api_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) { return false; }
}


function api_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        $value = $_SERVER[$key] ?? '';
        if (!$value) continue;
        $first = trim(explode(',', (string)$value)[0]);
        if ($first !== '') return mb_substr($first, 0, 80);
    }
    return 'unknown';
}

function api_rate_limit(string $action, ?string $clientId = null, int $maxHits = 60, int $windowSeconds = 60): bool {
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
            key_hash CHAR(64) NOT NULL PRIMARY KEY,
            action_name VARCHAR(80) NOT NULL,
            client_key VARCHAR(160) NULL,
            ip_address VARCHAR(80) NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            window_start DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            INDEX idx_action_window (action_name, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ip = api_client_ip();
        $clientKey = trim((string)($clientId ?? ''));
        $hash = hash('sha256', $action . '|' . $ip . '|' . $clientKey);
        $now = time();

        $stmt = $pdo->prepare('SELECT hits, window_start FROM api_rate_limits WHERE key_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            $stmt = $pdo->prepare('INSERT INTO api_rate_limits (key_hash, action_name, client_key, ip_address, hits, window_start, last_seen) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
            $stmt->execute([$hash, $action, mb_substr($clientKey,0,160), $ip]);
            return true;
        }

        $start = strtotime((string)$row['window_start']) ?: $now;
        $hits = (int)$row['hits'];

        if (($now - $start) >= $windowSeconds) {
            $stmt = $pdo->prepare('UPDATE api_rate_limits SET hits = 1, window_start = NOW(), last_seen = NOW(), client_key = ?, ip_address = ? WHERE key_hash = ?');
            $stmt->execute([mb_substr($clientKey,0,160), $ip, $hash]);
            return true;
        }

        if ($hits >= $maxHits) return false;

        $stmt = $pdo->prepare('UPDATE api_rate_limits SET hits = hits + 1, last_seen = NOW() WHERE key_hash = ?');
        $stmt->execute([$hash]);
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function api_limit_or_fail(string $action, ?string $clientId, int $maxHits, int $windowSeconds): void {
    if (!api_rate_limit($action, $clientId, $maxHits, $windowSeconds)) {
        api_security_log($action, $clientId, 'rate_limit', ['max' => $maxHits, 'window' => $windowSeconds]);
        json_response(['ok' => false, 'message' => 'Çok sık işlem yapıldı. Lütfen biraz bekleyip tekrar deneyin.'], 429);
    }
}

function api_clean_text(string $value, int $maxLength): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    return mb_substr($value, 0, $maxLength);
}

function api_security_table(PDO $pdo): void {
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
}

function api_security_log(string $action, ?string $clientId, string $reason, array $payload = []): void {
    try {
        $pdo = db();
        api_security_table($pdo);
        $digest = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stmt = $pdo->prepare('INSERT INTO api_security_events (action_name, client_key, ip_address, reason, payload_digest, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$action, mb_substr((string)($clientId ?? ''), 0, 160), api_client_ip(), mb_substr($reason, 0, 160), $digest]);
    } catch (Throwable $e) {}
}

function api_fail_security(string $action, ?string $clientId, string $reason, string $message, int $status = 422, array $payload = []): void {
    api_security_log($action, $clientId, $reason, $payload);
    json_response(['ok' => false, 'message' => $message], $status);
}

function api_text_quality_check(string $title, string $body, ?string $clientId = null): void {
    $titlePlain = trim($title);
    $bodyPlain = trim($body);
    $combined = trim($titlePlain . ' ' . $bodyPlain);
    $len = function_exists('mb_strlen') ? mb_strlen($combined, 'UTF-8') : strlen($combined);

    if ($len < 12) {
        api_fail_security('dua_add', $clientId, 'too_short', 'Dua isteğini biraz daha açıklayıcı yaz.', 422, ['title' => $titlePlain, 'body' => $bodyPlain]);
    }

    if (preg_match('/https?:\/\/|www\.|t\.me\/|wa\.me\/|bit\.ly|tinyurl|\.com|\.net|\.org/i', $combined)) {
        api_fail_security('dua_add', $clientId, 'link_blocked', 'Dua isteğinde bağlantı paylaşımı kabul edilmiyor.', 422, ['title' => $titlePlain, 'body' => $bodyPlain]);
    }

    if (preg_match('/(.)\1{12,}/u', $combined)) {
        api_fail_security('dua_add', $clientId, 'repeat_chars', 'Dua isteğinde çok fazla tekrar eden karakter var.', 422, ['title' => $titlePlain, 'body' => $bodyPlain]);
    }

    $words = preg_split('/\s+/u', mb_strtolower($combined, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) >= 8) {
        $counts = array_count_values($words);
        arsort($counts);
        $max = (int)reset($counts);
        if ($max >= max(6, (int)ceil(count($words) * 0.72))) {
            api_fail_security('dua_add', $clientId, 'repeated_words', 'Dua isteğinde çok fazla tekrar eden kelime var.', 422, ['title' => $titlePlain, 'body' => $bodyPlain]);
        }
    }

    $defaultBanned = 'bahis,kumar,casino,escort,porno,telegram,whatsapp grup,kripto yatırım,bedava para,reklam';
    $rawBanned = (string)setting('api_banned_words', $defaultBanned);
    $banned = array_filter(array_map('trim', preg_split('/[\r\n,]+/u', $rawBanned) ?: []));
    $lower = mb_strtolower($combined, 'UTF-8');
    foreach ($banned as $word) {
        if ($word !== '' && mb_stripos($lower, mb_strtolower($word, 'UTF-8'), 0, 'UTF-8') !== false) {
            api_fail_security('dua_add', $clientId, 'banned_word', 'Dua isteği güvenlik filtresine takıldı.', 422, ['title' => $titlePlain, 'body' => $bodyPlain]);
        }
    }
}

function api_duplicate_dua_check(PDO $pdo, ?string $clientId, string $title, string $body): void {
    $titleNorm = mb_strtolower(trim($title), 'UTF-8');
    $bodyNorm = mb_strtolower(trim($body), 'UTF-8');

    if ($clientId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dua_requests WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        try {
            $stmt->execute([$clientId]);
            if ((int)$stmt->fetchColumn() >= 3) {
                api_fail_security('dua_add', $clientId, 'client_burst', 'Kısa sürede çok fazla dua isteği gönderildi. Lütfen biraz bekle.', 429, ['client_id' => $clientId]);
            }
        } catch (Throwable $e) {}
    }

    try {
        if ($clientId) {
            $stmt = $pdo->prepare("SELECT title, body FROM dua_requests WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 12");
            $stmt->execute([$clientId]);
        } else {
            $stmt = $pdo->prepare("SELECT title, body FROM dua_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 12");
            $stmt->execute();
        }
        foreach ($stmt->fetchAll() as $row) {
            $oldTitle = mb_strtolower(trim((string)($row['title'] ?? '')), 'UTF-8');
            $oldBody = mb_strtolower(trim((string)($row['body'] ?? '')), 'UTF-8');
            if ($oldTitle === $titleNorm && $oldBody === $bodyNorm) {
                api_fail_security('dua_add', $clientId, 'duplicate_dua', 'Aynı dua isteği kısa süre önce gönderilmiş görünüyor.', 409, ['title' => $title, 'body' => $body]);
            }
        }
    } catch (Throwable $e) {}
}



try {
    switch ($action) {
        case 'bootstrap':
            $pdo = db();
            $clientId = trim((string)($_GET['client_id'] ?? ''));
            $settingsRows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            $settings = [];
            foreach ($settingsRows as $row) { $settings[$row['setting_key']] = $row['setting_value']; }

            $daily = $pdo->query('SELECT * FROM daily_contents WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch() ?: null;
            $zikirs = $pdo->query('SELECT * FROM zikirs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
            $sessions = $pdo->query('SELECT zs.*, z.title AS zikir_title, z.arabic_text, z.meaning FROM zikir_sessions zs LEFT JOIN zikirs z ON z.id = zs.zikir_id WHERE zs.is_live = 1 ORDER BY zs.id DESC')->fetchAll();
            $duaCircle = $pdo->query('SELECT * FROM dua_circles WHERE is_live = 1 ORDER BY id DESC LIMIT 1')->fetch() ?: null;
            $duaRequests = $pdo->query('SELECT * FROM dua_requests WHERE is_approved = 1 ORDER BY id DESC LIMIT 20')->fetchAll();
            $recentZikir = $pdo->query('SELECT zc.*, zs.title AS session_title FROM zikir_contributions zc LEFT JOIN zikir_sessions zs ON zs.id = zc.session_id ORDER BY zc.id DESC LIMIT 12')->fetchAll();
            $hatim = $pdo->query("SELECT * FROM hatims WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch() ?: null;
            $juz = [];
            if ($hatim) {
                $stmt = $pdo->prepare('SELECT * FROM hatim_juz WHERE hatim_id = ? ORDER BY juz_number ASC');
                $stmt->execute([$hatim['id']]);
                $juz = $stmt->fetchAll();
            }

            $myStats = [
                'zikir_today' => 0,
                'my_juz_count' => 0,
                'my_completed_juz_count' => 0,
                'amin_count' => 0,
                'my_dua_count' => 0
            ];
            $myDuaRequests = [];
            if ($clientId !== '') {
                $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM zikir_contributions WHERE client_id = ? AND DATE(created_at) = CURDATE()');
                $stmt->execute([$clientId]);
                $myStats['zikir_today'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM dua_joins WHERE client_id = ?");
                $stmt->execute([$clientId]);
                $myStats['amin_count'] = (int)$stmt->fetchColumn();
                if (api_column_exists($pdo, 'dua_requests', 'client_id')) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dua_requests WHERE client_id = ?');
                    $stmt->execute([$clientId]);
                    $myStats['my_dua_count'] = (int)$stmt->fetchColumn();
                    $stmt = $pdo->prepare('SELECT * FROM dua_requests WHERE client_id = ? ORDER BY id DESC LIMIT 20');
                    $stmt->execute([$clientId]);
                    $myDuaRequests = $stmt->fetchAll();
                }
                if ($hatim) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hatim_juz WHERE hatim_id = ? AND client_id = ? AND status IN ('reserved','completed')");
                    $stmt->execute([$hatim['id'], $clientId]);
                    $myStats['my_juz_count'] = (int)$stmt->fetchColumn();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hatim_juz WHERE hatim_id = ? AND client_id = ? AND status = 'completed'");
                    $stmt->execute([$hatim['id'], $clientId]);
                    $myStats['my_completed_juz_count'] = (int)$stmt->fetchColumn();
                }
            }
            json_response([
                'ok' => true,
                'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
                'site_url' => defined('SITE_URL') ? SITE_URL : '',
                'server_time' => now_mysql(),
                'settings' => $settings,
                'daily' => $daily,
                'zikirs' => $zikirs,
                'zikir_sessions' => $sessions,
                'dua_circle' => $duaCircle,
                'dua_requests' => $duaRequests,
                'my_dua_requests' => $myDuaRequests,
                'zikir_recent' => $recentZikir,
                'hatim' => $hatim,
                'hatim_juz' => $juz,
                'my_stats' => $myStats
            ]);
            break;
        case 'zikir_contribute':
            $data = request_json();
            $sessionId = max(1, (int)($data['session_id'] ?? 0));
            $amount = max(1, min(100000, (int)($data['amount'] ?? 0)));
            $nickname = api_clean_text((string)($data['nickname'] ?? 'Misafir'), 120) ?: 'Misafir';
            $clientId = trim((string)($data['client_id'] ?? '')) ?: null;
            api_limit_or_fail('zikir_contribute', $clientId ?: $nickname, 80, 60);

            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM zikir_sessions WHERE id = ? FOR UPDATE');
            $stmt->execute([$sessionId]);
            $sessionCheck = $stmt->fetch();
            if (!$sessionCheck) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Zikir oturumu bulunamadı.'], 404);
            }
            if ((int)($sessionCheck['is_live'] ?? 0) !== 1) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Bu zikir halkası şu anda yayında değil.'], 409);
            }

            $isFirstJoin = true;
            if ($clientId) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM zikir_contributions WHERE session_id = ? AND client_id = ?');
                $check->execute([$sessionId, $clientId]);
                $isFirstJoin = ((int)$check->fetchColumn()) === 0;
            }

            $stmt = $pdo->prepare('INSERT INTO zikir_contributions (session_id, nickname, amount, client_id, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$sessionId, $nickname, $amount, $clientId]);

            $joinInc = $isFirstJoin ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE zikir_sessions SET current_count = current_count + ?, participant_count = participant_count + ?, updated_at = NOW() WHERE id = ? AND is_live = 1');
            $stmt->execute([$amount, $joinInc, $sessionId]);

            $stmt = $pdo->prepare('SELECT * FROM zikir_sessions WHERE id = ?');
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            $pdo->commit();
            json_response(['ok' => true, 'message' => 'Zikir katkın kaydedildi.', 'session' => $session]);
            break;


        case 'dua_add':
            $data = request_json();
            $incomingClientId = trim((string)($data['client_id'] ?? ''));
            api_limit_or_fail('dua_add', $incomingClientId, 10, 300);
            if (setting('public_dua_enabled', '1') !== '1') { json_response(['ok' => false, 'message' => 'Dua isteği gönderimi şu an kapalı.'], 403); }
            $circleId = (int)($data['circle_id'] ?? 1);
            $nickname = trim((string)($data['nickname'] ?? 'Misafir')) ?: 'Misafir';
            $clientId = trim((string)($data['client_id'] ?? '')) ?: null;
            $category = trim((string)($data['category'] ?? 'Genel')) ?: 'Genel';
            $title = api_clean_text((string)($data['title'] ?? ''), 120);
            $body = api_clean_text((string)($data['body'] ?? ''), 700);
            if ($title === '' || $body === '') { api_fail_security('dua_add', $clientId, 'empty_dua', 'Başlık ve dua metni gerekli.', 422, $data); }
            api_text_quality_check($title, $body, $clientId);
            $isApproved = setting('duas_require_approval', '0') === '1' ? 0 : 1;
            $pdo = db();
            api_security_table($pdo);
            api_duplicate_dua_check($pdo, $clientId, $title, $body);
            $hasClient = api_column_exists($pdo, 'dua_requests', 'client_id');
            $hasCategory = api_column_exists($pdo, 'dua_requests', 'category');
            if ($hasClient && $hasCategory) {
                $stmt = $pdo->prepare('INSERT INTO dua_requests (circle_id, nickname, client_id, category, title, body, amin_count, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())');
                $stmt->execute([$circleId ?: null, $nickname, $clientId, $category, $title, $body, $isApproved]);
            } elseif ($hasClient) {
                $stmt = $pdo->prepare('INSERT INTO dua_requests (circle_id, nickname, client_id, title, body, amin_count, is_approved, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())');
                $stmt->execute([$circleId ?: null, $nickname, $clientId, $title, $body, $isApproved]);
            } elseif ($hasCategory) {
                $stmt = $pdo->prepare('INSERT INTO dua_requests (circle_id, nickname, category, title, body, amin_count, is_approved, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())');
                $stmt->execute([$circleId ?: null, $nickname, $category, $title, $body, $isApproved]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO dua_requests (circle_id, nickname, title, body, amin_count, is_approved, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())');
                $stmt->execute([$circleId ?: null, $nickname, $title, $body, $isApproved]);
            }
            json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
            break;
        case 'dua_amin':
            $data = request_json();
            $requestId = max(1, (int)($data['request_id'] ?? 0));
            $nickname = api_clean_text((string)($data['nickname'] ?? 'Misafir'), 120) ?: 'Misafir';
            $clientId = trim((string)($data['client_id'] ?? '')) ?: bin2hex(random_bytes(8));
            api_limit_or_fail('dua_amin', $clientId, 40, 60);

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM dua_requests WHERE id = ? FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Dua isteği bulunamadı.'], 404);
            }
            if ((int)($request['is_approved'] ?? 0) !== 1) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Bu dua isteği şu anda yayında değil.'], 409);
            }

            try {
                $stmt = $pdo->prepare('INSERT INTO dua_joins (request_id, nickname, client_id, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$requestId, $nickname, $clientId]);

                $stmt = $pdo->prepare('UPDATE dua_requests SET amin_count = amin_count + 1, updated_at = NOW() WHERE id = ? AND is_approved = 1');
                $stmt->execute([$requestId]);

                $pdo->prepare('UPDATE dua_circles SET participant_count = participant_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int)($request['circle_id'] ?? 0)]);
                $message = 'Âmin duan kaydedildi. Allah kabul etsin.';
            } catch (Throwable $e) {
                $message = 'Bu duaya daha önce Âmin demişsin. Allah kabul etsin.';
            }

            $stmt = $pdo->prepare('SELECT * FROM dua_requests WHERE id = ?');
            $stmt->execute([$requestId]);
            $row = $stmt->fetch();
            $pdo->commit();
            json_response(['ok' => true, 'message' => $message, 'request' => $row]);
            break;
        case 'hatim_take':
            $data = request_json();
            $hatimId = max(1, (int)($data['hatim_id'] ?? 0));
            $juzNumber = max(1, min(30, (int)($data['juz_number'] ?? 0)));
            $nickname = api_clean_text((string)($data['nickname'] ?? 'Misafir'), 120) ?: 'Misafir';
            $clientId = trim((string)($data['client_id'] ?? '')) ?: null;
            if ($clientId === null) { json_response(['ok' => false, 'message' => 'Cüz almak için cihaz bilgisi bulunamadı.'], 422); }
            api_limit_or_fail('hatim_take', $clientId, 30, 60);

            $pdo = db();
            $stmt = $pdo->prepare("UPDATE hatim_juz SET status = 'reserved', nickname = ?, client_id = ?, reserved_at = NOW(), updated_at = NOW() WHERE hatim_id = ? AND juz_number = ? AND status = 'empty'");
            $stmt->execute([$nickname, $clientId, $hatimId, $juzNumber]);
            if ($stmt->rowCount() > 0) {
                $pdo->prepare('UPDATE hatims SET participant_count = participant_count + 1, updated_at = NOW() WHERE id = ?')->execute([$hatimId]);
                json_response(['ok' => true, 'message' => 'Cüz alındı.']);
            }
            json_response(['ok' => false, 'message' => 'Bu cüz alınmış veya tamamlanmış görünüyor. Sayfayı yenileyip tekrar deneyin.'], 409);
            break;
        case 'hatim_complete':
            $data = request_json();
            $hatimId = max(1, (int)($data['hatim_id'] ?? 0));
            $juzNumber = max(1, min(30, (int)($data['juz_number'] ?? 0)));
            $clientId = trim((string)($data['client_id'] ?? ''));
            if ($clientId === '') { json_response(['ok' => false, 'message' => 'Cüz tamamlamak için cihaz bilgisi bulunamadı.'], 422); }
            api_limit_or_fail('hatim_complete', $clientId, 30, 60);

            $pdo = db();
            $stmt = $pdo->prepare("UPDATE hatim_juz SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE hatim_id = ? AND juz_number = ? AND status = 'reserved' AND client_id = ?");
            $stmt->execute([$hatimId, $juzNumber, $clientId]);
            if ($stmt->rowCount() > 0) {
                json_response(['ok' => true, 'message' => 'Cüz tamamlandı olarak işaretlendi. Allah kabul etsin.']);
            }
            json_response(['ok' => false, 'message' => 'Bu cüz sana ait değil, boşta değil ya da zaten tamamlanmış görünüyor.'], 409);
            break;
        case 'hatim_release':
            $data = request_json();
            $hatimId = max(1, (int)($data['hatim_id'] ?? 0));
            $juzNumber = max(1, min(30, (int)($data['juz_number'] ?? 0)));
            $clientId = trim((string)($data['client_id'] ?? ''));
            if ($clientId === '') { json_response(['ok' => false, 'message' => 'Cüz bırakmak için cihaz bilgisi bulunamadı.'], 422); }
            api_limit_or_fail('hatim_release', $clientId, 30, 60);

            $pdo = db();
            $stmt = $pdo->prepare("UPDATE hatim_juz SET status = 'empty', nickname = NULL, client_id = NULL, reserved_at = NULL, completed_at = NULL, updated_at = NOW() WHERE hatim_id = ? AND juz_number = ? AND status = 'reserved' AND client_id = ?");
            $stmt->execute([$hatimId, $juzNumber, $clientId]);
            if ($stmt->rowCount() > 0) {
                $pdo->prepare('UPDATE hatims SET participant_count = GREATEST(participant_count - 1, 0), updated_at = NOW() WHERE id = ?')->execute([$hatimId]);
                json_response(['ok' => true, 'message' => 'Cüz boşa çıkarıldı.']);
            }
            json_response(['ok' => false, 'message' => 'Bu cüz sende değil ya da tamamlanmış görünüyor.'], 409);
            break;


        default:
            json_response(['ok' => false, 'message' => 'Geçersiz işlem.'], 404);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
