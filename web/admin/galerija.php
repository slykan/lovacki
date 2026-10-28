<?php
/** Upload jedne slike u galeriju zapisa → JSON {id, mala}. Pripaja se zapisu pri spremanju. */
require __DIR__ . '/../inc/app.php';
header('Content-Type: application/json; charset=utf-8');
if (!prijavljen() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(array('greska' => 'Niste prijavljeni.')));
}
provjeri_csrf();
$f = $_FILES['slika'] ?? null;
if (!$f || !is_uploaded_file($f['tmp_name']) || $f['size'] > 20 * 1024 * 1024) {
    http_response_code(422);
    exit(json_encode(array('greska' => 'Slika nije poslana ili je veća od 20 MB.')));
}
pocisti_galeriju();
$put = spremi_sliku($f['tmp_name'], 1600, 600, 'galerija/' . date('Y/m'));
if (!$put) {
    http_response_code(422);
    exit(json_encode(array('greska' => 'Datoteka nije slika (JPG, PNG, WEBP).')));
}
db()->prepare('INSERT INTO galerija (sadrzaj_id, slika, redoslijed, izradeno) VALUES (NULL, ?, 0, ?)')->execute(array($put, sada()));
echo json_encode(array('id' => (int) db()->lastInsertId(), 'mala' => slika_url(array('slika' => $put))));
