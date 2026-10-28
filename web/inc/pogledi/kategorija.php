<?php
/**
 * Popis kategorije: opis, podkategorije (kartice s brojem zapisa) i zapisi prema načinu prikaza:
 *   novosti   — mreža sa slikom i datumom, godine i stranice
 *   popis     — kartice s naslovom i sažetkom (članice, divljač, lov)
 *   dokumenti — svi privitci zapisa kao gumbi za preuzimanje
 * @var array $kat
 */
$db = db();
$djeca = array_values(array_filter(array_map('kategorija', $kat['djeca']), function ($k) { return $k['vidljiva']; }));
$god = isset($_GET['god']) && preg_match('/^\d{4}$/', $_GET['god']) ? $_GET['god'] : null;
$str = max(1, (int) ($_GET['str'] ?? 1));
$uvjet = 'kategorija_id = ' . (int) $kat['id'] . ' AND objavljeno = 1';
if ($kat['prikaz'] === 'novosti' && $god) $uvjet .= ' AND datum >= ' . $db->quote($god . '-01-01') . ' AND datum < ' . $db->quote(((int) $god + 1) . '-01-01');
$ukupno = (int) $db->query("SELECT COUNT(*) FROM sadrzaj WHERE $uvjet")->fetchColumn();
$poStr = $kat['prikaz'] === 'novosti' ? PO_STRANICI : 200;
$stranica_n = max(1, (int) ceil($ukupno / $poStr));
$str = min($str, $stranica_n);
$zapisi = $db->query("SELECT * FROM sadrzaj WHERE $uvjet ORDER BY " . redanje_sql($kat) . " LIMIT $poStr OFFSET " . (($str - 1) * $poStr))->fetchAll();
$godine = $kat['prikaz'] === 'novosti' ? $db->query("SELECT SUBSTR(datum, 1, 4) g, COUNT(*) n FROM sadrzaj WHERE kategorija_id = " . (int) $kat['id'] . " AND objavljeno = 1 GROUP BY g ORDER BY g DESC")->fetchAll() : array();
$broj = function ($k) use ($db) { return (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id IN (' . implode(',', kategorija_grana($k['id'])) . ')')->fetchColumn(); };

$stranica['naslov'] = $kat['naziv'] . ($god ? ' ' . $god . '.' : '') . ($str > 1 ? ' — stranica ' . $str : '');
$stranica['opis'] = trim(strip_tags($kat['opis'])) !== '' ? sazetak_iz($kat['opis'], 160) : $kat['naziv'] . ' — ' . $GLOBALS['ORG']['naziv'] . '.';
$q = array_filter(array('god' => $god, 'str' => $str > 1 ? $str : null));
$stranica['canonical'] = SITE_URL . $kat['url'] . ($q ? '?' . http_build_query($q) : '');
$stranica['mrvice'] = array();
foreach ($kat['put'] as $pid) { $p = kategorija($pid); $stranica['mrvice'][] = array($p['naziv'], SITE_URL . $p['url']); }
$link = function ($n) use ($god, $kat) { $q = array_filter(array('god' => $god, 'str' => $n > 1 ? $n : null)); return $kat['url'] . ($q ? '?' . http_build_query($q) : ''); };
require __DIR__ . '/_vrh.php';
?>
<header class="stranica-vrh">
  <div class="wrap">
    <p class="mrvice"><a href="/">Naslovna</a><?php foreach ($kat['put'] as $pid): $p = kategorija($pid); ?> <span aria-hidden="true">›</span> <?php if ((int) $pid === (int) $kat['id']): ?><span><?= e($p['naziv']) ?></span><?php else: ?><a href="<?= e($p['url']) ?>"><?= e($p['naziv']) ?></a><?php endif; endforeach; ?></p>
    <h1><?= e($kat['naziv']) ?><?= $god ? ' <span>' . e($god) . '.</span>' : '' ?></h1>
  </div>
</header>

<section class="sec sec-prvi">
  <div class="wrap">
    <?php if (trim(strip_tags($kat['opis'], '<img><table>')) !== '' && $str === 1 && !$god): ?>
    <div class="tekst kat-opis"><?= $kat['opis'] ?></div>
    <?php endif; ?>

    <?php if ($djeca): ?>
    <div class="podkategorije">
      <?php foreach ($djeca as $d): $n = $broj($d); ?>
      <a class="podkategorija" href="<?= e($d['url']) ?>">
        <b><?= e($d['naziv']) ?></b>
        <span><?= $n ?> <?= $kat['slug'] === 'clanice-saveza' ? ($n % 10 === 1 && $n % 100 !== 11 ? 'članica' : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) ? 'članice' : 'članica')) : ($n === 1 ? 'zapis' : 'zapisa') ?></span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($godine && count($godine) > 1): ?>
    <nav class="godine" aria-label="Godine">
      <a href="<?= e($kat['url']) ?>"<?= !$god ? ' aria-current="page"' : '' ?>>Sve</a>
      <?php foreach ($godine as $g): ?><a href="<?= e($kat['url']) ?>?god=<?= e($g['g']) ?>"<?= $god === $g['g'] ? ' aria-current="page"' : '' ?>><?= e($g['g']) ?> <small><?= (int) $g['n'] ?></small></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($zapisi && $kat['prikaz'] === 'dokumenti'): ?>
      <div class="privitci privitci-popis">
      <?php foreach ($zapisi as $s): $prv = privitci_za($s['id']); ?>
        <?php if (!$prv): ?>
          <a class="privitak" href="<?= e(url_za($s)) ?>"><span class="privitak-ikona" aria-hidden="true">TXT</span><span class="privitak-tekst"><b><?= e($s['naslov']) ?></b><small>Tekst na stranici</small></span><span class="privitak-gumb">Otvori</span></a>
        <?php endif; ?>
        <?php foreach ($prv as $pr) { $naslovPr = count($prv) > 1 ? $s['naslov'] . ' — ' . $pr['naziv'] : $s['naslov']; require __DIR__ . '/_privitak.php'; } ?>
      <?php endforeach; ?>
      </div>
    <?php elseif ($zapisi): ?>
      <?php if ($djeca): ?><h2 class="podnaslov">Ostalo u kategoriji</h2><?php endif; ?>
      <div class="<?= $kat['prikaz'] === 'novosti' ? 'vijesti-mreza' : 'stavke' ?>">
        <?php $prikaz = $kat['prikaz']; foreach ($zapisi as $s) require __DIR__ . '/_kartica.php'; ?>
      </div>
    <?php elseif (!$djeca): ?>
      <p>U ovoj kategoriji još nema objava.</p>
    <?php endif; ?>

    <?php if ($stranica_n > 1): ?>
    <nav class="stranice" aria-label="Stranice">
      <?php if ($str > 1): ?><a href="<?= e($link($str - 1)) ?>" rel="prev">‹ Novije</a><?php endif; ?>
      <?php for ($i = 1; $i <= $stranica_n; $i++):
          if ($i !== 1 && $i !== $stranica_n && abs($i - $str) > 2) { if (abs($i - $str) === 3) echo '<span>…</span>'; continue; } ?>
        <a href="<?= e($link($i)) ?>"<?= $i === $str ? ' aria-current="page"' : '' ?>><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($str < $stranica_n): ?><a href="<?= e($link($str + 1)) ?>" rel="next">Starije ›</a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/_dno.php'; ?>
