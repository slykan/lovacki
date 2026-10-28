<?php
/**
 * Kontakt obrazac → e-mail Savezu (SMTP iz inc/mail-config.php, Reply-To je pošiljatelj).
 * Zaštita: skriveno polje, minimalno vrijeme ispunjavanja, 5 slanja/sat po IP-u.
 */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/mail.php';

define('TEST_MODE', getenv('LSO_TEST_MAIL') === '1'); // LSO_TEST_MAIL=1 → poruka u data/mail-test.log umjesto slanja

$json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$O = $GLOBALS['ORG'];

function odgovor($ok, $poruka, $status = 200)
{
    global $json;
    if ($json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => $ok, 'message' => $poruka), JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: /kontakt?poslano=' . ($ok ? 1 : 0), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') odgovor(false, 'Neispravan zahtjev.', 405);
$cfg = is_file(__DIR__ . '/inc/mail-config.php') ? include __DIR__ . '/inc/mail-config.php' : null;
if (!is_array($cfg)) odgovor(false, 'Obrazac trenutno nije dostupan. Pišite nam na ' . $O['email'] . '.', 500);
if (field('web', 200) !== '') odgovor(true, 'Hvala! Poruka je poslana.');
$t = (int) field('t', 20);
if ($t > 0 && time() - (int) floor($t / 1000) < 4) odgovor(false, 'Poslano prebrzo. Pokušajte ponovno.', 429);

// ograničenje po IP-u
$ipf = sys_get_temp_dir() . '/lso-obrazac-' . md5(ip());
$hits = array_filter(explode(',', (string) @file_get_contents($ipf)), function ($x) { return (int) $x > time() - 3600; });
if (count($hits) >= 5) odgovor(false, 'Poslali ste previše poruka. Pokušajte ponovno za sat vremena.', 429);

$p = function ($k, $max = 200) { return oneLine(field($k, $max)); };
$email = $p('email', 160);
$ime = $p('ime', 120);
$poruka = field('poruka', 5000);
$teme = array('Opći upit', 'Članstvo i lovačka društva', 'Lovački ispit i osposobljavanje', 'Lovišta i divljač', 'Novosti i događanja');
$tema = in_array($p('tema', 60), $teme, true) ? $p('tema', 60) : 'Opći upit';
$greske = array();
if ($ime === '') $greske[] = 'ime i prezime';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $greske[] = 'ispravnu e-mail adresu';
if (textLength($poruka) < 3) $greske[] = 'poruku';
if (!isset($_POST['privola'])) $greske[] = 'privolu za obradu podataka';
if ($greske) odgovor(false, 'Molimo upišite: ' . implode(', ', $greske) . '.', 422);

$hits[] = time();
@file_put_contents($ipf, implode(',', $hits), LOCK_EX);

$polja = array('Ime i prezime' => $ime, 'E-mail' => $email, 'Telefon' => $p('mobitel', 30), 'Tema' => $tema);
$naslov = $tema . ': ' . $ime;
$crta = str_repeat('-', 44);
$tijelo = "Nova poruka s kontakt obrasca (lovacki-savez-osijek.hr)\n" . $crta . "\n";
foreach ($polja as $k => $v) $tijelo .= $k . ':' . str_repeat(' ', max(1, 16 - mb_strlen($k))) . ($v !== '' ? $v : '-') . "\n";
$tijelo .= $crta . "\n" . $poruka . "\n" . $crta . "\n"
    . 'Poslano: ' . date('d.m.Y. H:i') . "\nOdgovorom na ovaj e-mail pišete pošiljatelju.\nPošiljatelj je potvrdio privolu za obradu osobnih podataka.\n";

$mail = implode("\r\n", array(
    'Date: ' . date('r'),
    'From: ' . encodeName($cfg['from_name'] ?? 'Lovački savez OBŽ — web') . ' <' . $cfg['from'] . '>',
    'To: <' . $cfg['to'] . '>',
    'Reply-To: ' . encodeName($ime) . ' <' . $email . '>',
    'Subject: =?UTF-8?B?' . base64_encode($naslov) . '?=',
    'Message-ID: <' . md5(uniqid('', true)) . '@lovacki-savez-osijek.hr>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    rtrim(chunk_split(base64_encode($tijelo), 76, "\r\n")),
));

if (TEST_MODE) {
    $ok = (bool) file_put_contents(DATA_DIR . '/mail-test.log', $naslov . "\n" . $tijelo . "\n", FILE_APPEND | LOCK_EX);
} else {
    $err = smtpSend($cfg, $cfg['to'], $mail);
    $ok = $err === null;
    if (!$ok) error_log('LSO obrazac SMTP: ' . $err);
}
if (!$ok) odgovor(false, 'Slanje trenutno nije uspjelo. Pišite nam na ' . $O['email'] . ' ili nazovite ' . $O['tel'] . '.', 500);
odgovor(true, 'Hvala! Poruka je poslana, javit ćemo vam se u najkraćem roku.');
