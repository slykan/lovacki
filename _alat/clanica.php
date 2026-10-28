<?php
// Zajedničko za uvoz.php i clanice-polja.php (treba e() iz web/inc/app.php).

/** Članica (LD): tablice „oznaka: vrijednost” → kartice polja. */
function clanica($html)
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
    libxml_clear_errors();
    $polja = array();
    $txt = function ($n) { return trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $n->textContent))); };
    // zajedničko lovište iz zaglavlja
    foreach ($doc->getElementsByTagName('p') as $p) {
        if (preg_match('/lovište broj:\s*(.+)$/iu', $txt($p), $m)) { $polja[] = array('Zajedničko lovište', trim($m[1])); break; }
    }
    $zadnja = null;
    foreach ($doc->getElementsByTagName('tr') as $tr) {
        $td = array();
        foreach ($tr->childNodes as $c) if ($c instanceof DOMElement && in_array($c->nodeName, array('td', 'th'))) $td[] = $c;
        if (count($td) < 2) continue;
        $oznaka = rtrim($txt($td[0]), ': ');
        $vrijednost = trim($txt($td[1]), " .\t");
        if (in_array($vrijednost, array('', '-', '–'), true)) { if ($oznaka !== '') $zadnja = null; continue; }
        if ($oznaka === '') {
            if ($zadnja !== null) $polja[$zadnja][1] .= ', ' . $vrijednost;
            continue;
        }
        $oznaka = mb_strtoupper(mb_substr($oznaka, 0, 1)) . mb_substr($oznaka, 1);
        $polja[] = array($oznaka, $vrijednost);
        $zadnja = count($polja) - 1;
    }
    if (!$polja) return null;
    // klijent (2026-10-28): bez broja mobitela i broja pripravnika; e-mail kao „E-mail društva” na mjestu mobitela
    $ciste = array(); $mobPoz = null; $email = null;
    foreach ($polja as $p) {
        if (preg_match('/^broj mobitela$/iu', $p[0])) { $mobPoz = count($ciste); continue; }
        if (preg_match('/^broj pripravnika$/iu', $p[0])) continue;
        if (preg_match('/^e-?mail/iu', $p[0])) { $email = array('E-mail društva', $p[1]); continue; }
        $ciste[] = $p;
    }
    if ($email) array_splice($ciste, $mobPoz !== null ? $mobPoz : count($ciste), 0, array($email));
    $polja = $ciste;
    // sažetak za popis: kontakt osoba · e-mail društva · lovište
    $po = array();
    foreach ($polja as $p) $po[mb_strtolower($p[0])] = $p[1];
    $GLOBALS['clanica_sazetak'] = implode(' · ', array_filter(array(
        isset($po['kontakt osoba']) ? 'Kontakt: ' . $po['kontakt osoba'] : null,
        $po['e-mail društva'] ?? null,
        isset($po['zajedničko lovište']) ? 'lovište ' . $po['zajedničko lovište'] : null,
    )));
    $out = '<div class="polja">';
    $povijest = '';
    foreach ($polja as $p) {
        list($o, $v) = $p;
        if (preg_match('/povijest/i', $o)) { $povijest = $v; continue; }
        $v = e($v);
        if (preg_match('/e-?mail/i', $o)) $v = preg_replace('/([\w.+-]+@[\w-]+\.[\w.]+)/', '<a href="mailto:$1">$1</a>', $v);
        if (preg_match('/^www/i', $o) && preg_match('#^(https?://)?([\w-]+\.)+\w+#', html_entity_decode($v))) $v = '<a href="' . (preg_match('#^https?://#', $v) ? '' : 'http://') . $v . '" target="_blank" rel="noopener">' . $v . '</a>';
        if (preg_match('/mobitel|telefon|tel\b/i', $o)) $v = preg_replace_callback('#[+\d][\d /-]{6,}\d#', function ($m) { return '<a href="tel:' . preg_replace('/[^\d+]/', '', $m[0]) . '">' . $m[0] . '</a>'; }, $v);
        $out .= '<p class="polje"><span class="titula">' . e($o) . '</span> <strong>' . $v . '</strong></p>';
    }
    $out .= '</div>';
    if ($povijest !== '') $out .= '<h2>Kratka povijest</h2><p>' . e($povijest) . '</p>';
    return $out;
}
