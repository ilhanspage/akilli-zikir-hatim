Akıllı Zikir & Hatim v1.0.35 - Android Bildirim Hazırlığı

Bu dosya geliştirici/admin içindir; mobil kullanıcı ekranında görünmez.

Hazırlanan yapı:
- PWA tarafında bildirim izin durumu ve test bildirimi eklendi.
- Android APK tarafı için Capacitor Local Notifications bağımlılığı hazırlandı.
- Android 13+ cihazlarda POST_NOTIFICATIONS izni gerekecektir.
- Varsayılan bildirim kanalı:
  - ID: zikir_reminders
  - Ad: Zikir Hatırlatıcıları

APK aşamasında yapılacaklar:
1. npm install
2. npx cap sync android
3. AndroidManifest.xml içinde POST_NOTIFICATIONS iznini kontrol et.
4. Local Notifications plugin ile zikir hatırlatıcılarını native zamanlamaya bağla.
5. Test APK'da bildirim izni, test bildirimi ve saatli hatırlatıcıları kontrol et.

Not:
Bu sürüm hâlâ APK üretmez; sadece bildirim ve Android izin hazırlığıdır.
