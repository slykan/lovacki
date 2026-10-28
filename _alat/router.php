<?php
// Lokalni test (php -S): oponaša .htaccess — datoteke izravno, zaštićene mape 403, ostalo kroz index.php.
$put = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(inc|data)(/|$)#', $put)) { http_response_code(403); exit('403'); }
$f = $_SERVER['DOCUMENT_ROOT'] . urldecode($put);
if ($put !== '/' && is_file($f)) {
    if (substr($f, -4) === '.php') { chdir(dirname($f)); require $f; return true; }
    return false;
}
if (is_dir($f) && is_file(rtrim($f, '/') . '/index.php')) { chdir($f); require rtrim($f, '/') . '/index.php'; return true; }
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
