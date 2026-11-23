<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

try {
    $db = database();
    $password = $db->query('SELECT password FROM owner WHERE id=1')->fetchColumn();
    $target = is_string($_GET['q'] ?? null) && preg_match('/^[a-f0-9]{24}$/D', $_GET['q']) ? '?q=' . $_GET['q'] : '';
    $signedIn = time() - (int)($_SESSION['admin_seen'] ?? 0) < 3600;
    $error = '';
    if (post()) {
        $action = field('action', 20);
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect('/admin/');
        }
        if (!$signedIn && in_array($action, ['setup','login'], true)) {
            if (limited($db, 'login', 6)) {
                $error = 'Çok fazla deneme yapıldı. Birkaç dakika sonra tekrar deneyin.';
            } elseif ($action === 'setup' && !$password && hash_equals(LUX_SETUP_HASH, hash('sha256', field('setup',128)))) {
                $chosen = $_POST['password'] ?? '';
                if (is_string($chosen) && strlen($chosen) >= 14 && strlen($chosen) <= 72 && $chosen === ($_POST['confirm'] ?? null)) {
                    $insert = $db->prepare('INSERT OR IGNORE INTO owner (id,password) VALUES (1,?)');
                    $insert->execute([password_hash($chosen, PASSWORD_DEFAULT)]);
                    if ($insert->rowCount()) {
                        session_regenerate_id(true);
                        $_SESSION['admin_seen'] = time();
                        redirect('/admin/' . $target);
                    }
                }
                $error = 'Birbiriyle eşleşen, en az 14 karakterlik bir parola seçin.';
            } elseif ($action === 'login' && $password && is_string($_POST['password'] ?? null) && password_verify($_POST['password'], $password)) {
                session_regenerate_id(true);
                $_SESSION['admin_seen'] = time();
                redirect('/admin/' . $target);
            } else {
                $error = 'Giriş bilgileri doğrulanamadı.';
            }
        } elseif ($signedIn && $action === 'update') {
            $status = field('status', 30);
            $id = field('id', 24);
            $revision = field('revision', 12);
            if (!isset(STATUSES[$status]) || !preg_match('/^[a-f0-9]{24}$/D', $id) || !ctype_digit($revision) || !is_string($_POST['note'] ?? null) || strlen($_POST['note']) > 4000) {
                $error = 'Güncelleme bilgileri geçersiz.';
            } else {
                $update = $db->prepare('UPDATE requests SET status=?, note=?, revision=revision+1 WHERE id=? AND revision=?');
                $update->execute([$status,field('note'),$id,(int)$revision]);
                if ($update->rowCount()) {
                    $_SESSION['flash'] = 'Talep güncellendi.';
                    redirect('/admin/#request-' . $id);
                }
                $error = 'Talep başka bir oturumda güncellendi. Sayfayı yenileyip tekrar deneyin.';
            }
        }
    }
    if (!$signedIn) {
        startPage($password ? 'Yönetici girişi' : 'Yönetici hesabını kurun', 'YÖNETİM PANELİ');
        if ($error) echo '<p role="alert">' . escape($error) . '</p>';
        echo '<form method="post" class="stack">';
        csrf();
        echo '<input type="hidden" name="action" value="' . ($password ? 'login' : 'setup') . '">';
        if (!$password) echo '<label>Tek kullanımlık kurulum kodu<input name="setup" autocomplete="one-time-code" required></label>';
        echo '<label>Yönetici parolası<input name="password" type="password" autocomplete="' . ($password ? 'current-password' : 'new-password') . '" required></label>';
        if (!$password) echo '<label>Parolayı tekrarlayın<input name="confirm" type="password" autocomplete="new-password" required></label>';
        echo '<button type="submit">' . ($password ? 'Giriş yap' : 'Hesabı kur') . '</button></form></main></body></html>';
        exit;
    }
    $_SESSION['admin_seen'] = time();
    $status = is_string($_GET['status'] ?? null) && isset(STATUSES[$_GET['status']]) ? $_GET['status'] : '';
    $category = is_string($_GET['category'] ?? null) && isset(CATEGORIES[$_GET['category']]) ? $_GET['category'] : '';
    $search = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']),0,120) : '';
    $page = min(10000,max(0,filter_input(INPUT_GET,'page',FILTER_VALIDATE_INT) ?: 0));
    $query = $db->prepare("SELECT * FROM requests WHERE (?='' OR status=?) AND (?='' OR category=?) AND (?='' OR instr(lower(name),lower(?)) OR instr(lower(email),lower(?)) OR instr(id,lower(?))) ORDER BY created_at DESC,id LIMIT 26 OFFSET " . ($page*25));
    $query->execute([$status,$status,$category,$category,$search,$search,$search,$search]);
    $items = $query->fetchAll();
    $flash = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
} catch (Throwable $error) {
    error_log('Lux admin operation failed: ' . get_class($error));
    http_response_code(503);
    exit('Yönetim paneline şu anda ulaşılamıyor.');
}
startPage('Gelen talepler', 'YÖNETİM PANELİ');
?>
<p><a href="/admin/email-settings.php">E-posta bildirimleri</a> · <a href="/admin/site/">Siteyi düzenle →</a></p>
<form method="post"><?php csrf(); ?><input type="hidden" name="action" value="logout"><button>Oturumu kapat</button></form>
<?php if ($error || $flash): ?><p role="status"><?= escape($error ?: $flash) ?></p><?php endif; ?>
<form method="get" class="filters">
<label>Ad, e-posta veya talep numarası<input type="search" name="q" maxlength="120" value="<?= escape($search) ?>"></label>
<label>Hizmet<select name="category"><?php options([''=>'Tüm hizmetler']+CATEGORIES,$category); ?></select></label>
<label>Durum<select name="status"><?php options([''=>'Tüm durumlar']+STATUSES,$status); ?></select></label>
<button>Filtrele</button>
</form>
<?php if (!$items): ?><p>Bu filtrelerle eşleşen talep yok.</p><?php endif; ?>
<?php foreach (array_slice($items,0,25) as $item): ?>
<details id="request-<?= escape($item['id']) ?>">
<summary><strong><?= escape($item['name']) ?></strong> <?= escape(CATEGORIES[$item['category']] . ' · ' . STATUSES[$item['status']] . ' · ' . (new DateTimeImmutable('@' . $item['created_at']))->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('d.m.Y H:i')) ?></summary>
<dl>
<?php foreach (['Talep numarası'=>'id','E-posta'=>'email','Telefon'=>'phone','Hizmet tarihi'=>'travel_date','Talep ayrıntısı'=>'message'] as $label=>$key): ?>
<dt><?= escape($label) ?></dt><dd><?= escape($item[$key] ?: '—') ?></dd>
<?php endforeach; ?>
</dl>
<form method="post">
<?php csrf(); ?><input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= escape($item['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$item['revision'] ?>">
<label>Durum<select name="status"><?php options(STATUSES,$item['status']); ?></select></label>
<label>İç not<textarea name="note" rows="3" maxlength="1000"><?= escape($item['note']) ?></textarea></label>
<button>Değişiklikleri kaydet</button>
</form></details>
<?php endforeach; ?>
<nav aria-label="Sayfalama">
<?php foreach ([-1=>'Önceki',1=>'Sonraki'] as $step=>$label): ?>
<?php if ($step<0 ? $page>0 : count($items)>25): ?><a href="?<?= escape(http_build_query(['status'=>$status,'category'=>$category,'q'=>$search,'page'=>$page+$step])) ?>"><?= $label ?></a><?php endif; ?>
<?php endforeach; ?>
</nav></main></body></html>


