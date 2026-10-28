<?php
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();
$db = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$z = null;
if ($id) {
    $st = $db->prepare('SELECT * FROM sadrzaj WHERE id = ?');
    $st->execute(array($id));
    $z = $st->fetch();
    if (!$z) {
        flash('greska', 'Zapis ne postoji.');
        header('Location: /admin/');
        exit;
    }
}
$greske = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();

    if (isset($_POST['obrisi']) && $z) {
        obrisi_sliku($z['slika']);
        foreach (privitci_za($z['id']) as $pr) obrisi_privitak($pr['datoteka']);
        $db->prepare('DELETE FROM privitci WHERE sadrzaj_id = ?')->execute(array($z['id']));
        foreach (galerija_za($z['id']) as $g) obrisi_sliku($g['slika']);
        $db->prepare('DELETE FROM galerija WHERE sadrzaj_id = ?')->execute(array($z['id']));
        $db->prepare('DELETE FROM sadrzaj WHERE id = ?')->execute(array($z['id']));
        flash('ok', '„' . $z['naslov'] . '” je obrisano.');
        header('Location: /admin/?kat=' . ($z['kategorija_id'] ?: 'stranice'));
        exit;
    }

    $kat = kategorija((int) ($_POST['kategorija_id'] ?? 0)) ? (int) $_POST['kategorija_id'] : null;
    $redoslijed = (int) ($_POST['redoslijed'] ?? 0);
    $naslov = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['naslov'] ?? '')));
    $slugUnos = trim((string) ($_POST['slug'] ?? ''));
    $datum = (string) ($_POST['datum'] ?? '');
    $tekst = sanitiziraj_html((string) ($_POST['tekst'] ?? ''));
    $sazetak = trim((string) ($_POST['sazetak'] ?? ''));
    $objavljeno = isset($_POST['objavljeno']) ? 1 : 0;

    if ($naslov === '') $greske[] = 'Upišite naslov.';
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datum) ?: DateTime::createFromFormat('Y-m-d', $datum);
    if (!$dt) $greske[] = 'Upišite ispravan datum.';
    if (trim(strip_tags($tekst, '<img><iframe>')) === '') $greske[] = 'Upišite tekst.';

    $slika = $z['slika'] ?? null;
    if (!empty($_FILES['slika']['tmp_name']) && is_uploaded_file($_FILES['slika']['tmp_name'])) {
        if ($_FILES['slika']['size'] > 15 * 1024 * 1024) {
            $greske[] = 'Slika je prevelika (najviše 15 MB).';
        } else {
            $nova = spremi_sliku($_FILES['slika']['tmp_name']);
            if (!$nova) $greske[] = 'Datoteka nije slika (JPG, PNG, WEBP).';
            elseif (!$greske) { if ($slika) obrisi_sliku($slika); $slika = $nova; }
            else obrisi_sliku($nova);
        }
    } elseif (isset($_POST['makni_sliku']) && $slika) {
        obrisi_sliku($slika);
        $slika = null;
    }

    if (!$greske) {
        $slug = slug_jedinstven(slugify($slugUnos !== '' ? $slugUnos : $naslov), $z['id'] ?? 0);
        $vals = array($kat, $naslov, $slug, $dt->format('Y-m-d H:i:s'), $tekst, $sazetak !== '' ? $sazetak : sazetak_iz($tekst), $slika, $objavljeno, $redoslijed);
        if ($z) {
            $vals[] = $z['id'];
            $db->prepare("UPDATE sadrzaj SET kategorija_id=?, naslov=?, slug=?, datum=?, tekst=?, sazetak=?, slika=?, objavljeno=?, redoslijed=?, izmijenjeno=? WHERE id=?")->execute(array_merge(array_slice($vals, 0, -1), array(sada(), end($vals))));
            $id = $z['id'];
            // promijenjena adresa → staru preusmjeri na novu
            if (url_za($z) !== url_za(array('kategorija_id' => $kat, 'slug' => $slug))) {
                spremi_preusmjerenje(ltrim(url_za($z), '/'), url_za(array('kategorija_id' => $kat, 'slug' => $slug)));
            }
        } else {
            $db->prepare('INSERT INTO sadrzaj (kategorija_id, naslov, slug, datum, tekst, sazetak, slika, objavljeno, redoslijed, izradeno, izmijenjeno) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($vals, array(sada(), sada())));
            $id = (int) $db->lastInsertId();
        }
        spremi_galeriju($id, (array) ($_POST['galerija'] ?? array()), (array) ($_POST['galerija_obrisi'] ?? array()));
        // privitci: preimenovanje, redoslijed, brisanje, novi
        $porukePr = array();
        foreach (privitci_za($id) as $pr) {
            if (!empty($_POST['pr_obrisi'][$pr['id']])) {
                obrisi_privitak($pr['datoteka']);
                $db->prepare('DELETE FROM privitci WHERE id = ?')->execute(array($pr['id']));
                continue;
            }
            $nz = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['pr_naziv'][$pr['id']] ?? $pr['naziv'])));
            $db->prepare('UPDATE privitci SET naziv = ?, redoslijed = ? WHERE id = ?')->execute(array($nz !== '' ? mb_substr($nz, 0, 200) : $pr['naziv'], (int) ($_POST['pr_red'][$pr['id']] ?? $pr['redoslijed']), $pr['id']));
        }
        if (!empty($_FILES['privitci']['name']) && is_array($_FILES['privitci']['name'])) {
            $red = count(privitci_za($id)) * 10;
            foreach ($_FILES['privitci']['name'] as $i => $ime) {
                if ($ime === '' || $_FILES['privitci']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES['privitci']['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['privitci']['tmp_name'][$i])) {
                    $porukePr[] = '„' . $ime . '” nije učitan (prevelik? najviše ' . ini_get('upload_max_filesize') . ').';
                    continue;
                }
                $p = spremi_privitak($_FILES['privitci']['tmp_name'][$i], $ime);
                if (is_string($p)) { $porukePr[] = $p; continue; }
                $red += 10;
                $db->prepare('INSERT INTO privitci (sadrzaj_id, naziv, datoteka, velicina, redoslijed) VALUES (?,?,?,?,?)')->execute(array($id, $p[1], $p[0], $p[2], $red));
            }
        }
        flash($porukePr ? 'greska' : 'ok', 'Spremljeno. ' . (!$kat && !$z ? 'Stranicu možete dodati u izbornik pod „Izbornik”. ' : '') . implode(' ', $porukePr));
        header('Location: /admin/uredi.php?id=' . $id);
        exit;
    }
    // ponovno prikaži unos
    $z = array_merge($z ?: array('id' => 0), array('kategorija_id' => $kat, 'naslov' => $naslov, 'slug' => $slugUnos, 'datum' => $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'), 'tekst' => $tekst, 'sazetak' => $sazetak, 'slika' => $slika, 'objavljeno' => $objavljeno, 'redoslijed' => $redoslijed));
}

$z = $z ?: array('id' => 0, 'kategorija_id' => kategorija((int) ($_GET['kat'] ?? 0)) ? (int) $_GET['kat'] : null, 'naslov' => '', 'slug' => '', 'datum' => date('Y-m-d H:i:s'), 'tekst' => '', 'sazetak' => '', 'slika' => null, 'objavljeno' => 1, 'redoslijed' => 0);
$zk = $z['kategorija_id'] ? kategorija($z['kategorija_id']) : null;
$naslovStr = !empty($z['id']) ? 'Uredi: ' . $z['naslov'] : ($zk ? 'Novo u „' . $zk['naziv'] . '”' : 'Nova stranica');
admin_vrh($naslovStr, empty($z['id']) ? 'nova' : 'sadrzaj');
?>
<div class="a-glava">
  <h1><?= e($naslovStr) ?></h1>
  <?php if (!empty($z['id'])): ?><a class="a-link" href="<?= e(url_za($z)) ?>" target="_blank" rel="noopener">Pogledaj na webu ↗</a><?php endif; ?>
</div>
<?php foreach ($greske as $g): ?><p class="a-flash greska"><?= e($g) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="a-obrazac" data-uredi>
  <?= csrf_polje() ?>
  <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
  <div class="a-stupci">
    <div class="a-glavni">
      <label>Naslov<input name="naslov" value="<?= e($z['naslov']) ?>" required maxlength="200" data-naslov></label>

      <div class="a-polje">
        <span class="a-oznaka-polja">Tekst</span>
        <div class="a-editor" data-editor>
          <div class="a-traka" role="toolbar" aria-label="Oblikovanje">
            <button type="button" data-cmd="bold" title="Podebljano (Ctrl+B)"><b>B</b></button>
            <button type="button" data-cmd="italic" title="Kurziv (Ctrl+I)"><i>I</i></button>
            <button type="button" data-cmd="underline" title="Podcrtano"><u>U</u></button>
            <span class="a-sep"></span>
            <button type="button" data-blok="h2" title="Podnaslov">H2</button>
            <button type="button" data-blok="h3" title="Manji podnaslov">H3</button>
            <button type="button" data-blok="p" title="Običan odlomak">¶</button>
            <button type="button" data-blok="blockquote" title="Citat">❝</button>
            <span class="a-sep"></span>
            <button type="button" data-cmd="insertUnorderedList" title="Popis">• Popis</button>
            <button type="button" data-cmd="insertOrderedList" title="Numerirani popis">1. Popis</button>
            <span class="a-sep"></span>
            <button type="button" data-link title="Poveznica">🔗 Link</button>
            <button type="button" data-slika title="Umetni sliku">🖼 Slika</button>
            <button type="button" data-video title="YouTube video">▶ Video</button>
            <button type="button" data-cmd="removeFormat" title="Očisti oblikovanje">✕ Oblik</button>
            <span class="a-sep"></span>
            <button type="button" data-html class="a-html-btn" title="Uredi HTML kod">&lt;/&gt; HTML</button>
          </div>
          <div class="a-sadrzaj tekst" contenteditable="true" data-vizualno><?= $z['tekst'] ?></div>
          <textarea name="tekst" class="a-kod" data-kod spellcheck="false" hidden><?= e($z['tekst']) ?></textarea>
          <input type="file" accept="image/*" hidden data-slika-datoteka>
        </div>
        <small class="a-pomoc">Zalijepljeni tekst s Facebooka ili Worda automatski se očisti pri spremanju. Gumb „HTML” prikazuje i uređuje čisti HTML.</small>
      </div>

      <label>Sažetak <small>(nije obavezno — za popise, Google i dijeljenje; ako je prazno, uzima se početak teksta)</small><textarea name="sazetak" rows="2" maxlength="300"><?= e($z['sazetak']) ?></textarea></label>
    </div>

    <aside class="a-strana">
      <div class="a-kutija-mala">
        <label>Kategorija
          <select name="kategorija_id">
            <?= kategorije_opcije($z['kategorija_id']) ?>
            <option value=""<?= !$z['kategorija_id'] ? ' selected' : '' ?>>— Stranica bez kategorije (O nama, Kontakt…) —</option>
          </select>
        </label>
        <label>Datum i vrijeme<input type="datetime-local" name="datum" value="<?= e(date('Y-m-d\TH:i', strtotime($z['datum']))) ?>" required></label>
        <label>Redni broj <small>(za kategorije poredane „redom”; manji ide prvi)</small><input type="number" name="redoslijed" value="<?= (int) ($z['redoslijed'] ?? 0) ?>" step="1"></label>
        <label class="a-kvacica"><input type="checkbox" name="objavljeno" value="1"<?= $z['objavljeno'] ? ' checked' : '' ?>> Objavljeno (vidljivo na webu)</label>
        <label>Adresa (slug) <small>prazno = iz naslova</small><input name="slug" value="<?= e($z['slug']) ?>" maxlength="90" pattern="[a-z0-9-]*" data-slug></label>
        <button class="a-btn a-btn-veliki" type="submit">Spremi</button>
      </div>

      <div class="a-kutija-mala">
        <span class="a-oznaka-polja">Glavna slika</span>
        <?php if ($z['slika']): ?>
          <img class="a-pregled" src="<?= e(slika_url($z)) ?>" alt="" data-pregled>
          <label class="a-kvacica"><input type="checkbox" name="makni_sliku" value="1"> Makni sliku</label>
        <?php else: ?>
          <img class="a-pregled" alt="" data-pregled hidden>
        <?php endif; ?>
        <input type="file" name="slika" accept="image/jpeg,image/png,image/webp" data-glavna-slika>
        <small class="a-pomoc">Smanjuje se automatski na 1600 px.</small>
      </div>

      <div class="a-kutija-mala" data-galerija>
        <span class="a-oznaka-polja">Galerija <small>(slike ispod teksta)</small></span>
        <div class="a-galerija" data-galerija-popis>
          <?php foreach (!empty($z['id']) ? galerija_za($z['id']) : array() as $g): ?>
          <div class="a-gal-slika">
            <img src="<?= e(slika_url($g)) ?>" alt="">
            <input type="hidden" name="galerija[]" value="<?= (int) $g['id'] ?>">
            <span class="a-gal-gumbi"><button type="button" data-gal-lijevo title="Pomakni ranije">‹</button><button type="button" data-gal-desno title="Pomakni kasnije">›</button><button type="button" data-gal-makni title="Makni iz galerije">×</button></span>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-galerija-datoteke>
        <small class="a-pomoc" data-galerija-stanje>Možete odabrati više slika odjednom. Smanjuju se odmah pri učitavanju; redoslijed i brisanje vrijede nakon „Spremi”.</small>
      </div>

      <div class="a-kutija-mala">
        <span class="a-oznaka-polja">Privitci <small>(dokumenti za preuzimanje)</small></span>
        <?php foreach (!empty($z['id']) ? privitci_za($z['id']) : array() as $pr): ?>
        <div class="a-privitak">
          <input name="pr_naziv[<?= (int) $pr['id'] ?>]" value="<?= e($pr['naziv']) ?>" maxlength="200" aria-label="Naziv privitka">
          <input type="hidden" name="pr_red[<?= (int) $pr['id'] ?>]" value="<?= (int) $pr['redoslijed'] ?>">
          <small><a href="/<?= e($pr['datoteka']) ?>" target="_blank" rel="noopener"><?= e(strtoupper(pathinfo($pr['datoteka'], PATHINFO_EXTENSION))) ?> · <?= e(velicina_hr($pr['velicina'])) ?></a>
            <label class="a-kvacica a-mala-kv"><input type="checkbox" name="pr_obrisi[<?= (int) $pr['id'] ?>]" value="1"> makni</label></small>
        </div>
        <?php endforeach; ?>
        <input type="file" name="privitci[]" multiple accept=".<?= e(implode(',.', privitak_vrste())) ?>">
        <small class="a-pomoc">PDF, Word, Excel… (najviše <?= e(ini_get('upload_max_filesize')) ?> po datoteci). Na webu se ispod teksta prikazuje gumb „Preuzmi”. Naziv možete promijeniti nakon učitavanja.</small>
      </div>

      <?php if (!empty($z['id'])): ?>
      <div class="a-kutija-mala a-opasno">
        <button class="a-btn a-btn-crveni" type="submit" name="obrisi" value="1" data-potvrdi="Sigurno obrisati „<?= e($z['naslov']) ?>”? Ovo se ne može poništiti." formnovalidate>Obriši</button>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</form>
<?php admin_dno(true); ?>
