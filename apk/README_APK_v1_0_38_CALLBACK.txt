Akıllı Zikir & Hatim v1.0.38 - APK Build Callback / Sonuç Sistemi

Bu dosya admin/geliştirici içindir. Mobil kullanıcıya görünmez.

Eklenen yapı:
- Admin APK Build Merkezi artık build sonucunu manuel güncelleyebilir.
- Build sunucusu sonuç dönebilmesi için callback endpoint eklendi:
  /update/apk_build_callback.php

Callback örnek JSON:
{
  "request_uid": "apk_20260504_150000_abc123",
  "status": "completed",
  "artifact_url": "https://...",
  "response_text": "Build başarılı"
}

Token kullanımı:
- Admin panel > APK Build Merkezi > Callback token alanı
- Callback çağrısı:
  /update/apk_build_callback.php?token=TOKEN

Not:
Bu sürüm APK üretmez. Admin panel ile dış build sistemi arasındaki sonuç takibini hazırlar.
