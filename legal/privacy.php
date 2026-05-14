<?php
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/helpers.php';
$appName = setting('app_name', 'Akıllı Zikir & Hatim');
$publisher = setting('publisher_name', 'İlhan BELUK');
$developer = setting('developer_name', 'İlhan BELUK');
$updated = date('d.m.Y');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gizlilik Politikası - <?=h($appName)?></title>
<style>body{margin:0;background:#031915;color:#fff1d0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.7}.wrap{max-width:820px;margin:auto;padding:28px 18px}h1,h2{font-family:Georgia,serif;color:#f2d389}.card{border:1px solid rgba(215,175,104,.28);background:rgba(4,45,38,.65);border-radius:22px;padding:20px;margin:14px 0}.muted{color:#cbbf9a}a{color:#f2d389}</style></head>
<body><main class="wrap"><h1>Gizlilik Politikası</h1><p class="muted">Son güncelleme: <?=h($updated)?></p>
<section class="card"><h2>1. Genel Bilgi</h2><p><?=h($appName)?>; kişisel zikir takibi, toplu zikir halkaları, dua halkası ve hatim takibi için hazırlanmış reklamsız bir uygulamadır. Yayımcı / geliştirici: <?=h($publisher)?>.</p></section>
<section class="card"><h2>2. İşlenen Bilgiler</h2><p>Uygulama; kullanıcının belirlediği takma ad, zikir katkıları, dua isteği içerikleri, hatim cüz katılım bilgileri ve uygulama içi tercihleri işleyebilir.</p></section>
<section class="card"><h2>3. Cihazda Saklanan Veriler</h2><p>Kişisel sayaç geçmişi, hedefler, favoriler, vird/tesbihat durumları ve bazı ayarlar cihazda/offline saklanabilir. Bu veriler kullanıcının cihazında tutulur.</p></section>
<section class="card"><h2>4. Topluluk Özellikleri</h2><p>Toplu zikir, dua ve hatim alanlarında kullanıcının takma adı ve katkı bilgileri topluluk ekranlarında görünebilir.</p></section>
<section class="card"><h2>5. Reklam ve Gönüllü Katkı</h2><p>Uygulamada reklam bulunmamaktadır. Kullanıcı isterse uygulamanın geliştirilmesine gönüllü katkıda bulunabilir.</p></section>
<section class="card"><h2>6. Veri Silme</h2><p>Cihazda saklanan veriler uygulama içindeki offline veri temizleme seçenekleriyle temizlenebilir. Topluluk verileri için veri silme talebi destek sayfası üzerinden iletilebilir.</p></section>
<section class="card"><h2>7. İletişim</h2><p>Yayımcı / Geliştirici: <?=h($developer)?></p><p><a href="/legal/support.php">Destek ve iletişim sayfası</a></p></section>
</main></body></html>