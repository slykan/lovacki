<?php
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    $akcija = $_POST['akcija'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($akcija === 'spremi') {
        $tip = in_array($_POST['tip'] ?? '', array('naslovna', 'kategorija', 'stranica', 'link', 'grupa'), true) ? $_POST['tip'] : 'stranica';
        $naslov = trim((string) ($_POST['naslov'] ?? ''));
        $rod = (int) ($_POST['roditelj'] ?? 0) ?: null;
        $sid = $tip === 'stranica' ? ((int) ($_POST['sadrzaj_id'] ?? 0) ?: null) : null;
        $kid = $tip === 'kategorija' && kategorija((int) ($_POST['kategorija_id'] ?? 0)) ? (int) $_POST['kategorija_id'] : null;
        $url = $tip === 'link' ? trim((string) ($_POST['url'] ?? '')) : null;
        if ($url !== null && !preg_match('#^(https?://|/|mailto:|tel:)#i', $url)) $url = 'https://' . $url;
        if ($rod === $id && $id) $rod = null;
        if ($naslov === '' && $kid) $naslov = kategorija($kid)['naziv'];
        if ($naslov === '' && $sid) {
            $st = $db->prepare('SELECT naslov FROM sadrzaj WHERE id = ?'); $st->execute(array($sid)); $naslov = (string) $st->fetchColumn();
        }
        if ($naslov === '' || ($tip === 'stranica' && !$sid) || ($tip === 'kategorija' && !$kid) || ($tip === 'link' && !$url)) {
            flash('greska', 'Upišite naslov i odaberite stranicu ili link.');
        } elseif ($id) {
            $db->prepare('UPDATE izbornik SET roditelj=?, naslov=?, tip=?, sadrzaj_id=?, kategorija_id=?, url=?, novi_prozor=? WHERE id=?')
                ->execute(array($rod, $naslov, $tip, $sid, $kid, $url, isset($_POST['novi_prozor']) ? 1 : 0, $id));
            flash('ok', 'Stavka je spremljena.');
        } else {
            $st = $db->prepare('SELECT COALESCE(MAX(redoslijed), 0) + 10 FROM izbornik WHERE ' . null_jednako('roditelj'));
            $st->execute(array($rod));
            $db->prepare('INSERT INTO izbornik (roditelj, naslov, tip, sadrzaj_id, kategorija_id, url, novi_prozor, redoslijed) VALUES (?,?,?,?,?,?,?,?)')
                ->execute(array($rod, $naslov, $tip, $sid, $kid, $url, isset($_POST['novi_prozor']) ? 1 : 0, (int) $st->fetchColumn()));
            flash('ok', 'Stavka je dodana u izbornik.');
        }
    } elseif ($akcija === 'obrisi' && $id) {
        $db->prepare('DELETE FROM izbornik WHERE id = ?')->execute(array($id));
        flash('ok', 'Stavka je maknuta iz izbornika (stranica i dalje postoji).');
    } elseif (in_array($akcija, array('gore', 'dolje'), true) && $id) {
        $st = $db->prepare('SELECT * FROM izbornik WHERE id = ?'); $st->execute(array($id)); $s = $st->fetch();
        if ($s) {
            $braca = $db->prepare('SELECT id FROM izbornik WHERE ' . null_jednako('roditelj') . ' ORDER BY redoslijed, id');
            $braca->execute(array($s['roditelj']));
            $ids = $braca->fetchAll(PDO::FETCH_COLUMN);
            $i = array_search($s['id'], $ids);
            $j = $akcija === 'gore' ? $i - 1 : $i + 1;
            if (isset($ids[$j])) { $t = $ids[$i]; $ids[$i] = $ids[$j]; $ids[$j] = $t; }
            $u = $db->prepare('UPDATE izbornik SET redoslijed = ? WHERE id = ?');
            foreach ($ids as $k => $x) $u->execute(array(($k + 1) * 10, $x));
        }
    }
    header('Location: /admin/izbornik.php');
    exit;
}

$stablo = izbornik_stablo();
$stranice = $db->query('SELECT id, naslov, kategorija_id FROM sadrzaj WHERE objavljeno = 1 AND (kategorija_id IS NULL OR kategorija_id IN (SELECT id FROM kategorije WHERE prikaz = \'popis\')) ORDER BY kategorija_id IS NOT NULL, naslov')->fetchAll();
$vrh = $db->query('SELECT id, naslov FROM izbornik WHERE roditelj IS NULL ORDER BY redoslijed')->fetchAll();
$uredi = null;
if (!empty($_GET['uredi'])) {
    $st = $db->prepare('SELECT * FROM izbornik WHERE id = ?'); $st->execute(array((int) $_GET['uredi'])); $uredi = $st->fetch();
}
$u = $uredi ?: array('id' => 0, 'roditelj' => null, 'naslov' => '', 'tip' => 'stranica', 'sadrzaj_id' => (int) ($_GET['dodaj'] ?? 0) ?: null, 'kategorija_id' => null, 'url' => '', 'novi_prozor' => 0);
$tipovi = array('stranica' => 'Stranica ili zapis', 'kategorija' => 'Kategorija (popis)', 'naslovna' => 'Naslovna', 'link' => 'Vanjski link / PDF', 'grupa' => 'Samo naslov podizbornika');

admin_vrh('Izbornik', 'izbornik');
$red = function ($s, $dub) {
    ?>
    <li class="a-iz<?= $dub ? ' pod' : '' ?>">
      <span class="a-iz-naslov"><?= e($s['naslov']) ?> <small><?= e($s['tip'] === 'stranica' ? ($s['href'] ?? '') : ($s['tip'] === 'grupa' ? 'podizbornik' : ($s['href'] ?? ''))) ?></small></span>
      <span class="a-iz-gumbi">
        <form method="post"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button name="akcija" value="gore" title="Gore">▲</button><button name="akcija" value="dolje" title="Dolje">▼</button></form>
        <a href="?uredi=<?= (int) $s['id'] ?>">Uredi</a>
        <form method="post"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button name="akcija" value="obrisi" class="a-crveno" data-potvrdi="Maknuti „<?= e($s['naslov']) ?>” iz izbornika<?= !empty($s['djeca']) ? ' zajedno s podstavkama' : '' ?>?">Makni</button></form>
      </span>
    </li>
    <?php
};
?>
<div class="a-glava"><h1>Izbornik</h1></div>
<div class="a-stupci">
  <div class="a-glavni">
    <ul class="a-izbornik">
      <?php foreach ($stablo as $s): $red($s, 0); foreach ($s['djeca'] as $d) $red($d, 1); endforeach; ?>
    </ul>
    <p class="a-pomoc">Redoslijed mijenjate strelicama. „Makni” uklanja samo stavku iz izbornika — stranica ili kategorija ostaje.</p>
  </div>
  <aside class="a-strana">
    <form method="post" class="a-kutija-mala a-obrazac" data-izbornik-obrazac>
      <h2><?= $uredi ? 'Uredi stavku' : 'Dodaj stavku' ?></h2>
      <?= csrf_polje() ?>
      <input type="hidden" name="akcija" value="spremi">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <label>Vrsta
        <select name="tip" data-tip><?php foreach ($tipovi as $k => $v): ?><option value="<?= $k ?>"<?= $u['tip'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label data-za="stranica">Stranica
        <select name="sadrzaj_id"><option value="">— odaberite —</option><?php foreach ($stranice as $p): ?><option value="<?= (int) $p['id'] ?>"<?= (int) $u['sadrzaj_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['naslov']) ?><?= $p['kategorija_id'] ? ' (' . e(kategorija($p['kategorija_id'])['naziv']) . ')' : '' ?></option><?php endforeach; ?></select>
      </label>
      <label data-za="kategorija">Kategorija
        <select name="kategorija_id"><option value="">— odaberite —</option><?= kategorije_opcije($u['kategorija_id']) ?></select>
      </label>
      <label data-za="link">Adresa (URL)<input name="url" value="<?= e($u['url']) ?>" placeholder="https://… ili /images/dokument.pdf"></label>
      <label>Naslov u izborniku <small>(prazno = naslov stranice)</small><input name="naslov" value="<?= e($u['naslov']) ?>" maxlength="60"></label>
      <label>Pod stavkom
        <select name="roditelj"><option value="">— glavni izbornik —</option><?php foreach ($vrh as $v): if ((int) $v['id'] === (int) $u['id']) continue; ?><option value="<?= (int) $v['id'] ?>"<?= (int) $u['roditelj'] === (int) $v['id'] ? ' selected' : '' ?>><?= e($v['naslov']) ?></option><?php endforeach; ?></select>
      </label>
      <label class="a-kvacica" data-za="link"><input type="checkbox" name="novi_prozor" value="1"<?= $u['novi_prozor'] ? ' checked' : '' ?>> Otvori u novom prozoru</label>
      <button class="a-btn" type="submit"><?= $uredi ? 'Spremi' : 'Dodaj u izbornik' ?></button>
      <?php if ($uredi): ?><a class="a-link" href="/admin/izbornik.php">Odustani</a><?php endif; ?>
    </form>
  </aside>
</div>
<?php admin_dno(true); ?>
