# Akıllı Zikir & Hatim v1.2.72 — GitHub Actions APK/AAB Build

Bu paket, Android Studio kurmadan GitHub Actions üzerinden APK/AAB üretmek için hazırlanmıştır.

## Eklenen workflow

`.github/workflows/android-build.yml`

Workflow çıktıları:

- `akilli-zikir-debug-apk`
- `akilli-zikir-release-apk`
- `akilli-zikir-release-aab-play-console`
- İlk çalıştırmada secrets yoksa: `SAVE-THIS-upload-keystore-and-credentials`

## Nasıl çalıştırılır?

1. Site/proje dosyalarını GitHub reposuna yükle.
2. Repo içinde şu klasör bulunmalı:

   `android/AkilliZikirHatim/`

3. GitHub → Actions → `Build Android APK and AAB`
4. `Run workflow` butonuna bas.
5. İşlem bitince Artifacts bölümünden `.aab` dosyasını indir.

## Google Play için AAB

Play Console’a yüklenecek dosya şudur:

`akilli-zikir-release-aab-play-console`

İçindeki `.aab` dosyası Google Play Console için kullanılacak ana dosyadır.

## Keystore konusu çok önemli

İlk çalıştırmada GitHub Secrets girmezsen workflow otomatik upload key üretir.

O zaman şu artifact’i kesin indir ve sakla:

`SAVE-THIS-upload-keystore-and-credentials`

Bu dosyaları kaybedersen sonraki Play Store güncellemelerinde sorun yaşarsın.

## Sonraki çalıştırmalarda önerilen Secrets

İlk üretilen key’i GitHub Secrets olarak ekle:

- `ANDROID_KEYSTORE_BASE64`
- `ANDROID_KEYSTORE_PASSWORD`
- `ANDROID_KEY_ALIAS`
- `ANDROID_KEY_PASSWORD`

`ANDROID_KEYSTORE_BASE64` üretmek için:

```bash
base64 -w 0 upload-keystore.jks
```

Windows PowerShell için:

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("upload-keystore.jks"))
```

## Teknik not

- Android proje: `android/AkilliZikirHatim/`
- Package ID: `com.ilhanbeluk.akillizikirhatim`
- Version Name: `1.2.72`
- Version Code: `1272`
- Gradle: `8.9`
- AGP: `8.7.3`
- JDK: `17`
- targetSdk: `35`
