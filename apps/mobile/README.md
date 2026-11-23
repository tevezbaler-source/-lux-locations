# Lux Locations mobil uygulaması (iOS + Android)

Expo (React Native) ile yazılmış yerli uygulama. İçerik siteden **canlı** gelir: panelde (`/admin/site/`) yapılan değişiklikler uygulamada mağaza güncellemesi gerekmeden görünür.

## İçerik nereden geliyor

| Uygulamada | Kaynak | Panelden değişince |
| --- | --- | --- |
| Oteller listesi ve otel sayfaları | `lx-content/app-api.php` → `data/hotels.json` | Uygulama bir sonraki açılışta / aşağı çekince günceller |
| Ana sayfa, rezervasyon ve form metinleri | `@lux/shared/i18n` (sitenin dil dosyaları) + paneldeki metin değişiklikleri (`texts`) | Panelde değiştirilen metin uygulamada da değişir |
| Boğaz turu sayfası | Sitedeki sayfanın kendisi, uygulama içinde (üst menü ve alt bilgi gizli) | Aynen görünür |
| WhatsApp numarası | Sitenin ana sayfası | Aynen görünür |

- İçerik telefonda saklanır; internet yokken son indirilen içerik gösterilir.
- Veri adresi değişiklik yoksa `304` döner, uygulama boşuna indirmez.
- Yeni bir **ekran** ya da **özellik** eklemek mağaza güncellemesi ister; içerik değişikliği istemez.

Canlı veri adresi: `https://luxlocationstours.com/lx-content/app-api.php?lang=tr` (11 dil: tr, en, ar, ru, de, fr, es, it, zh, fa, ro).

## Ekranlar

- `src/app/(tabs)/index.tsx` Keşfet: Boğaz turu, otel şeridi (canlı), rezervasyon türleri, hizmetler
- `src/app/(tabs)/oteller.tsx` Oteller (canlı, yıldıza göre filtre)
- `src/app/otel/[slug].tsx` Otel sayfası: fotoğraflar, odalar, olanaklar, konum, WhatsApp teklif
- `src/app/(tabs)/rezervasyon.tsx` Oda / grup / kongre formu → dolu WhatsApp mesajı (sitedeki formla aynı)
- `src/app/(tabs)/iletisim.tsx` WhatsApp, arama, e-posta, adres, sosyal medya, gizlilik politikası
- `src/app/sayfa.tsx` Siteden bir sayfayı uygulama içinde açar
- `src/app/dil.tsx` Dil seçimi (ilk açılışta telefonun dili)

## Siteye yüklenecek dosyalar (bir kez)

- `apps/web/public/lx-content/app-api.php` → sunucuda `httpdocs/lx-content/app-api.php`
- `apps/web/public/gizlilik/index.html` → sunucuda `httpdocs/gizlilik/index.html` (mağazaların istediği gizlilik politikası: `https://luxlocationstours.com/gizlilik/`)

Başka dosyaya dokunulmaz. Yükleme betiği: `scripts/uygulama/Siteye-Yukle.bat` (iki dosyayı yanına aynı klasör yapısıyla koyup çalıştırın, FTP şifresini sorar).

Mağaza formlarına girilecek metinler: `magaza/magaza-bilgileri.md`.

## Deneme

```bash
npm install              # depo kökünde
npm run mobile           # Expo Go ile QR
```

Yerel bir siteyle denemek için: `EXPO_PUBLIC_SITE_URL=http://<bilgisayar-ip>:8099 npm run mobile`.

Android cihazda release derlemesi için kök `CLAUDE.md`'deki **Android derlemesi** adımları (`D:\lx` kopyası).

## Mağazaya gönderme (EAS)

Windows'ta iPhone derlemesi yapılamadığı için iki mağaza da Expo'nun bulut derlemesiyle (EAS) çıkar. `eas.json` hazır.

Gerekenler:
1. **Expo hesabı** (ücretsiz): https://expo.dev/signup
2. **Google Play Console** (bir kez 25 $): https://play.google.com/console
3. **Apple Developer Program** (yıllık 99 $): https://developer.apple.com/programs/enroll/ (şirket adına açılacaksa D-U-N-S numarası gerekir)

Komutlar (`apps/mobile` içinde, bir kez `npm i -g eas-cli` ve `eas login`):

```bash
eas init                                   # projeyi Expo hesabına bağlar (app.json'a projectId yazar)
eas build --platform android --profile preview      # telefona kurulacak deneme APK'sı
eas build --platform android --profile production   # Play Store için .aab
eas build --platform ios --profile production       # App Store için (Apple hesabıyla giriş ister, sertifikaları EAS kurar)
eas submit --platform ios                  # TestFlight / App Store Connect'e yükler
eas submit --platform android              # Play Console'a yükler (ilk sürüm Play Console'dan elle yüklenmeli)
```

Mağaza bilgileri için hazır olanlar:
- Paket / bundle: `com.luxlocations.app`
- Gizlilik politikası: `https://luxlocationstours.com/gizlilik/`
- Veri güvenliği / App Privacy: uygulama veri toplamaz, izleme yapmaz (form WhatsApp mesajı olarak kullanıcının kendi WhatsApp'ından gider)
- Şifreleme: yalnızca standart HTTPS (`ITSAppUsesNonExemptEncryption: false` ayarlı)
- iPad: kapalı (yalnızca iPhone ekran görüntüleri yeterli)
