<?php
// Siteden ve uygulamadan gelen talepler (rezervasyon/teklif formları).
// Veriler sitenin kendi veritabanında: httpdocs'un bir üstündeki lux-private/requests.sqlite (form uç noktası /admin/talep.php oraya yazar).
// Panel bu dosyayı yalnız okur ve durum/iç not günceller; tabloyu oluşturmaz.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const TALEP_KATEGORI = ['oda' => 'Oda rezervasyonu', 'grup' => 'Grup rezervasyonu', 'kongre' => 'Toplantı ve kongre', 'tur' => 'Özel tur', 'bogaz' => 'Boğaz tekne turu',
    'transfer' => 'Transfer', 'organizasyon' => 'Organizasyon', 'kiralama' => 'Kiralama', 'rehberlik' => 'Rehberlik', 'diger' => 'Diğer'];
const TALEP_DURUM = ['new' => 'Yeni', 'review' => 'İnceleniyor', 'quoted' => 'Teklif iletildi', 'confirmed' => 'Onaylandı', 'closed' => 'Kapandı'];

function talep_db_path(): string {
    $c = lx_config()['requests_db'] ?? '';
    if ($c) return $c;
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
    return dirname($root) . '/lux-private/requests.sqlite';
}

function talep_db(): ?PDO {
    static $db = false;
    if ($db !== false) return $db;
    $f = talep_db_path();
    if (!is_file($f)) return $db = null;
    $db = new PDO('sqlite:' . $f, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    return $db;
}

function talep_list(string $durum = '', string $ara = ''): array {
    need('talep');
    $base = ['statuses' => TALEP_DURUM, 'categories' => TALEP_KATEGORI, 'counts' => [], 'rows' => [], 'ready' => false];
    $db = talep_db();
    if (!$db) return $base;
    $counts = [];
    foreach ($db->query('SELECT status, COUNT(*) c FROM requests GROUP BY status') as $c) $counts[$c['status']] = (int)$c['c'];
    if (!isset(TALEP_DURUM[$durum])) $durum = '';
    $ara = mb_substr(trim($ara), 0, 80);
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $ara) . '%';
    $st = $db->prepare("SELECT id, created_at, category, name, email, phone, travel_date, message, status, note, revision FROM requests
        WHERE (? = '' OR status = ?) AND (? = '' OR name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' OR message LIKE ? ESCAPE '\\')
        ORDER BY created_at DESC LIMIT 300");
    $st->execute([$durum, $durum, $ara, $like, $like, $like, $like]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['created_at'] = (int)$r['created_at'];
        $r['revision'] = (int)$r['revision'];
        $r['at'] = (new DateTimeImmutable('@' . $r['created_at']))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i');
    }
    return ['counts' => $counts, 'rows' => $rows, 'ready' => true] + $base;
}

function talep_update(array $in): array {
    need('talep');
    if (!empty($_SESSION['egitim'])) throw new ApiError('Eğitim bölümündesiniz; talepler gerçek panelden güncellenir.');
    $db = talep_db();
    if (!$db) throw new ApiError('Talepler veritabanı bulunamadı.');
    $id = (string)($in['id'] ?? '');
    $durum = (string)($in['status'] ?? '');
    if (!preg_match('/^[a-f0-9]{24}$/D', $id) || !isset(TALEP_DURUM[$durum])) throw new ApiError('Talep bilgileri geçersiz.');
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 2000);
    $old = $db->prepare('SELECT name, status, note, revision FROM requests WHERE id = ?');
    $old->execute([$id]);
    $o = $old->fetch();
    if (!$o) throw new ApiError('Talep bulunamadı.', 404);
    if (isset($in['revision']) && (int)$in['revision'] !== (int)$o['revision']) throw new ApiError('Bu talep siz açtıktan sonra başka biri tarafından değiştirildi. Sayfayı yenileyip tekrar deneyin.', 409);
    $db->prepare('UPDATE requests SET status = ?, note = ?, revision = revision + 1 WHERE id = ?')->execute([$durum, $note, $id]);
    audit('talep', null, 'guncelle', 'Talep: ' . $o['name'] . ' → ' . TALEP_DURUM[$durum],
        ['id' => $id, 'status' => $o['status'], 'note' => $o['note']], ['id' => $id, 'status' => $durum, 'note' => $note], db_main());
    return ['ok' => true, 'revision' => (int)$o['revision'] + 1];
}
