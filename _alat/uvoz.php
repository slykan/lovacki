<?php
/**
 * Uvoz stare Joomla/K2 stranice u novu bazu (web/data/lovacki.sqlite).
 * Izvor: lokalna MySQL baza lovacki_izvor (iz lovacki_cms.sql, bez tablice sesija) + datoteke:
 *   images/{N}/…              K2 galerije (N = ID članka) i slike iz starih tekstova (images/stories/…)
 *   media/k2/items/src/…      glavne slike K2 (md5("Image".ID).jpg)
 *   media/k2/galleries/{N}/   K2 galerije (ako su skinute pod tim imenom)
 *   media/k2/attachments/…    privitci (dokumenti)
 * Datoteke se traže u lovacki/ i lovacki/izvor/. Skripta se smije pokretati ponovno (npr. kad stignu
 * nove slike): baza i web/uploads/ se svaki put grade iznova.
 *
 *   php _alat/uvoz.php
 */
error_reporting(E_ALL);
ini_set('memory_limit', '1024M');
$KORIJEN = dirname(__DIR__);
$WEB = $KORIJEN . '/web';

// nova baza i čisti uploads (samo ono što je ova skripta napravila)
foreach (glob($WEB . '/data/lovacki.sqlite*') as $f) unlink($f);
$rmdir = function ($d) use (&$rmdir) {
    foreach (glob($d . '/{,.}[!.]*', GLOB_BRACE) ?: array() as $f) is_dir($f) ? $rmdir($f) : unlink($f);
    if (basename($d) !== 'uploads') rmdir($d);
};
foreach (glob($WEB . '/uploads/*', GLOB_ONLYDIR) ?: array() as $d) $rmdir($d); // sve mape (GGGG/, galerija/, privitci/); .htaccess ostaje

require $WEB . '/inc/app.php';
require __DIR__ . '/clanica.php';
$db = db();
// izvor: lokalno baza lovacki_izvor (root bez lozinke); na poslužitelju LSO_JOOMLA=~/old (stara Joomla:
// baza iz configuration.php, slike iz images/ i media/ te mape)
$JOOMLA = getenv('LSO_JOOMLA') ?: null;
if ($JOOMLA) {
    $jc = file_get_contents($JOOMLA . '/configuration.php');
    $j = function ($k) use ($jc) { return preg_match('/public \$' . $k . '\s*=\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $jc, $m) ? stripslashes($m[1]) : ''; };
    if ($j('dbprefix') !== 'a8ubr_') exit('Neočekivani prefiks tablica: ' . $j('dbprefix') . PHP_EOL);
    $izv = new PDO('mysql:host=' . ($j('host') === 'localhost' ? '127.0.0.1' : $j('host')) . ';dbname=' . $j('db') . ';charset=utf8mb4', $j('user'), $j('password'), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
} else {
    $izv = new PDO('mysql:host=127.0.0.1;dbname=lovacki_izvor;charset=utf8mb4', 'root', '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
}
$log = array('nema_glavne' => 0, 'galerije' => 0, 'slike_gal' => 0, 'nedostaje' => array());

function nadji($rel)
{
    global $KORIJEN, $JOOMLA;
    $rel = ltrim(rawurldecode($rel), '/');
    foreach (array_filter(array($JOOMLA ? rtrim($JOOMLA, '/') . '/' : null, $KORIJEN . '/', $KORIJEN . '/izvor/')) as $r) {
        if (is_file($r . $rel) || is_dir($r . $rel)) return $r . $rel;
    }
    return null;
}

$db->beginTransaction();

// ------------------------------------------------------------------ kategorije
$kat = array();   // ključ → novi id
$k2kat = array(); // stari K2 catid → novi id
$dodajKat = function ($kljuc, $naziv, $slug, $rod, $prikaz, $redanje, $red, $stari = null, $opis = '', $objavljeno = 1) use ($db, &$kat, &$k2kat) {
    $db->prepare('INSERT INTO kategorije (roditelj, naziv, slug, opis, prikaz, redanje, redoslijed, objavljeno, stari_id) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute(array($rod ? $kat[$rod] : null, $naziv, $slug, $opis, $prikaz, $redanje, $red, $objavljeno, $stari));
    $kat[$kljuc] = (int) $db->lastInsertId();
    if ($stari) $k2kat[$stari] = $kat[$kljuc];
};
$dodajKat('novosti', 'Novosti', 'novosti', null, 'novosti', 'datum', 10, 7, '<p>Događanja, obljetnice, lovovi i obavijesti lovačkih društava i Saveza.</p>');
$dodajKat('clanice', 'Članice Saveza', 'clanice-saveza', null, 'popis', 'naslov', 20, 8, '<p>Lovačka društva i pravne osobe učlanjene u Lovački savez Osječko-baranjske županije, raspoređene po lovnim uredima.</p>');
foreach (array(15 => array('lu-baranja', 'Lovni ured Baranja'), 16 => array('lu-donji-miholjac', 'Lovni ured Donji Miholjac'), 17 => array('lu-dakovo', 'Lovni ured Đakovo'), 18 => array('lu-nasice', 'Lovni ured Našice'), 19 => array('lu-osijek', 'Lovni ured Osijek'), 20 => array('lu-valpovo', 'Lovni ured Valpovo')) as $stari => $x) {
    $dodajKat($x[0], $x[1], $x[0], 'clanice', 'popis', 'naslov', $stari, $stari);
}
$dodajKat('divljac', 'Divljač', 'divljac', null, 'popis', 'naslov', 30, 6, '<p>Opisi vrsta divljači koje obitavaju u lovištima Osječko-baranjske županije.</p>');
$dodajKat('krupna', 'Krupna divljač', 'krupna-divljac', 'divljac', 'popis', 'naslov', 10, 21);
$dodajKat('sitna', 'Sitna divljač', 'sitna-divljac', 'divljac', 'popis', 'naslov', 20, 22);
$dodajKat('lov', 'Lov', 'lov', null, 'popis', 'redom', 40, 9, '<p>Načini lova, lovački običaji i što se u lovištu radi kroz godinu.</p>');
$dodajKat('lovište', 'Lovište kroz godinu', 'lovni-kalendar', 'lov', 'popis', 'redom', 30, 12, '<p>Što se u lovištu događa i što lovac treba raditi u pojedinom mjesecu.</p>');
$dodajKat('dokumenti', 'Dokumenti', 'dokumenti', null, 'dokumenti', 'naslov', 50, 23, '<p>Zakoni, pravilnici, naredbe i obrasci vezani uz lovstvo.</p>');
$dodajKat('arhiva', 'Arhiva', 'arhiva', null, 'popis', 'naslov', 90, null, '<p>Stari sadržaj s prethodne stranice (oglasi iz 2011., salon 2012.). Skriveno s weba.</p>', 0);
$k2kat[11] = $kat['arhiva'];
$k2kat[13] = $kat['arhiva'];
$k2kat[10] = 'lovni-uredi'; // tekstovi lovnih ureda → opis LU kategorija

// ------------------------------------------------------------------ pomoćno za tekst
$mjeseci = array('siječnju' => 1, 'veljači' => 2, 'ožujku' => 3, 'travnju' => 4, 'svibnju' => 5, 'lipnju' => 6, 'srpnju' => 7, 'kolovozu' => 8, 'rujnu' => 9, 'listopadu' => 10, 'studenom' => 11, 'prosincu' => 12);

/** Slike iz starih tekstova: images/… → /images/… (kopira se u web/images ako postoji). */
function slike_u_tekstu($html)
{
    global $WEB, $log;
    return preg_replace_callback('#(src|href)="(?:https?://(?:www\.)?lovacki-savez-osijek\.hr)?/?(images/[^"]+)"#i', function ($m) use ($WEB, &$log) {
        $rel = rawurldecode(html_entity_decode($m[2]));
        $izvor = nadji($rel);
        if ($izvor && is_file($izvor)) {
            $cilj = $WEB . '/' . $rel;
            if (!is_dir(dirname($cilj))) mkdir(dirname($cilj), 0755, true);
            if (!is_file($cilj)) copy($izvor, $cilj);
        } else {
            $log['nedostaje'][] = $rel;
        }
        return $m[1] . '="/' . str_replace('%2F', '/', rawurlencode($rel)) . '"';
    }, $html);
}

/** Opće čišćenje K2 teksta. */
function ocisti($html)
{
    $html = preg_replace('#\{gallery\}.*?\{/gallery\}#is', '', $html);
    $html = preg_replace('#<object\b.*?</object>#is', '', $html);
    $html = preg_replace('#<p>[^<]*Karta lovišta[^<]*</p>#iu', '', $html);
    // Word: „1958. godine…” pretvoren u numerirani popis <ol start="1958"><li>godine…
    $html = preg_replace_callback('#<ol start="(\d{3,4})">\s*<li>(.*?)</li>\s*</ol>#is', function ($m) { return '<p>' . $m[1] . '. ' . $m[2] . '</p>'; }, $html);
    $html = str_replace(array('<p>&nbsp;</p>', "\xC2\xA0"), array('', ' '), $html);
    $html = slike_u_tekstu($html);
    // relativne poveznice na staru stranicu → /index.php/… (ruter ih preusmjerava)
    $html = preg_replace('#href="(?!https?:|mailto:|tel:|/|\#)([^"]+)"#i', 'href="/index.php/$1"', $html);
    $html = preg_replace('#href="https?://(?:www\.)?lovacki-savez-osijek\.hr/?#i', 'href="/', $html);
    return sanitiziraj_html($html);
}


/** Datoteke slika u mapi galerije, prirodnim redom. */
function slike_mape($dir)
{
    $f = array_values(array_filter(glob($dir . '/*') ?: array(), function ($x) { return is_file($x) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $x); }));
    natcasesort($f);
    return array_values($f);
}

// ------------------------------------------------------------------ zapisi
$redovi = $izv->query('SELECT i.*, c.trash AS ctrash FROM a8ubr_k2_items i JOIN a8ubr_k2_categories c ON c.id = i.catid WHERE i.trash = 0 ORDER BY i.id')->fetchAll();
$grupe = array();
foreach ($redovi as $r) {
    if ($r['ctrash'] || !isset($k2kat[$r['catid']])) continue;
    $kljuc = $r['catid'] . '|' . mb_strtolower(trim(preg_replace('/\s+/u', ' ', $r['title']))) . '|' . md5($r['introtext'] . $r['fulltext']);
    $grupe[$kljuc][] = $r;
}
$privitciSvi = $izv->query('SELECT * FROM a8ubr_k2_attachments ORDER BY id')->fetchAll();
$lu = array('Baranja' => 'lu-baranja', 'Donji Miholjac' => 'lu-donji-miholjac', 'Đakovo' => 'lu-dakovo', 'Našice' => 'lu-nasice', 'Osijek' => 'lu-osijek', 'Valpovo' => 'lu-valpovo');
$uvezeno = 0; $preusm = array(); $odgode = array(); $luNaslovi = array();
foreach ($redovi as $r) if ($r['catid'] >= 15 && $r['catid'] <= 20) $luNaslovi[mb_strtolower(trim(preg_replace('/\s+/u', ' ', $r['title'])))] = true;

foreach ($grupe as $g) {
    // zadrži objavljeni, pa noviji ID (kopije iz 2017. su ponegdje dopunjene)
    usort($g, function ($a, $b) { return array($b['published'], $b['id']) <=> array($a['published'], $a['id']); });
    $r = $g[0];
    $ids = array_column($g, 'id');
    $tekst = $r['introtext'] . $r['fulltext'];

    // lovni uredi → opis kategorije LU
    if ($k2kat[$r['catid']] === 'lovni-uredi') {
        foreach ($lu as $ime => $slug) {
            if (preg_match('/' . preg_quote($ime, '/') . '$/u', trim($r['title']))) {
                // ostaje samo odlomak s voditeljem ureda; popis društava (klijent ga ne želi, društva su kartice ispod) se briše
                $opis = ocisti($tekst);
                if (preg_match('#<p>(?:(?!</p>).)*Voditelj(?:(?!</p>).)*</p>#su', $opis, $vod)) $opis = $vod[0];
                $db->prepare('UPDATE kategorije SET opis = ? WHERE id = ?')->execute(array(sanitiziraj_html($opis), $kat[$slug]));
                foreach ($ids as $i) $preusm['item/' . $i] = '/clanice-saveza/' . $slug;
            }
        }
        continue;
    }
    $katId = $k2kat[$r['catid']];
    $naslov = trim(preg_replace('/\s+/u', ' ', $r['title']));
    // „Članice” (stara nadkategorija): pregled → opis kategorije; starije kopije članica iz LU → preskoči;
    // ostale članice → u svoj LU prema poveznici „Lovni ured X” na početku teksta
    if ($r['catid'] == 8) {
        if ($naslov === 'Članice Saveza') {
            $db->prepare('UPDATE kategorije SET opis = ? WHERE id = ?')->execute(array(ocisti($tekst), $kat['clanice']));
            foreach ($ids as $i) $preusm['item/' . $i] = '/clanice-saveza';
            continue;
        }
        if (isset($luNaslovi[mb_strtolower($naslov)])) {
            $odgode[] = array($ids, mb_strtolower($naslov));
            continue;
        }
        if (preg_match('/Lovni ured (Baranja|Donji Miholjac|Đakovo|Našice|Osijek|Valpovo)/u', strip_tags($tekst), $mu)) $katId = $kat[$lu[$mu[1]]];
    }
    $redoslijed = 0;
    if ($katId === $kat['lovište']) {
        $naslov = rtrim($naslov, '. ');
        foreach ($mjeseci as $m => $n) if (mb_stripos($naslov, $m) !== false) $redoslijed = $n;
    }
    if ($katId === $kat['arhiva'] && $r['catid'] == 13) $naslov = 'Salon 2012 – ' . $naslov;
    if ($r['catid'] == 9) $redoslijed = mb_stripos($naslov, 'način') !== false ? 10 : 20;

    $html = null; $GLOBALS['clanica_sazetak'] = '';
    if ($r['catid'] == 8 || ($r['catid'] >= 15 && $r['catid'] <= 20)) $html = clanica($tekst); // 8 = stara nadkategorija „Članice”
    if ($html === null) $html = ocisti($tekst);
    // video iz K2 polja
    if (preg_match('#\{YouTube\}([\w-]{6,})\{/YouTube\}#i', (string) $r['video'], $v)) {
        $html .= '<p><iframe src="https://www.youtube-nocookie.com/embed/' . $v[1] . '" width="560" height="315" allowfullscreen title="Video"></iframe></p>';
    } elseif (preg_match('#youtube\.com/(?:watch\?v=|embed/)([\w-]{6,})|youtu\.be/([\w-]{6,})#', (string) $r['video'], $v)) {
        $html .= '<p><iframe src="https://www.youtube-nocookie.com/embed/' . ($v[1] ?: $v[2]) . '" width="560" height="315" allowfullscreen title="Video"></iframe></p>';
    }

    // mjesečni tekstovi „Lovište u …” imali su istek objave (2011./2014.), ali vrijede svake godine
    $objavljeno = (int) ($r['published'] == 1 && ($r['catid'] == 12 || !$r['publish_down'] || $r['publish_down'] < '1971' || $r['publish_down'] > date('Y-m-d')));
    $datum = $r['created'] && $r['created'] > '1971' ? $r['created'] : $r['publish_up'];
    $podmapa = date('Y/m', strtotime($datum));
    $sazetak = $GLOBALS['clanica_sazetak'] !== '' ? $GLOBALS['clanica_sazetak'] : (trim((string) $r['metadesc']) !== '' ? trim($r['metadesc']) : sazetak_iz($html));

    // glavna slika: K2 slika bilo kojeg od duplikata, inače prva iz galerije
    $glavna = null;
    foreach ($ids as $i) {
        foreach (array('jpg', 'png', 'jpeg', 'gif') as $ext) {
            if (!$glavna && ($f = nadji('media/k2/items/src/' . md5('Image' . $i) . '.' . $ext))) $glavna = $f;
        }
    }
    // galerija: {gallery}N{/gallery} u polju ili tekstu; N je gotovo uvijek ID članka
    $brojevi = array();
    if (preg_match_all('#\{gallery\}(?:\.\./media/k2/galleries/)?(\d+)\{/gallery\}#', $r['gallery'] . $tekst, $mm)) $brojevi = $mm[1];
    $slikeGal = array();
    foreach (array_unique(array_merge($brojevi, $ids)) as $n) {
        foreach (array('images/' . $n, 'media/k2/galleries/' . $n) as $d) {
            if (($dir = nadji($d)) && is_dir($dir) && !$slikeGal) $slikeGal = slike_mape($dir);
        }
    }
    if ($brojevi && !$slikeGal) $log['nedostaje'][] = 'galerija ' . implode(',', array_unique($brojevi)) . ' (članak ' . $r['id'] . ')';
    $slika = null;
    if ($glavna) $slika = spremi_sliku($glavna, 1600, 720, $podmapa);
    elseif ($slikeGal) $slika = spremi_sliku($slikeGal[0], 1600, 720, $podmapa);
    elseif ($katId === $kat['novosti']) $log['nema_glavne']++;

    $slug = slugify($naslov); // stare adrese preusmjeravaju se po K2 ID-ju, pa slug može biti iz naslova
    $slug = slug_jedinstven(in_array($slug, array('o-nama', 'kontakt', 'lovidba', 'politika-privatnosti'), true) ? $slug . '-' . $r['id'] : $slug);
    $db->prepare('INSERT INTO sadrzaj (kategorija_id, naslov, slug, datum, tekst, sazetak, slika, objavljeno, redoslijed, pregledi, stari_id, izradeno, izmijenjeno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(array($katId, $naslov, $slug, $datum, $html, $sazetak, $slika, $objavljeno, $redoslijed, (int) array_sum(array_column($g, 'hits')), (int) $r['id'], $datum, $r['modified'] > '1971' ? $r['modified'] : $datum));
    $id = (int) $db->lastInsertId();
    $uvezeno++;
    foreach (array_slice($ids, 1) as $i) $preusm['item/' . $i] = url_za(array('kategorija_id' => $katId, 'slug' => $slug));

    if ($slikeGal) {
        $log['galerije']++;
        foreach ($slikeGal as $k => $f) {
            if (!($put = spremi_sliku($f, 1600, 600, 'galerija/' . $podmapa))) continue;
            $db->prepare('INSERT INTO galerija (sadrzaj_id, slika, redoslijed, izradeno) VALUES (?,?,?,?)')->execute(array($id, $put, ($k + 1) * 10, $datum));
            $log['slike_gal']++;
        }
    }

    // privitci (svi ID-jevi grupe)
    $red = 0;
    foreach ($privitciSvi as $p) {
        if (!in_array((int) $p['itemID'], array_map('intval', $ids), true)) continue;
        $naziv = trim($p['title']);
        if ($naziv === '' || $naziv === $p['filename'] || preg_match('/^klikni/i', $naziv) || preg_match('/\.\w{2,4}$/', $naziv)) {
            $naziv = count(array_filter($privitciSvi, function ($x) use ($ids) { return in_array((int) $x['itemID'], array_map('intval', $ids), true); })) === 1
                ? $naslov
                : trim(preg_replace(array('/^\d+_/', '/[_]+/', '/\.\w+$/'), array('', ' ', ''), $p['filename']));
        }
        $ext = strtolower(pathinfo($p['filename'], PATHINFO_EXTENSION));
        $ime = substr(slugify($naziv), 0, 70) . '.' . $ext;
        $dat = 'uploads/privitci/stari/' . $ime;
        $izvor = nadji('media/k2/attachments/' . $p['filename']);
        $vel = 0;
        if ($izvor) {
            if (!is_dir($WEB . '/uploads/privitci/stari')) mkdir($WEB . '/uploads/privitci/stari', 0755, true);
            copy($izvor, $WEB . '/' . $dat);
            $vel = filesize($izvor);
        } else {
            $log['nedostaje'][] = 'media/k2/attachments/' . $p['filename'];
        }
        $red += 10;
        $db->prepare('INSERT INTO privitci (sadrzaj_id, naziv, datoteka, velicina, redoslijed) VALUES (?,?,?,?,?)')->execute(array($id, $naziv, $dat, $vel, $red));
    }
}

// starije kopije članica → adresa iste članice u LU
$poNaslovu = array();
foreach ($db->query('SELECT kategorija_id, slug, naslov FROM sadrzaj WHERE kategorija_id IN (' . implode(',', array_map(function ($s) use ($kat) { return $kat[$s]; }, $lu)) . ')') as $s) {
    $poNaslovu[mb_strtolower($s['naslov'])] = url_za($s);
}
foreach ($odgode as $o) {
    foreach ($o[0] as $i) $preusm['item/' . $i] = $poNaslovu[$o[1]] ?? '/clanice-saveza';
}

// ------------------------------------------------------------------ samostalne stranice
$O = $GLOBALS['ORG'];
// fotografije vodstva (images/… sa stare stranice) → web/images/vodstvo/; bez fotografije ostaje ikona
$foto = function ($ime, $alt) use ($WEB) {
    foreach (array('images/' . $ime, 'images/2023/03/03/' . $ime, 'images/2019/07/11/' . $ime) as $rel) {
        if ($f = nadji($rel)) {
            if (!is_dir($WEB . '/images/vodstvo')) mkdir($WEB . '/images/vodstvo', 0755, true);
            copy($f, $WEB . '/images/vodstvo/' . $ime);
            return '<img src="/images/vodstvo/' . $ime . '" alt="' . e($alt) . '" width="224" height="295">';
        }
    }
    return '';
};
$stranice = array(
    'o-nama' => array('O nama', '<div class="uvod"><p>Prilagodba Zakonu o lovu (1994.) i novom teritorijalnom ustroju Republike Hrvatske rezultirala je osnivanjem <strong>Lovačkog saveza Osječko-baranjske županije 21. siječnja 1995.</strong> Cilj Saveza je objedinjavanje rada učlanjenih članica s područja Osječko-baranjske županije, promidžba lovstva te unapređivanje uzgoja, zaštite, lova i korištenja divljači.</p><p>Lovački savez Osječko-baranjske županije članica je Hrvatskog lovačkog saveza. Savez zastupa predsjednik, a predstavlja ga i osoba koju predsjednik za to ovlasti.</p></div>'
        . '<p class="skupina">Vodstvo Saveza</p><div class="osobe">'
        . '<p class="osoba istaknuta">' . $foto('blazevic.jpg', 'Antun Blažević') . '<span class="uloga">Predsjednik</span> <strong>Antun Blažević</strong> <span class="titula">Izabran za predsjednika Saveza 27. 1. 2023. Član LD „Čepin” Čepin.</span></p>'
        . '<p class="osoba">' . $foto('dopredsjednikzvone.jpg', 'Zvonimir Pavošević') . '<span class="uloga">Zamjenik predsjednika</span> <strong>Zvonimir Pavošević</strong> <span class="titula">Predsjednik LD „Jastreb” Čepinski Martinci</span></p>'
        . '<p class="osoba">' . $foto('dopredsjednikistokovic.jpg', 'Miro Ištoković') . '<span class="uloga">Zamjenik predsjednika</span> <strong>Miro Ištoković</strong> <span class="titula">Predsjednik LD „Prepelica” Šag-Nard</span></p>'
        . '<p class="osoba">' . $foto('dopredsjednikzpanic.jpg', 'Josip Županić') . '<span class="uloga">Zamjenik predsjednika</span> <strong>Josip Županić</strong> <span class="titula">Predsjednik LD „Jarebica” Čeminac</span></p>'
        . '<p class="osoba">' . $foto('andrasevicgoranls.jpg', 'Goran Andrašević') . '<span class="uloga">Stručna služba</span> <strong>Goran Andrašević</strong> <span class="titula">Administrativni djelatnik Saveza; tajnik LD „Sokol” Koška</span></p>'
        . '</div><p class="skupina">Podaci o Savezu</p><div class="polja">'
        . '<p class="polje"><span class="titula">Naziv</span> <strong>' . e($O['naziv']) . '</strong></p>'
        . '<p class="polje"><span class="titula">Adresa</span> <strong>' . e($O['adresa'] . ', ' . $O['mjesto']) . '</strong></p>'
        . '<p class="polje"><span class="titula">OIB</span> <strong>' . e($O['oib']) . '</strong></p>'
        . '<p class="polje"><span class="titula">Matični broj</span> <strong>' . e($O['mb']) . '</strong></p>'
        . '<p class="polje"><span class="titula">IBAN</span> <strong>' . e($O['iban']) . '</strong></p>'
        . '<p class="polje"><span class="titula">Osnovan</span> <strong>21. siječnja 1995.</strong></p>'
        . '</div>'),
    'kontakt' => array('Kontakt', '<p>Ured Lovačkog saveza Osječko-baranjske županije nalazi se u Osijeku. Za upite o članstvu, lovačkim ispitima, dokumentima i lovnim uredima javite se telefonom, e-mailom ili putem obrasca.</p>'),
    'lovidba' => array('Lovidba', '<p>Što se u lovištu događa ovaj mjesec i što lovac treba raditi. Lovostaj i lovna sezona pojedinih vrsta propisani su <a href="/dokumenti">Pravilnikom o lovostaji</a>.</p>'),
    'politika-privatnosti' => array('Politika privatnosti', '<p>Lovački savez Osječko-baranjske županije (OIB ' . e($O['oib']) . ', ' . e($O['adresa'] . ', ' . $O['mjesto']) . ') voditelj je obrade osobnih podataka koje nam pošaljete putem kontakt obrasca.</p>'
        . '<h2>Koje podatke prikupljamo</h2><p>Ime i prezime, e-mail adresu, broj telefona (ako ga upišete) i sadržaj poruke. Podatke koristimo isključivo kako bismo odgovorili na vaš upit.</p>'
        . '<h2>Koliko ih čuvamo</h2><p>Poruke se čuvaju u e-mail sandučiću Saveza onoliko dugo koliko je potrebno za odgovor i eventualnu daljnju komunikaciju, a najduže dvije godine. Podatke ne prosljeđujemo trećim osobama.</p>'
        . '<h2>Kolačići</h2><p>Stranica koristi samo nužni kolačić za prijavu u administraciju. Ne koristimo kolačiće za praćenje ni oglašavanje.</p>'
        . '<h2>Vaša prava</h2><p>Možete zatražiti uvid, ispravak ili brisanje svojih podataka na <a href="mailto:' . e($O['email']) . '">' . e($O['email']) . '</a>. Pritužbu možete podnijeti Agenciji za zaštitu osobnih podataka (azop.hr).</p>'),
);
$stranicaId = array();
foreach ($stranice as $slug => $s) {
    $db->prepare('INSERT INTO sadrzaj (kategorija_id, naslov, slug, datum, tekst, sazetak, objavljeno, izradeno, izmijenjeno) VALUES (NULL,?,?,?,?,?,1,?,?)')
        ->execute(array($s[0], $slug, sada(), sanitiziraj_html($s[1]), sazetak_iz($s[1]), sada(), sada()));
    $stranicaId[$slug] = (int) $db->lastInsertId();
}

// ------------------------------------------------------------------ izbornik
$stavka = function ($naslov, $tip, $rod = null, $sid = null, $kid = null, $red = 0) use ($db) {
    $db->prepare('INSERT INTO izbornik (roditelj, naslov, tip, sadrzaj_id, kategorija_id, redoslijed) VALUES (?,?,?,?,?,?)')->execute(array($rod, $naslov, $tip, $sid, $kid, $red));
    return (int) $db->lastInsertId();
};
$stavka('O nama', 'stranica', null, $stranicaId['o-nama'], null, 10);
$stavka('Novosti', 'kategorija', null, null, $kat['novosti'], 20);
$cl = $stavka('Članice', 'kategorija', null, null, $kat['clanice'], 30);
foreach ($lu as $ime => $slug) $stavka('Lovni ured ' . $ime, 'kategorija', $cl, null, $kat[$slug], 0);
$dv = $stavka('Divljač', 'kategorija', null, null, $kat['divljac'], 40);
$stavka('Krupna divljač', 'kategorija', $dv, null, $kat['krupna'], 10);
$stavka('Sitna divljač', 'kategorija', $dv, null, $kat['sitna'], 20);
$lv = $stavka('Lov', 'kategorija', null, null, $kat['lov'], 50);
$stavka('Lovidba ovaj mjesec', 'stranica', $lv, $stranicaId['lovidba'], null, 10);
$st = $db->prepare('SELECT id, naslov FROM sadrzaj WHERE kategorija_id = ? ORDER BY redoslijed');
$st->execute(array($kat['lov']));
foreach ($st->fetchAll() as $k => $s) $stavka($s['naslov'], 'stranica', $lv, $s['id'], null, 20 + $k * 10);
$stavka('Lovište kroz godinu', 'kategorija', $lv, null, $kat['lovište'], 90);
$stavka('Dokumenti', 'kategorija', null, null, $kat['dokumenti'], 60);
$stavka('Kontakt', 'stranica', null, $stranicaId['kontakt'], null, 70);

// ------------------------------------------------------------------ preusmjerenja starih adresa (/index.php/…)
$nacin = $db->query("SELECT kategorija_id, slug FROM sadrzaj WHERE kategorija_id = {$kat['lov']} AND redoslijed = 10")->fetch();
$obicaji = $db->query("SELECT kategorija_id, slug FROM sadrzaj WHERE kategorija_id = {$kat['lov']} AND redoslijed = 20")->fetch();
$preusm += array(
    'naslovna' => '/', 'home2' => '/', 'onama' => '/o-nama', 'kontakt' => '/kontakt', 'lovidba' => '/lovidba',
    'clanice-saveza' => '/clanice-saveza', 'divljac' => '/divljac', 'novosti' => '/novosti', 'dokumenti' => '/dokumenti',
    'lov' => '/lov', 'lov/nacin-lova' => url_za($nacin), 'lov/lovacki-obicaji' => url_za($obicaji),
);
foreach ($lu as $ime => $slug) $preusm['clanice-saveza/' . $slug] = '/clanice-saveza/' . $slug;
foreach ($preusm as $s => $n) spremi_preusmjerenje($s, $n);

$db->commit();

// ------------------------------------------------------------------ izvještaj
$n = $db->query('SELECT k.naziv, COUNT(s.id) n, SUM(s.objavljeno) pub, SUM(s.slika IS NOT NULL) sl FROM kategorije k LEFT JOIN sadrzaj s ON s.kategorija_id = k.id GROUP BY k.id ORDER BY k.redoslijed')->fetchAll();
$izvj = "Uvoz " . date('Y-m-d H:i') . "\nZapisa: $uvezeno (od " . count($redovi) . " K2 redova, duplikati spojeni)\n";
foreach ($n as $r) $izvj .= sprintf("  %-28s %4d  objavljeno %4d  sa slikom %4d\n", $r['naziv'], $r['n'], $r['pub'], $r['sl']);
$izvj .= "Galerija: {$log['galerije']}, slika u galerijama: {$log['slike_gal']}\nNovosti bez glavne slike: {$log['nema_glavne']}\n";
$izvj .= 'Privitaka: ' . $db->query('SELECT COUNT(*) FROM privitci')->fetchColumn() . ', bez datoteke: ' . $db->query('SELECT COUNT(*) FROM privitci WHERE velicina = 0')->fetchColumn() . "\n";
$ned = array_values(array_unique($log['nedostaje']));
$izvj .= 'Nedostaje datoteka: ' . count($ned) . "\n  " . implode("\n  ", $ned) . "\n";
file_put_contents(__DIR__ . '/uvoz.log', $izvj);
echo substr($izvj, 0, 2500), "\n(sve u _alat/uvoz.log)\n";
