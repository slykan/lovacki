<?php
/**
 * Lovački savez Osječko-baranjske županije — jezgra: baza (SQLite lokalno / MySQL na poslužitelju),
 * kategorije, pomoćne funkcije, prijava, sanitizacija HTML-a. Radi na PHP 7.4+ s pdo, gd i dom.
 * Nastalo iz CMS-a za HNS Osijek; razlika: sav sadržaj je u kategorijama (stablo), stranice su bez kategorije.
 */

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');
define('SITE_URL', 'https://www.lovacki-savez-osijek.hr');
define('SITE_NAME', 'Lovački savez OBŽ');
define('PO_STRANICI', 12);

date_default_timezone_set('Europe/Zagreb');
mb_internal_encoding('UTF-8');

// podaci o savezu (podnožje, kontakt, JSON-LD)
$GLOBALS['ORG'] = array(
    'naziv'   => 'Lovački savez Osječko-baranjske županije',
    'kratko'  => 'Lovački savez OBŽ',
    'adresa'  => 'J. J. Strossmayera 65a',
    'mjesto'  => '31000 Osijek',
    'tel'     => '+385 31 369 592',
    'tel_link'=> '+38531369592',
    'mob'     => '+385 99 60 60 630',
    'mob_link'=> '+385996060630',
    'email'   => 'lovacki.savez.obz@gmail.com',
    'oib'     => '42752951222',
    'mb'      => '1035797',
    'iban'    => 'HR1025000091102029199',
    'osnovan' => '1995-01-21',
    'facebook'=> 'https://www.facebook.com/lovackisavezosijek/',
    'hls'     => 'https://www.hls.com.hr/',
    'steta'   => 'https://dekra-hls-portal.powerappsportals.com/hr-HR/Customer-Notice/', // prijava štete od divljači
    'geo'     => array(45.563461, 18.669041),
);

/**
 * Baza: MySQL ako postoji inc/db-config.php (poslužitelj), inače SQLite u data/ (lokalni rad).
 * Upiti su pisani tako da rade na oba (bez strftime/julianday/INSERT OR REPLACE…).
 */
function db()
{
    static $db = null;
    if ($db) {
        return $db;
    }
    $cfgFile = __DIR__ . '/db-config.php';
    if (is_file($cfgFile)) {
        $c = include $cfgFile;
        $db = new PDO('mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . $c['baza'] . ';charset=utf8mb4', $c['korisnik'], $c['lozinka'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
        ));
        $GLOBALS['DB_MYSQL'] = true;
        if (!$db->query("SHOW TABLES LIKE 'sadrzaj'")->fetchColumn()) {
            foreach (shema_sql(true) as $s) $db->exec($s);
        }
        return $db;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0750, true);
    }
    $db = new PDO('sqlite:' . DATA_DIR . '/lovacki.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    $GLOBALS['DB_MYSQL'] = false;
    foreach (shema_sql(false) as $s) $db->exec($s);
    return $db;
}

function je_mysql()
{
    db();
    return !empty($GLOBALS['DB_MYSQL']);
}

/**
 * CREATE TABLE naredbe za MySQL (8.0) ili SQLite.
 * kategorije: stablo (roditelj); prikaz = kako se popis prikazuje (novosti s datumom i slikom, popis kartica,
 * dokumenti za preuzimanje); redanje = datum (najnovije prvo), naslov (abecedno) ili redom (redni broj).
 * sadrzaj.kategorija_id NULL = samostalna stranica (O nama, Kontakt…).
 */
function shema_sql($mysql)
{
    if ($mysql) {
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return array(
            "CREATE TABLE IF NOT EXISTS kategorije (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                roditelj INT UNSIGNED NULL,
                naziv VARCHAR(120) NOT NULL,
                slug VARCHAR(90) NOT NULL UNIQUE,
                opis MEDIUMTEXT NOT NULL,
                prikaz VARCHAR(10) NOT NULL DEFAULT 'popis',
                redanje VARCHAR(10) NOT NULL DEFAULT 'naslov',
                redoslijed INT NOT NULL DEFAULT 0,
                objavljeno TINYINT(1) NOT NULL DEFAULT 1,
                stari_id INT NULL,
                CONSTRAINT kategorije_rod FOREIGN KEY (roditelj) REFERENCES kategorije(id) ON DELETE RESTRICT,
                CHECK (prikaz IN ('novosti','popis','dokumenti')),
                CHECK (redanje IN ('datum','naslov','redom'))
            )$t",
            "CREATE TABLE IF NOT EXISTS sadrzaj (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                kategorija_id INT UNSIGNED NULL,
                naslov VARCHAR(255) NOT NULL,
                slug VARCHAR(190) NOT NULL UNIQUE,
                datum DATETIME NOT NULL,
                tekst MEDIUMTEXT NOT NULL,
                sazetak TEXT NOT NULL,
                slika VARCHAR(255) NULL,
                objavljeno TINYINT(1) NOT NULL DEFAULT 1,
                redoslijed INT NOT NULL DEFAULT 0,
                pregledi INT UNSIGNED NOT NULL DEFAULT 0,
                stari_id INT NULL,
                izradeno DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                izmijenjeno DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY sadrzaj_kat_datum (kategorija_id, objavljeno, datum),
                KEY sadrzaj_stari (stari_id),
                CONSTRAINT sadrzaj_kat FOREIGN KEY (kategorija_id) REFERENCES kategorije(id) ON DELETE RESTRICT
            )$t",
            "CREATE TABLE IF NOT EXISTS izbornik (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                roditelj INT UNSIGNED NULL,
                naslov VARCHAR(120) NOT NULL,
                tip VARCHAR(10) NOT NULL,
                sadrzaj_id INT UNSIGNED NULL,
                kategorija_id INT UNSIGNED NULL,
                url VARCHAR(500) NULL,
                redoslijed INT NOT NULL DEFAULT 0,
                novi_prozor TINYINT(1) NOT NULL DEFAULT 0,
                CONSTRAINT izbornik_rod FOREIGN KEY (roditelj) REFERENCES izbornik(id) ON DELETE CASCADE,
                CONSTRAINT izbornik_sad FOREIGN KEY (sadrzaj_id) REFERENCES sadrzaj(id) ON DELETE SET NULL,
                CONSTRAINT izbornik_kat FOREIGN KEY (kategorija_id) REFERENCES kategorije(id) ON DELETE SET NULL,
                CHECK (tip IN ('naslovna','kategorija','stranica','link','grupa'))
            )$t",
            "CREATE TABLE IF NOT EXISTS korisnici (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, korisnik VARCHAR(60) NOT NULL UNIQUE, hash VARCHAR(255) NOT NULL)$t",
            "CREATE TABLE IF NOT EXISTS prijave (ip VARCHAR(45) NOT NULL, vrijeme INT NOT NULL, KEY prijave_ip (ip))$t",
            "CREATE TABLE IF NOT EXISTS preusmjerenja (stari VARCHAR(190) NOT NULL PRIMARY KEY, novi VARCHAR(500) NOT NULL)$t",
            "CREATE TABLE IF NOT EXISTS privitci (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sadrzaj_id INT UNSIGNED NOT NULL,
                naziv VARCHAR(200) NOT NULL,
                datoteka VARCHAR(255) NOT NULL,
                velicina INT UNSIGNED NOT NULL DEFAULT 0,
                redoslijed INT NOT NULL DEFAULT 0,
                KEY privitci_sadrzaj (sadrzaj_id),
                CONSTRAINT privitci_sad FOREIGN KEY (sadrzaj_id) REFERENCES sadrzaj(id) ON DELETE CASCADE
            )$t",
            "CREATE TABLE IF NOT EXISTS postavke (kljuc VARCHAR(60) NOT NULL PRIMARY KEY, vrijednost TEXT NOT NULL)$t",
            "CREATE TABLE IF NOT EXISTS galerija (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sadrzaj_id INT UNSIGNED NULL,
                slika VARCHAR(255) NOT NULL,
                redoslijed INT NOT NULL DEFAULT 0,
                izradeno DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY galerija_sadrzaj (sadrzaj_id),
                CONSTRAINT galerija_sad FOREIGN KEY (sadrzaj_id) REFERENCES sadrzaj(id) ON DELETE CASCADE
            )$t",
        );
    }
    return array(
        "CREATE TABLE IF NOT EXISTS kategorije (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            roditelj INTEGER REFERENCES kategorije(id) ON DELETE RESTRICT,
            naziv TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            opis TEXT NOT NULL DEFAULT '',
            prikaz TEXT NOT NULL DEFAULT 'popis' CHECK (prikaz IN ('novosti','popis','dokumenti')),
            redanje TEXT NOT NULL DEFAULT 'naslov' CHECK (redanje IN ('datum','naslov','redom')),
            redoslijed INTEGER NOT NULL DEFAULT 0,
            objavljeno INTEGER NOT NULL DEFAULT 1,
            stari_id INTEGER
        )",
        "CREATE TABLE IF NOT EXISTS sadrzaj (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kategorija_id INTEGER REFERENCES kategorije(id) ON DELETE RESTRICT,
            naslov TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            datum TEXT NOT NULL,
            tekst TEXT NOT NULL DEFAULT '',
            sazetak TEXT NOT NULL DEFAULT '',
            slika TEXT,
            objavljeno INTEGER NOT NULL DEFAULT 1,
            redoslijed INTEGER NOT NULL DEFAULT 0,
            pregledi INTEGER NOT NULL DEFAULT 0,
            stari_id INTEGER,
            izradeno TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            izmijenjeno TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )",
        'CREATE INDEX IF NOT EXISTS sadrzaj_kat_datum ON sadrzaj (kategorija_id, objavljeno, datum)',
        'CREATE INDEX IF NOT EXISTS sadrzaj_stari ON sadrzaj (stari_id)',
        "CREATE TABLE IF NOT EXISTS izbornik (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            roditelj INTEGER REFERENCES izbornik(id) ON DELETE CASCADE,
            naslov TEXT NOT NULL,
            tip TEXT NOT NULL CHECK (tip IN ('naslovna','kategorija','stranica','link','grupa')),
            sadrzaj_id INTEGER REFERENCES sadrzaj(id) ON DELETE SET NULL,
            kategorija_id INTEGER REFERENCES kategorije(id) ON DELETE SET NULL,
            url TEXT,
            redoslijed INTEGER NOT NULL DEFAULT 0,
            novi_prozor INTEGER NOT NULL DEFAULT 0
        )",
        'CREATE TABLE IF NOT EXISTS korisnici (id INTEGER PRIMARY KEY, korisnik TEXT NOT NULL UNIQUE, hash TEXT NOT NULL)',
        'CREATE TABLE IF NOT EXISTS prijave (ip TEXT NOT NULL, vrijeme INTEGER NOT NULL)',
        'CREATE TABLE IF NOT EXISTS preusmjerenja (stari TEXT PRIMARY KEY, novi TEXT NOT NULL)',
        "CREATE TABLE IF NOT EXISTS privitci (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sadrzaj_id INTEGER NOT NULL REFERENCES sadrzaj(id) ON DELETE CASCADE,
            naziv TEXT NOT NULL,
            datoteka TEXT NOT NULL,
            velicina INTEGER NOT NULL DEFAULT 0,
            redoslijed INTEGER NOT NULL DEFAULT 0
        )",
        'CREATE INDEX IF NOT EXISTS privitci_sadrzaj ON privitci (sadrzaj_id)',
        'CREATE TABLE IF NOT EXISTS postavke (kljuc TEXT PRIMARY KEY, vrijednost TEXT NOT NULL)',
        "CREATE TABLE IF NOT EXISTS galerija (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sadrzaj_id INTEGER REFERENCES sadrzaj(id) ON DELETE CASCADE,
            slika TEXT NOT NULL,
            redoslijed INTEGER NOT NULL DEFAULT 0,
            izradeno TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        )",
        'CREATE INDEX IF NOT EXISTS galerija_sadrzaj ON galerija (sadrzaj_id)',
    );
}

/** „roditelj = ?” koji radi i za NULL (MySQL <=>, SQLite IS). */
function null_jednako($stupac)
{
    return $stupac . (je_mysql() ? ' <=> ?' : ' IS ?');
}

/** Upis ili zamjena preusmjerenja (bez INSERT OR REPLACE / ON DUPLICATE). */
function spremi_preusmjerenje($stari, $novi)
{
    $db = db();
    $db->prepare('DELETE FROM preusmjerenja WHERE stari = ?')->execute(array($stari));
    $db->prepare('INSERT INTO preusmjerenja (stari, novi) VALUES (?, ?)')->execute(array($stari, $novi));
    // ako je nešto prije vodilo na staru adresu, neka vodi ravno na novu
    $db->prepare('UPDATE preusmjerenja SET novi = ? WHERE novi = ?')->execute(array($novi, '/' . $stari));
}

/** LIKE uzorak s ! kao escape znakom (isti zapis u MySQL-u i SQLite-u). */
function like_uzorak($q)
{
    return '%' . str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $q) . '%';
}

function sada()
{
    return date('Y-m-d H:i:s');
}

// ------------------------------------------------------------------ pomoćno

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Adresa zapisa: /putanja-kategorije/slug (npr. /clanice-saveza/lu-baranja/ld-jelen-bolman) ili /slug za stranicu. */
function url_za(array $s)
{
    $k = !empty($s['kategorija_id']) ? kategorija((int) $s['kategorija_id']) : null;
    return ($k ? $k['url'] : '') . '/' . $s['slug'];
}

// ------------------------------------------------------------------ kategorije

/**
 * Sve kategorije (jednom po zahtjevu), s izračunatim 'url' (/novosti, /clanice-saveza/lu-baranja),
 * 'put' (lanac od korijena) i 'djeca' (id-jevi podkategorija po redoslijedu).
 */
function kategorije($osvjezi = false)
{
    static $sve = null;
    if ($sve !== null && !$osvjezi) {
        return $sve;
    }
    $sve = array();
    foreach (db()->query('SELECT * FROM kategorije ORDER BY redoslijed, naziv') as $k) {
        $k['djeca'] = array();
        $sve[(int) $k['id']] = $k;
    }
    foreach ($sve as $id => $k) {
        if ($k['roditelj'] && isset($sve[(int) $k['roditelj']])) $sve[(int) $k['roditelj']]['djeca'][] = $id;
    }
    foreach ($sve as $id => $k) {
        $put = array(); $x = $k; $n = 0;
        while ($x && $n++ < 10) { array_unshift($put, (int) $x['id']); $x = $x['roditelj'] ? ($sve[(int) $x['roditelj']] ?? null) : null; }
        $sve[$id]['put'] = $put;
        $sve[$id]['url'] = '/' . implode('/', array_map(function ($i) use ($sve) { return $sve[$i]['slug']; }, $put));
        // skrivena je i ako je skriven netko iznad
        $sve[$id]['vidljiva'] = !array_filter($put, function ($i) use ($sve) { return !$sve[$i]['objavljeno']; });
    }
    return $sve;
}

function kategorija($id)
{
    $sve = kategorije();
    return $sve[(int) $id] ?? null;
}

/** Kategorija i sve podkategorije (id-jevi) — za popise „sve u Članicama”. */
function kategorija_grana($id)
{
    $sve = kategorije(); $out = array((int) $id);
    foreach ($sve[(int) $id]['djeca'] ?? array() as $d) $out = array_merge($out, kategorija_grana($d));
    return $out;
}

/** Opcije za <select>: stablo s uvlakom. */
function kategorije_opcije($odabrano = null, $osim = null)
{
    $html = '';
    $grana = function ($rod, $dub) use (&$grana, &$html, $odabrano, $osim) {
        foreach (kategorije() as $k) {
            if ((int) $k['roditelj'] !== (int) $rod || (int) $k['id'] === (int) $osim) continue;
            $html .= '<option value="' . (int) $k['id'] . '"' . ((int) $odabrano === (int) $k['id'] ? ' selected' : '') . '>' . str_repeat('— ', $dub) . e($k['naziv']) . '</option>';
            $grana($k['id'], $dub + 1);
        }
    };
    $grana(0, 0);
    return $html;
}

/**
 * Tekst „Lovište u …” za mjesec $m (1–12) iz kategorije lovni-kalendar; ako za taj mjesec nema teksta,
 * uzima se najbliži prethodni. Vraća array(zapis, je_li_tocno_taj_mjesec) ili null.
 */
function lovidba_za_mjesec($m)
{
    $k = null;
    foreach (kategorije() as $x) if ($x['slug'] === 'lovni-kalendar') $k = $x;
    if (!$k) return null;
    $po = array();
    foreach (db()->query('SELECT * FROM sadrzaj WHERE objavljeno = 1 AND redoslijed BETWEEN 1 AND 12 AND kategorija_id = ' . (int) $k['id']) as $s) $po[(int) $s['redoslijed']] = $s;
    for ($i = 0; $i < 12; $i++) {
        $n = (($m - 1 - $i + 12) % 12) + 1;
        if (isset($po[$n])) return array($po[$n], $i === 0);
    }
    return null;
}

function mjesec_naziv($m)
{
    $n = array('', 'siječanj', 'veljača', 'ožujak', 'travanj', 'svibanj', 'lipanj', 'srpanj', 'kolovoz', 'rujan', 'listopad', 'studeni', 'prosinac');
    return $n[(int) $m];
}

/** ORDER BY za popis u kategoriji. */
function redanje_sql($k)
{
    switch ($k['redanje'] ?? 'datum') {
        case 'naslov': return 'naslov';
        case 'redom': return 'redoslijed, naslov';
        default: return 'datum DESC, id DESC';
    }
}

function slugify($t)
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, array('č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss'));
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim($t, '-');
    return $t !== '' ? substr($t, 0, 90) : 'clanak';
}

/**
 * Jedinstven slug: dodaje -2, -3… ako već postoji (osim za isti zapis).
 * Zapisi i kategorije dijele prostor adresa, pa se provjeravaju obje tablice.
 */
function slug_jedinstven($slug, $osimId = 0, $tablica = 'sadrzaj')
{
    $baza = $slug; $n = 2;
    $sad = db()->prepare('SELECT 1 FROM sadrzaj WHERE slug = ? AND id <> ?');
    $kat = db()->prepare('SELECT 1 FROM kategorije WHERE slug = ? AND id <> ?');
    while (true) {
        $sad->execute(array($slug, $tablica === 'sadrzaj' ? (int) $osimId : 0));
        $kat->execute(array($slug, $tablica === 'kategorije' ? (int) $osimId : 0));
        if (!$sad->fetchColumn() && !$kat->fetchColumn() && !in_array($slug, array('admin', 'pretraga', 'sitemap', 'uploads', 'images', 'assets', 'inc', 'data', 'index'), true)) {
            return $slug;
        }
        $slug = $baza . '-' . $n++;
    }
}

function datum_hr($d, $sat = false)
{
    $t = strtotime($d);
    return date('j. n. Y.', $t) . ($sat ? date(' H:i', $t) : '');
}

function mjesec_hr($d)
{
    $m = array('', 'siječnja', 'veljače', 'ožujka', 'travnja', 'svibnja', 'lipnja', 'srpnja', 'kolovoza', 'rujna', 'listopada', 'studenoga', 'prosinca');
    $t = strtotime($d);
    return date('j.', $t) . ' ' . $m[(int) date('n', $t)] . ' ' . date('Y.', $t);
}

/** Kratki sažetak iz HTML-a. */
function sazetak_iz($html, $max = 220)
{
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<(/?(p|div|h[1-6]|li|br|span|td|th|blockquote|figcaption)\b)#i', ' <$1', $html)), ENT_QUOTES, 'UTF-8')));
    if (mb_strlen($t) <= $max) {
        return $t;
    }
    $t = mb_substr($t, 0, $max);
    return preg_replace('/\s+\S*$/u', '', $t) . '…';
}

function slika_url($s, $velicina = 'm')
{
    if (empty($s['slika'])) {
        return null;
    }
    // uploads/2026/04/abc.jpg -> uploads/2026/04/abc-m.jpg (manja verzija za popise)
    if ($velicina === 'm') {
        $m = preg_replace('/\.(jpe?g|png|webp)$/i', '-m.$1', $s['slika']);
        if (is_file(ROOT . '/' . $m)) {
            return '/' . $m;
        }
    }
    return '/' . $s['slika'];
}

// ------------------------------------------------------------------ sesija i prijava

function sesija()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('lso_admin');
    session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Lax'));
    session_start();
}

function prijavljen()
{
    sesija();
    return !empty($_SESSION['korisnik']) && ($_SESSION['istek'] ?? 0) > time();
}

function trazi_prijavu()
{
    if (!prijavljen()) {
        header('Location: /admin/prijava.php');
        exit;
    }
    $_SESSION['istek'] = time() + 4 * 3600; // 4 h neaktivnosti
}

function csrf_token()
{
    sesija();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_polje()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function provjeri_csrf()
{
    sesija();
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(400);
        exit('Sesija je istekla. Osvježite stranicu i pokušajte ponovno.');
    }
}

function ip()
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// ------------------------------------------------------------------ HTML iz uređivača

/**
 * Propušta samo sigurne oznake i atribute (admin je pouzdan, ali zalijepljeni tekst
 * s Facebooka/Worda zna donijeti skripte, stilove i smeće).
 */
function sanitiziraj_html($html)
{
    $dopusteno = array(
        'p' => array(), 'br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 's' => array(),
        'h2' => array(), 'h3' => array(), 'h4' => array(), 'blockquote' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
        'a' => array('href', 'title', 'target', 'rel'), 'img' => array('src', 'alt', 'title', 'width', 'height'),
        'figure' => array(), 'figcaption' => array(), 'table' => array(), 'thead' => array(), 'tbody' => array(), 'tr' => array(),
        'th' => array('colspan', 'rowspan'), 'td' => array('colspan', 'rowspan'), 'hr' => array(), 'sup' => array(), 'sub' => array(),
        'iframe' => array('src', 'width', 'height', 'allowfullscreen', 'title'), 'div' => array(), 'span' => array(),
    );
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="korijen">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $korijen = $doc->getElementById('korijen');
    if (!$korijen) {
        return '';
    }
    $obradi = function (DOMNode $n) use (&$obradi, $dopusteno, $doc) {
        for ($c = $n->firstChild; $c; $c = $sljedeci) {
            $sljedeci = $c->nextSibling;
            if ($c instanceof DOMComment) {
                $n->removeChild($c);
                continue;
            }
            if (!($c instanceof DOMElement)) {
                continue;
            }
            $ime = strtolower($c->nodeName);
            if (in_array($ime, array('script', 'style', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'meta', 'link', 'svg', 'math'), true)) {
                $n->removeChild($c);
                continue;
            }
            if (!isset($dopusteno[$ime])) {
                // nepoznata oznaka: zadrži sadržaj, makni omotač
                $obradi($c);
                while ($c->firstChild) {
                    $n->insertBefore($c->firstChild, $c);
                }
                $n->removeChild($c);
                continue;
            }
            // klase samo s popisa (polja/kartice u stranicama); ostale se brišu
            $klasa = in_array($ime, array('div', 'p', 'span', 'ul'), true)
                ? implode(' ', array_intersect(preg_split('/\s+/', trim($c->getAttribute('class'))), array('uvod', 'polja', 'polje', 'osobe', 'osoba', 'istaknuta', 'uloga', 'titula', 'skupina', 'napomena', 'popis-ureda')))
                : '';
            foreach (iterator_to_array($c->attributes) as $a) {
                if (!in_array(strtolower($a->name), $dopusteno[$ime], true)) {
                    $c->removeAttribute($a->name);
                }
            }
            if ($klasa !== '') {
                $c->setAttribute('class', $klasa);
            } elseif ($ime === 'div' || $ime === 'span') {
                // goli omotači (npr. iz Joomle/Worda) ne nose ništa: zadrži sadržaj, makni omotač
                $obradi($c);
                while ($c->firstChild) {
                    $n->insertBefore($c->firstChild, $c);
                }
                $n->removeChild($c);
                continue;
            }
            foreach (array('href', 'src') as $atr) {
                if ($c->hasAttribute($atr)) {
                    $v = trim($c->getAttribute($atr));
                    if (preg_match('/^\s*(javascript|vbscript|data):/i', $v) && !($ime === 'img' && preg_match('#^data:image/(png|jpe?g|gif|webp);#i', $v))) {
                        $c->removeAttribute($atr);
                    }
                }
            }
            if ($ime === 'iframe') {
                $src = $c->getAttribute('src');
                if (!preg_match('#^https://(www\.)?(youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.facebook\.com/plugins/video)#', $src)) {
                    $n->removeChild($c);
                    continue;
                }
            }
            if ($ime === 'a' && $c->getAttribute('target') === '_blank') {
                $c->setAttribute('rel', 'noopener');
            }
            $obradi($c);
        }
    };
    $obradi($korijen);
    $out = '';
    foreach ($korijen->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    // prazni odlomci koje ostavljaju uređivači
    $out = preg_replace('#<p>(\s|&nbsp;|\xC2\xA0|<br\s*/?>)*</p>#u', '', $out);
    return trim($out);
}

// ------------------------------------------------------------------ slike

/**
 * Spremi sliku (upload ili lokalna datoteka): ponovno kodiranje kroz GD (uklanja sve
 * što nije slika), najveća strana $max px, uz manju verziju "-m" za popise.
 * Vraća relativnu putanju (uploads/2026/04/xxxx.jpg) ili null.
 */
function spremi_sliku($izvor, $max = 1600, $manja = 720, $podmapa = null)
{
    $info = @getimagesize($izvor);
    if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF), true)) {
        return null;
    }
    $img = @imagecreatefromstring(file_get_contents($izvor));
    if (!$img) {
        return null;
    }
    // ispravi orijentaciju s mobitela
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($izvor);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) $img = imagerotate($img, 180, 0);
        elseif ($o == 6) $img = imagerotate($img, -90, 0);
        elseif ($o == 8) $img = imagerotate($img, 90, 0);
    }
    $png = $info[2] === IMAGETYPE_PNG;
    $podmapa = $podmapa ?: date('Y/m');
    $dir = UPLOAD_DIR . '/' . $podmapa;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ime = bin2hex(random_bytes(8)) . ($png ? '.png' : '.jpg');
    $spremi = function ($img, $max, $put) use ($png) {
        $w = imagesx($img); $h = imagesy($img); $k = min(1, $max / max($w, $h));
        $nw = (int) round($w * $k); $nh = (int) round($h * $k);
        $o = imagecreatetruecolor($nw, $nh);
        if ($png) {
            imagealphablending($o, false); imagesavealpha($o, true);
        } else {
            imagefill($o, 0, 0, imagecolorallocate($o, 255, 255, 255));
        }
        imagecopyresampled($o, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $png ? imagepng($o, $put, 8) : imagejpeg($o, $put, 82);
        imagedestroy($o);
    };
    $spremi($img, $max, $dir . '/' . $ime);
    if ($manja) {
        $spremi($img, $manja, $dir . '/' . preg_replace('/\.(jpg|png)$/', '-m.$1', $ime));
    }
    imagedestroy($img);
    return 'uploads/' . $podmapa . '/' . $ime;
}

function obrisi_sliku($put)
{
    if (!$put || strpos($put, 'uploads/') !== 0 || strpos($put, '..') !== false) {
        return;
    }
    @unlink(ROOT . '/' . $put);
    @unlink(ROOT . '/' . preg_replace('/\.(jpe?g|png|webp)$/i', '-m.$1', $put));
}

// ------------------------------------------------------------------ privitci

/** Dopuštene vrste privitaka (dokumenti, ne kod). */
function privitak_vrste()
{
    return array('pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp', 'zip', 'jpg', 'jpeg', 'png');
}

/**
 * Spremi učitani dokument u uploads/privitci/GGGG/ pod čitljivim imenom + nasumičnim dodatkom.
 * Vraća array(datoteka, naziv, velicina) ili poruku greške (string).
 */
function spremi_privitak($tmp, $izvorno)
{
    $ext = strtolower(pathinfo($izvorno, PATHINFO_EXTENSION));
    if (!in_array($ext, privitak_vrste(), true)) {
        return '„' . $izvorno . '”: ta vrsta datoteke nije dopuštena (' . implode(', ', privitak_vrste()) . ').';
    }
    if ($ext === 'pdf' && file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
        return '„' . $izvorno . '” nije ispravan PDF.';
    }
    $naziv = trim(preg_replace('/\s+/u', ' ', str_replace(array('_', '-'), ' ', pathinfo($izvorno, PATHINFO_FILENAME))));
    $dir = UPLOAD_DIR . '/privitci/' . date('Y');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ime = substr(slugify($naziv) ?: 'dokument', 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $ime)) {
        return '„' . $izvorno . '” nije spremljen (greška na poslužitelju).';
    }
    @chmod($dir . '/' . $ime, 0644);
    return array('uploads/privitci/' . date('Y') . '/' . $ime, $naziv !== '' ? $naziv : 'Dokument', filesize($dir . '/' . $ime));
}

function obrisi_privitak($put)
{
    if ($put && strpos($put, 'uploads/privitci/') === 0 && strpos($put, '..') === false) {
        @unlink(ROOT . '/' . $put);
    }
}

function privitci_za($id)
{
    $st = db()->prepare('SELECT * FROM privitci WHERE sadrzaj_id = ? ORDER BY redoslijed, id');
    $st->execute(array($id));
    return $st->fetchAll();
}

// ------------------------------------------------------------------ galerija

/** Slike galerije zapisa (uploads/galerija/GGGG/MM/…, uz manju verziju -m za niz ispod teksta). */
function galerija_za($id)
{
    $st = db()->prepare('SELECT * FROM galerija WHERE sadrzaj_id = ? ORDER BY redoslijed, id');
    $st->execute(array($id));
    return $st->fetchAll();
}

/**
 * Spremanje iz uređivača: redoslijed po $redom (id-jevi kako stoje u adminu), slike učitane
 * prije prvog spremanja (sadrzaj_id NULL) pripoje se zapisu, $obrisi se brišu s diska.
 */
function spremi_galeriju($sadrzajId, array $redom, array $obrisi)
{
    $db = db();
    foreach (array_map('intval', $obrisi) as $gid) {
        $st = $db->prepare('SELECT slika FROM galerija WHERE id = ? AND (sadrzaj_id = ? OR sadrzaj_id IS NULL)');
        $st->execute(array($gid, $sadrzajId));
        if (($put = $st->fetchColumn()) !== false) {
            obrisi_sliku($put);
            $db->prepare('DELETE FROM galerija WHERE id = ?')->execute(array($gid));
        }
    }
    $up = $db->prepare('UPDATE galerija SET sadrzaj_id = ?, redoslijed = ? WHERE id = ? AND (sadrzaj_id = ? OR sadrzaj_id IS NULL)');
    foreach (array_values(array_map('intval', $redom)) as $i => $gid) {
        $up->execute(array($sadrzajId, ($i + 1) * 10, $gid, $sadrzajId));
    }
}

/** Slike učitane u novu novost koja nikad nije spremljena (starije od dana). */
function pocisti_galeriju()
{
    $st = db()->prepare('SELECT id, slika FROM galerija WHERE sadrzaj_id IS NULL AND izradeno < ?');
    $st->execute(array(date('Y-m-d H:i:s', time() - 86400)));
    foreach ($st->fetchAll() as $r) {
        obrisi_sliku($r['slika']);
        db()->prepare('DELETE FROM galerija WHERE id = ?')->execute(array($r['id']));
    }
}

function velicina_hr($b)
{
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    return max(1, (int) round($b / 1024)) . ' kB';
}

// ------------------------------------------------------------------ postavke (popup …)

function postavka($kljuc, $zadano = '')
{
    static $sve = null;
    if ($sve === null) {
        $sve = array();
        foreach (db()->query('SELECT kljuc, vrijednost FROM postavke') as $r) $sve[$r['kljuc']] = $r['vrijednost'];
    }
    return array_key_exists($kljuc, $sve) ? $sve[$kljuc] : $zadano;
}

function spremi_postavku($kljuc, $vrijednost)
{
    db()->prepare('DELETE FROM postavke WHERE kljuc = ?')->execute(array($kljuc));
    db()->prepare('INSERT INTO postavke (kljuc, vrijednost) VALUES (?, ?)')->execute(array($kljuc, (string) $vrijednost));
}

// ------------------------------------------------------------------ izbornik

function izbornik_stablo()
{
    $svi = db()->query('SELECT i.*, s.slug, s.kategorija_id AS s_kat FROM izbornik i LEFT JOIN sadrzaj s ON s.id = i.sadrzaj_id ORDER BY i.redoslijed, i.id')->fetchAll();
    $po = array();
    foreach ($svi as $s) {
        $s['href'] = izbornik_href($s);
        $po[(int) $s['roditelj']][] = $s;
    }
    $grani = function ($rod) use (&$grani, $po) {
        $out = array();
        foreach ($po[$rod] ?? array() as $s) {
            $s['djeca'] = $grani((int) $s['id']);
            $out[] = $s;
        }
        return $out;
    };
    return $grani(0);
}

function izbornik_href(array $s)
{
    switch ($s['tip']) {
        case 'naslovna': return '/';
        case 'kategorija': $k = kategorija($s['kategorija_id']); return $k ? $k['url'] : '#';
        case 'stranica': return !empty($s['slug']) ? url_za(array('kategorija_id' => $s['s_kat'], 'slug' => $s['slug'])) : '#';
        case 'link': return $s['url'] ?: '#';
        default: return null; // grupa = samo naslov podizbornika
    }
}
