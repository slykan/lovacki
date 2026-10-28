<?php
/** Upload slike iz uređivača teksta → JSON {url}. */
require __DIR__ . '/../inc/app.php';
header('Content-Type: application/json; charset=utf-8');
if (!prijavljen() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(array('greska' => 'Niste prijavljeni.')));
}
provjeri_csrf();
$f = $_FILES['slika'] ?? null;
if (!$f || !is_uploaded_file($f['tmp_name']) || $f['size'] > 15 * 1024 * 1024) {
    http_response_code(422);
    exit(json_encode(array('greska' => 'Slika nije poslana ili je veća od 15 MB.')));
}
$put = spremi_sliku($f['tmp_name'], 1600, 0);
if (!$put) {
    http_response_code(422);
    exit(json_encode(array('greska' => 'Datoteka nije slika (JPG, PNG, WEBP).')));
}
echo json_encode(array('url' => '/' . $put));
