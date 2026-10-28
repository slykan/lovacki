<?php
/** Kategorije: stablo (npr. Članice › Lovni ured Baranja), opis iznad popisa, način prikaza i redanja. */
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();
$db = db();
$prikazi = array('novosti' => 'Novosti — slika i datum, najnovije prvo', 'popis' => 'Popis — kartice s naslovom i sažetkom', 'dokumenti' => 'Dokumenti — privitci za preuzimanje');
$redanja = array('datum' => 'po datumu (najnovije prvo)', 'naslov' => 'abecedno po naslovu', 'redom' => 'po rednom broju');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    $akcija = $_POST['akcija'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $k = $id ? kategorija($id) : null;

    if ($akcija === 'spremi') {
        $naziv = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['naziv'] ?? '')));
        $rod = (int) ($_POST['roditelj'] ?? 0) ?: null;
        if ($rod && $k && in_array($id, kategorije()[$rod]['put'], true)) $rod = $k['roditelj']; // ne pod samu sebe
        $prikaz = isset($prikazi[$_POST['prikaz'] ?? '']) ? $_POST['prikaz'] : 'popis';
        $redanje = isset($redanja[$_POST['redanje'] ?? '']) ? $_POST['redanje'] : 'naslov';
        $opis = sanitiziraj_html((string) ($_POST['opis'] ?? ''));
        $obj = isset($_POST['objavljeno']) ? 1 : 0;
        if ($naziv === '') {
            flash('greska', 'Upišite naziv kategorije.');
            header('Location: /admin/kategorije.php' . ($id ? '?uredi=' . $id : ''));
            exit;
        }
        $slug = slug_jedinstven(slugify(trim((string) ($_POST['slug'] ?? '')) ?: $naziv), $id, 'kategorije');
        if ($k) {
            $stariUrl = $k['url'];
            $db->prepare('UPDATE kategorije SET roditelj=?, naziv=?, slug=?, opis=?, prikaz=?, redanje=?, objavljeno=? WHERE id=?')->execute(array($rod, $naziv, $slug, $opis, $prikaz, $redanje, $obj, $id));
            $novi = kategorije(true)[$id]['url'];
            if ($novi !== $stariUrl) spremi_preusmjerenje(ltrim($stariUrl, '/'), $novi); // zapisi unutra se preusmjeravaju po slugu
            flash('ok', 'Kategorija „' . $naziv . '” je spremljena.');
        } else {
            $st = $db->prepare('SELECT COALESCE(MAX(redoslijed), 0) + 10 FROM kategorije WHERE ' . null_jednako('roditelj'));
            $st->execute(array($rod));
            $db->prepare('INSERT INTO kategorije (roditelj, naziv, slug, opis, prikaz, redanje, redoslijed, objavljeno) VALUES (?,?,?,?,?,?,?,?)')->execute(array($rod, $naziv, $slug, $opis, $prikaz, $redanje, (int) $st->fetchColumn(), $obj));
            flash('ok', 'Kategorija „' . $naziv . '” je dodana. U izbornik je dodajte pod „Izbornik”.');
        }
    } elseif ($akcija === 'obrisi' && $k) {
        $n = (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE kategorija_id = ' . $id)->fetchColumn();
        if ($n || $k['djeca']) {
            flash('greska', '„' . $k['naziv'] . '” nije prazna (' . $n . ' zapisa' . ($k['djeca'] ? ', ' . count($k['djeca']) . ' podkategorija' : '') . '). Premjestite ili obrišite sadržaj pa pokušajte ponovno.');
        } else {
            $db->prepare('DELETE FROM kategorije WHERE id = ?')->execute(array($id));
            flash('ok', 'Kategorija „' . $k['naziv'] . '” je obrisana.');
        }
    } elseif (in_array($akcija, array('gore', 'dolje'), true) && $k) {
        $braca = $db->prepare('SELECT id FROM kategorije WHERE ' . null_jednako('roditelj') . ' ORDER BY redoslijed, naziv');
        $braca->execute(array($k['roditelj']));
        $ids = $braca->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search($id, array_map('intval', $ids));
        $j = $akcija === 'gore' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) { $t = $ids[$i]; $ids[$i] = $ids[$j]; $ids[$j] = $t; }
        $u = $db->prepare('UPDATE kategorije SET redoslijed = ? WHERE id = ?');
        foreach ($ids as $n => $x) $u->execute(array(($n + 1) * 10, $x));
    }
    header('Location: /admin/kategorije.php');
    exit;
}

$uredi = !empty($_GET['uredi']) ? kategorija((int) $_GET['uredi']) : null;
$u = $uredi ?: array('id' => 0, 'roditelj' => (int) ($_GET['pod'] ?? 0) ?: null, 'naziv' => '', 'slug' => '', 'opis' => '', 'prikaz' => 'popis', 'redanje' => 'naslov', 'objavljeno' => 1);
$broj = $db->query('SELECT kategorija_id, COUNT(*) FROM sadrzaj GROUP BY kategorija_id')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_vrh('Kategorije', 'kategorije');
$red = function ($k, $dub) use (&$red, $broj) {
    ?>
    <li class="a-iz<?= $dub ? ' pod' : '' ?>" style="--dub: <?= $dub ?>">
      <span class="a-iz-naslov"><a href="/admin/?kat=<?= (int) $k['id'] ?>"><?= e($k['naziv']) ?></a> <small><?= e($k['url']) ?> · <?= (int) ($broj[$k['id']] ?? 0) ?> zapisa<?= $k['objavljeno'] ? '' : ' · <b>skrivena</b>' ?></small></span>
      <span class="a-iz-gumbi">
        <form method="post"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button name="akcija" value="gore" title="Gore">▲</button><button name="akcija" value="dolje" title="Dolje">▼</button></form>
        <a href="?uredi=<?= (int) $k['id'] ?>">Uredi</a>
        <a href="?pod=<?= (int) $k['id'] ?>">+ Pod</a>
        <form method="post"><?= csrf_polje() ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><button name="akcija" value="obrisi" class="a-crveno" data-potvrdi="Obrisati kategoriju „<?= e($k['naziv']) ?>”? Moguće samo ako je prazna.">Obriši</button></form>
      </span>
    </li>
    <?php
    foreach ($k['djeca'] as $d) $red(kategorija($d), $dub + 1);
};
?>
<div class="a-glava"><h1>Kategorije</h1><a class="a-btn" href="/admin/kategorije.php">+ Nova kategorija</a></div>
<div class="a-stupci">
  <div class="a-glavni">
    <ul class="a-izbornik">
      <?php foreach (kategorije() as $k) if (!$k['roditelj']) $red($k, 0); ?>
    </ul>
    <p class="a-pomoc">Sav sadržaj (novosti, članice, divljač, dokumenti…) pripada nekoj kategoriji. Podkategorije se na webu prikazuju kao kartice iznad popisa. Kategorija se može obrisati samo kad je prazna.</p>
  </div>
  <aside class="a-strana">
    <form method="post" class="a-kutija-mala a-obrazac">
      <h2><?= $uredi ? 'Uredi: ' . e($uredi['naziv']) : 'Nova kategorija' ?></h2>
      <?= csrf_polje() ?>
      <input type="hidden" name="akcija" value="spremi">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <label>Naziv<input name="naziv" value="<?= e($u['naziv']) ?>" maxlength="120" required></label>
      <label>Nadređena kategorija
        <select name="roditelj"><option value="">— nijedna (glavna kategorija) —</option><?= kategorije_opcije($u['roditelj'], $u['id'] ?: null) ?></select>
      </label>
      <label>Prikaz na webu
        <select name="prikaz"><?php foreach ($prikazi as $kk => $v): ?><option value="<?= $kk ?>"<?= $u['prikaz'] === $kk ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Redoslijed zapisa
        <select name="redanje"><?php foreach ($redanja as $kk => $v): ?><option value="<?= $kk ?>"<?= $u['redanje'] === $kk ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label>Opis iznad popisa <small>(HTML; nije obavezno)</small><textarea name="opis" rows="7" class="a-kod-mali"><?= e($u['opis']) ?></textarea></label>
      <label>Adresa (slug) <small>prazno = iz naziva</small><input name="slug" value="<?= e($u['slug']) ?>" maxlength="90" pattern="[a-z0-9-]*"></label>
      <label class="a-kvacica"><input type="checkbox" name="objavljeno" value="1"<?= $u['objavljeno'] ? ' checked' : '' ?>> Vidljiva na webu</label>
      <button class="a-btn" type="submit"><?= $uredi ? 'Spremi' : 'Dodaj kategoriju' ?></button>
      <?php if ($uredi): ?><a class="a-link" href="/admin/kategorije.php">Odustani</a><?php endif; ?>
    </form>
  </aside>
</div>
<?php admin_dno(true); ?>
