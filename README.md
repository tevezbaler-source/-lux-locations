# Lux Locations

Site, mobil uygulama ve ekip paneli tek depoda.

| Klasör | İçerik |
| --- | --- |
| `apps/mobile` | Expo (React Native) uygulaması, iOS + Android |
| `apps/web` | Next.js sitesi (ileride `/panel` ekip paneli) |
| `packages/shared` | Ortak içerik: 6 hizmet, şehirler, iletişim, talep formu kuralları, renkler |
| `supabase` | Veritabanı şeması (`migrations/`) ve başlangıç içeriği (`seed.sql`) |

Ham kaynaklar (Codex konsepti, tekne fotoğrafları, ikon dosyaları) bir üst klasördeki `kaynaklar/` içinde.

## Çalıştırma

```bash
npm install        # ilk seferde
npm run web        # site: http://localhost:3000
npm run mobile     # uygulama: telefonda Expo Go ile QR kodu okutun
npm run typecheck  # tüm paketlerde tip kontrolü
```

## Sıradaki adımlar

1. Supabase projesi açılır, `supabase/migrations` ve `seed.sql` çalıştırılır.
2. Sitedeki ve uygulamadaki talep formu `requests` tablosuna bağlanır.
3. Boğaz yemekli tekne turu sayfası (fotoğraflar `kaynaklar/incoming-photos-2026-11-02`).
4. Ekip paneli (`apps/web` içinde `/panel`).
