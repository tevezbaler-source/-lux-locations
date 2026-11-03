# Lux Locations

Site, mobil uygulama ve ekip paneli tek depoda (npm workspaces).

## Kurallar
- Kullanıcıya her zaman yalnızca **Türkçe** yanıt yaz.
- Kullanıcı her şeyin **D:** diskinde olmasını istiyor; C: dolu. Büyük dosyaları, önbellekleri ve derleme çıktılarını C:'ye yazma.

## Yapı
| Klasör | İçerik |
| --- | --- |
| `apps/mobile` | Expo (React Native) uygulaması, iOS + Android. Expo Router, `src/app`, `src/components`, `src/constants`, `src/hooks` |
| `apps/web` | Next.js sitesi (ileride `/panel` ekip paneli) |
| `packages/shared` | Ortak içerik: 6 hizmet, şehirler, iletişim, talep formu kuralları, renkler (`@lux/shared`) |
| `supabase` | `migrations/` (şema) ve `seed.sql` (başlangıç içeriği) |

Ham kaynaklar (konsept, tekne fotoğrafları, ikonlar) bir üst klasördeki `D:\lUX LOCATİON\kaynaklar\` içinde; bu klasör git deposunun dışında. Uygulama ikonlarının son hâli `kaynaklar\app-icon-final\`, uygulamada kullanılan kopyaları `apps/mobile/assets/images/` altında.

## Komutlar (depo kökünde)
```bash
npm install        # ilk seferde
npm run web        # http://localhost:3000
npm run mobile     # Expo, telefonda Expo Go ile QR
npm run typecheck  # tüm paketlerde tip kontrolü
```

## Android derlemesi
Gradle, yoldaki `İ` harfini okuyamıyor ve 260 karakter yol sınırına takılıyor. Bu yüzden derleme doğrudan bu klasörden değil, **kısa `D:\lx` kopyasından** yapılır.
- Kopyayı güncelle: `robocopy /XJ`; `node_modules\@lux\shared` junction'ı `D:\lx\packages\shared`'e işaret etmeli.
- `android\gradle.properties`: `reactNativeArchitectures=arm64-v8a`.
- Ortam: `GRADLE_USER_HOME=D:\android-build\gradle-home`, `TEMP/TMP=D:\android-build\tmp`, `ANDROID_HOME=D:\android-build\sdk`, `JAVA_HOME=%USERPROFILE%\.gradle\jdks\eclipse_adoptium-17-amd64-windows.2`.
- Test telefonu: TECNO CE9, adb seri `059292509H000043`.
- Komut (`D:\lx\apps\mobile` içinde): `npx expo run:android --variant release --device 059292509H000043`

## Git / GitHub
- Uzak depo: https://github.com/tevezbaler-source/-lux-locations (adın başında tire var).
- Push `tevezbaler-source` hesabıyla yapılmalı; kayıtlı `useleadgen` hesabı 403 verir. Giriş: `git credential-manager github login --device`.
- Git deposu `lux-locations/` içindedir; üst klasör (`D:\lUX LOCATİON`) depo değildir.

## Uygulama bilgileri
- Paket/bundle: `com.luxlocations.app`, şema `luxlocations`, tema rengi `#3a5f78`.
- Expo 58, React 19, React Native 0.88, React Compiler ve typed routes açık.

## Sıradaki işler
1. Supabase projesini aç, `migrations` ve `seed.sql` çalıştır.
2. Site ve uygulamadaki talep formunu `requests` tablosuna bağla.
3. Boğaz yemekli tekne turu sayfası (fotoğraflar `kaynaklar/incoming-photos-2026-11-02`).
4. Ekip paneli (`apps/web` içinde `/panel`).
