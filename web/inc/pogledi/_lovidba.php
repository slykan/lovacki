<?php
/** Stranica „Lovidba”: tekst za tekući mjesec iz „Lovište kroz godinu” + svi mjeseci. */
$m = (int) date('n');
$lv = lovidba_za_mjesec($m);
$kal = null;
foreach (kategorije() as $x) if ($x['slug'] === 'lovni-kalendar') $kal = $x;
$svi = $kal ? db()->query('SELECT kategorija_id, slug, naslov, redoslijed FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id = ' . (int) $kal['id'] . ' ORDER BY redoslijed')->fetchAll() : array();
?>
<?php if ($lv): list($l, $tocno) = $lv; ?>
<section class="lovidba" aria-labelledby="lovidba-naslov">
  <p class="lovidba-mjesec"><span><?= e(ucfirst(mjesec_naziv($m))) ?></span> <?= date('Y') ?>.</p>
  <h2 id="lovidba-naslov"><?= e($l['naslov']) ?></h2>
  <?php if (!$tocno): ?><p class="mala">Za ovaj mjesec još nema posebnog teksta, pa prikazujemo prethodni.</p><?php endif; ?>
  <div class="tekst"><?= $l['tekst'] ?></div>
</section>
<?php endif; ?>
<?php if ($svi): ?>
<nav class="mjeseci" aria-label="Lovište kroz godinu">
  <h2>Lovište kroz godinu</h2>
  <ul>
    <?php foreach ($svi as $s): ?><li><a href="<?= e(url_za($s)) ?>"<?= (int) $s['redoslijed'] === $m ? ' aria-current="true"' : '' ?>><?= e(ucfirst(mjesec_naziv($s['redoslijed']))) ?></a></li><?php endforeach; ?>
  </ul>
</nav>
<?php endif; ?>
