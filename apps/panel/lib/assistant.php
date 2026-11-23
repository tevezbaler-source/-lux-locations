<?php
// Sesli asistan: kullanıcının sözünü panel işlemlerine çevirir (Claude API, araç kullanımı).
// Okuma işlemleri ve not ekleme hemen çalışır; diğer her değişiklik önce kullanıcıya onay kartı olarak gösterilir.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

use Anthropic\Client;

const ASST_MAX_STEPS = 10;
const ASST_MAX_MESSAGES = 80;

/* ---------------- Ayarlar ---------------- */

function asst_key_file(): string { return dirname(__DIR__) . '/data/asistan-anahtar.php'; }

/** Anahtar sırası: config.php → panelden girilen (data/asistan-anahtar.php) → ortam değişkeni. */
function asst_api_key(): string {
    $c = lx_config();
    $k = trim((string)($c['anthropic_api_key'] ?? ''));
    if ($k === '' && is_file(asst_key_file())) { $f = include asst_key_file(); $k = trim((string)($f['key'] ?? '')); }
    if ($k === '') $k = (string)(getenv('ANTHROPIC_API_KEY') ?: '');
    return $k;
}

function asst_status(): array {
    $vendor = is_file(dirname(__DIR__) . '/vendor/autoload.php');
    $k = asst_api_key();
    return ['installed' => $vendor, 'configured' => $vendor && $k !== '', 'key_hint' => $k !== '' ? '…' . substr($k, -4) : null, 'can_set_key' => can('users')];
}

/** Yönetici anahtarı panelden girer; anahtar tarayıcıya hiç geri gönderilmez. */
function asst_set_key(string $key): array {
    need('users');
    $key = trim($key);
    if ($key !== '' && !preg_match('/^[A-Za-z0-9_\-]{20,300}$/', $key)) throw new ApiError('Anahtar biçimi hatalı. Anthropic Console\'dan kopyaladığınız anahtarı olduğu gibi yapıştırın.');
    $f = asst_key_file();
    if ($key === '') { if (is_file($f)) unlink($f); audit('ayar', null, 'asistan_anahtar', 'Asistan API anahtarı kaldırıldı', null, null, db_main()); }
    else {
        file_put_contents($f, "<?php\n// Panelden girilen asistan API anahtarı. Paylaşmayın.\nreturn " . var_export(['key' => $key, 'at' => now()], true) . ";\n", LOCK_EX);
        @chmod($f, 0600);
        audit('ayar', null, 'asistan_anahtar', 'Asistan API anahtarı girildi (…' . substr($key, -4) . ')', null, null, db_main());
    }
    return asst_status();
}

function asst_client(): Client {
    $auto = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($auto)) throw new ApiError('Asistan kütüphanesi kurulmamış (vendor klasörü yok). README "Sesli asistan" bölümüne bakın.', 503);
    require_once $auto;
    $key = asst_api_key();
    if ($key === '') throw new ApiError('Asistan için config.php içine anthropic_api_key yazılmalı.', 503);
    $c = lx_config();
    return new Client(apiKey: $key, baseUrl: ($c['anthropic_base_url'] ?? null) ?: null, requestOptions: ['timeout' => 120, 'maxRetries' => 2]);
}

/* ---------------- Araçlar ---------------- */

/** Okuma araçları ve not: onaysız çalışır. */
const ASST_INSTANT = ['ara', 'otel_ozeti', 'otel_detayi', 'grup_getir', 'grup_listesi', 'acente_detayi', 'kurlar', 'notlar', 'grup_onizle', 'not_ekle', 'not_tamamla'];

function asst_tools(): array {
    $obj = fn(array $props, array $req = []) => ['type' => 'object', 'properties' => (object)$props] + ($req ? ['required' => $req] : []);
    $str = fn(string $d) => ['type' => 'string', 'description' => $d];
    $int = fn(string $d) => ['type' => 'integer', 'description' => $d];
    $num = fn(string $d) => ['type' => 'number', 'description' => $d];
    $date = fn(string $d) => ['type' => 'string', 'description' => $d . ' (YYYY-AA-GG)'];
    $roomItem = $obj([
        'room_type' => ['type' => 'string', 'enum' => ROOM_TYPES],
        'qty' => $int('Oda adedi'),
        'buy_price' => $num('Alış: oda başı gecelik fiyat (alış dövizinde). Boşsa ve fill_buy_from_contract true ise sözleşmeden gelir.'),
        'sell_price' => $num('Satış: oda başı gecelik fiyat; sell_mode "pp" ise kişi başı gecelik fiyat (satış dövizinde).'),
    ], ['room_type', 'qty']);
    $freeItem = $obj(['direction' => ['type' => 'string', 'enum' => ['in', 'out'], 'description' => 'in: otelin bize verdiği free (alıştan düşer); out: bizim acenteye verdiğimiz free (satıştan düşer)'],
        'room_type' => ['type' => 'string', 'enum' => ROOM_TYPES], 'qty' => $int('Adet')], ['direction', 'room_type', 'qty']);
    $srvItem = $obj(['kind' => $str('Hizmet türü: Transfer, Rehber, Tur, Gala, Tekne...'), 'description' => $str('Açıklama'), 'buy' => $num('Alış tutarı (toplam)'),
        'sell' => $num('Satış tutarı (toplam)'), 'currency' => ['type' => 'string', 'enum' => CURRENCIES]], ['kind', 'currency']);
    $bookingFields = [
        'hotel_id' => $int('Otel id'),
        'agency_id' => $int('Acente id. Acente panelde yoksa boş bırakıp new_agency_name verin.'),
        'new_agency_name' => $str('Panelde olmayan yeni acentenin adı (onayla birlikte acente de eklenir).'),
        'code' => $str('Voucher kodu (LUX...). Boşsa sıradaki kod verilir.'),
        'check_in' => $date('Giriş'), 'check_out' => $date('Çıkış'),
        'board' => ['type' => 'string', 'enum' => ['BB', 'HB', 'FB', 'RO']],
        'status' => ['type' => 'string', 'enum' => array_keys(STATUSES), 'description' => 'teklif, opsiyon, konfirme, konaklamada, kapandi. İptal için grup_iptal kullanın.'],
        'rooms_reserved' => $int('Otelden ayrılan oda sayısı'), 'rooms_option' => $int('Opsiyondaki oda sayısı'), 'option_until' => $date('Opsiyon bitiş'),
        'hotel_conf_code' => $str('Otel konfirme kodu'),
        'buy_cur' => ['type' => 'string', 'enum' => CURRENCIES], 'sell_cur' => ['type' => 'string', 'enum' => CURRENCIES],
        'sell_mode' => ['type' => 'string', 'enum' => ['room', 'pp'], 'description' => 'Satış oda başı (room) mı kişi başı (pp) mı'],
        'rooms' => ['type' => 'array', 'items' => $roomItem, 'description' => 'Oda satırlarının TAMAMI (verilirse eskisinin yerine geçer)'],
        'free' => ['type' => 'array', 'items' => $freeItem, 'description' => 'Free satırlarının TAMAMI'],
        'services' => ['type' => 'array', 'items' => $srvItem, 'description' => 'Ek hizmetlerin TAMAMI'],
        'fill_buy_from_contract' => ['type' => 'boolean', 'description' => 'Alışı boş oda satırlarına otelin sözleşme fiyatını yaz'],
        'buy_note' => $str('Alış notu: sözleşmeden farklı alışın nedeni'),
        'notes' => $str('Grubun genel notu (tamamı; eklemek için append_note kullanın)'),
        'append_note' => $str('Grubun notunun sonuna eklenecek satır'),
        'eur_try' => $num('Gruba özel EUR/TL kuru (genelde boş)'), 'usd_try' => $num('Gruba özel USD/TL kuru (genelde boş)'),
        'force' => ['type' => 'boolean', 'description' => 'Aynı otel/acente/tarihte grup varken yine de yeni grup aç (kullanıcı açıkça isterse)'],
    ];
    $t = fn(string $name, string $desc, array $schema) => ['name' => $name, 'description' => $desc, 'inputSchema' => $schema];
    return [
        $t('ara', 'Otel, acente (diğer yazımları dahil) ve grup kodu arar. Kullanıcının söylediği adı id\'ye çevirmek için önce bunu kullanın.', $obj(['query' => $str('Aranan ad veya kod')], ['query'])),
        $t('otel_ozeti', 'Tarih aralığında konaklayan grupların otel bazında özeti: grup sayısı, alış/satış, kâr EUR/USD/TL, hatalı ve bekleyen gruplar, kontenjan doluluğu.', $obj(['from' => $date('Başlangıç'), 'to' => $date('Bitiş (hariç)'), 'search' => $str('Otel/acente/kod süzgeci')], ['from', 'to'])),
        $t('otel_detayi', 'Bir otelin sözleşme fiyatları, kontenjanları, notları ve tarih aralığındaki grupları.', $obj(['hotel_id' => $int('Otel id'), 'from' => $date('Başlangıç (boşsa bugünden 3 gün önce)'), 'days' => $int('Gün sayısı (7-92, varsayılan 45)')], ['hotel_id'])),
        $t('grup_getir', 'Bir grubun tüm bilgileri: odalar, fiyatlar, free, hizmetler, hesap, uyarılar, son değişiklikler.', $obj(['booking_id' => $int('Grup id'), 'code' => $str('veya voucher kodu')])),
        $t('grup_listesi', 'Grupları süzerek listeler (konaklama tarihi aralığa değen gruplar).', $obj(['from' => $date('Başlangıç'), 'to' => $date('Bitiş'), 'hotel_id' => $int('Otel'), 'agency_id' => $int('Acente'),
            'status' => ['type' => 'string', 'enum' => array_keys(STATUSES)], 'option_until_before' => $date('Opsiyonu bu tarihe kadar bitenler')])),
        $t('acente_detayi', 'Acente bilgileri (ödeme anlaşması, notlar) ve grupları.', $obj(['agency_id' => $int('Acente id')], ['agency_id'])),
        $t('kurlar', 'Paneldeki son kurlar (ofisin girdiği ve TCMB\'den otomatik alınanlar) ve Merkez Bankası\'nın güncel döviz alış/satış kurları.', $obj([])),
        $t('notlar', 'Not defterindeki notlar.', $obj(['include_done' => ['type' => 'boolean'], 'hotel_id' => $int('Otel'), 'agency_id' => $int('Acente'), 'booking_id' => $int('Grup')])),
        $t('grup_onizle', 'Kaydetmeden bir grubun hesabını ve uyarılarını görür (yeni grup için tüm alanlar, mevcut grup için booking_id + değişen alanlar).', $obj(['booking_id' => $int('Mevcut grup id (değişiklik önizlemesi için)')] + $bookingFields)),
        $t('not_ekle', 'Not defterine not veya hatırlatma ekler (onaysız). Notu ilgili otel, acente veya gruba bağlayabilirsiniz.', $obj(['text' => $str('Not'), 'due_date' => $date('Hatırlatma tarihi'),
            'hotel_id' => $int('Otel'), 'agency_id' => $int('Acente'), 'booking_id' => $int('Grup')], ['text'])),
        $t('not_tamamla', 'Notu yapıldı olarak işaretler.', $obj(['note_id' => $int('Not id')], ['note_id'])),
        // Onay isteyenler
        $t('grup_olustur', 'Yeni grup açar. Kullanıcı onaylayınca kaydedilir.', $obj($bookingFields, ['hotel_id', 'check_in', 'check_out', 'rooms'])),
        $t('grup_guncelle', 'Mevcut grubu değiştirir (yalnız verilen alanlar değişir). Durum değiştirmek (opsiyon, konfirme, konaklamada, kapandı) da bununla yapılır. Kullanıcı onaylayınca kaydedilir.', $obj(['booking_id' => $int('Grup id')] + $bookingFields, ['booking_id'])),
        $t('grup_iptal', 'Grubu iptal eder (kayıt silinmez). Neden zorunlu.', $obj(['booking_id' => $int('Grup id'), 'reason' => $str('İptal nedeni ve ön ödeme ne olacak')], ['booking_id', 'reason'])),
        $t('grup_devret', 'Grubu başka acenteye devreder: eski grup iptal olur, aynı odalarla yeni acente için yeni grup açılır.', $obj(['booking_id' => $int('Grup id'), 'agency_id' => $int('Yeni acente id'), 'check_in' => $date('Yeni giriş (değişmiyorsa boş)'), 'check_out' => $date('Yeni çıkış')], ['booking_id', 'agency_id'])),
        $t('grup_tasi', 'Grubu başka otele taşır; alış yeni otelin sözleşmesinden gelir.', $obj(['booking_id' => $int('Grup id'), 'hotel_id' => $int('Yeni otel id')], ['booking_id', 'hotel_id'])),
        $t('grup_sil', 'Yanlış açılmış, hiç olmamış grubu siler. Gerçek bir grup için grup_iptal kullanın.', $obj(['booking_id' => $int('Grup id')], ['booking_id'])),
        $t('kur_gir', 'Günün kurunu girer.', $obj(['rate_date' => $date('Tarih (boşsa bugün)'), 'eur_try' => $num('1 EUR kaç TL'), 'usd_try' => $num('1 USD kaç TL'), 'note' => $str('Not')], ['eur_try', 'usd_try'])),
        $t('otel_kaydet', 'Otel ekler veya günceller (hotel_id verilirse yalnız verilen alanlar değişir).', $obj(['hotel_id' => $int('Güncellenecek otel'), 'name' => $str('Ad'), 'stars' => $int('Yıldız'), 'region' => $str('Bölge'),
            'address' => $str('Adres'), 'contacts' => $str('Yetkili ve telefonlar'), 'notes' => $str('Notlar (tamamı)'), 'append_note' => $str('Notun sonuna eklenecek satır'), 'active' => ['type' => 'boolean']])),
        $t('sozlesme_kaydet', 'Otelin sözleşme (dönem) fiyatını ekler veya günceller.', $obj(['rate_id' => $int('Güncellenecek sözleşme satırı'), 'hotel_id' => $int('Otel'), 'date_from' => $date('Başlangıç'), 'date_to' => $date('Bitiş'),
            'currency' => ['type' => 'string', 'enum' => CURRENCIES], 'board' => ['type' => 'string', 'enum' => ['BB', 'HB', 'FB', 'RO']], 'sngl' => $num('SNGL'), 'dbl' => $num('DBL'), 'twin' => $num('TWIN'), 'trpl' => $num('TRPL'), 'quad' => $num('QUAD'),
            'invoiced' => ['type' => 'boolean', 'description' => 'Faturalı mı'], 'free_every' => $int('Kaç odaya 1 free'), 'free_type' => ['type' => 'string', 'enum' => ROOM_TYPES], 'contact' => $str('Kiminle görüşüldü'), 'note' => $str('Not')], ['hotel_id'])),
        $t('kontenjan_kaydet', 'Otelin bize verdiği oda kontenjanını ekler veya günceller.', $obj(['allotment_id' => $int('Güncellenecek kontenjan'), 'hotel_id' => $int('Otel'), 'date_from' => $date('Başlangıç'), 'date_to' => $date('Bitiş'), 'rooms' => $int('Oda'), 'note' => $str('Not')], ['hotel_id'])),
        $t('acente_kaydet', 'Acente ekler veya günceller (agency_id verilirse yalnız verilen alanlar değişir).', $obj(['agency_id' => $int('Güncellenecek acente'), 'name' => $str('Ad'), 'country' => $str('Ülke'), 'aliases' => $str('Diğer yazımlar, virgülle'),
            'owner' => $str('Bizden sorumlu kişi'), 'contact' => $str('İletişim'), 'payment_terms' => $str('Ödeme anlaşması'), 'notes' => $str('Notlar (tamamı)'), 'append_note' => $str('Notun sonuna eklenecek satır'), 'active' => ['type' => 'boolean']])),
    ];
}

function asst_system(): array {
    $u = current_user();
    $hotels = array_map(fn($h) => "#{$h['id']} {$h['name']}" . ($h['active'] ? '' : ' (pasif)'), q_all('SELECT id, name, active FROM hotels ORDER BY name'));
    $agencies = array_map(fn($a) => "#{$a['id']} {$a['name']}" . ($a['aliases'] ? " ({$a['aliases']})" : ''), q_all('SELECT id, name, aliases FROM agencies WHERE active = 1 ORDER BY name'));
    $rules = <<<TXT
Sen Lux Locations (İstanbul, otel ve grup turu şirketi) ofisinin otel paneli asistanısın. Ofis çalışanları sana Türkçe, çoğu zaman sesle konuşur; konuşma yazıya çevrilirken otel ve acente adları bozulabilir, adları panelde en yakın kayıtla eşleştir ve emin değilsen sor.

Görevin: kullanıcının istediğini panelin araçlarıyla yapmak ve sorularını panel verisiyle cevaplamak.
- Kullanıcıya her zaman kısa, sade Türkçe ile cevap ver. Cevabın sesli okunabilir; tablo ve madde işareti yerine kısa cümleler kur, rakamları açık yaz (ör. 1.440 euro).
- Okuma araçlarını ve not_ekle / not_tamamla'yı serbestçe kullan. Diğer araçlar (grup_olustur, grup_guncelle, grup_iptal, grup_devret, grup_tasi, grup_sil, kur_gir, otel_kaydet, sozlesme_kaydet, kontenjan_kaydet, acente_kaydet) hemen çalışmaz: kullanıcıya onay kartı olarak gösterilir, onaylarsa çalışır. Bu yüzden "yapayım mı?" diye ayrıca sorma, doğrudan aracı çağır; kartı kullanıcı onaylar ya da reddeder.
- Uydurma yapma. Kullanıcının söylemediği fiyatı, tarihi, oda sayısını tahmin etme. Alış fiyatı söylenmediyse fill_buy_from_contract kullan. Satış fiyatı söylenmediyse boş bırak ve bunu söyle. Tarih yılı söylenmediyse en yakın gelecekteki tarihi al.
- Hesap kuralları panelde: gece × oda × fiyat; PP satışta DBL/TWIN/SNGL ×2, TRPL ×3, QUAD ×4; gelen free alıştan, giden free satıştan düşer. Kâr EUR, USD ve TL olarak gösterilir. Konfirme, konaklamada ve kapandı gruplarda fiyatlar kilitlidir (yalnız Yönetici değiştirir). Kırmızı uyarılı grup konfirme edilemez; sözleşmeden farklı alışta buy_note gerekir.
- Bir işlem hata verirse hatayı kullanıcıya sade dille anlat; kullanıcı onaylamadıysa ısrar etme.
- Grubu kodu ile söyle (LUX...). Cevabın sonunda gereksiz soru sorma.
TXT;
    $ctx = "Bugün: " . date('Y-m-d') . ' (' . ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'][(int)date('w')] . ").\n"
        . "Kullanıcı: {$u['name']} (" . ROLES[$u['role']] . '). Yetkileri: ' . implode(', ', PERMS[$u['role']] ?? []) . ".\n"
        . (!empty($_SESSION['egitim']) ? "ŞU AN EĞİTİM BÖLÜMÜNDESİN: veriler örnektir, gerçek değildir.\n" : '')
        . 'Oteller: ' . (implode('; ', $hotels) ?: 'henüz yok') . "\n"
        . 'Acenteler: ' . (implode('; ', $agencies) ?: 'henüz yok');
    return [['type' => 'text', 'text' => $rules], ['type' => 'text', 'text' => $ctx]];
}

/* ---------------- Araçların çalıştırılması ---------------- */

function asst_compact_booking(array $b): array {
    $c = $b['calc'] ?? calc_booking($b);
    return [
        'id' => (int)$b['id'], 'code' => $b['code'], 'hotel' => $b['hotel_name'] ?? q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]),
        'agency' => $b['agency_name'] ?? ($b['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]) : null),
        'check_in' => $b['check_in'], 'check_out' => $b['check_out'], 'status' => $b['status'], 'option_until' => $b['option_until'] ?? null,
        'rooms' => $c['rooms'] ?? null, 'buy' => [$c['buy_net'] ?? null, $c['buy_cur'] ?? null], 'sell' => [$c['sell_net'] ?? null, $c['sell_cur'] ?? null],
        'profit_eur' => $c['profit_eur'] ?? null, 'profit_usd' => $c['profit_usd'] ?? null, 'profit_try' => $c['profit_try'] ?? null,
        'warnings' => array_map(fn($w) => $w['level'] . ': ' . $w['msg'], $c['warnings'] ?? []),
    ];
}

function asst_find_booking(array $in): array {
    if (!empty($in['booking_id'])) return booking_get((int)$in['booking_id']);
    $code = trim((string)($in['code'] ?? ''));
    if ($code !== '') {
        $id = q_val('SELECT id FROM bookings WHERE UPPER(code) = UPPER(?) AND deleted = 0 ORDER BY id DESC', [$code]);
        if ($id) return booking_get((int)$id);
    }
    throw new ApiError('Grup bulunamadı.', 404);
}

/** Mevcut grup + verilen değişiklikler → booking_save girdisi. */
function asst_booking_merge(?array $old, array $in): array {
    $fields = ['hotel_id', 'agency_id', 'code', 'check_in', 'check_out', 'board', 'status', 'rooms_reserved', 'rooms_option', 'option_until', 'hotel_conf_code',
        'buy_cur', 'sell_cur', 'sell_mode', 'eur_try', 'usd_try', 'buy_note', 'notes', 'rooms', 'free', 'services'];
    $b = [];
    foreach ($fields as $f) $b[$f] = $old[$f] ?? null;
    if (!$old) { $b['status'] = 'teklif'; $b['board'] = 'BB'; $b['sell_mode'] = 'room'; $b['rooms'] = $b['free'] = $b['services'] = []; }
    foreach ($fields as $f) if (array_key_exists($f, $in) && $in[$f] !== null) $b[$f] = $in[$f];
    if (!empty($in['append_note'])) $b['notes'] = trim(($b['notes'] ?? '') . "\n" . $in['append_note']);
    $contract = $b['hotel_id'] && $b['check_in'] ? contract_for((int)$b['hotel_id'], (string)$b['check_in']) : null;
    if (!$old) {
        $b['buy_cur'] = $b['buy_cur'] ?: ($contract['currency'] ?? 'USD');
        $b['sell_cur'] = $b['sell_cur'] ?: 'EUR';
        if (empty($b['rooms_reserved'])) $b['rooms_reserved'] = array_sum(array_map(fn($r) => (int)($r['qty'] ?? 0), $b['rooms'] ?? []));
    }
    if (!empty($in['fill_buy_from_contract']) && $contract) {
        $b['buy_cur'] = $contract['currency'];
        foreach ($b['rooms'] as &$r) if (($r['buy_price'] ?? null) === null || $r['buy_price'] === '') $r['buy_price'] = contract_price($contract, $r['room_type']);
        unset($r);
    }
    if ($old) { $b['id'] = (int)$old['id']; $b['version'] = (int)$old['version']; }
    if (!empty($in['force'])) $b['force'] = 1;
    return $b;
}

function asst_merge_simple(array $old, array $in, array $map, array $fields): array {
    $d = $old;
    foreach ($fields as $f) if (array_key_exists($f, $in) && $in[$f] !== null) $d[$f] = is_bool($in[$f]) ? (int)$in[$f] : $in[$f];
    if (!empty($in['append_note'])) $d['notes'] = trim(($d['notes'] ?? '') . "\n" . $in['append_note']);
    foreach ($map as $from => $to) if (isset($in[$from])) $d[$to] = $in[$from];
    return $d;
}

/** Ses yazıya çevrilirken bozulan adları da yakalayan gevşek ad eşleştirme. */
function asst_name_match(string $query, array $names): bool {
    $generic = ['hotel', 'otel', 'travel', 'tour', 'tours', 'tur', 'turizm', 'the', 'grand', 'deluxe', 'otelinin', 'oteli', 'acente', 'acentesi'];
    $words = fn(string $x) => array_values(array_filter(preg_split('/\s+/', strtr(mb_strtolower($x), ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c'])), fn($w) => mb_strlen($w) >= 3 && !in_array($w, $generic, true)));
    $qn = norm_name($query);
    $qw = array_map('norm_name', $words($query));
    foreach ($names as $name) {
        $n = norm_name((string)$name);
        if ($n === '' || $qn === '') continue;
        if (str_contains($n, $qn) || str_contains($qn, $n)) return true;
        foreach (array_map('norm_name', $words((string)$name)) as $nw) {
            foreach ($qw as $w) {
                if ($w === '' || $nw === '') continue;
                if ($w === $nw || levenshtein($w, $nw) <= max(1, intdiv(min(strlen($w), strlen($nw)), 4))) return true;
            }
        }
    }
    return false;
}

/** Okuma araçları ve not: hemen çalışır, sonuç diziye döner. */
function asst_run_instant(string $name, array $in): array {
    switch ($name) {
        case 'ara':
            $s = norm_name((string)($in['query'] ?? ''));
            if ($s === '') return ['hotels' => [], 'agencies' => [], 'bookings' => []];
            $q = (string)$in['query'];
            $hs = array_values(array_filter(q_all('SELECT id, name, region, active FROM hotels'), fn($h) => asst_name_match($q, [$h['name']])));
            $as = array_values(array_filter(q_all('SELECT id, name, aliases, country, active FROM agencies'), fn($a) => asst_name_match($q, array_merge([$a['name']], explode(',', (string)$a['aliases'])))));
            $bs = array_map(fn($r) => asst_compact_booking(booking_get((int)$r['id'])), q_all("SELECT id FROM bookings WHERE deleted = 0 AND UPPER(code) LIKE UPPER(?) LIMIT 10", ['%' . trim((string)$in['query']) . '%']));
            return ['hotels' => $hs, 'agencies' => $as, 'bookings' => $bs];
        case 'otel_ozeti':
            $from = clean_date($in['from'] ?? '') ?: date('Y-m-d'); $to = clean_date($in['to'] ?? '') ?: date_add_days($from, 30);
            if ($to <= $from || nights_between($from, $to) > 370) throw new ApiError('Tarih aralığı hatalı (en fazla 1 yıl).');
            $rows = hotels_grid($from, $to, (string)($in['search'] ?? ''));
            return ['from' => $from, 'to' => $to, 'rate' => latest_rate(), 'hotels' => array_map(fn($h) => ['id' => (int)$h['id'], 'name' => $h['name']] + $h['summary'], $rows)];
        case 'otel_detayi':
            $from = clean_date($in['from'] ?? '') ?: date_add_days(date('Y-m-d'), -3);
            $days = max(7, min(92, (int)($in['days'] ?? 45)));
            $h = hotel_detail((int)($in['hotel_id'] ?? 0), $from, date_add_days($from, $days));
            $h['bookings'] = array_map('asst_compact_booking', $h['bookings']);
            $h['busy_nights'] = array_values(array_filter($h['nights'], fn($n) => $n['reserved'] > 0));
            unset($h['nights'], $h['history']);
            $h['notes_open'] = notes_list(false, ['hotel_id' => (int)$h['id']]);
            return $h;
        case 'grup_getir':
            $b = booking_full((int)asst_find_booking($in)['id']);
            $c = $b['calc'];
            $b['history'] = array_slice(array_map(fn($h) => "{$h['at']} {$h['user_name']}: {$h['summary']}", $b['history']), 0, 8);
            $b['calc'] = ['nights' => $c['nights'], 'rooms' => $c['rooms'], 'buy_net' => $c['buy_net'], 'buy_cur' => $c['buy_cur'], 'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'],
                'profit_eur' => $c['profit_eur'], 'profit_usd' => $c['profit_usd'], 'profit_try' => $c['profit_try'], 'rate' => [$c['eur_try'], $c['usd_try'], $c['rate_source']],
                'contract' => $c['contract'], 'warnings' => $c['warnings']];
            return $b;
        case 'grup_listesi':
            $w = ['deleted = 0']; $p = [];
            if (!empty($in['from'])) { $w[] = 'check_out > ?'; $p[] = clean_date($in['from']); }
            if (!empty($in['to'])) { $w[] = 'check_in < ?'; $p[] = clean_date($in['to']); }
            foreach (['hotel_id', 'agency_id'] as $k) if (!empty($in[$k])) { $w[] = "$k = ?"; $p[] = (int)$in[$k]; }
            if (!empty($in['status'])) { $w[] = 'status = ?'; $p[] = $in['status']; }
            if (!empty($in['option_until_before'])) { $w[] = "option_until IS NOT NULL AND option_until <= ? AND status = 'opsiyon'"; $p[] = clean_date($in['option_until_before']); }
            $ids = q_all('SELECT id FROM bookings WHERE ' . implode(' AND ', $w) . ' ORDER BY check_in, id LIMIT 60', $p);
            return ['count' => count($ids), 'bookings' => array_map(fn($r) => asst_compact_booking(booking_get((int)$r['id'])), $ids)];
        case 'acente_detayi':
            $a = q_one('SELECT * FROM agencies WHERE id = ?', [(int)($in['agency_id'] ?? 0)]);
            if (!$a) throw new ApiError('Acente bulunamadı.', 404);
            $a['bookings'] = array_map(fn($r) => asst_compact_booking(booking_get((int)$r['id'])), q_all('SELECT id FROM bookings WHERE agency_id = ? AND deleted = 0 ORDER BY check_in DESC LIMIT 40', [$a['id']]));
            $a['notes_open'] = notes_list(false, ['agency_id' => (int)$a['id']]);
            return $a;
        case 'kurlar':
            require_once __DIR__ . '/tcmb.php';
            return ['rates' => q_all('SELECT rate_date, eur_try, usd_try, note FROM fx_rates ORDER BY rate_date DESC, id DESC LIMIT 10'), 'tcmb_guncel' => tcmb_rates()];
        case 'notlar':
            return ['notes' => array_map(fn($n) => array_intersect_key($n, array_flip(['id', 'text', 'due_date', 'done', 'hotel_name', 'agency_name', 'booking_code', 'created_by_name', 'created_at'])),
                notes_list(!empty($in['include_done']), $in))];
        case 'grup_onizle':
            $old = !empty($in['booking_id']) ? booking_get((int)$in['booking_id']) : null;
            $b = booking_input(asst_booking_merge($old, $in));
            $c = calc_booking($b);
            return ['calc' => ['nights' => $c['nights'], 'rooms' => $c['rooms'], 'buy_net' => $c['buy_net'], 'buy_cur' => $c['buy_cur'], 'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'],
                'profit_eur' => $c['profit_eur'], 'profit_usd' => $c['profit_usd'], 'profit_try' => $c['profit_try'], 'warnings' => $c['warnings']], 'rooms' => $b['rooms'], 'free' => $b['free']];
        case 'not_ekle':
            $n = note_save($in);
            return ['ok' => true, 'note_id' => (int)$n['id'], 'done_summary' => 'Not eklendi: ' . mb_substr($n['text'], 0, 80)];
        case 'not_tamamla':
            $n = note_done((int)($in['note_id'] ?? 0));
            return ['ok' => true, 'done_summary' => 'Not tamamlandı: ' . mb_substr($n['text'], 0, 80)];
    }
    throw new ApiError("Bilinmeyen araç: $name");
}

/** Onay kartı: ne yapılacağını insan diliyle anlatır (henüz hiçbir şey değişmez). */
function asst_describe(string $name, array $in): array {
    $hn = fn($id) => $id ? (q_val('SELECT name FROM hotels WHERE id = ?', [(int)$id]) ?: "otel #$id") : '?';
    $an = fn($id) => $id ? (q_val('SELECT name FROM agencies WHERE id = ?', [(int)$id]) ?: "acente #$id") : null;
    $bl = function ($id) use ($hn, $an) { $b = booking_get((int)$id); return trim(($b['code'] ?: "#{$b['id']}") . ' · ' . $hn($b['hotel_id']) . ' / ' . ($an($b['agency_id']) ?? '') . ' · ' . $b['check_in']); };
    $lines = []; $calc = null; $title = '';
    switch ($name) {
        case 'grup_olustur':
        case 'grup_guncelle':
            $old = $name === 'grup_guncelle' ? booking_get((int)$in['booking_id']) : null;
            $m = asst_booking_merge($old, $in);
            $b = booking_input($m);
            $c = calc_booking($b);
            $title = $old ? 'Grubu güncelle: ' . $bl($old['id']) : 'Yeni grup aç';
            $ag = $an($b['agency_id']) ?? (!empty($in['new_agency_name']) ? $in['new_agency_name'] . ' (yeni acente eklenecek)' : 'acente yok');
            if (!$old) $lines[] = $hn($b['hotel_id']) . ' · ' . $ag . ' · ' . ($b['code'] ?: 'kod: ' . next_code());
            else {
                $diff = audit_diff(json_encode(booking_snapshot($old)), json_encode(booking_snapshot($b)));
                foreach ($diff as $d) $lines[] = "{$d['field']}: {$d['from']} → {$d['to']}";
                if (!$diff) $lines[] = 'Değişiklik yok.';
            }
            if (!$old) {
                $lines[] = "{$b['check_in']} → {$b['check_out']} ({$c['nights']} gece) · {$b['board']} · " . STATUSES[$b['status']] . ($b['option_until'] ? " · opsiyon {$b['option_until']}'e kadar" : '');
                foreach ($b['rooms'] as $r) $lines[] = "{$r['qty']} {$r['room_type']} · alış " . ($r['buy_price'] ?? '—') . " {$b['buy_cur']} · satış " . ($r['sell_price'] ?? '—') . " {$b['sell_cur']}" . ($b['sell_mode'] === 'pp' ? ' (kişi başı)' : '');
                foreach ($b['free'] as $f) $lines[] = ($f['direction'] === 'in' ? 'Otelden alınan' : 'Acenteye verilen') . " free: {$f['qty']} {$f['room_type']}";
                foreach ($b['services'] as $s) $lines[] = "Hizmet: {$s['kind']} " . ($s['description'] ?? '') . " alış " . ($s['buy'] ?? '—') . ' / satış ' . ($s['sell'] ?? '—') . " {$s['currency']}";
                if ($b['notes']) $lines[] = 'Not: ' . mb_substr($b['notes'], 0, 200);
            }
            $calc = ['profit_eur' => $c['profit_eur'], 'profit_usd' => $c['profit_usd'], 'profit_try' => $c['profit_try'], 'buy' => [$c['buy_net'], $c['buy_cur']], 'sell' => [$c['sell_net'], $c['sell_cur']], 'warnings' => $c['warnings']];
            break;
        case 'grup_iptal': $title = 'Grubu iptal et: ' . $bl($in['booking_id']); $lines[] = 'Neden: ' . ($in['reason'] ?? ''); break;
        case 'grup_devret': $title = 'Başka acenteye devret: ' . $bl($in['booking_id']); $lines[] = 'Yeni acente: ' . ($an($in['agency_id'] ?? null) ?? '?') . (!empty($in['check_in']) ? " · yeni tarih {$in['check_in']} → " . ($in['check_out'] ?? '') : ''); $lines[] = 'Eski grup iptal olur, yeni grup açılır.'; break;
        case 'grup_tasi': $title = 'Başka otele taşı: ' . $bl($in['booking_id']); $lines[] = 'Yeni otel: ' . $hn($in['hotel_id'] ?? null) . '. Alış yeni otelin sözleşmesinden gelir.'; break;
        case 'grup_sil': $title = 'Grubu sil: ' . $bl($in['booking_id']); $lines[] = 'Yalnız yanlış açılmış kayıtlar için. Kayıt saklanır.'; break;
        case 'kur_gir': $title = 'Kur gir'; $lines[] = ($in['rate_date'] ?? date('Y-m-d')) . ': 1 € = ' . ($in['eur_try'] ?? '?') . ' TL, 1 $ = ' . ($in['usd_try'] ?? '?') . ' TL'; break;
        case 'otel_kaydet': $title = !empty($in['hotel_id']) ? 'Oteli güncelle: ' . $hn($in['hotel_id']) : 'Yeni otel: ' . ($in['name'] ?? ''); foreach ($in as $k => $v) if (!in_array($k, ['hotel_id'], true)) $lines[] = "$k: " . (is_bool($v) ? ($v ? 'evet' : 'hayır') : $v); break;
        case 'sozlesme_kaydet': $title = (!empty($in['rate_id']) ? 'Sözleşme fiyatını güncelle: ' : 'Sözleşme fiyatı ekle: ') . $hn($in['hotel_id'] ?? null); foreach ($in as $k => $v) if (!in_array($k, ['hotel_id', 'rate_id'], true)) $lines[] = "$k: " . (is_bool($v) ? ($v ? 'evet' : 'hayır') : $v); break;
        case 'kontenjan_kaydet': $title = 'Kontenjan: ' . $hn($in['hotel_id'] ?? null); $lines[] = ($in['date_from'] ?? '') . ' → ' . ($in['date_to'] ?? '') . ': ' . ($in['rooms'] ?? '') . ' oda'; break;
        case 'acente_kaydet': $title = !empty($in['agency_id']) ? 'Acenteyi güncelle: ' . $an($in['agency_id']) : 'Yeni acente: ' . ($in['name'] ?? ''); foreach ($in as $k => $v) if ($k !== 'agency_id') $lines[] = "$k: " . (is_bool($v) ? ($v ? 'evet' : 'hayır') : $v); break;
        default: throw new ApiError("Bilinmeyen araç: $name");
    }
    return ['tool' => $name, 'title' => $title, 'lines' => $lines, 'calc' => $calc];
}

/** Onaylanan değişikliği yapar. */
function asst_run_confirmed(string $name, array $in): array {
    switch ($name) {
        case 'grup_olustur':
        case 'grup_guncelle':
            $old = $name === 'grup_guncelle' ? booking_get((int)$in['booking_id']) : null;
            $m = asst_booking_merge($old, $in);
            if (!$old && empty($m['agency_id']) && !empty($in['new_agency_name'])) {
                $a = agency_find_by_name((string)$in['new_agency_name']) ?? agency_save(['name' => $in['new_agency_name']]);
                $m['agency_id'] = (int)$a['id'];
            }
            if (!$old && empty($m['code'])) $m['code'] = next_code();
            $b = booking_save($m);
            return ['ok' => true, 'booking_id' => (int)$b['id'], 'code' => $b['code'], 'link' => '#/grup/' . $b['id'], 'result' => asst_compact_booking($b),
                'done_summary' => ($old ? 'Grup güncellendi: ' : 'Grup açıldı: ') . ($b['code'] ?: '#' . $b['id'])];
        case 'grup_iptal':
            $b = booking_get((int)$in['booking_id']);
            $r = booking_cancel((int)$b['id'], (string)($in['reason'] ?? ''), (int)$b['version']);
            return ['ok' => true, 'link' => '#/grup/' . $r['id'], 'done_summary' => 'Grup iptal edildi: ' . ($r['code'] ?: '#' . $r['id'])];
        case 'grup_devret':
            $b = booking_get((int)$in['booking_id']);
            $r = booking_transfer((int)$b['id'], (int)$in['agency_id'], clean_date($in['check_in'] ?? ''), clean_date($in['check_out'] ?? ''), (int)$b['version']);
            return ['ok' => true, 'new_booking_id' => (int)$r['id'], 'code' => $r['code'], 'link' => '#/grup/' . $r['id'], 'done_summary' => "Grup devredildi, yeni grup {$r['code']}"];
        case 'grup_tasi':
            $b = booking_get((int)$in['booking_id']);
            $r = booking_move((int)$b['id'], (int)$in['hotel_id'], (int)$b['version']);
            return ['ok' => true, 'link' => '#/grup/' . $r['id'], 'result' => asst_compact_booking($r), 'done_summary' => 'Grup taşındı: ' . ($r['code'] ?: '#' . $r['id'])];
        case 'grup_sil':
            booking_delete((int)$in['booking_id']);
            return ['ok' => true, 'done_summary' => 'Grup silindi.'];
        case 'kur_gir':
            $r = fx_save($in);
            return ['ok' => true, 'link' => '#/kurlar', 'done_summary' => "Kur girildi: {$r['rate_date']}"];
        case 'otel_kaydet':
            $old = !empty($in['hotel_id']) ? hotel_get((int)$in['hotel_id']) : [];
            $d = asst_merge_simple($old, $in, ['hotel_id' => 'id'], ['name', 'stars', 'region', 'address', 'contacts', 'notes', 'active']);
            $r = hotel_save($d);
            return ['ok' => true, 'hotel_id' => (int)$r['id'], 'link' => '#/otel/' . $r['id'], 'done_summary' => 'Otel kaydedildi: ' . $r['name']];
        case 'sozlesme_kaydet':
            $old = !empty($in['rate_id']) ? (q_one('SELECT * FROM hotel_rates WHERE id = ?', [(int)$in['rate_id']]) ?? []) : [];
            $d = asst_merge_simple($old, $in, ['rate_id' => 'id'], ['hotel_id', 'date_from', 'date_to', 'currency', 'board', 'sngl', 'dbl', 'twin', 'trpl', 'quad', 'invoiced', 'free_every', 'free_type', 'contact', 'note']);
            $r = rate_save($d);
            return ['ok' => true, 'link' => '#/otel/' . $r['hotel_id'], 'done_summary' => 'Sözleşme fiyatı kaydedildi.'];
        case 'kontenjan_kaydet':
            $old = !empty($in['allotment_id']) ? (q_one('SELECT * FROM hotel_allotments WHERE id = ?', [(int)$in['allotment_id']]) ?? []) : [];
            $d = asst_merge_simple($old, $in, ['allotment_id' => 'id'], ['hotel_id', 'date_from', 'date_to', 'rooms', 'note']);
            $r = allotment_save($d);
            return ['ok' => true, 'link' => '#/otel/' . $r['hotel_id'], 'done_summary' => 'Kontenjan kaydedildi.'];
        case 'acente_kaydet':
            $old = !empty($in['agency_id']) ? (q_one('SELECT * FROM agencies WHERE id = ?', [(int)$in['agency_id']]) ?? []) : [];
            $d = asst_merge_simple($old, $in, ['agency_id' => 'id'], ['name', 'country', 'aliases', 'owner', 'contact', 'payment_terms', 'notes', 'active']);
            $r = agency_save($d);
            return ['ok' => true, 'agency_id' => (int)$r['id'], 'link' => '#/acente/' . $r['id'], 'done_summary' => 'Acente kaydedildi: ' . $r['name']];
    }
    throw new ApiError("Bilinmeyen araç: $name");
}

/** Bir aracı güvenle çalıştırıp tool_result bloğu üretir. */
function asst_result_block(string $id, callable $fn, array &$done): array {
    try {
        $GLOBALS['LX_VIA_ASSISTANT'] = true;
        $r = $fn();
        if (!empty($r['done_summary'])) $done[] = ['summary' => $r['done_summary'], 'link' => $r['link'] ?? null];
        return ['type' => 'tool_result', 'toolUseID' => $id, 'content' => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)];
    } catch (ApiError $e) {
        $msg = $e->getMessage() . (!empty($e->extra['duplicate']) ? ' (Kullanıcı isterse force: true ile tekrar deneyin.)' : '');
        return ['type' => 'tool_result', 'toolUseID' => $id, 'content' => 'Hata: ' . $msg, 'isError' => true];
    } catch (Throwable $e) {
        error_log('asistan araç hatası: ' . $e->getMessage());
        return ['type' => 'tool_result', 'toolUseID' => $id, 'content' => 'Hata: işlem yapılamadı.', 'isError' => true];
    } finally {
        $GLOBALS['LX_VIA_ASSISTANT'] = false;
    }
}

/* ---------------- Konuşma döngüsü ---------------- */

function asst_key(): string { return !empty($_SESSION['egitim']) ? 'asst_egitim' : 'asst'; }

function asst_state(): array {
    $s = $_SESSION[asst_key()] ?? null;
    if (!$s || (time() - ($s['t'] ?? 0)) > 3600 || count($s['messages'] ?? []) > ASST_MAX_MESSAGES) $s = ['messages' => [], 'pending' => null, 't' => time()];
    return $s;
}

function asst_save_state(array $s): void {
    lx_session_start();
    $s['t'] = time();
    $_SESSION[asst_key()] = $s;
}

/** SDK içerik bloğunu tekrar gönderilebilecek diziye çevirir (thinking blokları olduğu gibi korunur). */
function asst_block_array($block): array {
    return json_decode(json_encode($block), true);
}

/** Hatanın kısa ve güvenli açıklaması (anahtar gizlenir): sınıf, HTTP kodu, API mesajı. */
function asst_error_reason(Throwable $e): string {
    $cls = substr(strrchr('\\' . get_class($e), '\\'), 1);
    $code = method_exists($e, 'getCode') && $e->getCode() ? ' ' . $e->getCode() : '';
    $msg = $e->getMessage() . ($e->getPrevious() ? ' / ' . $e->getPrevious()->getMessage() : '');
    $msg = preg_replace('/sk-ant-[A-Za-z0-9_\-]+/', 'sk-ant-***', $msg);
    $msg = mb_substr(trim(preg_replace('/\s+/', ' ', $msg)), 0, 300);
    $why = $cls . $code . ': ' . $msg;
    @file_put_contents(dirname(__DIR__) . '/data/asistan.log', date('Y-m-d H:i:s') . ' ' . $why . "\n", FILE_APPEND | LOCK_EX);
    return $why;
}

function asst_call(array $messages): array {
    $c = lx_config();
    $client = asst_client();
    $model = $c['assistant_model'] ?? 'claude-haiku-5-5';
    // Yedek model (sunucu yoğunsa) Haiku'da yok; yalnızca diğer modellerde istenir.
    $fb = str_contains($model, 'haiku') ? [] : ['fallbacks' => 'default', 'betas' => ['server-side-fallback-2026-07-01']];
    $resp = $client->beta->messages->create(...[
        'model' => $model,
        'maxTokens' => 16000,
        'system' => asst_system(),
        'tools' => asst_tools(),
        'messages' => $messages,
        'outputConfig' => ['effort' => $c['assistant_effort'] ?? 'medium'],
        'cacheControl' => ['type' => 'ephemeral'],
    ] + $fb);
    return ['stop' => $resp->stopReason, 'content' => array_map('asst_block_array', $resp->content)];
}

/**
 * Döngüyü onay gerektiren bir araca ya da son cevaba kadar sürdürür.
 * $s['messages'] son mesajı kullanıcı mesajı (metin veya tool_result) olacak şekilde gelir.
 */
function asst_loop(array $s, array $done = []): array {
    session_write_close(); // uzun API çağrısı sırasında paneldeki diğer istekler beklemesin
    $texts = [];
    for ($step = 0; $step < ASST_MAX_STEPS; $step++) {
        try {
            $r = asst_call($s['messages']);
        } catch (ApiError $e) {
            throw $e;
        } catch (Throwable $e) {
            $why = asst_error_reason($e);
            error_log('asistan API hatası: ' . $why);
            asst_save_state($s);
            $admin = (current_user()['role'] ?? '') === 'yonetici';
            throw new ApiError('Asistana şu an ulaşılamıyor. Biraz sonra tekrar deneyin.' . ($admin ? ' [Ayrıntı: ' . $why . ']' : ''), 502);
        }
        $s['messages'][] = ['role' => 'assistant', 'content' => $r['content']];
        foreach ($r['content'] as $bk) if (($bk['type'] ?? '') === 'text' && trim($bk['text']) !== '') $texts[] = trim($bk['text']);
        if ($r['stop'] === 'refusal') { $texts[] = 'Bu isteği işleyemedim.'; break; }
        if ($r['stop'] !== 'tool_use') break;

        $uses = array_values(array_filter($r['content'], fn($b) => ($b['type'] ?? '') === 'tool_use'));
        $results = []; $proposals = [];
        foreach ($uses as $u) {
            $in = is_array($u['input'] ?? null) ? $u['input'] : [];
            if (in_array($u['name'], ASST_INSTANT, true)) {
                $results[$u['id']] = asst_result_block($u['id'], fn() => asst_run_instant($u['name'], $in), $done);
            } else {
                try {
                    $proposals[] = ['id' => $u['id'], 'name' => $u['name'], 'input' => $in] + asst_describe($u['name'], $in);
                } catch (Throwable $e) {
                    $results[$u['id']] = ['type' => 'tool_result', 'toolUseID' => $u['id'], 'content' => 'Hata: ' . ($e instanceof ApiError ? $e->getMessage() : 'işlem hazırlanamadı.'), 'isError' => true];
                }
            }
        }
        if ($proposals) {
            $s['pending'] = ['order' => array_column($uses, 'id'), 'results' => $results, 'proposals' => $proposals];
            asst_save_state($s);
            return ['reply' => implode("\n\n", $texts), 'proposals' => array_map(fn($p) => array_diff_key($p, ['input' => 1]), $proposals), 'done' => $done];
        }
        $s['messages'][] = ['role' => 'user', 'content' => array_values(array_map(fn($id) => $results[$id], array_column($uses, 'id')))];
    }
    $s['pending'] = null;
    asst_save_state($s);
    return ['reply' => implode("\n\n", $texts) ?: 'Tamam.', 'proposals' => [], 'done' => $done];
}

function asst_message(string $text): array {
    @set_time_limit(300);
    $text = trim($text);
    if ($text === '') throw new ApiError('Bir şey söyleyin ya da yazın.');
    $s = asst_state();
    if ($s['pending']) $s = asst_resolve($s, false, 'Kullanıcı bu işlemi onaylamadı, onun yerine yeni bir şey söyledi.');
    $s['messages'][] = ['role' => 'user', 'content' => mb_substr($text, 0, 4000)];
    return asst_loop($s);
}

/** Bekleyen onay kartlarını onaylar ya da reddeder, tool_result'ları ekler. */
function asst_resolve(array $s, bool $approve, string $rejectMsg = 'Kullanıcı onaylamadı; işlem yapılmadı.', array &$done = []): array {
    $p = $s['pending'];
    $results = $p['results'];
    foreach ($p['proposals'] as $pr) {
        $results[$pr['id']] = $approve
            ? asst_result_block($pr['id'], fn() => asst_run_confirmed($pr['name'], $pr['input']), $done)
            : ['type' => 'tool_result', 'toolUseID' => $pr['id'], 'content' => $rejectMsg];
    }
    $s['messages'][] = ['role' => 'user', 'content' => array_values(array_map(fn($id) => $results[$id], $p['order']))];
    $s['pending'] = null;
    return $s;
}

function asst_confirm(bool $approve): array {
    @set_time_limit(300);
    $s = asst_state();
    if (!$s['pending']) throw new ApiError('Onay bekleyen işlem yok.');
    $done = [];
    $s = asst_resolve($s, $approve, 'Kullanıcı onaylamadı; işlem yapılmadı.', $done);
    return asst_loop($s, $done);
}

function asst_reset(): void {
    unset($_SESSION[asst_key()]);
}
