<?php
require __DIR__ . '/../inc/app.php';
sesija();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    provjeri_csrf();
    $_SESSION = array();
    session_destroy();
}
header('Location: /admin/prijava.php');
