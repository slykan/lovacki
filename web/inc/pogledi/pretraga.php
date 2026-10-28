<?php
$q = trim((string) ($_GET['q'] ?? ''));
$zapisi = array();
if (mb_strlen($q) >= 2) {
    $skrivene = array_keys(array_filter(kategorije(), function ($k) { return !$k['vidljiva']; }));
    $st = db()->prepare("SELECT * FROM sadrzaj WHERE objavljeno=1" . ($skrivene ? ' AND (kategorija_id IS NULL OR kategorija_id NOT IN (' . implode(',', $skrivene) . '))' : '') . " AND (naslov LIKE :q1 ESCAPE '!' OR tekst LIKE :q2 ESCAPE '!') ORDER BY (naslov LIKE :q3 ESCAPE '!') DESC, datum DESC LIMIT 60");
    $u = like_uzorak($q);
    $st->execute(array(':q1' => $u, ':q2' => $u, ':q3' => $u));
    $zapisi = $st->fetchAll();
}
$stranica['naslov'] = 'Pretraga' . ($q !== '' ? ': ' . $q : '');
$stranica['canonical'] = SITE_URL . '/pretraga';
$stranica['noindex'] = true;
require __DIR__ . '/_vrh.php';
?>
<header class="stranica-vrh">
  <div class="wrap">
    <p class="mrvice"><a href="/">Naslovna</a> <span aria-hidden="true">›</span> <span>Pretraga</span></p>
    <h1>Pretraga</h1>
    <form class="trazi-velika" action="/pretraga" role="search">
      <label class="sr" for="q">Pojam</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Upišite pojam, npr. Hubert, Bizovac, srna…" autofocus>
      <button class="btn btn-glavni" type="submit">Traži</button>
    </form>
  </div>
</header>
<section class="sec sec-prvi">
  <div class="wrap">
    <?php if ($q !== ''): ?>
      <p class="mala"><?= count($zapisi) ?> rezultata<?= count($zapisi) === 60 ? ' (prikazano prvih 60)' : '' ?></p>
      <div class="stavke"><?php foreach ($zapisi as $s): $ks = $s['kategorija_id'] ? kategorija($s['kategorija_id']) : null; ?>
        <article class="stavka">
          <?php if ($s['slika']): ?><a class="stavka-slika" href="<?= e(url_za($s)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(slika_url($s)) ?>" alt="" loading="lazy"></a><?php endif; ?>
          <div>
            <p class="meta"><?= e($ks ? $ks['naziv'] : 'Stranica') ?><?= $ks && $ks['prikaz'] === 'novosti' ? ' · ' . e(datum_hr($s['datum'])) : '' ?></p>
            <h3><a href="<?= e(url_za($s)) ?>"><?= e($s['naslov']) ?></a></h3>
            <p><?= e(mb_strimwidth($s['sazetak'], 0, 160, '…')) ?></p>
          </div>
        </article>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/_dno.php'; ?>
