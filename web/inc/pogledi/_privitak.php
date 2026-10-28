<?php
/** @var array $pr — jedan privitak kao gumb za preuzimanje; $naslovPr (nije obavezno) zamjenjuje naziv */
$ext = strtolower(pathinfo($pr['datoteka'], PATHINFO_EXTENSION));
$naziv = $naslovPr ?? $pr['naziv'];
unset($naslovPr);
?>
<a class="privitak vrsta-<?= e($ext) ?>" href="/<?= e($pr['datoteka']) ?>" download="<?= e(slugify($pr['naziv']) . '.' . $ext) ?>">
  <span class="privitak-ikona" aria-hidden="true"><?= e(strtoupper($ext)) ?></span>
  <span class="privitak-tekst"><b><?= e($naziv) ?></b><small><?= e(strtoupper($ext)) ?><?= $pr['velicina'] ? ' · ' . e(velicina_hr($pr['velicina'])) : '' ?></small></span>
  <span class="privitak-gumb">Preuzmi <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg></span>
</a>
