<?php
/**
 * Članice (2026-10-28, zahtjev klijenta), nad ŽIVOM bazom:
 *  - kod svih članica: makni „Broj mobitela” i „Broj pripravnika”, „E-mail” → „E-mail društva” na mjestu mobitela;
 *    sažetak kartice: „Kontakt: ime · e-mail · lovište …” (bez mobitela)
 *  - lovni uredi: makni zbirni popis „Lovačka društva i kontakt osobe” iz opisa
 * Na poslužitelju (čita inc/db-config.php iz public_html):
 *   php ~/lovacki/_alat/clanice-polja.php ~/public_html              — samo ispis (ništa ne mijenja)
 *   php ~/lovacki/_alat/clanice-polja.php ~/public_html --primijeni  — zapiše promjene
 */
$web = rtrim($argv[1] ?? (dirname(__DIR__) . '/web'), '/');
$primijeni = in_array('--primijeni', $argv, true);
require $web . '/inc/app.php';
require __DIR__ . '/clanica.php';
$db = db();

$lu = array();
foreach (kategorije() as $k) if (strpos($k['slug'], 'lu-') === 0) $lu[] = (int) $k['id'];
if (!$lu) exit("Nema kategorija lovnih ureda (lu-…).\n");

// 1) opisi lovnih ureda
foreach ($lu as $id) {
    $k = kategorija($id);
    // ostaje samo odlomak s voditeljem ureda; popis društava („… - Kontakt osoba: …”) se briše (društva su kartice ispod)
    $novi = $k['opis'];
    if (mb_stripos($novi, 'Kontakt osoba') !== false && preg_match('#<p>(?:(?!</p>).)*Voditelj(?:(?!</p>).)*</p>#su', $novi, $vod)) {
        $novi = $vod[0];
    }
    if ($novi !== $k['opis']) {
        echo "opis: {$k['naziv']} — zbirni popis maknut\n";
        if ($primijeni) $db->prepare('UPDATE kategorije SET opis = ? WHERE id = ?')->execute(array(trim($novi), $id));
    }
}

// 2) članice
$polje = '#<p class="polje"><span class="titula">([^<]+)</span>\s*<strong>(.*?)</strong></p>#su';
$st = $db->query('SELECT id, naslov, tekst, sazetak FROM sadrzaj WHERE kategorija_id IN (' . implode(',', $lu) . ')');
$promjena = 0; $sEmailom = 0;
foreach ($st->fetchAll() as $r) {
    if (!preg_match('#<div class="polja">(.*?)</div>#su', $r['tekst'], $blok)) {
        // stare tablice (članice koje su bile u nadkategoriji „Članice”) → kartice polja po istim pravilima
        if (strpos($r['tekst'], '<table') === false || !($novo = clanica($r['tekst']))) continue;
        $promjena++;
        echo "članica (tablica → polja): {$r['naslov']}\n";
        if ($primijeni) $db->prepare('UPDATE sadrzaj SET tekst = ?, sazetak = ?, izmijenjeno = ? WHERE id = ?')->execute(array(sanitiziraj_html($novo), $GLOBALS['clanica_sazetak'], sada(), $r['id']));
        continue;
    }
    preg_match_all($polje, $blok[1], $mm, PREG_SET_ORDER);
    $polja = array(); $mobPoz = null; $email = null; $emailPoz = null; $emailTekst = '';
    foreach ($mm as $m) {
        $oznaka = trim($m[1]);
        if ($oznaka === 'Broj mobitela') { $mobPoz = count($polja); continue; }
        if ($oznaka === 'Broj pripravnika') continue;
        if (preg_match('/^e-?mail( društva)?$/iu', $oznaka)) {
            $email = $m[2]; $emailTekst = trim(strip_tags($m[2])); $emailPoz = count($polja); // mjesto e-maila ako mobitela nema
            continue;
        }
        $polja[] = array($oznaka, $m[2]);
    }
    if ($email !== null) {
        array_splice($polja, $mobPoz !== null ? $mobPoz : $emailPoz, 0, array(array('E-mail društva', $email)));
        $sEmailom++;
    }
    $html = '';
    foreach ($polja as $p) $html .= '<p class="polje"><span class="titula">' . $p[0] . '</span> <strong>' . $p[1] . '</strong></p>';
    $tekst = str_replace($blok[0], '<div class="polja">' . $html . '</div>', $r['tekst']);

    $po = array();
    foreach ($polja as $p) $po[$p[0]] = trim(html_entity_decode(strip_tags($p[1]), ENT_QUOTES, 'UTF-8'));
    $sazetak = implode(' · ', array_filter(array(
        isset($po['Kontakt osoba']) ? 'Kontakt: ' . $po['Kontakt osoba'] : null,
        $emailTekst !== '' ? $emailTekst : null,
        isset($po['Zajedničko lovište']) ? 'lovište ' . $po['Zajedničko lovište'] : null,
    )));
    if ($tekst === $r['tekst'] && $sazetak === $r['sazetak']) continue;
    $promjena++;
    if ($promjena <= 3) echo "članica: {$r['naslov']}\n   sažetak: {$r['sazetak']}\n        → {$sazetak}\n";
    if ($primijeni) $db->prepare('UPDATE sadrzaj SET tekst = ?, sazetak = ?, izmijenjeno = ? WHERE id = ?')->execute(array($tekst, $sazetak, sada(), $r['id']));
}
echo "\nčlanica za promjenu: $promjena (s e-mailom društva: $sEmailom)\n";
echo $primijeni ? "ZAPISANO.\n" : "Ništa nije zapisano — pokrenite s --primijeni.\n";
