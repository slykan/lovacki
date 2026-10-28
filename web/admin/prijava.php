<?php
require __DIR__ . '/../inc/app.php';
sesija();
if (prijavljen()) {
    header('Location: /admin/');
    exit;
}

$greska = '';
$db = db();
$db->prepare('DELETE FROM prijave WHERE vrijeme < ?')->execute(array(time() - 900));
$st = $db->prepare('SELECT COUNT(*) FROM prijave WHERE ip = ?');
$st->execute(array(ip()));
$pokusaji = (int) $st->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    if ($pokusaji >= 5) {
        $greska = 'Previše neuspjelih pokušaja. Pokušajte ponovno za 15 minuta.';
    } else {
        $k = trim((string) ($_POST['korisnik'] ?? ''));
        $l = (string) ($_POST['lozinka'] ?? '');
        $st = $db->prepare('SELECT * FROM korisnici WHERE korisnik = ?');
        $st->execute(array($k));
        $u = $st->fetch();
        if ($u && password_verify($l, $u['hash'])) {
            session_regenerate_id(true);
            $_SESSION['korisnik'] = $u['korisnik'];
            $_SESSION['istek'] = time() + 4 * 3600;
            $db->prepare('DELETE FROM prijave WHERE ip = ?')->execute(array(ip()));
            if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
                $db->prepare('UPDATE korisnici SET hash = ? WHERE id = ?')->execute(array(password_hash($l, PASSWORD_DEFAULT), $u['id']));
            }
            header('Location: /admin/');
            exit;
        }
        $db->prepare('INSERT INTO prijave (ip, vrijeme) VALUES (?, ?)')->execute(array(ip(), time()));
        usleep(600000);
        $greska = 'Pogrešno korisničko ime ili lozinka.';
    }
}
?><!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Prijava — Admin Lovački savez OBŽ</title>
<link rel="icon" href="/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Alegreya+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(ROOT . "/assets/css/admin.css") ?>">
</head>
<body class="a-prijava">
<form method="post" class="a-kutija">
  <img src="/assets/img/logo-160.png" alt="Lovački savez OBŽ" width="120" height="114">
  <h1>Prijava u admin</h1>
  <?php if ($greska): ?><p class="a-flash greska"><?= e($greska) ?></p><?php endif; ?>
  <?= csrf_polje() ?>
  <label>Korisničko ime<input name="korisnik" required autocomplete="username" autofocus></label>
  <label>Lozinka<input name="lozinka" type="password" required autocomplete="current-password"></label>
  <button class="a-btn" type="submit">Prijava</button>
</form>
</body>
</html>
