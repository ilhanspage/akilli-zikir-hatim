# Akıllı Zikir & Hatim v1.2.63 — APK/AAB + Google Play Billing Başlangıç

Bu paket Android APK/AAB aşamasını başlatır.

## Paket adı

`com.ilhanbeluk.akillizikirhatim`

## Web başlangıç URL

`https://zikir.next-sosyal.com/app/`

## Google Play Billing ürünleri

Play Console içinde One-time products / In-app products alanında şu ürün ID'leri açılmalıdır:

- `support_25` — Gönüllü Destek 25 TL
- `support_50` — Gönüllü Destek 50 TL
- `support_100` — Gönüllü Destek 100 TL
- `support_250` — Gönüllü Destek 250 TL

Bu ürünler **premium özellik açmaz**. Sadece gönüllü destek/teşekkür amacıyla kullanılır.

## Web/PWA güvenliği

Tarayıcı/PWA içinde Google Play Billing köprüsü yoksa ödeme butonları görünmez. Sadece bilgilendirme ekranı görünür.

Android APK/AAB içinde `AkilliZikirBilling` JS köprüsü varsa destek butonları görünür ve ödeme Google Play Billing ile başlatılır.

## Android proje yolu

`android/AkilliZikirHatim/`

Bu klasör Android Studio ile açılabilir.

## Build komutları

Android Studio ile:

1. `android/AkilliZikirHatim/` klasörünü aç.
2. Gradle sync yap.
3. Build > Generate Signed Bundle / APK
4. Android App Bundle seç.
5. Keystore oluştur veya mevcut keystore ile imzala.
6. AAB dosyasını Play Console'a yükle.

Komut satırı ile Gradle Wrapper eklendikten sonra:

```bash
./gradlew bundleRelease
```

## Önemli

- İlk yayın için uygulama ücretsiz kalmalıdır.
- Harici ödeme linki, IBAN, havale/EFT yönlendirmesi yoktur.
- Google Play Billing aktifse ödeme sadece Google Play üzerinden açılır.
- Destek veren kullanıcıya ayrıcalık/premium/rozet verilmez.
