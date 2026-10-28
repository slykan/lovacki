<?php
/** /sitemap.xml i /novosti.xml (RSS) — generira se iz baze. */
$db = db();
$O = $GLOBALS['ORG'];
$vidljive = array_keys(array_filter(kategorije(), function ($k) { return $k['vidljiva']; }));
$uKat = '(kategorija_id IS NULL' . ($vidljive ? ' OR kategorija_id IN (' . implode(',', $vidljive) . ')' : '') . ')';
if (($rss = !empty($GLOBALS['RSS']))) {
    $nov = 0;
    foreach (kategorije() as $k) if ($k['slug'] === 'novosti') $nov = (int) $k['id'];
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0"><channel><title>' . e($O['kratko']) . ' — novosti</title><link>' . SITE_URL . '/novosti</link><description>Novosti Lovačkog saveza Osječko-baranjske županije</description><language>hr</language>' . "\n";
    foreach ($db->query("SELECT * FROM sadrzaj WHERE kategorija_id = $nov AND objavljeno=1 ORDER BY datum DESC LIMIT 30") as $s) {
        echo '<item><title>' . e($s['naslov']) . '</title><link>' . SITE_URL . e(url_za($s)) . '</link><guid>' . SITE_URL . e(url_za($s)) . '</guid><pubDate>' . date('r', strtotime($s['datum'])) . '</pubDate><description>' . e($s['sazetak']) . "</description></item>\n";
    }
    echo '</channel></rss>';
    return;
}
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
$zadnja = $db->query("SELECT MAX(izmijenjeno) FROM sadrzaj WHERE objavljeno=1")->fetchColumn();
echo '<url><loc>' . SITE_URL . '/</loc><lastmod>' . date('Y-m-d', strtotime($zadnja)) . "</lastmod><changefreq>weekly</changefreq></url>\n";
foreach (kategorije() as $k) {
    if ($k['vidljiva']) echo '<url><loc>' . SITE_URL . e($k['url']) . '</loc><lastmod>' . date('Y-m-d', strtotime($zadnja)) . "</lastmod></url>\n";
}
foreach ($db->query("SELECT kategorija_id, slug, izmijenjeno, slika FROM sadrzaj WHERE objavljeno=1 AND $uKat ORDER BY datum DESC") as $s) {
    echo '<url><loc>' . SITE_URL . e(url_za($s)) . '</loc><lastmod>' . date('Y-m-d', strtotime($s['izmijenjeno'])) . '</lastmod>'
        . ($s['slika'] ? '<image:image><image:loc>' . SITE_URL . '/' . e($s['slika']) . '</image:loc></image:image>' : '') . "</url>\n";
}
echo '</urlset>';
