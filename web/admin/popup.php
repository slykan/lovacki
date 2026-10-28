<?php
/** Skočni prozor (popup) na webu: uključi/isključi, slika, poveznica, koliko često. */
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();

$greske = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    $slika = postavka('popup_slika');
    if (!empty($_FILES['slika']['tmp_name']) && is_uploaded_file($_FILES['slika']['tmp_name'])) {
        $nova = $_FILES['slika']['size'] <= 15 * 1024 * 1024 ? spremi_sliku($_FILES['slika']['tmp_name'], 1400, 0, 'popup') : null;
        if (!$nova) {
            $greske[] = 'Slika nije spremljena — mora biti JPG, PNG ili WEBP do 15 MB.';
        } else {
            obrisi_sliku($slika);
            $slika = $nova;
        }
    } elseif (isset($_POST['makni_sliku'])) {
        obrisi_sliku($slika);
        $slika = '';
    }
    $link = trim((string) ($_POST['link'] ?? ''));
    if ($link !== '' && !preg_match('#^(https?://|/)#i', $link)) {
        $greske[] = 'Poveznica mora počinjati s https:// ili / (npr. /pristupnica).';
        $link = postavka('popup_link');
    }
    $ukljucen = isset($_POST['ukljucen']) ? '1' : '0';
    if ($ukljucen === '1' && !$slika) {
        $greske[] = 'Popup se ne može uključiti bez slike.';
        $ukljucen = '0';
    }
    spremi_postavku('popup_slika', $slika);
    spremi_postavku('popup_link', $link);
    spremi_postavku('popup_opis', trim(mb_substr((string) ($_POST['opis'] ?? ''), 0, 200)));
    spremi_postavku('popup_ucestalost', in_array($_POST['ucestalost'] ?? '', array('jednom', 'posjet'), true) ? $_POST['ucestalost'] : 'jednom');
    spremi_postavku('popup_ukljucen', $ukljucen);
    spremi_postavku('popup_verzija', (string) time());
    if (!$greske) {
        flash('ok', $ukljucen === '1' ? 'Spremljeno — popup je uključen.' : 'Spremljeno — popup je isključen.');
        header('Location: /admin/popup.php');
        exit;
    }
}

$slika = postavka('popup_slika');
$uc = postavka('popup_ucestalost', 'jednom');
admin_vrh('Popup', 'popup');
?>
<div class="a-glava">
  <h1>Popup na webu</h1>
  <?php if (postavka('popup_ukljucen') === '1'): ?><span class="a-znacka a-znacka-ok">Uključen</span><?php else: ?><span class="a-znacka">Isključen</span><?php endif; ?>
</div>
<?php foreach ($greske as $g): ?><p class="a-flash greska"><?= e($g) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="a-obrazac a-popup-obrazac">
  <?= csrf_polje() ?>
  <div class="a-kutija-mala">
    <label class="a-kvacica a-prekidac"><input type="checkbox" name="ukljucen" value="1"<?= postavka('popup_ukljucen') === '1' ? ' checked' : '' ?>> Prikazuj popup posjetiteljima</label>

    <span class="a-oznaka-polja">Slika</span>
    <?php if ($slika): ?>
      <img class="a-pregled a-pregled-popup" src="/<?= e($slika) ?>" alt="" data-pregled>
      <label class="a-kvacica"><input type="checkbox" name="makni_sliku" value="1"> Makni sliku</label>
    <?php else: ?>
      <img class="a-pregled a-pregled-popup" alt="" data-pregled hidden>
    <?php endif; ?>
    <input type="file" name="slika" accept="image/jpeg,image/png,image/webp" data-glavna-slika>
    <small class="a-pomoc">Npr. plakat događanja ili poziv na izbore. Uspravni ili kvadratni format izgleda najbolje; smanjuje se na 1400 px.</small>

    <label>Poveznica <small>(nije obavezno — klik na sliku vodi ovdje)</small><input name="link" value="<?= e(postavka('popup_link')) ?>" placeholder="/novosti/... ili https://..." maxlength="500"></label>
    <label>Opis slike <small>(za čitače ekrana i Google)</small><input name="opis" value="<?= e(postavka('popup_opis')) ?>" maxlength="200" placeholder="npr. Poziv na tribinu 15. 11. u 18 h"></label>

    <fieldset class="a-izbor">
      <legend>Koliko često</legend>
      <label class="a-kvacica"><input type="radio" name="ucestalost" value="jednom"<?= $uc !== 'posjet' ? ' checked' : '' ?>> Jednom po posjetitelju (dok ne promijenite popup)</label>
      <label class="a-kvacica"><input type="radio" name="ucestalost" value="posjet"<?= $uc === 'posjet' ? ' checked' : '' ?>> Pri svakom posjetu (novo otvaranje preglednika)</label>
    </fieldset>

    <button class="a-btn a-btn-veliki" type="submit">Spremi</button>
  </div>
</form>
<?php admin_dno(true); ?>
