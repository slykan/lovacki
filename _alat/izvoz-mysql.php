<?php
/**
 * Izvoz lokalne SQLite baze (web/data/lovacki.sqlite) u MySQL dump za poslužitelj (baza lovacki_new).
 *   php _alat/izvoz-mysql.php > _alat/lovacki-baza.sql
 * Tablica prijave se ne izvozi. Dump sadrži hash admin lozinke — ne ide u git (_alat/*.sql je u .gitignore).
 */
require dirname(__DIR__) . '/web/inc/app.php';

$sqlite = new PDO('sqlite:' . DATA_DIR . '/lovacki.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function q($v)
{
    if ($v === null) return 'NULL';
    return "'" . strtr((string) $v, array('\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z')) . "'";
}

$tablice = array('kategorije', 'sadrzaj', 'izbornik', 'korisnici', 'preusmjerenja', 'privitci', 'galerija', 'postavke');
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
echo 'DROP TABLE IF EXISTS ' . implode(', ', array_merge($tablice, array('prijave'))) . ";\n";
foreach (shema_sql(true) as $s) echo $s, ";\n";

foreach ($tablice as $t) {
    $redovi = $sqlite->query("SELECT * FROM $t" . (in_array($t, array('preusmjerenja', 'postavke'), true) ? '' : ' ORDER BY id'))->fetchAll();
    foreach (array_chunk($redovi, 50) as $dio) {
        $stupci = '`' . implode('`, `', array_keys($dio[0])) . '`';
        $vr = array();
        foreach ($dio as $r) $vr[] = '(' . implode(', ', array_map('q', array_values($r))) . ')';
        echo "INSERT INTO $t ($stupci) VALUES\n" . implode(",\n", $vr) . ";\n";
    }
    fwrite(STDERR, "$t: " . count($redovi) . "\n");
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
