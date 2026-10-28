<?php
/** @var array $zapis — zapis u kategoriji ili samostalna stranica (kategorija_id NULL) */
$db = db();
$kz = $zapis['kategorija_id'] ? kategorija($zapis['kategorija_id']) : null;
$novost = $kz && $kz['prikaz'] === 'novosti';
$stranica['naslov'] = $zapis['naslov'] . ($kz && $kz['slug'] !== 'novosti' && $kz['roditelj'] ? ' — ' . $kz['naziv'] : '');
$stranica['opis'] = $zapis['sazetak'] ?: sazetak_iz($zapis['tekst'], 160);
$stranica['slika'] = $zapis['slika'] ? '/' . $zapis['slika'] : null;
$stranica['canonical'] = SITE_URL . url_za($zapis);
$stranica['mrvice'] = array();
foreach ($kz ? $kz['put'] : array() as $pid) { $p = kategorija($pid); $stranica['mrvice'][] = array($p['naziv'], SITE_URL . $p['url']); }
$stranica['mrvice'][] = array($zapis['naslov'], $stranica['canonical']);
if ($novost) {
    $stranica['tip'] = 'article';
    $stranica['objavljeno'] = date('c', strtotime($zapis['datum']));
    $stranica['ld'] = array('@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $zapis['naslov'],
        'datePublished' => date('c', strtotime($zapis['datum'])), 'dateModified' => date('c', strtotime($zapis['izmijenjeno'])),
        'image' => array(SITE_URL . ($stranica['slika'] ?: '/assets/img/og-lso.jpg')),
        'mainEntityOfPage' => $stranica['canonical'],
        'author' => array('@type' => 'Organization', 'name' => $GLOBALS['ORG']['naziv'], 'url' => SITE_URL . '/'),
        'publisher' => array('@id' => SITE_URL . '/#savez'));
    $st = $db->prepare('SELECT * FROM sadrzaj WHERE kategorija_id = ? AND objavljeno = 1 AND id <> ? AND datum <= ? ORDER BY datum DESC LIMIT 2');
    $st->execute(array($kz['id'], $zapis['id'], $zapis['datum']));
    $povezane = $st->fetchAll();
    $st = $db->prepare('SELECT * FROM sadrzaj WHERE kategorija_id = ? AND objavljeno = 1 AND id <> ? AND datum > ? ORDER BY datum ASC LIMIT ' . (4 - count($povezane)));
    $st->execute(array($kz['id'], $zapis['id'], $zapis['datum']));
    $povezane = array_merge(array_reverse($st->fetchAll()), $povezane);
    $st = $db->prepare('SELECT kategorija_id, slug, naslov FROM sadrzaj WHERE kategorija_id = ? AND objavljeno = 1 AND datum < ? ORDER BY datum DESC LIMIT 1');
    $st->execute(array($kz['id'], $zapis['datum'])); $starija = $st->fetch();
    $st = $db->prepare('SELECT kategorija_id, slug, naslov FROM sadrzaj WHERE kategorija_id = ? AND objavljeno = 1 AND datum > ? ORDER BY datum ASC LIMIT 1');
    $st->execute(array($kz['id'], $zapis['datum'])); $novija = $st->fetch();
} elseif ($kz) {
    // ostalo u istoj kategoriji (članice istog lovnog ureda, ista skupina divljači, mjeseci…)
    $braca = $db->query('SELECT id, kategorija_id, slug, naslov FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id = ' . (int) $kz['id'] . ' ORDER BY ' . redanje_sql($kz))->fetchAll();
}
require __DIR__ . '/_vrh.php';
?>
<article class="clanak<?= $novost ? ' je-novost' : '' ?>">
  <header class="stranica-vrh">
    <div class="wrap">
      <p class="mrvice"><a href="/">Naslovna</a><?php foreach ($kz ? $kz['put'] : array() as $pid): $p = kategorija($pid); ?> <span aria-hidden="true">›</span> <a href="<?= e($p['url']) ?>"><?= e($p['naziv']) ?></a><?php endforeach; ?></p>
      <?php if ($novost): ?><p class="meta"><time datetime="<?= e(date('c', strtotime($zapis['datum']))) ?>"><?= e(mjesec_hr($zapis['datum'])) ?></time></p><?php endif; ?>
      <h1><?= e($zapis['naslov']) ?></h1>
    </div>
  </header>
  <div class="wrap clanak-raspored<?= !empty($braca) && count($braca) > 1 ? ' s-bocnom' : '' ?>">
    <div class="clanak-tijelo">
      <?php if ($zapis['slika'] && $novost): ?>
      <figure class="clanak-slika"><a href="/<?= e($zapis['slika']) ?>" data-lightbox><img src="/<?= e($zapis['slika']) ?>" alt="<?= e($zapis['naslov']) ?>"></a></figure>
      <?php endif; ?>
      <div class="tekst" data-tekst>
        <?= $zapis['tekst'] ?>
      </div>

      <?php if ($zapis['slug'] === 'lovidba' && !$kz) require __DIR__ . '/_lovidba.php'; ?>
      <?php if ($zapis['slug'] === 'kontakt' && !$kz) require __DIR__ . '/_kontakt.php'; ?>

      <?php $galerija = galerija_za($zapis['id']); if ($galerija): $ng = count($galerija); ?>
      <section class="galerija" aria-label="Galerija fotografija" data-galerija>
        <header class="galerija-vrh">
          <h2>Galerija <small><?= $ng ?> <?= $ng % 10 >= 2 && $ng % 10 <= 4 && ($ng % 100 < 12 || $ng % 100 > 14) ? 'fotografije' : 'fotografija' ?></small></h2>
          <?php if ($ng > 1): ?><span class="galerija-strelice"><button type="button" data-gal-pret aria-label="Prethodne slike">‹</button><button type="button" data-gal-slj aria-label="Sljedeće slike">›</button></span><?php endif; ?>
        </header>
        <div class="galerija-traka" data-gal-traka>
          <?php foreach ($galerija as $k => $g): ?>
          <a class="galerija-slika" href="/<?= e($g['slika']) ?>" data-gal-slika><img src="<?= e(slika_url($g)) ?>" alt="<?= e($zapis['naslov']) ?> — fotografija <?= $k + 1 ?>" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php $privitci = privitci_za($zapis['id']); if ($privitci): ?>
      <section class="privitci" aria-label="Dokumenti za preuzimanje">
        <h2>Dokumenti za preuzimanje</h2>
        <?php foreach ($privitci as $pr) require __DIR__ . '/_privitak.php'; ?>
      </section>
      <?php endif; ?>

      <?php if ($novost): ?>
      <div class="podijeli">
        <span>Podijeli:</span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($stranica['canonical']) ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://wa.me/?text=<?= rawurlencode($zapis['naslov'] . ' ' . $stranica['canonical']) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <button type="button" data-kopiraj="<?= e($stranica['canonical']) ?>">Kopiraj link</button>
      </div>
      <nav class="prev-next" aria-label="Susjedne novosti">
        <?php if ($novija): ?><a href="<?= e(url_za($novija)) ?>"><small>‹ Novija</small><?= e($novija['naslov']) ?></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($starija): ?><a class="desno" href="<?= e(url_za($starija)) ?>"><small>Starija ›</small><?= e($starija['naslov']) ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>

    <?php if (!empty($braca) && count($braca) > 1): ?>
    <aside class="bocno" aria-label="<?= e($kz['naziv']) ?>">
      <h2><a href="<?= e($kz['url']) ?>"><?= e($kz['naziv']) ?></a></h2>
      <ul>
        <?php foreach ($braca as $b): ?><li><a href="<?= e(url_za($b)) ?>"<?= (int) $b['id'] === (int) $zapis['id'] ? ' aria-current="page"' : '' ?>><?= e($b['naslov']) ?></a></li><?php endforeach; ?>
      </ul>
    </aside>
    <?php endif; ?>
  </div>
</article>

<?php if (!empty($povezane)): ?>
<section class="sec sec-krem">
  <div class="wrap">
    <header class="sec-head"><h2>Iz istog razdoblja</h2><a class="vise" href="<?= e($kz['url']) ?>">Sve novosti <span aria-hidden="true">›</span></a></header>
    <div class="vijesti-mreza"><?php $prikaz = 'novosti'; foreach ($povezane as $s) require __DIR__ . '/_kartica.php'; ?></div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_dno.php'; ?>
