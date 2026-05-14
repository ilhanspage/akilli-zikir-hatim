Akıllı Zikir & Hatim v1.0.36 - Admin APK Build Merkezi

Bu dosya geliştirici/admin içindir. Mobil kullanıcıya görünmez.

Mantık:
- PHP hosting içinde doğrudan APK derlenmez.
- Admin panelde APK Build Merkezi'nden talep oluşturulur.
- Talep, ayarlanmışsa webhook/GitHub Actions/build sunucusuna gönderilir.
- Webhook yoksa talep admin panelde kayıt olarak bekler.

Admin panel:
- /admin/?page=apk_build

Önerilen profesyonel akış:
1. GitHub reposu oluştur.
2. apk/github-actions-android-build.sample.yml dosyasını .github/workflows/android-build.yml olarak ekle.
3. Admin panelde webhook veya build sunucusu bağlantısını ayarla.
4. Admin panelden Test APK talebi oluştur.
5. Build sonucu APK artifact olarak alınır.

Not:
Release APK/AAB üretimi için keystore ve Google Play imzalama bilgileri ayrıca güvenli şekilde tanımlanmalıdır.
