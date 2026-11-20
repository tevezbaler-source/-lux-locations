# Otel Paneli

Ofisin WhatsApp grubunda tuttuğu yerleştirme ve alış/satış hesaplarının yerine geçen ekip paneli.
Oteller kart kart görünür; otele tıklayınca takvim, gruplar, sözleşme fiyatları ve kâr (EUR, USD, TL) çıkar.

- PHP 8.1+ ile çalışır, derleme gerekmez. PC'de SQLite, sunucuda MySQL kullanır.
- Her kullanıcının kendi hesabı var; her değişiklik kim, ne zaman, hangi IP ile yaptı diye kaydedilir.
- Gerçek panel **boş** başlar. Örnek veriler yalnızca **Eğitim bölümünde** (ayrı dosya: `data/egitim.sqlite`).
- Panelin içinde yeni çalışanlar için **Eğitim** sayfası var (dersler + alıştırmalar).

## 1. PC'de çalıştırma (her şey D: diskinde)

1. PHP'yi indirin: https://windows.php.net/download → "VS16 x64 Thread Safe" zip. `D:\php` klasörüne açın.
2. `D:\php\php.ini-development` dosyasını `D:\php\php.ini` olarak kopyalayın ve şu satırların başındaki `;` işaretini kaldırın:
   ```
   extension_dir = "ext"
   extension=pdo_sqlite
   extension=sqlite3
   extension=mbstring
   ```
3. Komut istemcisinde:
   ```bat
   cd /d "D:\lUX LOCATİON\lux-locations\apps\panel"
   D:\php\php.exe -S localhost:8080
   ```
4. Tarayıcıda http://localhost:8080 açın. İlk açılışta **yönetici hesabı** oluşturulur (adınız, kullanıcı adınız, şifreniz).
5. **Kullanıcılar** sayfasından ofisteki herkes için ayrı hesap açın.

Veriler `apps/panel/data/panel.sqlite` dosyasındadır (git'e girmez). Bu dosyayı kopyalamak yedek almaktır.

Aynı ağdaki başka bilgisayarlardan denemek için `D:\php\php.exe -S 0.0.0.0:8080` ile başlatıp
`http://<bu-pc-ip>:8080` adresini açın.

## 2. Siteye taşıma (luxlocationstours.com/panel/)

1. cPanel → **MySQL Databases**: bir veritabanı ve kullanıcı oluşturun, kullanıcıya tüm yetkileri verin.
2. `config.sample.php` dosyasını `config.php` olarak kopyalayın: `'driver' => 'mysql'` yapın, veritabanı adı, kullanıcı ve şifreyi yazın.
3. `apps/panel` klasörünün içeriğini FTP ile sunucuda `public_html/panel/` altına yükleyin.
   `data/*.sqlite` dosyalarını yüklemeyin. `/admin/` ve sitenin diğer dosyalarına dokunmayın.
4. https://luxlocationstours.com/panel/ açın, yönetici hesabını oluşturun, kullanıcıları ekleyin.
5. `lib/`, `data/`, `tools/` klasörlerinde `.htaccess` var; tarayıcıdan açılamazlar. Kontrol için
   https://luxlocationstours.com/panel/lib/db.php adresi **403** vermeli.

PC'de girdiğiniz gerçek verileri sunucuya taşımak gerekirse söyleyin; SQLite'tan MySQL'e aktarma betiği eklenir.

## 3. Yedek (site çökse de geçmiş kaybolmasın)

- Panelde hiçbir kayıt gerçekten silinmez (iptal ve silme işaretlenir, tarihçe kalır).
- cPanel → **Cron Jobs**, her gece 03:00:
  ```
  php /home/KULLANICI/public_html/panel/tools/backup.php
  ```
  `data/backups/` altına sıkıştırılmış tam yedek yazar, 60 günden eskileri siler.
- **Site dışı kopya:** Yönetici, **İşlem kaydı** sayfasındaki **Yedek indir (JSON)** düğmesiyle tüm verinin JSON yedeğini indirir.
  Haftada bir indirip PC'de `D:\` altında saklayın.

## Sesli asistan

Sağ alttaki **Asistan** düğmesi (ya da Ctrl + Boşluk) asistan penceresini açar. Konuşarak ya da yazarak panelde her işi yaptırabilirsiniz:
grup açma/güncelleme/konfirme, iptal, devir, taşıma, kur, otel, sözleşme, kontenjan, acente ve not. Sorulara panel verisiyle cevap verir.

- Kayıtları değiştiren her işlemden önce **onay kartı** çıkar; "Onayla" denmeden hiçbir şey değişmez. Notlar onaysız eklenir.
- Asistan, giren kullanıcının rolünün izin verdiği işleri yapabilir. Yaptığı her şey işlem kaydına kullanıcının adıyla, "(sesli asistan)" notuyla düşer.
- Ses tanıma Chrome ve Edge'de çalışır (tarayıcının kendi servisi). Sitede HTTPS gerekir; localhost'ta da çalışır. "Sesli cevap" kutusu cevabı sesli okutur.
- Komutlar ve ilgili panel verileri (otel, acente, fiyat) yapay zekâ servisine (Anthropic Claude API) gönderilir.

Kurulum:
1. Kütüphane: panel klasöründe `composer install` (PC'de `D:\php\php.exe composer.phar install`). Oluşan `vendor/` klasörü sunucuya da yüklenir.
   PHP'de `extension=curl`, `extension=openssl`, `extension=zip` açık olmalı.
2. API anahtarı: https://console.anthropic.com → API Keys → yeni anahtar. Yönetici olarak panele girip asistan penceresine yapıştırın
   (`data/asistan-anahtar.php` dosyasına yazılır, ekranda bir daha gösterilmez). İsterseniz `config.php` içindeki `anthropic_api_key` alanına da yazabilirsiniz.
   Anahtarı değiştirmek için `data/asistan-anahtar.php` dosyasını silip yeniden girin.
3. Kullandıkça ücretlendirilir; Console'da aylık harcama sınırı koyabilirsiniz.

## Şifre unutulursa

Yönetici başka bir kullanıcının şifresini **Kullanıcılar** sayfasından değiştirebilir.
Yöneticinin kendi şifresi unutulduysa, panel klasöründe komut satırından:
```bat
D:\php\php.exe tools\sifre-sifirla.php
```
Kullanıcıları listeler; kullanıcı adını, istenirse yeni kullanıcı adını ve yeni şifreyi sorar. Bu dosya tarayıcıdan açılamaz; sunucuda cPanel Terminal ile çalıştırılır.

## Roller

| Rol | Yapabildikleri |
| --- | --- |
| Yönetici | Her şey; kullanıcılar, işlem kaydı, konfirme kilidini açma, yedek |
| Operasyon | Grup, otel, sözleşme, acente, kur |
| Rezervasyon | Grup, otel, sözleşme, acente |
| Muhasebe | Kur, acente; diğerlerini görür |

## Dosyalar

| Yol | İçerik |
| --- | --- |
| `index.php`, `assets/` | Ekran (tek sayfa uygulama), `assets/guide.js` eğitim metni |
| `api.php` | Tüm veri işlemleri (JSON) |
| `lib/assistant.php` | Sesli asistan: araçlar, onay kartları, Claude API çağrısı |
| `lib/calc.php` | Alış/satış/free/PP/kur ve kâr hesabı, uyarılar |
| `lib/data.php` | Kayıt, sürüm kontrolü (aynı anda düzenleme), iptal/devir/taşıma |
| `lib/sample.php` | Eğitim bölümünün örnek verileri |
| `tools/backup.php` | Gece yedeği (cron) |
