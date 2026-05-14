<?php
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/helpers.php';
$appName = setting('app_name', 'Akıllı Zikir & Hatim');
$publisher = setting('publisher_name', 'İlhan BELUK');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kullanım Şartları - <?=h($appName)?></title>
<style>body{margin:0;background:#031915;color:#fff1d0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.7}.wrap{max-width:820px;margin:auto;padding:28px 18px}h1,h2{font-family:Georgia,serif;color:#f2d389}.card{border:1px solid rgba(215,175,104,.28);background:rgba(4,45,38,.65);border-radius:22px;padding:20px;margin:14px 0}.muted{color:#cbbf9a}a{color:#f2d389}</style></head>
<body><main class="wrap"><h1>Kullanım Şartları</h1>
<section class="card"><h2>1. Amaç</h2><p><?=h($appName)?>; zikir, dua ve hatim takibini kolaylaştırmak amacıyla hazırlanmıştır.</p></section>
<section class="card"><h2>2. Kullanıcı Sorumluluğu</h2><p>Kullanıcı, uygulamada paylaştığı dua ve topluluk içeriklerinden sorumludur. Uygulama iyi niyetli, saygılı ve uygun kullanım için sunulmuştur.</p></section>
<section class="card"><h2>3. Dini İçerik Notu</h2><p>Uygulama ibadet ve manevi takip süreçlerinde yardımcı araçtır. Dini konularda nihai hüküm veya fetva kaynağı olarak değerlendirilmemelidir.</p></section>
<section class="card"><h2>4. Reklamsız Kullanım ve Katkı</h2><p>Uygulama reklam içermez. Gönüllü katkılar, uygulamanın sürdürülebilirliği ve geliştirilmesi için isteğe bağlıdır.</p></section>
<section class="card"><h2>5. Yayımcı</h2><p>Yayımcı / Geliştirici: <?=h($publisher)?></p></section>
</main></body></html>