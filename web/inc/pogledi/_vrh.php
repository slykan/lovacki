<?php
/** @var array $stranica  @var array $izbornik */
$O = $GLOBALS['ORG'];
$naslov = $stranica['naslov'] ? $stranica['naslov'] . ' — ' . SITE_NAME : 'Lovački savez Osječko-baranjske županije — lovstvo, članice, novosti';
$opis = sazetak_iz($stranica['opis'] ?: 'Lovački savez Osječko-baranjske županije od 1995. objedinjuje 109 lovačkih društava: novosti, članice po lovnim uredima, divljač, lovidba i dokumenti.', 160);
$slika = $stranica['slika'] ? SITE_URL . $stranica['slika'] : SITE_URL . '/assets/img/og-lso.jpg';
$aktivni = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$jeAktivan = function ($href) use ($aktivni) { return $href && $href !== '/' && $href !== '#' && ($aktivni === $href || strpos($aktivni, rtrim($href, '/') . '/') === 0); };
$ld = array('@context' => 'https://schema.org', '@type' => 'SportsOrganization', '@id' => SITE_URL . '/#savez', 'name' => $O['naziv'], 'alternateName' => $O['kratko'],
    'url' => SITE_URL . '/', 'logo' => SITE_URL . '/assets/img/logo-512.png', 'email' => $O['email'], 'telephone' => $O['tel'], 'foundingDate' => $O['osnovan'], 'taxID' => $O['oib'],
    'address' => array('@type' => 'PostalAddress', 'streetAddress' => $O['adresa'], 'postalCode' => '31000', 'addressLocality' => 'Osijek', 'addressRegion' => 'Osječko-baranjska županija', 'addressCountry' => 'HR'),
    'sport' => 'Lovstvo', 'memberOf' => array('@type' => 'Organization', 'name' => 'Hrvatski lovački savez', 'url' => $O['hls']), 'sameAs' => array($O['facebook']));
?><!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($naslov) ?></title>
<meta name="description" content="<?= e($opis) ?>">
<?php if (http_response_code() === 404 || !empty($stranica['noindex'])): ?><meta name="robots" content="noindex, follow"><?php else: ?><link rel="canonical" href="<?= e($stranica['canonical']) ?>"><?php endif; ?>
<meta property="og:type" content="<?= e($stranica['tip']) ?>">
<meta property="og:locale" content="hr_HR">
<meta property="og:site_name" content="<?= e($O['naziv']) ?>">
<meta property="og:title" content="<?= e($stranica['naslov'] ?: $O['naziv']) ?>">
<meta property="og:description" content="<?= e($opis) ?>">
<meta property="og:url" content="<?= e($stranica['canonical']) ?>">
<meta property="og:image" content="<?= e($slika) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php if (!$stranica['slika']): ?><meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<?php endif; ?>
<?php if (!empty($stranica['objavljeno'])): ?><meta property="article:published_time" content="<?= e($stranica['objavljeno']) ?>">
<?php endif; ?>
<meta name="theme-color" content="#2b1d12">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/img/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="alternate" type="application/rss+xml" title="<?= e($O['kratko']) ?> — novosti" href="/novosti.xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alegreya:wght@500;700;800&family=Alegreya+Sans:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(ROOT . "/assets/css/style.css") ?>">
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php if ($stranica['ld']): ?><script type="application/ld+json"><?= json_encode($stranica['ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
<?php if (!empty($stranica['mrvice'])): $mr = array(); foreach (array_merge(array(array('Naslovna', SITE_URL . '/')), $stranica['mrvice']) as $i => $m) $mr[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $m[0], 'item' => $m[1]); ?><script type="application/ld+json"><?= json_encode(array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $mr), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
</head>
<body>
<a class="skip" href="#sadrzaj">Preskoči na sadržaj</a>

<div class="traka">
  <div class="wrap traka-in">
    <span>Član Hrvatskog lovačkog saveza · od 1995.</span>
    <span class="traka-kontakt">
      <a href="tel:<?= e($O['tel_link']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><?= e($O['tel']) ?></a>
      <a href="mailto:<?= e($O['email']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><?= e($O['email']) ?></a>
      <a href="<?= e($O['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true" class="puna"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v7h4v-7h3l1-4h-4V8z"/></svg></a>
    </span>
  </div>
</div>

<header class="top" data-top>
  <div class="wrap top-in">
    <a class="brand" href="/" aria-label="<?= e($O['naziv']) ?> — naslovna">
      <img src="/assets/img/logo-160.png" alt="" width="64" height="61">
      <span><b>Lovački savez</b>Osječko-baranjske županije</span>
    </a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="izbornik" data-menu-btn><span></span><span></span><span></span><b class="sr">Izbornik</b></button>
    <nav id="izbornik" class="nav" aria-label="Glavni izbornik" data-menu>
      <ul>
        <?php foreach ($izbornik as $st): $ima = !empty($st['djeca']); ?>
        <li class="<?= $ima ? 'ima-pod' : '' ?>">
          <?php if ($ima): ?>
            <button type="button" class="nav-grupa<?= $jeAktivan($st['href']) ? ' aktivna' : '' ?>" aria-expanded="false" data-pod-btn><?= e($st['naslov']) ?><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg></button>
            <ul class="pod">
              <?php if ($st['href'] && $st['href'] !== '#'): ?><li><a href="<?= e($st['href']) ?>"<?= $st['href'] === $aktivni ? ' aria-current="page"' : '' ?>><?= $st['tip'] === 'kategorija' ? 'Sve: ' : '' ?><?= e($st['naslov']) ?></a></li><?php endif; ?>
              <?php foreach ($st['djeca'] as $d): ?>
              <li><a href="<?= e($d['href'] ?: '#') ?>"<?= $d['novi_prozor'] || preg_match('/\.pdf$/i', (string) $d['href']) ? ' target="_blank" rel="noopener"' : '' ?><?= $d['href'] === $aktivni ? ' aria-current="page"' : '' ?>><?= e($d['naslov']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <a href="<?= e($st['href'] ?: '#') ?>"<?= $st['href'] === $aktivni ? ' aria-current="page"' : ($jeAktivan($st['href']) ? ' class="aktivna"' : '') ?>><?= e($st['naslov']) ?></a>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <form class="nav-trazi" action="/pretraga" role="search">
        <label class="sr" for="q-top">Pretraga</label>
        <input id="q-top" name="q" type="search" placeholder="Pretraži…" value="<?= e($_GET['q'] ?? '') ?>">
        <button type="submit" aria-label="Traži"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg></button>
      </form>
    </nav>
  </div>
</header>

<main id="sadrzaj">
