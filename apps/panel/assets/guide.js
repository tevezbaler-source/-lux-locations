/* Panelin içindeki eğitim rehberi. Yeni gelen bir çalışan bunu baştan sona okuyup
   eğitim bölümündeki alıştırmaları yaparak paneli öğrenir. */
window.LX_GUIDE = {
  intro: `
    <p>Bu panel, WhatsApp grubunda yazdığımız yerleştirme ve hesapların yerini alır. Her otel bir kart olarak görünür; karta tıklayınca o otele yerleştirdiğimiz grupları, alış ve satış fiyatlarını ve kârımızı görürsünüz.</p>
    <p>Panelde iki ayrı bölüm var:</p>
    <ul>
      <li><b>Gerçek panel:</b> ofisin gerçek işleri. Buraya yazılan her şey gerçektir ve kimin yazdığı kaydedilir.</li>
      <li><b>Eğitim bölümü:</b> örnek otel, acente ve gruplarla dolu bir deneme alanı. Burada istediğiniz gibi deneyin; gerçek kayıtlara hiçbir şey olmaz. Eğitimdeyken ekranın üstünde <b>turuncu bir çubuk</b> görünür.</li>
    </ul>
    <p>Önce aşağıdaki dersleri okuyun, sonra eğitim bölümüne geçip en alttaki alıştırmaları sırayla yapın. Toplam yaklaşık bir saat sürer.</p>`,

  lessons: [
    {
      title: 'Giriş ve ekranın düzeni',
      html: `
        <p>Panele kendi kullanıcı adınız ve şifrenizle girin. <b>Şifrenizi kimseyle paylaşmayın</b>; panelde yapılan her değişikliğin yanında giren kişinin adı yazar. Başkasının hesabıyla yapılan iş, onun yapmış gibi görünür.</p>
        <p>Üstteki menü:</p>
        <ul>
          <li><b>Oteller:</b> ana ekran, otel kartları.</li>
          <li><b>Acenteler:</b> çalıştığımız acenteler, her birinin grupları ve toplam satışı.</li>
          <li><b>Kurlar:</b> ofisin elle girdiği EUR/TL ve USD/TL kuru.</li>
          <li><b>Otel listesi:</b> otel ekleme, sözleşme fiyatları ve kontenjanlar.</li>
          <li><b>İşlem kaydı:</b> kim, ne zaman, neyi değiştirdi (yöneticiler görür).</li>
          <li><b>Eğitim:</b> bu sayfa.</li>
        </ul>
        <p>Sağ üstte adınız ve <b>Çıkış</b> düğmesi var. İşiniz bitince, özellikle ortak bilgisayarda, çıkış yapın.</p>`
    },
    {
      title: 'Otel kartları',
      html: `
        <p>Ana ekranda her otel bir karttır. Kartta şunlar yazar:</p>
        <ul>
          <li>Seçilen dönemdeki <b>grup sayısı</b> ve en dolu gecedeki oda / kontenjan çubuğu,</li>
          <li><b>Kâr</b>: büyük olarak EUR, altında USD ve TL; ayrıca toplam alış ve satış,</li>
          <li>Kırmızı etiket: o otelde <b>hatalı</b> bir grup var (ör. sözleşmeye uymayan fiyat, zarar) ya da <b>kontenjan aşılmış</b>,</li>
          <li>Sarı etiket: <b>konfirme bekleyen</b> ya da fiyatı/kuru eksik olduğu için <b>hesaplanamayan</b> grup var.</li>
        </ul>
        <p>Üstteki hazır düğmelerle (<b>Bu hafta, 30 gün, Bu ay, Gelecek ay, Yılbaşı, 90 gün</b>) ya da başlangıç/bitiş tarihiyle dönemi seçin. Bölge seçebilir, arama kutusuna otel, acente adı veya LUX kodu yazıp Enter'a basabilirsiniz. En üstteki toplam kutuları, seçilen dönemdeki tüm otellerin alış, satış ve kârını EUR, USD ve TL olarak gösterir.</p>`
    },
    {
      title: 'Otel detayı ve takvim',
      html: `
        <p>Bir karta tıklayınca otelin sayfası açılır:</p>
        <ul>
          <li><b>Takvim:</b> her gün için kaç oda dolu, kontenjan kaç. Kontenjanı aşan günler kırmızı görünür. Bir güne tıklayınca o tarihten giriş yapan yeni bir grup açılır.</li>
          <li><b>Gruplar listesi:</b> bu oteldeki tüm gruplar; kod, acente, tarih, oda, durum, alış, satış ve kâr.</li>
          <li><b>Sözleşme:</b> otelle anlaştığımız oda fiyatları ve free kuralı (ör. "her 20 odaya 1 free").</li>
        </ul>
        <p>Otelin telefonu, yetkilisi gibi bilgiler <b>Otel bilgileri</b> düğmesinin altındadır.</p>`
    },
    {
      title: 'Yeni grup girmek, adım adım',
      html: `
        <ol>
          <li>Otelin sayfasında <b>Yeni grup</b>'a basın (ya da takvimde giriş gününe tıklayın).</li>
          <li><b>Kod:</b> panel sıradaki LUX numarasını önerir. Voucher'da başka bir numara varsa onu yazın; aynı kod iki kez kullanılamaz.</li>
          <li><b>Acente:</b> yazmaya başlayın, listeden seçin. Listede yoksa yeni acente eklenir; panel benzer isimli bir acente varsa uyarır (ör. "Soca" ile "Soca Tour"). Aynı acenteyi iki kez açmayın.</li>
          <li><b>Giriş ve çıkış tarihi:</b> gece sayısı kendiliğinden hesaplanır.</li>
          <li><b>Pansiyon</b> (BB, HB…) ve <b>durum</b>. Yeni bir grup genelde <b>Teklif</b> ya da <b>Opsiyon</b> olarak açılır.</li>
          <li><b>Odalar:</b> her oda tipi için bir satır (DBL, TRPL…), adet, alış ve satış fiyatı. <b>Alışı sözleşmeden doldur</b> düğmesi alış fiyatlarını sözleşmeden getirir.</li>
          <li><b>Free odalar:</b> otelin bize verdiği free (<i>gelen</i>) ve bizim acenteye verdiğimiz free (<i>giden</i>). <b>Sözleşme kuralına göre öner</b> otelin kuralına göre kaç free hakkımız olduğunu hesaplar.</li>
          <li>Varsa <b>hizmetler</b> (transfer, rehber, tur…): alış ve satış fiyatıyla.</li>
          <li>Sağdaki hesap kutusunu kontrol edin, sonra <b>Kaydet</b>.</li>
        </ol>
        <p>Hesap kutusu siz yazdıkça güncellenir; kaydetmeden önce kârı ve uyarıları orada görürsünüz.</p>`
    },
    {
      title: 'Alış, satış, PP ve free nasıl hesaplanır',
      html: `
        <p>Panel hesabı şöyle yapar:</p>
        <p><code>gece × oda adedi × oda fiyatı</code></p>
        <ul>
          <li><b>Oda başı (room):</b> fiyat bir odanın bir gecelik fiyatıdır.</li>
          <li><b>Kişi başı (PP):</b> acenteye kişi başı fiyat verdiysek satış modunu PP seçin. Panel odadaki kişi sayısıyla çarpar: SNGL, DBL ve TWIN için 2, TRPL için 3, QUAD için 4. Örnek: PP 25 EUR, DBL oda için gecelik 50 EUR demektir.</li>
          <li><b>Gelen free</b> (otelin verdiği) alıştan düşer, <b>giden free</b> (acenteye verdiğimiz) satıştan düşer.</li>
          <li>Alış ve satış farklı para birimindeyse (ör. alış USD, satış EUR) panel kur ile çevirir. Grup konfirme olana kadar <b>son girilen kur</b> kullanılır; konfirme edildiği anda o günün kuru gruba sabitlenir.</li>
        </ul>
        <p>Kâr = satış − alış (hizmetler dahil). Sonuç <b>EUR, USD ve TL</b> olarak gösterilir.</p>`
    },
    {
      title: 'Uyarıları okumak',
      html: `
        <p>Hesap kutusunun altında üç renk uyarı çıkar:</p>
        <ul>
          <li><span class="badge" style="background:#fde2e1;color:#9b1c1c">Kırmızı (hata)</span>: düzeltmeden konfirme edemezsiniz. Örnekler: alış fiyatı sözleşmeden farklı, grup zararda, alış ya da satış fiyatı eksik, tarih hatalı.</li>
          <li><span class="badge" style="background:#fff3cd;color:#7a5b00">Sarı (dikkat)</span>: kaydedilir ama bakmanız gerekir. Örnekler: free sayısı sözleşme kuralına uymuyor, oda satırlarındaki oda sayısı ayrılan odadan fazla, kur girilmemiş.</li>
          <li><span class="badge" style="background:#e0ecf5;color:#244a63">Mavi (bilgi)</span>: sadece bilgi. Örnekler: alış ve satış farklı para biriminde, o tarih için sözleşme fiyatı girilmemiş.</li>
        </ul>
        <p><b>Sözleşmeden farklı alış fiyatı</b> bazen doğrudur (otelle o grup için ayrı anlaştık). Bu durumda <b>Alış notu</b> alanına nedenini yazın (ör. "TRPL 85 otelle görüşüldü"). Not yazılınca hata, sarı uyarıya döner.</p>`
    },
    {
      title: 'Durumlar ve konfirme kilidi',
      html: `
        <p>Bir grup şu durumlardan geçer:</p>
        <ol>
          <li><b>Teklif:</b> acenteye fiyat verdik, henüz yer tutulmadı.</li>
          <li><b>Opsiyon:</b> otelde yer tuttuk; <b>opsiyon bitiş tarihini</b> mutlaka yazın. Kontenjandan düşer.</li>
          <li><b>Konfirme:</b> kesinleşti. <b>Konfirme et ve kaydet</b> ile yapılır.</li>
          <li><b>Konaklamada:</b> grup otelde.</li>
          <li><b>Kapandı:</b> grup çıktı, hesap bitti.</li>
          <li><b>İptal:</b> iptal edildi; neden yazmak zorunludur. İptal edilen grup silinmez, geçmişte görünmeye devam eder.</li>
        </ol>
        <p><b>Konfirme, konaklamada ve kapandı</b> gruplarda fiyatlar <b>kilitlidir</b>. Konfirmeden sonra fiyat değişecekse bir yöneticiye söyleyin; kilidi sadece yönetici açabilir ve bu da kayda geçer.</p>
        <p>Kırmızı uyarısı olan grup konfirme edilemez. Zararına bilerek satılan bir grubu yalnızca yönetici onaylayabilir.</p>`
    },
    {
      title: 'İptal, devir ve otel değiştirme',
      html: `
        <ul>
          <li><b>İptal et:</b> grup iptal olduysa. Nedeni yazın ("acente vazgeçti", "otel overbooking" gibi).</li>
          <li><b>Başka acenteye devret:</b> bir acentenin bıraktığı odaları başka acenteye verdiğimizde. Panel eski grubu "devredildi" diye iptal eder, aynı odalarla yeni acente adına yeni bir grup açar. İkisi birbirine bağlı kalır.</li>
          <li><b>Başka otele taşı:</b> grubu başka otele aldığımızda (ör. otel iptal etti). Panel alış fiyatlarını yeni otelin sözleşmesinden yeniden getirir; <b>taşıdıktan sonra alışı kontrol edin</b>.</li>
          <li><b>Grubu sil:</b> sadece yanlışlıkla açılmış, hiç olmamış bir kayıt için. Gerçekten olmuş bir grup iptal edilir, silinmez.</li>
        </ul>`
    },
    {
      title: 'Kurlar',
      html: `
        <p>Kurları ofis elle girer: <b>Kurlar</b> sayfasında tarih, EUR/TL ve USD/TL yazıp kaydedin. Her sabah işe başlarken günün kurunu girmek iyi bir alışkanlıktır.</p>
        <p>Konfirme edilmemiş grupların TL ve çapraz kur hesabı son girilen kurla yapılır. Konfirme edilen grup, konfirme günündeki kuru saklar; sonradan kur değişse bile o grubun kârı değişmez.</p>`
    },
    {
      title: 'Kim ne yaptı: tarihçe ve aynı anda çalışma',
      html: `
        <ul>
          <li>Her grubun en altında <b>tarihçe</b> vardır: kim, ne zaman, hangi alanı neyden neye değiştirdi.</li>
          <li>Panel birkaç kişi aynı anda kullanılabilir. Başkası bir değişiklik yapınca sağ altta kısa bir bildirim çıkar ve ekran kendiliğinden yenilenir.</li>
          <li>Siz bir grubu düzenlerken başkası aynı grubu kaydettiyse, kaydet dediğinizde panel sizi uyarır ve kimin değiştirdiğini söyler. Böyle bir durumda <b>Yeni hâlini yükle</b> deyip değişikliğinizi tekrar yapın; böylece kimsenin işi üzerine yazılmaz.</li>
          <li>Hiçbir kayıt gerçekten silinmez. Tüm veriler her gece site dışına yedeklenir.</li>
        </ul>`
    },
    {
      title: 'Sesli asistan ve notlar',
      html: `
        <p>Sağ alttaki <b>Asistan</b> düğmesine basın (ya da <b>Ctrl + Boşluk</b>) ve ne istediğinizi söyleyin. Mikrofon kırmızıyken dinliyordur; susunca söylediğiniz yazıya dökülüp gönderilir. İsterseniz yazarak da kullanabilirsiniz.</p>
        <p>Örnekler:</p>
        <ul>
          <li>"Glorious'a Marwen için 10 Aralık'tan 3 gece 10 DBL opsiyon aç, alış sözleşmeden, satış 55 dolar, opsiyon 25 Kasım'a kadar."</li>
          <li>"LUX6302'yi konfirme et, otelin konfirme kodu RM-4471."</li>
          <li>"Bu hafta opsiyonu biten gruplar hangileri?" · "Bu ay en çok kâr eden otel hangisi?"</li>
          <li>"Bugünün kuru euro 50,10, dolar 43,05."</li>
          <li>"Not al: pazartesi Glorious'u ara, Harun Bey yaz fiyatını verecek."</li>
        </ul>
        <p>Kayıtları değiştiren her işlemde asistan önce bir <b>onay kartı</b> gösterir: ne yapacağını, kârı ve uyarıları yazar. <b>Onayla</b>'ya basmadan hiçbir şey değişmez. Kartı okuyun; yanlış anladıysa <b>Vazgeç</b> deyip düzeltin. Notlar onaysız eklenir.</p>
        <p>Asistan da sizin hesabınızla çalışır: yetkiniz olmayan işi yapamaz ve yaptığı her şey işlem kaydına adınızla, "(sesli asistan)" notuyla yazılır.</p>
        <p><b>Notlar</b> sayfasında ofisin hatırlatmaları durur. Tarihi geçen not kırmızı çizgiyle görünür; yapılınca kutucuğu işaretleyin.</p>`
    },
    {
      title: 'Altın kurallar',
      html: `
        <ol>
          <li>WhatsApp'a değil, <b>panele</b> yazın. Panelde olmayan grup yok sayılır.</li>
          <li>Herkes <b>kendi hesabıyla</b> girer.</li>
          <li>Kırmızı uyarıyı görmezden gelmeyin; düzeltin ya da alış notuna nedenini yazın.</li>
          <li>Opsiyonlarda <b>bitiş tarihini</b> yazın.</li>
          <li>Acenteyi listeden seçin; aynı acenteyi ikinci kez açmayın.</li>
          <li>Olmuş bir grubu silmeyin, <b>iptal edin</b> ve nedenini yazın.</li>
          <li>Emin olmadığınız bir şeyi önce <b>eğitim bölümünde</b> deneyin.</li>
        </ol>`
    }
  ],

  tasks: [
    `Üst menüden <b>Eğitim</b>'e girin ve <b>Eğitim bölümüne geç ve dene</b>'ye basın. Ekranın üstünde turuncu çubuğu görün.`,
    `<b>Oteller</b> ekranında dönemi <b>Bu ay</b>, <b>Gelecek ay</b> ve <b>Yılbaşı</b> yaparak kartların nasıl değiştiğine bakın. En çok kâr eden oteli bulun.`,
    `<b>Glorious</b> otelini açın, <b>LUX6309</b> grubuna girin. Kırmızı uyarıları okuyun: alış 48 USD girilmiş ama sözleşme 45, satış 40 olduğu için grup zararda. <b>Alışı sözleşmeden doldur</b>'a basın, satışı 55 yapın, uyarıların kaybolduğunu görüp kaydedin.`,
    `<b>Yiğitalp</b> otelindeki <b>LUX6310</b> grubunda satış fiyatı eksik. DBL satışını 70 EUR, TRPL satışını 95 EUR girin ve kaydedin. Kârın EUR, USD ve TL olarak çıktığını görün.`,
    `<b>Ramada Grand Bazaar</b> otelindeki <b>LUX6302</b> opsiyonunu <b>Konfirme et ve kaydet</b> ile konfirme edin. Fiyat kutularının kilitlendiğini görün. (Yönetici hesabıyla girdiyseniz kilit yerine "siz yönetici olduğunuz için değiştirebilirsiniz" yazısı çıkar.)`,
    `<b>İlkbal Deluxe</b> otelinde takvimden boş bir güne tıklayıp yeni bir grup açın: acente <b>Basilica</b>, 3 gece, 15 DBL. <b>Alışı sözleşmeden doldur</b> ve <b>Sözleşme kuralına göre öner</b>'i kullanın, satışı girip <b>opsiyon</b> olarak kaydedin. Opsiyon bitiş tarihini yazmayı unutmayın.`,
    `Açtığınız grubu <b>Başka acenteye devret</b> ile <b>Hello</b> acentesine devredin. Eski grubun iptal olduğunu, yenisinin açıldığını görün.`,
    `<b>Kurlar</b> sayfasında bugünün tarihiyle yeni bir kur girin (ör. EUR 50,10 / USD 43,05). Konfirme edilmemiş grupların TL kârının değiştiğini, konfirme olanların değişmediğini kontrol edin.`,
    `<b>Grand Madrid</b>'deki <b>LUX6304</b> grubunun altındaki tarihçeye bakın; sizin yaptığınız değişikliklerin adınızla yazdığını görün.`,
    `<b>Asistan</b> düğmesine basıp "Bu ay en çok kâr eden otel hangisi?" diye sorun. Sonra "Not al: yarın Hello Holidays'i ara" deyin ve notun <b>Notlar</b> sayfasına düştüğünü görün.`,
    `Asistana "<b>LUX6307</b>'yi konfirme et" deyin. Onay kartını okuyun ve <b>Vazgeç</b>'e basın; grubun değişmediğini kontrol edin.`,
    `Bitirdiğinizde turuncu çubuktaki <b>Gerçek panele dön</b>'e basın. Gerçek panelde örnek verilerin hiçbirinin görünmediğini kontrol edin.`
  ]
};
