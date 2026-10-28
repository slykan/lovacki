<?php
/** @var array $s — zapis u mreži; $prikaz = 'novosti' (slika + datum) ili 'popis' (naslov + sažetak) */
$prikaz = $prikaz ?? 'novosti';
$ks = !empty($s['kategorija_id']) ? kategorija($s['kategorija_id']) : null;
?>
<?php if ($prikaz === 'novosti'): ?>
<article class="kartica">
  <a class="kartica-slika" href="<?= e(url_za($s)) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($s['slika']): ?><img src="<?= e(slika_url($s)) ?>" alt="" loading="lazy"><?php else: ?><span class="bez-slike"><img src="/assets/img/logo-160.png" alt="" width="72" height="68" loading="lazy"></span><?php endif; ?>
  </a>
  <p class="meta"><time datetime="<?= e(date('Y-m-d', strtotime($s['datum']))) ?>"><?= e(datum_hr($s['datum'])) ?></time><?php if ($ks && $ks['slug'] !== 'novosti'): ?> · <?= e($ks['naziv']) ?><?php endif; ?></p>
  <h3><a href="<?= e(url_za($s)) ?>"><?= e($s['naslov']) ?></a></h3>
</article>
<?php else: ?>
<article class="stavka">
  <?php if ($s['slika']): ?><a class="stavka-slika" href="<?= e(url_za($s)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(slika_url($s)) ?>" alt="" loading="lazy"></a><?php endif; ?>
  <div>
    <h3><a href="<?= e(url_za($s)) ?>"><?= e($s['naslov']) ?></a></h3>
    <?php if (!empty($s['sazetak'])): ?><p><?= e(mb_strimwidth($s['sazetak'], 0, 150, '…')) ?></p><?php endif; ?>
  </div>
</article>
<?php endif; ?>
