<?php
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/helpers.php';
$appName = setting('app_name', 'Akıllı Zikir & Hatim');
$publisher = setting('publisher_name', 'İlhan BELUK');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Destek - <?=h($appName)?></title>
<style>body{margin:0;background:#031915;color:#fff1d0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.7}.wrap{max-width:820px;margin:auto;padding:28px 18px}h1,h2{font-family:Georgia,serif;color:#f2d389}.card{border:1px solid rgba(215,175,104,.28);background:rgba(4,45,38,.65);border-radius:22px;padding:20px;margin:14px 0}.muted{color:#cbbf9a}a{color:#f2d389}</style></head>
<body><main class="wrap"><h1>Destek ve İletişim</h1>
<section class="card"><h2><?=h($appName)?></h2><p>Uygulama ile ilgili destek, öneri, hata bildirimi veya veri silme talebi için yayımcı/geliştirici ile iletişime geçebilirsiniz.</p><p>Yayımcı / Geliştirici: <?=h($publisher)?></p></section>
<section class="card"><h2>Veri Silme Talebi</h2><p>Topluluk özellikleriyle ilişkili takma ad, dua isteği veya katkı kayıtlarınız için silme talebi iletebilirsiniz. Cihazdaki offline veriler uygulama içinden temizlenebilir.</p></section>
<section class="card"><h2>Gönüllü Katkı</h2><p>Uygulama reklam içermez. Beğendiyseniz geliştirilmesine gönüllü katkıda bulunabilirsiniz.</p></section>
</main></body></html>