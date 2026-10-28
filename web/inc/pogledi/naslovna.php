<?php
$db = db();
$O = $GLOBALS['ORG'];
$nov = null; $lu = array();
foreach (kategorije() as $k) {
    if ($k['slug'] === 'novosti') $nov = $k;
    if ($k['slug'] === 'clanice-saveza') foreach ($k['djeca'] as $d) $lu[] = kategorija($d);
}
$vijesti = $nov ? $db->query('SELECT * FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id = ' . (int) $nov['id'] . ' ORDER BY datum DESC LIMIT 7')->fetchAll() : array();
$glavna = array_shift($vijesti);
$clanica = $lu ? (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id IN (' . implode(',', array_map(function ($k) { return (int) $k['id']; }, $lu)) . ')')->fetchColumn() : 0;
$m = (int) date('n');
$lv = lovidba_za_mjesec($m);
$godina = (int) date('Y') - 1995;
$stranica['opis'] = 'Lovački savez Osječko-baranjske županije od 1995. objedinjuje lovačka društva s područja županije: novosti, članice po lovnim uredima, divljač, lovidba i dokumenti.';
require __DIR__ . '/_vrh.php';
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-tekst">
      <p class="hero-nad">Od 21. siječnja 1995. · <?= $godina ?> godina</p>
      <h1>Lovački savez <span>Osječko-baranjske županije</span></h1>
      <p class="hero-uvod">Objedinjujemo lovačka društva s područja županije, promičemo lovstvo te unapređujemo uzgoj, zaštitu, lov i korištenje divljači.</p>
      <p class="hero-gumbi">
        <a class="btn btn-zlato" href="/novosti">Novosti</a>
        <a class="btn btn-obrub-svijetli" href="/clanice-saveza">Članice Saveza</a>
      </p>
    </div>
    <div class="hero-pecat">
      <img src="/assets/img/logo-640.png" alt="Grb Lovačkog saveza Osječko-baranjske županije 1995–2025" width="420" height="400" fetchpriority="high">
    </div>
  </div>
  <div class="hero-krajolik" aria-hidden="true"></div>
</section>

<section class="brzo" aria-label="Brzi linkovi">
  <div class="wrap brzo-in">
    <a class="plocica" href="/lovidba">
      <span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><circle cx="12" cy="15" r="2"/></svg></span>
      <b>Lovidba</b><span>Što se u lovištu radi u <?= e(array('', 'siječnju', 'veljači', 'ožujku', 'travnju', 'svibnju', 'lipnju', 'srpnju', 'kolovozu', 'rujnu', 'listopadu', 'studenom', 'prosincu')[$m]) ?></span>
    </a>
    <a class="plocica" href="/dokumenti">
      <span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/></svg></span>
      <b>Dokumenti</b><span>Zakoni, pravilnici i obrasci</span>
    </a>
    <a class="plocica" href="/clanice-saveza">
      <span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><circle cx="17" cy="9" r="2.6"/><path d="M16.5 14.6c2.6.2 4.4 2 5 5"/></svg></span>
      <b>Članice</b><span><?= $clanica ?> lovačkih društava u 6 lovnih ureda</span>
    </a>
    <a class="plocica" href="/divljac">
      <span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3c0 3 1 4 3 5M16 3c0 3-1 4-3 5M5 5c1 2 3 3 5 3.5M19 5c-1 2-3 3-5 3.5"/><path d="M9 9.5h6l1 4-4 7-4-7z"/></svg></span>
      <b>Divljač</b><span>Krupna i sitna divljač</span>
    </a>
  </div>
  <div class="wrap">
    <div class="steta">
      <span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5M12 17.5v.01"/></svg></span>
      <div>
        <b>Šteta od divljači?</b>
        <p>Štetu od divljači prijavite online putem portala DEKRA – Hrvatski lovački savez.</p>
      </div>
      <a class="btn btn-glavni" href="<?= e($O['steta']) ?>" target="_blank" rel="noopener">Prijavi štetu <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</section>

<?php if ($glavna): ?>
<section class="sec">
  <div class="wrap">
    <header class="sec-head"><h2>Novosti</h2><a class="vise" href="/novosti">Sve novosti <span aria-hidden="true">›</span></a></header>
    <article class="vijest-glavna">
      <a href="<?= e(url_za($glavna)) ?>" tabindex="-1" aria-hidden="true" class="vijest-glavna-slika">
        <?php if ($glavna['slika']): ?><img src="/<?= e($glavna['slika']) ?>" alt="" loading="lazy"><?php else: ?><span class="bez-slike"><img src="/assets/img/logo-160.png" alt="" width="96" height="91"></span><?php endif; ?>
      </a>
      <div>
        <p class="meta"><time datetime="<?= e(date('Y-m-d', strtotime($glavna['datum']))) ?>"><?= e(mjesec_hr($glavna['datum'])) ?></time></p>
        <h3><a href="<?= e(url_za($glavna)) ?>"><?= e($glavna['naslov']) ?></a></h3>
        <p><?= e($glavna['sazetak']) ?></p>
        <a class="btn btn-glavni" href="<?= e(url_za($glavna)) ?>">Pročitaj više</a>
      </div>
    </article>
    <div class="vijesti-mreza">
      <?php $prikaz = 'novosti'; foreach ($vijesti as $s) require __DIR__ . '/_kartica.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec sec-krem">
  <div class="wrap lovidba-naslovna">
    <?php if ($lv): list($l) = $lv; ?>
    <div class="lovidba-kratko">
      <p class="lovidba-mjesec"><span><?= e(ucfirst(mjesec_naziv($m))) ?></span> u lovištu</p>
      <h2><?= e($l['naslov']) ?></h2>
      <p><?= e(sazetak_iz($l['tekst'], 420)) ?></p>
      <a class="btn btn-glavni" href="/lovidba">Lovidba ovaj mjesec</a>
    </div>
    <?php endif; ?>
    <dl class="brojke">
      <div><dt>Članica</dt><dd>109</dd></div>
      <div><dt>Lovaca</dt><dd>2.812</dd></div>
      <div><dt>ha zajedničkih lovišta</dt><dd>274.554</dd></div>
      <div><dt>Lovnih ureda</dt><dd>6</dd></div>
    </dl>
  </div>
</section>

<?php if ($lu): ?>
<section class="sec">
  <div class="wrap">
    <header class="sec-head"><h2>Lovni uredi</h2><a class="vise" href="/clanice-saveza">Sve članice <span aria-hidden="true">›</span></a></header>
    <div class="podkategorije">
      <?php foreach ($lu as $k): $n = (int) $db->query('SELECT COUNT(*) FROM sadrzaj WHERE objavljeno = 1 AND kategorija_id = ' . (int) $k['id'])->fetchColumn(); ?>
      <a class="podkategorija" href="<?= e($k['url']) ?>"><b><?= e($k['naziv']) ?></b><span><?= $n ?> <?= $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) ? 'članice' : 'članica' ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="poziv">
  <div class="wrap poziv-in">
    <div>
      <h2>Imate pitanje za Savez?</h2>
      <p>Članstvo, lovački ispit, lovišta ili dokumenti — javite nam se telefonom, e-mailom ili putem obrasca.</p>
    </div>
    <p class="hero-gumbi"><a class="btn btn-zlato" href="/kontakt">Kontakt</a><a class="btn btn-obrub-svijetli" href="tel:<?= e($O['tel_link']) ?>"><?= e($O['tel']) ?></a></p>
  </div>
</section>
<?php require __DIR__ . '/_dno.php'; ?>
