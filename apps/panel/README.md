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

Hosting IHS, yönetim paneli **Plesk**. Sitenin kök klasörü FTP'de `httpdocs`.

1. Önce FTP ve Plesk şifrelerini değiştirin (panelde fiyat, kâr ve acente bilgileri olacak).
2. Plesk → **Web Siteleri ve Alan Adları → PHP**: sürüm **8.2 veya üstü** olmalı (asistan kütüphanesi bunu ister).
3. Plesk → **Veritabanları → Veritabanı Ekle**: MySQL/MariaDB, bir veritabanı adı ve kullanıcı oluşturun. Şifreyi siz belirleyin.
4. `config.sample.php` dosyasını `config.php` olarak kopyalayın: `'driver' => 'mysql'`, veritabanı adı, kullanıcı ve şifre.
5. `apps/panel` klasörünün içeriğini `vendor/` dahil FTP ile `httpdocs/panel/` altına yükleyin.
   `data/*.sqlite` dosyalarını yüklemeyin. `/admin/` ve sitenin diğer dosyalarına dokunmayın.
6. https://luxlocationstours.com/panel/ açın, yönetici hesabını oluşturun, kullanıcıları ekleyin.
7. `lib/`, `data/`, `tools/`, `vendor/` tarayıcıdan açılamaz. Kontrol: https://luxlocationstours.com/panel/lib/db.php **403** vermeli.

**PC'deki verileri taşımak:** `data/panel.sqlite` dosyasını sunucuda `httpdocs/panel/data/` altına yükleyin ve
`tools/aktar-mysql.php` betiğini bir kez çalıştırın (Plesk → **Zamanlanmış Görevler → Görev Ekle → PHP betiği**, ardından **Şimdi çalıştır**).
Kullanıcılar şifreleriyle birlikte, tüm kayıtlar ve işlem kaydı aktarılır. MySQL'de kayıt varsa betik hiçbir şeyin üzerine yazmaz.
Bittikten sonra sunucudaki `data/panel.sqlite` dosyasını silin. Bu durumda 6. adımdaki yönetici kurulumu çıkmaz; PC'deki kullanıcıyla girilir.

## 3. Yedek (site çökse de geçmiş kaybolmasın)

- Panelde hiçbir kayıt gerçekten silinmez (iptal ve silme işaretlenir, tarihçe kalır).
- Plesk → **Zamanlanmış Görevler → Görev Ekle → PHP betiği**: `httpdocs/panel/tools/backup.php`, her gün 03:00.
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
3. Kullandıkça ücretlendirilir (varsayılan model Haiku: basit bir komut yaklaşık 0,1-0,3 sent). Console'da aylık harcama sınırı koyabilirsiniz.
   Model `config.php` içindeki `assistant_model` ile değişir; karmaşık işler için `claude-sonnet-5-5`.

## Şifre unutulursa

Yönetici başka bir kullanıcının şifresini **Kullanıcılar** sayfasından değiştirebilir.
Yöneticinin kendi şifresi unutulduysa, panel klasöründe komut satırından:
```bat
D:\php\php.exe tools\sifre-sifirla.php
```
Kullanıcıları listeler; kullanıcı adını, istenirse yeni kullanıcı adını ve yeni şifreyi sorar. Bu dosya tarayıcıdan açılamaz; soru sorduğu için sunucuda değil PC'de çalışır; sunucudaki yönetici şifresi unutulursa diğer yönetici değiştirir ya da Claude'a yazın.

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
| `tools/backup.php` | Gece yedeği (zamanlanmış görev) |
| `tools/aktar-mysql.php` | PC verisini (SQLite) sunucu MySQL'ine bir kez aktarma |
