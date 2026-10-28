<?php
// svi HTML blokovi (raw_html / text) iz SP Page Builder stranice: php spb-html.php <id>
$db = new PDO('mysql:host=127.0.0.1;dbname=lovacki_izvor;charset=utf8mb4', 'root', '');
$t = $db->query('SELECT text FROM a8ubr_sppagebuilder WHERE id=' . (int) $argv[1])->fetchColumn();
$hod = function ($n, $k = '') use (&$hod) {
    if (is_array($n) || is_object($n)) { foreach ($n as $kk => $v) $hod($v, $kk); return; }
    if (is_string($n) && in_array($k, array('html', 'text', 'content'), true) && strlen($n) > 200) echo "----- $k\n$n\n";
};
$hod(json_decode($t));
