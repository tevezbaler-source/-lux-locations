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
        WHERE (? = '' OR status = ?) AND (? = '' OR id = ? OR name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' OR message LIKE ? ESCAPE '\\')
        ORDER BY created_at DESC LIMIT 300");
    $st->execute([$durum, $durum, $ara, $ara, $like, $like, $like, $like]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['created_at'] = (int)$r['created_at'];
        $r['revision'] = (int)$r['revision'];
        $r['at'] = (new DateTimeImmutable('@' . $r['created_at']))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i');
        $r['log'] = [];
    }
    unset($r);
    // Panelde yapılan işlemler (cevap e-postaları, açılan gruplar)
    if ($rows) {
        $ids = array_column($rows, 'id');
        $pos = array_flip($ids);
        $logs = q_all('SELECT l.id, l.request_id, l.kind, l.booking_id, l.to_addr, l.cc, l.subject, l.body, l.ok, l.error, l.user_name, l.at, b.code AS booking_code
            FROM talep_log l LEFT JOIN bookings b ON b.id = l.booking_id WHERE l.request_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY l.id', $ids, db_main());
        foreach ($logs as $l) $rows[$pos[$l['request_id']]]['log'][] = $l;
    }
    return ['counts' => $counts, 'rows' => $rows, 'ready' => true, 'mail_ready' => mail_settings_public()['ready'],
        'notify' => can('users') ? talep_notify_get() : null] + $base;
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

function talep_get(string $id): array {
    need('talep');
    $db = talep_db();
    if (!$db) throw new ApiError('Talepler veritabanı bulunamadı.');
    if (!preg_match('/^[a-f0-9]{24}$/D', $id)) throw new ApiError('Talep bilgileri geçersiz.');
    $st = $db->prepare('SELECT id, created_at, category, name, email, phone, travel_date, message, status, note, revision FROM requests WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) throw new ApiError('Talep bulunamadı.', 404);
    $r['category_name'] = TALEP_KATEGORI[$r['category']] ?? $r['category'];
    return $r;
}

/** Yeni taleplerde durum "Yeni" ise "İnceleniyor"a çeker (cevap verildi ya da grup açıldı). */
function talep_touch(string $id): void {
    $db = talep_db();
    if ($db) $db->prepare("UPDATE requests SET status = 'review', revision = revision + 1 WHERE id = ? AND status = 'new'")->execute([$id]);
}

function talep_log_add(string $id, string $kind, array $f): void {
    $u = current_user();
    q('INSERT INTO talep_log (request_id, kind, booking_id, to_addr, cc, subject, body, ok, error, user_id, user_name, at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
        $id, $kind, $f['booking_id'] ?? null, $f['to'] ?? null, $f['cc'] ?? null, $f['subject'] ?? null, $f['body'] ?? null,
        isset($f['ok']) ? (int)$f['ok'] : 1, $f['error'] ?? null, $u['id'] ?? null, $u['name'] ?? null, now(),
    ], db_main());
}

/** Talebe panelden e-postayla cevap: reservation@ (panelin e-posta ayarı) üzerinden gider, kaydı tutulur. */
function talep_reply(array $in): array {
    need('talep');
    if (!empty($_SESSION['egitim'])) throw new ApiError('Eğitim bölümündesiniz; talebe cevap gerçek panelden gönderilir.');
    $r = talep_get((string)($in['id'] ?? ''));
    if (!mail_settings_public()['ready']) throw new ApiError('E-posta ayarı yapılmamış. Kullanıcılar sayfasındaki "E-posta gönderimi" bölümünden gönderen adresi ve şifresini girin.');
    $to = mail_addr_list((string)($in['to'] ?? ''), 'Alıcı');
    if (!$to) throw new ApiError('Alıcı e-posta adresini yazın.');
    $cc = mail_addr_list((string)($in['cc'] ?? ''), 'Kopya (CC)');
    $subject = clean_str($in['subject'] ?? '', 200) ?: 'Lux Locations · ' . (TALEP_KATEGORI[$r['category']] ?? 'Talebiniz');
    $body = trim((string)($in['body'] ?? ''));
    if ($body === '') throw new ApiError('Mesajı yazın.');
    if (mb_strlen($body) > 20000) throw new ApiError('Mesaj çok uzun.');
    $s = mail_settings();
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1d2730">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))
        . '<br><br><span style="color:#3a5f78;font-weight:bold">' . htmlspecialchars($s['from_name'], ENT_QUOTES, 'UTF-8') . '</span><br>'
        . '<a href="https://luxlocationstours.com" style="color:#3a5f78">luxlocationstours.com</a></div>';
    $text = $body . "\n\n" . $s['from_name'] . "\nhttps://luxlocationstours.com";
    $log = ['to' => implode(', ', $to), 'cc' => implode(', ', $cc), 'subject' => $subject, 'body' => mb_substr($body, 0, 20000)];
    try {
        mail_send($to, $cc, $subject, $html, $text);
    } catch (ApiError $e) {
        talep_log_add($r['id'], 'mail', $log + ['ok' => 0, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        throw $e;
    }
    talep_log_add($r['id'], 'mail', $log);
    talep_touch($r['id']);
    audit('talep', null, 'eposta', 'Talebe e-posta: ' . $r['name'] . ' → ' . implode(', ', $to), null, null, db_main());
    return ['ok' => true];
}

/** Talepten açılan grubu talebe bağlar. */
function talep_link(array $in): array {
    need('booking');
    if (!empty($_SESSION['egitim'])) return ['ok' => true];
    $r = talep_get((string)($in['id'] ?? ''));
    $bid = (int)($in['booking_id'] ?? 0);
    if (!q_one('SELECT id FROM bookings WHERE id = ? AND deleted = 0', [$bid], db_main())) throw new ApiError('Grup bulunamadı.');
    talep_log_add($r['id'], 'grup', ['booking_id' => $bid]);
    talep_touch($r['id']);
    return ['ok' => true];
}

/* ---- Yeni talep bildirimi: alıcılar sitenin talep veritabanındaki notification_settings satırında ---- */

function talep_notify_get(): array {
    $db = talep_db();
    $out = ['ready' => false, 'recipients' => [], 'sender' => ''];
    if (!$db) return $out;
    try { $row = $db->query('SELECT recipient, sender, username, password FROM notification_settings WHERE id = 1')->fetch(); }
    catch (PDOException $e) { return $out; }
    if (!$row) return $out;
    return ['ready' => $row['password'] !== '' && $row['username'] !== '',
        'recipients' => preg_split('/[\s,;]+/', (string)$row['recipient'], -1, PREG_SPLIT_NO_EMPTY), 'sender' => (string)$row['sender']];
}

function talep_notify_save(array $in): array {
    need('users');
    if (!empty($_SESSION['egitim'])) throw new ApiError('Eğitim bölümündesiniz; bildirim ayarı gerçek panelden değiştirilir.');
    $db = talep_db();
    if (!$db) throw new ApiError('Talepler veritabanı bulunamadı.');
    $list = mail_addr_list((string)($in['recipients'] ?? ''), 'Alıcı');
    if (!$list) throw new ApiError('En az bir alıcı adresi yazın.');
    if (count($list) > 10) throw new ApiError('En fazla 10 alıcı yazılabilir.');
    $old = talep_notify_get();
    try { $n = $db->prepare('UPDATE notification_settings SET recipient = ? WHERE id = 1'); $n->execute([implode(', ', $list)]); }
    catch (PDOException $e) { throw new ApiError('Bildirim ayarı bulunamadı. Önce sitede e-posta bildirimi kurulmuş olmalı.'); }
    if (!$n->rowCount()) throw new ApiError('Bildirim ayarı bulunamadı. Önce sitede e-posta bildirimi kurulmuş olmalı.');
    audit('ayar', null, 'bildirim', 'Talep bildirim alıcıları: ' . implode(', ', $list), ['recipients' => $old['recipients']], ['recipients' => $list], db_main());
    return talep_notify_get();
}

/** Alıcılara panelin e-posta ayarıyla bir deneme bildirimi yollar. */
function talep_notify_test(): array {
    need('users');
    $n = talep_notify_get();
    if (!$n['recipients']) throw new ApiError('Önce alıcı adreslerini kaydedin.');
    $msg = "Bu bir deneme bildirimidir. Siteden yeni talep geldiğinde bu adreslere haber gelecek.\n\nTalepler: https://luxlocationstours.com/panel/#/talepler";
    mail_send($n['recipients'], [], 'Deneme · Yeni talep bildirimi', nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')), $msg);
    return ['ok' => true, 'to' => $n['recipients']];
}
