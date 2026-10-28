<?php
/**
 * Lovački savez OBŽ — javni dio (ruter).
 *   /                               naslovna
 *   /{kategorija}[/{podkategorija}]  popis (novosti, članice, divljač, dokumenti…), ?str=2, ?god=2024
 *   /{kategorija}/…/{slug}          zapis u kategoriji
 *   /{slug}                         stranica bez kategorije (O nama, Kontakt, Lovidba…)
 *   /pretraga?q=                    pretraga
 *   /sitemap.xml, /novosti.xml      karta weba i RSS
 *   /index.php/...                  stare Joomla/K2 adrese → 301 na nove
 */
require __DIR__ . '/inc/app.php';

$put = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$put = '/' . trim($put, '/');

function preusmjeri($kamo)
{
    header('Location: ' . $kamo, true, 301);
    exit;
}

// ---------- stare Joomla/K2 adrese → 301 na nove
$k2id = null;
if (preg_match('#(?:^|/)item/(\d+)#', $put, $x)) {
    $k2id = (int) $x[1];                                     // /index.php/novosti/item/1097-…, /component/k2/item/85
} elseif (($_GET['option'] ?? '') === 'com_k2' && ($_GET['view'] ?? '') === 'item' && isset($_GET['id'])) {
    $k2id = (int) $_GET['id'];                               // /index.php?option=com_k2&view=item&id=85
}
if ($k2id !== null) {
    $st = db()->prepare('SELECT kategorija_id, slug FROM sadrzaj WHERE stari_id = ?');
    $st->execute(array($k2id));
    if ($s = $st->fetch()) preusmjeri(url_za($s));
    // duplikati iz stare baze (isti tekst pod dva ID-ja) vode na zadržani zapis
    $st = db()->prepare('SELECT novi FROM preusmjerenja WHERE stari = ?');
    $st->execute(array('item/' . $k2id));
    preusmjeri($st->fetchColumn() ?: '/novosti');
}
if (preg_match('#(?:^|/)itemlist/category/(\d+)#', $put, $x) || (($_GET['view'] ?? '') === 'itemlist' && isset($_GET['id']) && preg_match('/^(\d+)/', $_GET['id'], $x))) {
    foreach (kategorije() as $k) {
        if ((int) $k['stari_id'] === (int) $x[1]) preusmjeri($k['url']);
    }
    preusmjeri('/');
}
if (preg_match('#^/index\.php(/.*)?$#', $put, $m)) {
    $stari = trim($m[1] ?? '', '/');
    $cilj = '/';
    if ($stari !== '') {
        $st = db()->prepare('SELECT novi FROM preusmjerenja WHERE stari = ?');
        $st->execute(array($stari));
        $cilj = $st->fetchColumn();
        if (!$cilj) {
            // zadnji dio stare adrese je često alias (npr. index.php/lov/nacin-lova)
            $alias = slugify(preg_replace('/^\d+-/', '', basename($stari)));
            $st = db()->prepare('SELECT kategorija_id, slug FROM sadrzaj WHERE slug = ? AND objavljeno = 1');
            $st->execute(array($alias));
            $s = $st->fetch();
            $cilj = $s ? url_za($s) : '/';
            foreach (kategorije() as $k) if ($k['slug'] === $alias) $cilj = $k['url'];
        }
    }
    preusmjeri($cilj);
}

if ($put === '/sitemap.xml' || $put === '/novosti.xml') {
    $GLOBALS['RSS'] = $put === '/novosti.xml';
    require __DIR__ . '/inc/sitemap.php';
    exit;
}

$stranica = array('naslov' => null, 'opis' => null, 'slika' => null, 'canonical' => SITE_URL . ($put === '/' ? '/' : $put), 'tip' => 'website', 'ld' => null);
$kat = null;
$zapis = null;

if ($put === '/') {
    $pogled = 'naslovna';
} elseif ($put === '/pretraga') {
    $pogled = 'pretraga';
} elseif (preg_match('#^(/[a-z0-9-]+)+$#', $put)) {
    foreach (kategorije() as $k) {
        if ($k['url'] === $put && $k['vidljiva']) { $kat = $k; break; }
    }
    if ($kat) {
        $pogled = 'kategorija';
    } else {
        $st = db()->prepare('SELECT * FROM sadrzaj WHERE slug = ? AND objavljeno = 1');
        $st->execute(array(basename($put)));
        $zapis = $st->fetch();
        if ($zapis) {
            $k = $zapis['kategorija_id'] ? kategorija($zapis['kategorija_id']) : null;
            if ($k && !$k['vidljiva']) {
                $zapis = null;
            } elseif (url_za($zapis) !== $put) {
                preusmjeri(url_za($zapis));          // npr. zapis premješten u drugu kategoriju
            }
        }
        $pogled = $zapis ? 'clanak' : '404';
    }
} else {
    $pogled = '404';
}

if ($pogled === 'clanak') {
    // broji preglede (bez admina i robota)
    if (!prijavljen() && !preg_match('/bot|crawl|spider|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        db()->prepare('UPDATE sadrzaj SET pregledi = pregledi + 1 WHERE id = ?')->execute(array($zapis['id']));
    }
}

if ($pogled === '404') {
    // adresa promijenjena u adminu → 301 na novu
    $st = db()->prepare('SELECT novi FROM preusmjerenja WHERE stari = ?');
    $st->execute(array(ltrim($put, '/')));
    if ($novi = $st->fetchColumn()) preusmjeri($novi);
    http_response_code(404);
}

$izbornik = izbornik_stablo();
require __DIR__ . '/inc/pogledi/' . $pogled . '.php';
