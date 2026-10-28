<?php
/** Zajednički vrh/dno admina. Pozvati admin_vrh('Naslov') i admin_dno(). */
function admin_vrh($naslov, $aktivno = '')
{
    $poruka = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?><!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($naslov) ?> — Admin Lovački savez OBŽ</title>
<link rel="icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alegreya+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(ROOT . "/assets/css/admin.css") ?>">
</head>
<body>
<header class="a-top">
  <a class="a-brand" href="/admin/"><img src="/assets/img/logo-160.png" alt="" width="40" height="38" class="a-logo"> Lovački savez · admin</a>
  <nav>
    <a href="/admin/"<?= $aktivno === 'sadrzaj' ? ' aria-current="page"' : '' ?>>Sadržaj</a>
    <a href="/admin/uredi.php?kat=<?= (int) admin_novosti_id() ?>"<?= $aktivno === 'nova' ? ' aria-current="page"' : '' ?>>+ Nova novost</a>
    <a href="/admin/kategorije.php"<?= $aktivno === 'kategorije' ? ' aria-current="page"' : '' ?>>Kategorije</a>
    <a href="/admin/izbornik.php"<?= $aktivno === 'izbornik' ? ' aria-current="page"' : '' ?>>Izbornik</a>
    <a href="/admin/popup.php"<?= $aktivno === 'popup' ? ' aria-current="page"' : '' ?>>Popup</a>
    <a href="/admin/lozinka.php"<?= $aktivno === 'lozinka' ? ' aria-current="page"' : '' ?>>Lozinka</a>
    <a href="/" target="_blank" rel="noopener">Web ↗</a>
    <form method="post" action="/admin/odjava.php"><?= csrf_polje() ?><button type="submit">Odjava</button></form>
  </nav>
</header>
<main class="a-main">
<?php if ($poruka): ?><p class="a-flash <?= e($poruka[0]) ?>"><?= e($poruka[1]) ?></p><?php endif; ?>
<?php
}

function admin_dno($js = false)
{
    ?></main>
<?php if ($js): ?><script src="/assets/js/admin.js?v=<?= filemtime(ROOT . "/assets/js/admin.js") ?>" defer></script><?php endif; ?>
</body>
</html>
<?php
}

function admin_novosti_id()
{
    foreach (kategorije() as $k) if ($k['slug'] === 'novosti') return $k['id'];
    return 0;
}

function flash($tip, $tekst)
{
    $_SESSION['flash'] = array($tip, $tekst);
}
