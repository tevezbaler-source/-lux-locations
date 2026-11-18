<?php
// Eğitim bölümü için örnek veriler. Ofis WhatsApp grubundaki tipik kayıtlardan uyarlandı; tarihler bugüne göre kayar.
// Bu veriler YALNIZ eğitim veritabanına yazılır, gerçek panele asla girmez.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function lx_load_sample(PDO $pdo): void {
    $t = date('Y-m-d H:i:s');
    $d = fn(int $n) => date('Y-m-d', strtotime(sprintf('today %+d day', $n)));
    $ins = function (string $table, array $row) use ($pdo): int {
        $cols = array_keys($row);
        $st = $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($row));
        return (int)$pdo->lastInsertId();
    };
    $meta = ['version' => 1, 'created_at' => $t, 'updated_at' => $t];

    $pdo->beginTransaction();

    $ins('fx_rates', ['rate_date' => $d(0), 'eur_try' => 49.50, 'usd_try' => 42.50, 'note' => 'Eğitim için örnek kur'] + $meta);

    $hotels = [
        'grand_marcello' => ['Grand Marcello Hotel', 4, 'Laleli', 'Erdal Bey'],
        'marcello' => ['Marcello Hotel', 4, 'Laleli', 'Erdal Bey'],
        'buyuk_sahinler' => ['Büyük Şahinler Hotel', 4, 'Laleli', 'Satış ofisi'],
        'ilkbal' => ['İlkbal Deluxe Hotel', 4, 'Laleli', 'Veli Bey (yeni müdür)'],
        'antik' => ['Antik Hotel', 4, 'Laleli', 'Mutlu Hanım'],
        'blue_marmaray' => ['Blue Marmaray Hotel', 4, 'Laleli', 'Mehmet'],
        'glorious' => ['Glorious Hotel', 4, 'Laleli', 'Harun Bey'],
        'grand_madrid' => ['Grand Madrid Hotel', 4, 'Laleli', 'Canser Hanım'],
        'new_emin' => ['New Emin Hotel', 4, 'Laleli', 'Sait Bey'],
        'royal' => ['İstanbul Royal Hotel', 4, 'Laleli', 'Satış ofisi'],
        'ramada' => ['Ramada Hotel Grand Bazaar', 4, 'Laleli', 'Marina Hanım'],
        'yigitalp' => ['Yiğitalp Hotel', 4, 'Laleli', 'Satış ofisi'],
    ];
    $H = [];
    foreach ($hotels as $k => [$name, $stars, $region, $contact]) {
        $H[$k] = $ins('hotels', ['name' => $name, 'stars' => $stars, 'region' => $region, 'contacts' => $contact, 'notes' => 'Eğitim verisi', 'active' => 1] + $meta);
    }

    // Sözleşme fiyatları: [otel, başlangıç, bitiş, döviz, sngl, dbl, trpl, free_every, free_type, görüşülen]
    $rates = [
        ['grand_marcello', -30, 200, 'USD', 50, 50, 70, 25, 'DBL', 'Erdal Bey'],
        ['marcello', -30, 200, 'USD', 48, 48, 68, 25, 'DBL', 'Erdal Bey'],
        ['buyuk_sahinler', -30, 200, 'USD', 50, 50, 70, 25, 'DBL', 'Satış ofisi'],
        ['ilkbal', -30, 200, 'EUR', 50, 50, 70, 25, 'DBL', 'Veli Bey'],
        ['antik', -30, 200, 'USD', 50, 50, 70, null, null, 'Mutlu Hanım'],
        ['blue_marmaray', -30, 60, 'USD', 45, 45, 60, 25, 'DBL', 'Mehmet'],
        ['glorious', -30, 130, 'USD', 45, 45, 60, 25, 'DBL', 'Harun Bey'],
        ['grand_madrid', -30, 200, 'EUR', 50, 50, 75, 25, 'DBL', 'Canser Hanım'],
        ['new_emin', -30, 200, 'USD', 45, 45, 65, 25, 'DBL', 'Sait Bey'],
        ['royal', -30, 200, 'USD', 50, 50, 70, 25, 'DBL', 'Satış ofisi'],
        ['ramada', -30, 200, 'USD', 60, 60, 80, 25, 'DBL', 'Marina Hanım'],
        ['yigitalp', -30, 200, 'USD', 60, 60, 85, 25, 'DBL', 'Satış ofisi'],
    ];
    foreach ($rates as [$k, $f, $to, $cur, $s, $db, $tr, $fe, $ft, $who]) {
        $ins('hotel_rates', ['hotel_id' => $H[$k], 'date_from' => $d($f), 'date_to' => $d($to), 'currency' => $cur, 'board' => 'BB',
            'sngl' => $s, 'dbl' => $db, 'twin' => $db, 'trpl' => $tr, 'quad' => $db * 2, 'invoiced' => 1, 'free_every' => $fe, 'free_type' => $ft,
            'contact' => $who, 'note' => "$who ile görüşmeye istinaden"] + $meta);
    }
    // Glorious için faturasız ayrı fiyat örneği
    $ins('hotel_rates', ['hotel_id' => $H['glorious'], 'date_from' => $d(131), 'date_to' => $d(220), 'currency' => 'USD', 'board' => 'BB',
        'sngl' => 52, 'dbl' => 52, 'twin' => 52, 'trpl' => 70, 'quad' => 104, 'invoiced' => 1, 'free_every' => 25, 'free_type' => 'DBL',
        'contact' => 'Harun Bey', 'note' => 'Yaz dönemi fiyatı'] + $meta);

    foreach (['grand_marcello' => 50, 'marcello' => 25, 'royal' => 75, 'ilkbal' => 75, 'buyuk_sahinler' => 50, 'grand_madrid' => 25] as $k => $n) {
        $ins('hotel_allotments', ['hotel_id' => $H[$k], 'date_from' => $d(-10), 'date_to' => $d(200), 'rooms' => $n, 'note' => 'Otelin bize verdiği oda'] + $meta);
    }

    $agencies = [
        'soca' => ['Soca Tour', 'Romanya', 'Soco', 'Lazar'],
        'tudan' => ['Tudan Romania', 'Romanya', 'Tudan', 'Lazar'],
        'metropolitan' => ['Metropolitan Travel', 'Romanya', 'SC Metropolitan Travel SRL', 'Lazar'],
        'cistour' => ['Cistour', 'Romanya', 'Cis', 'Şeyma'],
        'hello' => ['Hello Holidays', 'Romanya', 'Hello', 'Şeyma'],
        'ade' => ['Ade Travel', 'Romanya', '', 'Lazar'],
        'totpol' => ['Tot Pol', 'Romanya', '', 'Lazar'],
        'ozlem' => ['Özlem Tur', 'Türkiye', '', 'Milad'],
        'basilica' => ['Basilica', 'Romanya', '', 'Lazar'],
        'marwen' => ['Marwen', 'Fas', 'Mervan', 'Maho'],
        'star' => ['Star Travel', 'Romanya', '', 'Milad'],
        'qerimi' => ['Qerimi (Atmaxha)', 'Arnavutluk', 'Atmaxha', 'Kenan'],
    ];
    $A = [];
    foreach ($agencies as $k => [$name, $country, $alias, $owner]) {
        $A[$k] = $ins('agencies', ['name' => $name, 'country' => $country, 'aliases' => $alias ?: null, 'owner' => $owner, 'notes' => 'Eğitim verisi', 'active' => 1] + $meta);
    }
    $pdo->prepare('UPDATE agencies SET payment_terms = ? WHERE id = ?')->execute(['Rezervasyonu aynı gün veya ertesi gün öder, ayrıca her cuma 1.000 USD.', $A['marwen']]);

    // Gruplar: [kod, otel, acente, giriş(gün), gece, durum, alış döviz, satış döviz, satış şekli, odalar[[tip,adet,alış,satış]], free[[yön,tip,adet]], not, alış notu]
    $books = [
        ['LUX6301', 'ilkbal', 'totpol', 9, 4, 'konfirme', 'EUR', 'EUR', 'room', [['DBL', 75, 50, 55]], [['in', 'DBL', 3], ['out', 'DBL', 3]], '75 oda, 3 DBL free iki tarafta. Veli Bey ile görüşmeye istinaden.', null],
        ['LUX6302', 'ramada', 'tudan', 10, 3, 'opsiyon', 'USD', 'EUR', 'room', [['DBL', 25, 60, 58]], [['in', 'DBL', 1], ['out', 'DBL', 1]], 'Listede 21 oda, 4 oda opsiyon. Alış USD, satış EUR!', null],
        ['LUX6303', 'new_emin', 'ozlem', 10, 3, 'konfirme', 'USD', 'EUR', 'pp', [['DBL', 25, 45, 25]], [['in', 'DBL', 1], ['out', 'DBL', 1]], 'Royal iptal oldu, New Emin\'e alındı. Satış PP25 EUR.', null],
        ['LUX6304', 'grand_madrid', 'ade', 42, 4, 'opsiyon', 'EUR', 'EUR', 'room', [['DBL', 25, 50, 75]], [['in', 'DBL', 1]], 'Yılbaşı. Star Travel iptal edildi, Ade Travel\'e devredildi.', null],
        ['LUX6305', 'royal', 'cistour', 41, 4, 'konfirme', 'USD', 'EUR', 'room', [['DBL', 40, 50, 70], ['TRPL', 10, 70, 100]], [['in', 'DBL', 2]], 'Yılbaşı 75 odanın 50\'si Cistour. 25 oda boşta.', null],
        ['LUX6306', 'buyuk_sahinler', 'soca', 50, 3, 'konfirme', 'USD', 'EUR', 'room', [['DBL', 30, 50, 55]], [['in', 'DBL', 1], ['out', 'DBL', 1]], null, null],
        ['LUX6307', 'grand_marcello', 'metropolitan', 11, 4, 'opsiyon', 'USD', 'EUR', 'room', [['DBL', 20, 50, 56], ['QUAD', 5, 100, 112]], [['in', 'DBL', 1], ['out', 'DBL', 1]], 'Quad satış = DBL x 2 (herkese standart).', null],
        ['LUX6308', 'antik', 'hello', 3, 3, 'konaklamada', 'USD', 'EUR', 'room', [['DBL', 4, 50, 60]], [], 'Sena Hanım\'ın unuttuğu 4 oda. Ödemesi Sena Hanım\'dan.', null],
        // Eğitim için bilerek hatalı grup: alış sözleşmede 38 değil 48 girilmiş, satış alışın altında
        ['LUX6309', 'glorious', 'marwen', 6, 2, 'teklif', 'USD', 'USD', 'room', [['DBL', 10, 48, 40]], [], 'EĞİTİM: bu grupta hata var. Alış 48 USD girilmiş, sözleşme 45; satış 40. Uyarıları inceleyin.', null],
        // Satışı henüz yazılmamış grup
        ['LUX6310', 'yigitalp', 'metropolitan', 10, 4, 'teklif', 'USD', 'EUR', 'room', [['DBL', 29, 60, null], ['TRPL', 1, 85, null]], [['in', 'TRPL', 1], ['out', 'DBL', 1]], 'Satış fiyatı henüz belli değil.', 'TRPL 85 otelle görüşüldü (sözleşmede 85).'],
        ['LUX6311', 'grand_marcello', 'star', 42, 4, 'iptal', 'USD', 'EUR', 'room', [['DBL', 25, 50, 64]], [['in', 'DBL', 1], ['out', 'DBL', 1]], null, null],
    ];
    foreach ($books as [$code, $hk, $ak, $inDay, $n, $status, $bc, $sc, $mode, $rooms, $free, $note, $buyNote]) {
        $row = ['code' => $code, 'hotel_id' => $H[$hk], 'agency_id' => $A[$ak], 'check_in' => $d($inDay), 'check_out' => $d($inDay + $n), 'board' => 'BB',
            'status' => $status, 'rooms_reserved' => array_sum(array_column($rooms, 1)), 'buy_cur' => $bc, 'sell_cur' => $sc, 'sell_mode' => $mode,
            'notes' => $note, 'buy_note' => $buyNote, 'deleted' => 0] + $meta;
        if (in_array($status, ['konfirme', 'konaklamada'], true)) { $row['eur_try'] = 49.50; $row['usd_try'] = 42.50; $row['hotel_conf_code'] = 'KNF-' . substr($code, 3); }
        if ($status === 'opsiyon') $row['option_until'] = $d(3);
        if ($status === 'iptal') $row['cancel_reason'] = 'Ade Travel acentesine devredildi. 1.500 EUR nakit ön ödeme Star Travel\'e iade edilecek.';
        $bid = $ins('bookings', $row);
        foreach ($rooms as [$type, $qty, $buy, $sell]) $ins('booking_rooms', ['booking_id' => $bid, 'room_type' => $type, 'qty' => $qty, 'buy_price' => $buy, 'sell_price' => $sell]);
        foreach ($free as [$dir, $type, $qty]) $ins('booking_free', ['booking_id' => $bid, 'direction' => $dir, 'room_type' => $type, 'qty' => $qty]);
        if ($code === 'LUX6304') {
            $ins('booking_services', ['booking_id' => $bid, 'kind' => 'Transfer', 'description' => 'Havalimanı gidiş-dönüş', 'buy' => 250, 'sell' => 350, 'currency' => 'EUR']);
            $ins('booking_services', ['booking_id' => $bid, 'kind' => 'Rehber', 'description' => 'Şehir turu', 'buy' => 80, 'sell' => 100, 'currency' => 'EUR']);
        }
        $ins('audit_log', ['at' => $t, 'user_name' => 'Eğitim', 'entity' => 'grup', 'entity_id' => $bid, 'action' => 'ekle', 'summary' => "Örnek grup eklendi: $code"]);
    }
    $pdo->commit();
}
