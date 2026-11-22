<?php
declare(strict_types=1);
session_start();

$configPath = __DIR__ . '/config.php';
$dataPath = __DIR__ . '/build.json';
$configReady = is_file($configPath);
if ($configReady) {
    require $configPath;
}
$adminPassword = defined('ADMIN_PASSWORD') ? (string) ADMIN_PASSWORD : '';
$message = '';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function loadBuild(string $path): array {
    if (!is_file($path)) return [];
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'login' && $adminPassword !== '' && hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: ?admin=1');
        exit;
    }
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: /');
        exit;
    }
    if ($action === 'save' && !empty($_SESSION['admin'])) {
        $url = trim((string)($_POST['url'] ?? ''));
        $name = trim((string)($_POST['name'] ?? 'CounterSource APK'));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
            $saved = file_put_contents($dataPath, json_encode(['url'=>$url, 'name'=>$name], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            $message = $saved === false ? 'Не удалось сохранить. Проверьте права на запись для папки сайта.' : 'Ссылка на сборку сохранена.';
        } else {
            $message = 'Укажите корректную HTTPS-ссылку на файл.';
        }
    }
}
$build = loadBuild($dataPath);
$isAdmin = !empty($_SESSION['admin']);
$adminMode = isset($_GET['admin']);
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CounterSource — APK Hub</title>
<style>
:root{color-scheme:dark;--bg:#090d13;--panel:#111923;--line:#293747;--muted:#91a0af;--accent:#e5a33a;--white:#f2f5f8}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 50% -20%,#26384b 0,transparent 55%),var(--bg);color:var(--white);font:15px/1.5 Arial,sans-serif;min-height:100vh}
header{height:68px;border-bottom:1px solid #263442;display:flex;align-items:center;justify-content:space-between;padding:0 max(5vw,20px);background:#080d13cc}
.logo{font-weight:900;letter-spacing:2px}.logo span{color:var(--accent)}.tag{font-size:11px;color:var(--muted);letter-spacing:2px}
main{width:min(850px,92vw);margin:64px auto}.eyebrow{color:var(--accent);font-size:11px;letter-spacing:3px;font-weight:bold}h1{font-size:clamp(30px,6vw,54px);line-height:1.05;margin:12px 0}
p,.muted{color:var(--muted)}.panel{margin-top:28px;background:linear-gradient(145deg,#141f2b,#0e151e);border:1px solid var(--line);border-radius:12px;padding:26px;box-shadow:0 22px 70px #0008}
input{width:100%;background:#080e15;border:1px solid #354657;border-radius:7px;color:white;padding:14px;margin:10px 0 14px;font-size:15px}
button,.btn{border:0;border-radius:7px;background:var(--accent);color:#15110a;font-weight:800;padding:12px 18px;cursor:pointer;text-decoration:none;display:inline-block}
.secondary{background:#243344;color:#eaf0f5;border:1px solid #405366}.notice{font-size:12px;color:#9ba9b6;margin-top:14px}.status{margin:14px 0;color:#e5a33a;overflow-wrap:anywhere}
.row{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}footer{text-align:center;color:#687887;font-size:12px;padding:30px}
</style></head>
<body>
<header><div class="logo">COUNTER<span>SOURCE</span></div><div class="tag">COMMUNITY BUILD HUB</div></header>
<main><div class="eyebrow">ANDROID BUILD PORTAL</div><h1>CounterSource<br>APK Hub</h1>
<p>Портал публикации сборок CounterSource.</p>
<?php if (!$configReady || $adminPassword === '' || $adminPassword === 'CHANGE_THIS_PASSWORD'): ?>
<section class="panel"><h2>Требуется настройка</h2><p>Скопируйте config.example.php в config.php и задайте собственный пароль администратора. Инструкция находится в README_INFINITYFREE_RU.txt.</p></section>
<?php elseif ($adminMode && !$isAdmin): ?>
<section class="panel"><h2>Вход администратора</h2><form method="post"><input type="hidden" name="action" value="login"><input type="password" name="password" placeholder="Пароль администратора" required autocomplete="current-password"><button type="submit">ВОЙТИ</button></form></section>
<?php elseif ($isAdmin): ?>
<section class="panel"><div class="row"><h2>Настройки публикации</h2><form method="post"><input type="hidden" name="action" value="logout"><button class="secondary">ВЫЙТИ</button></form></div>
<p>Вставьте HTTPS-ссылку на APK, размещённый во внешнем файловом хранилище. InfinityFree не хранит APK размером больше 10 МБ.</p>
<form method="post"><input type="hidden" name="action" value="save"><label>Название сборки</label><input name="name" value="<?=h((string)($build['name'] ?? 'CounterSource APK'))?>" maxlength="80"><label>Прямая HTTPS-ссылка на скачивание</label><input name="url" type="url" value="<?=h((string)($build['url'] ?? ''))?>" placeholder="https://..." required><button type="submit">СОХРАНИТЬ ССЫЛКУ</button></form>
<?php if ($message !== ''): ?><div class="status"><?=h($message)?></div><?php endif; ?></section>
<?php else: ?>
<section class="panel"><div class="row"><div><h2><?=h((string)($build['name'] ?? 'Последняя сборка'))?></h2><div class="muted"><?=empty($build['url']) ? 'Пока нет опубликованной сборки.' : 'Ссылка на последнюю опубликованную сборку.'?></div></div>
<?php if (!empty($build['url'])): ?><a class="btn" href="<?=h((string)$build['url'])?>" target="_blank" rel="noopener noreferrer">СКАЧАТЬ APK</a><?php endif; ?></div></section>
<p style="text-align:right"><a class="muted" href="?admin=1">Вход администратора</a></p>
<?php endif; ?>
</main><footer>COUNTERSOURCE · ANDROID BUILDS</footer></body></html>