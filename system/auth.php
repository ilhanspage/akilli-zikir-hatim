<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('akilli_zikir_admin');
    session_start();
}

function admin_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function require_admin(): void
{
    if (!admin_user()) {
        redirect('/admin/login.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}
