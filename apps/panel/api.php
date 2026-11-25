<?php
// JSON API. Tüm yazma işlemleri POST + CSRF başlığı ister.
define('LX_PANEL', 1);
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/calc.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/tcmb.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/belge.php';
require __DIR__ . '/lib/talepler.php';
require __DIR__ . '/lib/gunluk.php';
require __DIR__ . '/lib/rapor.php';
require __DIR__ . '/lib/cari.php';
require __DIR__ . '/lib/site_otel.php';
require __DIR__ . '/lib/oneri.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('Europe/Istanbul');

function out($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

try {
    lx_session_start();
    $a = $_GET['a'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $in = [];
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $in = $raw !== '' ? (json_decode($raw, true) ?? []) : $_POST;
        if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) out(['error' => 'Oturum doğrulanamadı; sayfayı yenileyin.'], 419);
    }

    // Girişsiz erişilebilenler
    if ($a === 'session') {
        $u = current_user();
        out([
            'csrf' => $_SESSION['csrf'], 'setup' => !users_exist(),
            'user' => $u ? ['id' => (int)$u['id'], 'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role'], 'role_name' => ROLES[$u['role']], 'perms' => PERMS[$u['role']]] : null,
            'egitim' => !empty($_SESSION['egitim']),
            'consts' => ['room_types' => ROOM_TYPES, 'statuses' => STATUSES, 'currencies' => CURRENCIES, 'roles' => ROLES, 'pp_mult' => PP_MULT, 'locked' => LOCKED_STATUSES],
        ]);
    }
    if ($a === 'setup' && $method === 'POST') {
        if (users_exist()) out(['error' => 'Kurulum zaten yapılmış.'], 400);
        $name = clean_str($in['name'] ?? ''); $user = mb_strtolower(clean_str($in['username'] ?? '', 60) ?? '');
        if (!$name || !preg_match('/^[a-z0-9._-]{3,60}$/', $user)) out(['error' => 'Ad ve kullanıcı adı (en az 3 harf, Türkçe karakter ve boşluk olmadan) gerekli.'], 400);
        if ($e = validate_password($in['password'] ?? '')) out(['error' => $e], 400);
        q('INSERT INTO users (username, name, pass_hash, role, active, created_at) VALUES (?,?,?,?,1,?)', [$user, $name, password_hash($in['password'], PASSWORD_DEFAULT), 'yonetici', now()], db_main());
        $r = do_login($user, $in['password']);
        out($r);
    }
    if ($a === 'login' && $method === 'POST') {
        $r = do_login((string)($in['username'] ?? ''), (string)($in['password'] ?? ''));
        out($r, $r['ok'] ? 200 : 401);
    }

    $u = current_user();
    if (!$u) out(['error' => 'Oturum kapandı, tekrar giriş yapın.', 'login' => true], 401);
    // Günlük yedek ve opsiyon hatırlatması: cevap gittikten sonra, günde bir kez
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        try { gunluk_calistir(); } catch (Throwable $e) { error_log('panel gunluk: ' . $e->getMessage()); }
    });

    if ($method === 'GET') {
        switch ($a) {
            case 'grid':
                $from = clean_date($_GET['from'] ?? '') ?: date('Y-m-d');
                $to = clean_date($_GET['to'] ?? '') ?: date_add_days($from, 30);
                if ($to <= $from) throw new ApiError('Bitiş tarihi başlangıçtan sonra olmalı.');
                if (nights_between($from, $to) > 370) throw new ApiError('En fazla 1 yıllık aralık seçilebilir.');
                $regions = array_column(q_all("SELECT DISTINCT region FROM hotels WHERE active = 1 AND region IS NOT NULL ORDER BY region"), 'region');
                if (empty($_SESSION['egitim'])) { try { tcmb_auto_record(db_main()); } catch (Throwable $e) {} }
                if (empty($_SESSION['egitim'])) { try { site_hotels_sync(); } catch (Throwable $e) { error_log('site otel esitleme: ' . $e->getMessage()); } }
                if (empty($_SESSION['egitim'])) { try { hotels_import_pending(); } catch (Throwable $e) { error_log('toplu otel: ' . $e->getMessage()); } }
                $hiddenN = 0;
                $gridH = hotels_grid($from, $to, trim($_GET['q'] ?? ''), trim($_GET['region'] ?? ''), !empty($_GET['hepsi']), $hiddenN);
                out(['hotels' => $gridH, 'hidden' => $hiddenN, 'from' => $from, 'to' => $to, 'regions' => $regions, 'rate' => latest_rate(), 'tcmb' => tcmb_rates(), 'options' => opsiyon_yaklasan()]);
            case 'hotel':
                $from = clean_date($_GET['from'] ?? '') ?: date('Y-m-d');
                $days = max(7, min(92, (int)($_GET['days'] ?? 31)));
                out(hotel_detail((int)($_GET['id'] ?? 0), $from, date_add_days($from, $days)) + ['from' => $from, 'days' => $days]);
            case 'hotel_names':
                out(['hotels' => hotel_name_suggestions()]);
            case 'hotels':
                out(['hotels' => q_all('SELECT id, name, stars, region, active FROM hotels ORDER BY active DESC, name')]);
            case 'booking':
                out(booking_full((int)($_GET['id'] ?? 0)));
            case 'agencies':
                $list = agencies_list();
                if (!can('cari')) foreach ($list as &$x) unset($x['balance']);
                unset($x);
                out(['agencies' => $list]);
            case 'agency':
                $id = (int)($_GET['id'] ?? 0);
                $ag = q_one('SELECT * FROM agencies WHERE id = ?', [$id]);
                if (!$ag) throw new ApiError('Acente bulunamadı.', 404);
                $rows = q_all('SELECT id FROM bookings WHERE agency_id = ? AND deleted = 0 ORDER BY check_in DESC', [$id]);
                $ag['bookings'] = array_map(function ($r) {
                    $b = booking_get((int)$r['id']);
                    $c = calc_booking($b);
                    return ['id' => (int)$b['id'], 'code' => $b['code'], 'hotel' => q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]), 'check_in' => $b['check_in'], 'check_out' => $b['check_out'],
                        'status' => $b['status'], 'rooms' => $c['rooms'], 'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'], 'profit_eur' => $c['profit_eur']];
                }, $rows);
                if (can('cari')) $ag['cari'] = cari_agency($id);
                out($ag);
            case 'agency_names':
                out(['agencies' => q_all('SELECT id, name, aliases, country FROM agencies WHERE active = 1 ORDER BY name')]);
            case 'gecmis':
                out(['values' => oneri_gecmis((string)($_GET['f'] ?? ''), (int)($_GET['hotel_id'] ?? 0))]);
            case 'hizmet_fiyat':
                out(['services' => oneri_hizmetler()]);
            case 'ara':
                out(['results' => hizli_ara((string)($_GET['q'] ?? ''))]);
            case 'talep_acente':
                need('talep');
                out(['agency' => talep_acente_eslestir((string)($_GET['name'] ?? ''), (string)($_GET['email'] ?? ''))]);
            case 'tcmb':
                $r = tcmb_rates(!empty($_GET['force']));
                if (empty($_SESSION['egitim'])) { try { tcmb_auto_record(db_main(), $r); } catch (Throwable $e) {} }
                out(['tcmb' => $r]);
            case 'fx':
                if (empty($_SESSION['egitim'])) { try { tcmb_auto_record(db_main()); } catch (Throwable $e) {} }
                out(['tcmb' => tcmb_rates(), 'rates' => q_all('SELECT f.*, (SELECT user_name FROM audit_log WHERE entity = \'kur\' AND entity_id = f.id ORDER BY id LIMIT 1) AS user_name FROM fx_rates f ORDER BY rate_date DESC, id DESC LIMIT 100')]);
            case 'contract':
                $c = contract_for((int)($_GET['hotel_id'] ?? 0), clean_date($_GET['date'] ?? '') ?: date('Y-m-d'));
                out(['contract' => $c, 'rate' => latest_rate(), 'next_code' => next_code()]);
            case 'next_code':
                out(['code' => next_code()]);
            case 'changes':
                $since = (int)($_GET['since'] ?? 0);
                $last = (int)q_val('SELECT MAX(id) FROM audit_log');
                $items = $since > 0 ? q_all('SELECT id, at, user_id, user_name, entity, entity_id, action, summary FROM audit_log WHERE id > ? ORDER BY id LIMIT 50', [$since]) : [];
                out(['last' => $last, 'items' => $items, 'egitim' => !empty($_SESSION['egitim'])]);
            case 'audit':
                need('audit');
                $where = []; $p = [];
                if (!empty($_GET['entity'])) { $where[] = 'entity = ?'; $p[] = $_GET['entity']; }
                if (!empty($_GET['user'])) { $where[] = 'user_name = ?'; $p[] = $_GET['user']; }
                if (!empty($_GET['date'])) { $where[] = 'at LIKE ?'; $p[] = clean_date($_GET['date']) . '%'; }
                $sql = 'SELECT id, at, user_name, ip, entity, entity_id, action, summary, old_json, new_json FROM audit_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300';
                $rows = q_all($sql, $p);
                foreach ($rows as &$r) { $r['changes'] = audit_diff($r['old_json'], $r['new_json']); unset($r['old_json'], $r['new_json']); }
                out(['rows' => $rows, 'users' => array_column(q_all('SELECT DISTINCT user_name FROM audit_log WHERE user_name IS NOT NULL ORDER BY user_name'), 'user_name')]);
            case 'users':
                need('users');
                out(['users' => q_all('SELECT id, username, name, role, active, created_at, last_login FROM users ORDER BY active DESC, name', [], db_main())]);
            case 'notes':
                out(['notes' => notes_list(!empty($_GET['all']))]);
            case 'talepler':
                out(talep_list((string)($_GET['durum'] ?? ''), (string)($_GET['q'] ?? '')));
            case 'talep':
                out(talep_get((string)($_GET['id'] ?? '')));
            case 'mail_settings':
                need('users');
                out(mail_settings_public());
            case 'assistant_status':
                require_once __DIR__ . '/lib/assistant.php';
                out(asst_status());
            case 'rapor':
            case 'rapor_csv':
                $from = clean_date($_GET['from'] ?? '') ?: date('Y-01-01');
                $to = clean_date($_GET['to'] ?? '') ?: date('Y-01-01', strtotime('+1 year'));
                if ($to <= $from) throw new ApiError('Bitiş tarihi başlangıçtan sonra olmalı.');
                $kapsam = ($_GET['kapsam'] ?? '') === 'hepsi' ? 'hepsi' : 'satis';
                if ($a === 'rapor_csv') { need('report'); rapor_csv($from, $to, $kapsam); }
                out(rapor($from, $to, $kapsam));
            case 'yedekler':
                need('backup');
                $files = [];
                foreach (glob(gunluk_dir() . '/*.gz') ?: [] as $f) $files[] = ['name' => basename($f), 'size' => filesize($f), 'at' => date('Y-m-d H:i', filemtime($f))];
                usort($files, fn($a, $b) => strcmp($b['name'], $a['name']));
                out(['files' => $files, 'last' => is_file(gunluk_dir() . '/son-calisma.txt') ? trim(file_get_contents(gunluk_dir() . '/son-calisma.txt')) : null]);
            case 'yedek_indir':
                need('backup');
                $n = (string)($_GET['name'] ?? '');
                if (!preg_match('/^(panel|talepler)-\d{4}-\d{2}-\d{2}\.(sqlite|json)\.gz$/D', $n) || !is_file(gunluk_dir() . '/' . $n)) throw new ApiError('Yedek bulunamadı.', 404);
                audit('yedek', null, 'indir', 'Gece yedeği indirildi: ' . $n, null, null, db_main());
                header('Content-Type: application/gzip');
                header('Content-Disposition: attachment; filename="' . $n . '"');
                header('Content-Length: ' . filesize(gunluk_dir() . '/' . $n));
                readfile(gunluk_dir() . '/' . $n);
                exit;
            case 'backup':
                need('backup');
                if (!empty($_SESSION['egitim'])) throw new ApiError('Eğitim bölümündesiniz; yedek gerçek panelden alınır.');
                require __DIR__ . '/lib/backup.php';
                $json = backup_json(db_main());
                audit('yedek', null, 'indir', 'Yedek indirildi', null, null, db_main());
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="otel-paneli-yedek-' . date('Y-m-d-His') . '.json"');
                echo $json; exit;
        }
        throw new ApiError('Bilinmeyen istek.', 404);
    }

    // POST
    switch ($a) {
        case 'logout':
            audit('user', (int)$u['id'], 'cikis', 'Panelden çıktı', null, null, db_main());
            $_SESSION = []; session_regenerate_id(true);
            out(['ok' => true]);
        case 'mode':
            $_SESSION['egitim'] = !empty($in['egitim']);
            out(['egitim' => $_SESSION['egitim']]);
        case 'training_reset':
            if (empty($_SESSION['egitim'])) throw new ApiError('Yalnız eğitim bölümünde sıfırlanabilir.');
            training_reset();
            db_training();
            out(['ok' => true]);
        case 'calc':
            $b = booking_input($in);
            out(calc_booking($b) + ['next_code' => next_code()]);
        case 'booking_save':
            out(booking_save($in));
        case 'booking_cancel':
            out(booking_cancel((int)$in['id'], (string)($in['reason'] ?? ''), clean_int($in['version'] ?? null)));
        case 'booking_transfer':
            out(booking_transfer((int)$in['id'], (int)$in['agency_id'], clean_date($in['check_in'] ?? ''), clean_date($in['check_out'] ?? ''), clean_int($in['version'] ?? null)));
        case 'booking_move':
            out(booking_move((int)$in['id'], (int)$in['hotel_id'], clean_int($in['version'] ?? null)));
        case 'booking_delete':
            booking_delete((int)$in['id']);
            out(['ok' => true]);
        case 'hotels_import':
            out(hotels_import((string)($in['text'] ?? ''), !empty($in['commit']), (array)($in['add_similar'] ?? [])));
        case 'hotel_save':
            out(hotel_save($in));
        case 'rate_save':
            out(rate_save($in));
        case 'rate_delete':
            rate_delete((int)$in['id']); out(['ok' => true]);
        case 'allotment_save':
            out(allotment_save($in));
        case 'allotment_delete':
            allotment_delete((int)$in['id']); out(['ok' => true]);
        case 'agency_save':
            out(agency_save($in));
        case 'guests_save':
            out(guests_save($in));
        case 'talep_send':
            out(talep_send($in));
        case 'payment_save':
            out(payment_save($in));
        case 'payment_delete':
            out(payment_delete((int)($in['id'] ?? 0)));
        case 'talep_update':
            out(talep_update($in));
        case 'talep_reply':
            out(talep_reply($in));
        case 'talep_link':
            out(talep_link($in));
        case 'talep_notify_save':
            out(talep_notify_save($in));
        case 'talep_notify_test':
            out(talep_notify_test());
        case 'mail_settings_save':
            out(mail_settings_save($in));
        case 'mail_test':
            out(mail_test());
        case 'fx_save':
            out(fx_save($in));
        case 'note_save':
            out(note_save($in));
        case 'note_done':
            out(note_done((int)($in['id'] ?? 0), !isset($in['done']) || !empty($in['done'])));
        case 'assistant':
            require_once __DIR__ . '/lib/assistant.php';
            out(asst_message((string)($in['text'] ?? '')));
        case 'assistant_confirm':
            require_once __DIR__ . '/lib/assistant.php';
            out(asst_confirm(!empty($in['approve'])));
        case 'assistant_key':
            require_once __DIR__ . '/lib/assistant.php';
            out(asst_set_key((string)($in['key'] ?? '')));
        case 'assistant_reset':
            require_once __DIR__ . '/lib/assistant.php';
            asst_reset();
            out(['ok' => true]);
        case 'password':
            if (!password_verify((string)($in['old'] ?? ''), (string)q_val('SELECT pass_hash FROM users WHERE id = ?', [$u['id']], db_main()))) throw new ApiError('Mevcut şifre hatalı.');
            if ($e = validate_password((string)($in['new'] ?? ''))) throw new ApiError($e);
            q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($in['new'], PASSWORD_DEFAULT), $u['id']], db_main());
            audit('user', (int)$u['id'], 'sifre', 'Kendi şifresini değiştirdi', null, null, db_main());
            out(['ok' => true]);
        case 'user_save':
            need('users');
            $id = clean_int($in['id'] ?? null);
            $name = clean_str($in['name'] ?? '', 120);
            $role = $in['role'] ?? '';
            if (!$name || !isset(ROLES[$role])) throw new ApiError('Ad ve rol gerekli.');
            $active = isset($in['active']) ? (int)!!$in['active'] : 1;
            if ($id) {
                $old = q_one('SELECT id, username, name, role, active FROM users WHERE id = ?', [$id], db_main());
                if (!$old) throw new ApiError('Kullanıcı bulunamadı.', 404);
                if ($id === (int)$u['id'] && ($role !== 'yonetici' || !$active)) throw new ApiError('Kendi yönetici yetkinizi kaldıramazsınız.');
                q('UPDATE users SET name = ?, role = ?, active = ? WHERE id = ?', [$name, $role, $active, $id], db_main());
                if (!empty($in['password'])) {
                    if ($e = validate_password($in['password'])) throw new ApiError($e);
                    q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($in['password'], PASSWORD_DEFAULT), $id], db_main());
                }
                audit('user', $id, 'guncelle', "Kullanıcı güncellendi: $name (" . ROLES[$role] . ')' . (!empty($in['password']) ? ', şifre yenilendi' : '') . (!$active ? ', pasif' : ''), $old, ['name' => $name, 'role' => $role, 'active' => $active], db_main());
            } else {
                $user = mb_strtolower(clean_str($in['username'] ?? '', 60) ?? '');
                if (!preg_match('/^[a-z0-9._-]{3,60}$/', $user)) throw new ApiError('Kullanıcı adı en az 3 karakter; yalnız küçük harf, rakam, nokta, tire.');
                if (q_one('SELECT id FROM users WHERE username = ?', [$user], db_main())) throw new ApiError('Bu kullanıcı adı alınmış.');
                if ($e = validate_password((string)($in['password'] ?? ''))) throw new ApiError($e);
                q('INSERT INTO users (username, name, pass_hash, role, active, created_at) VALUES (?,?,?,?,?,?)', [$user, $name, password_hash($in['password'], PASSWORD_DEFAULT), $role, $active, now()], db_main());
                $id = (int)db_main()->lastInsertId();
                audit('user', $id, 'ekle', "Kullanıcı eklendi: $name (" . ROLES[$role] . ')', null, null, db_main());
            }
            out(['ok' => true]);
    }
    throw new ApiError('Bilinmeyen istek.', 404);
} catch (ApiError $e) {
    out(['error' => $e->getMessage()] + $e->extra, $e->status);
} catch (Throwable $e) {
    error_log('[otel-paneli] ' . $e);
    out(['error' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
