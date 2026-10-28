<?php
/**
 * Admin korisnik: php _alat/korisnik.php [korisnicko_ime]
 * Napravi (ili resetira) korisnika s nasumičnom lozinkom i zapiše je u _alat/admin-pristup.txt
 * (lokalno, ne uploadati). Na poslužitelju se pokreće isto, iz mape iznad web/.
 */
require __DIR__ . '/../web/inc/app.php';
$k = $argv[1] ?? 'savez';
$abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$l = '';
for ($i = 0; $i < 14; $i++) $l .= $abc[random_int(0, strlen($abc) - 1)];
$hash = password_hash($l, PASSWORD_DEFAULT);
$st = db()->prepare('UPDATE korisnici SET hash = ? WHERE korisnik = ?');
$st->execute(array($hash, $k));
if (!$st->rowCount()) db()->prepare('INSERT INTO korisnici (korisnik, hash) VALUES (?, ?)')->execute(array($k, $hash));
file_put_contents(__DIR__ . '/admin-pristup.txt', "Admin: https://www.lovacki-savez-osijek.hr/admin/\nKorisnik: $k\nLozinka: $l\n(promijeniti nakon prve prijave: Admin › Lozinka)\n");
echo "Korisnik '$k' spremljen; lozinka je u _alat/admin-pristup.txt\n";
