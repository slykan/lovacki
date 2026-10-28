<?php
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/_okvir.php';
trazi_prijavu();
$db = db();

// kat = id kategorije (s podkategorijama) ili 'stranice' (zapisi bez kategorije)
$kat = $_GET['kat'] ?? (string) admin_novosti_id();
$k = $kat === 'stranice' ? null : kategorija((int) $kat);
if ($kat !== 'stranice' && !$k) { $kat = 'stranice'; }
$q = trim((string) ($_GET['q'] ?? ''));
$str = max(1, (int) ($_GET['str'] ?? 1));
$po = 40;
$uvjet = $k ? 'kategorija_id IN (' . implode(',', kategorija_grana($k['id'])) . ')' : 'kategorija_id IS NULL';
$par = array();
if ($q !== '') {
    $uvjet .= " AND (naslov LIKE :q ESCAPE '!' OR tekst LIKE :q2 ESCAPE '!')";
    $par[':q'] = $par[':q2'] = like_uzorak($q);
}
$st = $db->prepare("SELECT COUNT(*) FROM sadrzaj WHERE $uvjet");
$st->execute($par);
$ukupno = (int) $st->fetchColumn();
$st = $db->prepare("SELECT id, kategorija_id, naslov, slug, datum, objavljeno, slika, pregledi, redoslijed FROM sadrzaj WHERE $uvjet ORDER BY " . ($k ? redanje_sql($k) : 'naslov') . " LIMIT $po OFFSET " . (($str - 1) * $po));
$st->execute($par);
$zapisi = $st->fetchAll();
$vrh = array_filter(kategorije(), function ($x) { return !$x['roditelj']; });
$sDatumom = $k && $k['prikaz'] === 'novosti';

admin_vrh('Sadržaj', 'sadrzaj');
?>
<div class="a-glava">
  <h1>Sadržaj</h1>
  <a class="a-btn" href="/admin/uredi.php?kat=<?= e($kat) ?>">+ Novo u „<?= e($k ? $k['naziv'] : 'Stranice') ?>”</a>
</div>
<div class="a-alati">
  <nav class="a-kartice">
    <?php foreach ($vrh as $v): $n = (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE kategorija_id IN (' . implode(',', kategorija_grana($v['id'])) . ')')->fetchColumn(); ?>
      <a href="?kat=<?= (int) $v['id'] ?>"<?= $k && in_array((int) $v['id'], $k['put'], true) ? ' aria-current="page"' : '' ?>><?= e($v['naziv']) ?> <small><?= $n ?></small></a>
    <?php endforeach; ?>
    <a href="?kat=stranice"<?= !$k ? ' aria-current="page"' : '' ?>>Stranice <small><?= (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE kategorija_id IS NULL')->fetchColumn() ?></small></a>
  </nav>
  <form class="a-trazi">
    <input type="hidden" name="kat" value="<?= e($kat) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Traži po naslovu ili tekstu…">
    <button class="a-btn a-btn-siva" type="submit">Traži</button>
  </form>
</div>
<?php if ($k): $korijen = kategorija($k['put'][0]); if ($korijen['djeca']): ?>
<nav class="a-kartice a-podkartice">
  <a href="?kat=<?= (int) $korijen['id'] ?>"<?= (int) $k['id'] === (int) $korijen['id'] ? ' aria-current="page"' : '' ?>>Sve</a>
  <?php foreach ($korijen['djeca'] as $d): $dk = kategorija($d); ?><a href="?kat=<?= (int) $d ?>"<?= (int) $k['id'] === (int) $d ? ' aria-current="page"' : '' ?>><?= e($dk['naziv']) ?></a><?php endforeach; ?>
</nav>
<?php endif; endif; ?>

<table class="a-tablica">
  <thead><tr><th></th><th>Naslov</th><th><?= $sDatumom ? 'Datum' : 'Kategorija / adresa' ?></th><th>Pregledi</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($zapisi as $z): $zk = $z['kategorija_id'] ? kategorija($z['kategorija_id']) : null; ?>
    <tr>
      <td class="a-sl"><?php if ($z['slika']): ?><img src="<?= e(slika_url($z)) ?>" alt="" loading="lazy"><?php endif; ?></td>
      <td><a href="/admin/uredi.php?id=<?= (int) $z['id'] ?>"><b><?= e($z['naslov']) ?></b></a></td>
      <td class="a-nowrap"><?= $sDatumom ? e(datum_hr($z['datum'], true)) : ($zk ? e($zk['naziv']) : '<code>' . e(url_za($z)) . '</code>') ?></td>
      <td><?= (int) $z['pregledi'] ?></td>
      <td><?= $z['objavljeno'] ? '<span class="a-oznaka ok">Objavljeno</span>' : '<span class="a-oznaka">Skica</span>' ?> <a class="a-link" href="<?= e(url_za($z)) ?>" target="_blank" rel="noopener">pogledaj ↗</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$zapisi): ?><tr><td colspan="5">Nema zapisa.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php $stranica_n = (int) ceil($ukupno / $po); if ($stranica_n > 1): ?>
<nav class="a-stranice">
  <?php for ($i = 1; $i <= $stranica_n; $i++): ?>
    <a href="?<?= e(http_build_query(array('kat' => $kat, 'q' => $q, 'str' => $i))) ?>"<?= $i === $str ? ' aria-current="page"' : '' ?>><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php admin_dno(); ?>
