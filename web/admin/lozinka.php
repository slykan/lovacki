<?php
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();
$greska = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    $st = db()->prepare('SELECT * FROM korisnici WHERE korisnik = ?');
    $st->execute(array($_SESSION['korisnik']));
    $u = $st->fetch();
    $stara = (string) ($_POST['stara'] ?? '');
    $nova = (string) ($_POST['nova'] ?? '');
    if (!$u || !password_verify($stara, $u['hash'])) {
        $greska = 'Trenutna lozinka nije točna.';
    } elseif (mb_strlen($nova) < 10) {
        $greska = 'Nova lozinka mora imati barem 10 znakova.';
    } elseif ($nova !== (string) ($_POST['nova2'] ?? '')) {
        $greska = 'Nove lozinke se ne podudaraju.';
    } else {
        db()->prepare('UPDATE korisnici SET hash = ? WHERE id = ?')->execute(array(password_hash($nova, PASSWORD_DEFAULT), $u['id']));
        session_regenerate_id(true);
        flash('ok', 'Lozinka je promijenjena.');
        header('Location: /admin/lozinka.php');
        exit;
    }
}
admin_vrh('Promjena lozinke', 'lozinka');
?>
<h1>Promjena lozinke</h1>
<?php if ($greska): ?><p class="a-flash greska"><?= e($greska) ?></p><?php endif; ?>
<form method="post" class="a-obrazac a-uski">
  <?= csrf_polje() ?>
  <label>Trenutna lozinka<input type="password" name="stara" required autocomplete="current-password"></label>
  <label>Nova lozinka <small>(barem 10 znakova)</small><input type="password" name="nova" required minlength="10" autocomplete="new-password"></label>
  <label>Ponovi novu lozinku<input type="password" name="nova2" required minlength="10" autocomplete="new-password"></label>
  <button class="a-btn" type="submit">Promijeni lozinku</button>
</form>
<?php admin_dno(); ?>
