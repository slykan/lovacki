<?php
// ispis tekstualnih polja iz SP Page Buildera: php spb-tekst.php <id>
$db = new PDO('mysql:host=127.0.0.1;dbname=lovacki_izvor;charset=utf8mb4', 'root', '');
$t = $db->query('SELECT text FROM a8ubr_sppagebuilder WHERE id=' . (int) $argv[1])->fetchColumn();
$hod = function ($n, $put = '') use (&$hod) {
    if (is_array($n) || is_object($n)) { foreach ($n as $k => $v) $hod($v, "$put.$k"); return; }
    if (!is_string($n) || mb_strlen(trim(strip_tags($n))) < 3) return;
    if (preg_match('/\.(id|class|type|name|style|color|animation|font|width|height|icon|layout|align|position|size|margin|padding)/i', $put) && !preg_match('/(title|text|content|address|phone|email)$/i', $put)) return;
    if (preg_match('/^(#|rgba?\(|\d+(px|%)?$|[a-z_-]+$)/i', trim($n))) return;
    if (preg_match('/(image|src|url)$/i', $put)) { echo "[IMG] $n\n"; return; }
    echo preg_replace('/^\.?[^.]*(\.[^.]+){0,1}/', '', '') . trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(array('</p>', '<br'), array("</p> | ", ' | <br'), $n)))) . "\n";
};
$hod(json_decode($t));
