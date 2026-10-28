<?php
// Minimalni SMTP klijent i pomoćne funkcije za obrasce (isti kao na ostalim on-click stranicama).
function cut($value, $max)
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function textLength($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function field($name, $max)
{
    $value = isset($_POST[$name]) ? trim((string) $_POST[$name]) : '';
    // Bez kontrolnih znakova; poruka smije imati novi red.
    $clean = preg_replace('/[^\P{C}\n\t]/u', '', $value);

    return cut($clean === null ? '' : $clean, $max);
}

function oneLine($value)
{
    // Zastita od ubacivanja zaglavlja: u zaglavlja ide samo jedan redak.
    return trim(str_replace(array("\r", "\n"), ' ', $value));
}

function encodeName($name)
{
    return '=?UTF-8?B?' . base64_encode($name) . '?=';
}

/**
 * Jedan odgovor SMTP posluzitelja (visered odgovori spojeni).
 */
function smtpRead($fp)
{
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

/**
 * @return string|null null = ocekivani odgovor, inace odgovor posluzitelja
 */
function smtpCmd($fp, $line, $expect)
{
    if ($line !== null) {
        fwrite($fp, $line . "\r\n");
    }
    $reply = smtpRead($fp);

    return (int) substr($reply, 0, 3) === $expect ? null : trim($reply);
}

/**
 * Minimalni SMTP klijent (SSL na 465 ili STARTTLS na 587, AUTH LOGIN).
 *
 * @return string|null null = poslano, inace opis greske
 */
function smtpSend(array $c, $to, $message)
{
    $ssl = (int) $c['port'] === 465;
    $verify = isset($c['verify_ssl']) ? (bool) $c['verify_ssl'] : true;
    $ctx = stream_context_create(array('ssl' => array(
        'peer_name' => $c['host'],
        'verify_peer' => $verify,
        'verify_peer_name' => $verify,
        'allow_self_signed' => !$verify,
    )));
    $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'], $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return 'spajanje: ' . $errstr . ' (' . $errno . ')';
    }
    stream_set_timeout($fp, 15);

    $helo = 'EHLO ' . (isset($c['helo']) ? $c['helo'] : 'lovacki-savez-osijek.hr');
    $steps = array(array(null, 220), array($helo, 250));
    if (!$ssl) {
        $steps[] = array('STARTTLS', 220);
    }
    foreach ($steps as $step) {
        $err = smtpCmd($fp, $step[0], $step[1]);
        if ($err !== null) {
            fclose($fp);
            return $err;
        }
    }

    if (!$ssl) {
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $err = smtpCmd($fp, $helo, 250);
        if ($err !== null) {
            fclose($fp);
            return $err;
        }
    }

    // Tocka na pocetku retka udvostrucuje se (RFC 5321).
    $data = preg_replace('/^\./m', '..', $message);

    $steps = array(
        array('AUTH LOGIN', 334),
        array(base64_encode($c['username']), 334),
        array(base64_encode($c['password']), 235),
        array('MAIL FROM:<' . $c['from'] . '>', 250),
        array('RCPT TO:<' . $to . '>', 250),
        array('DATA', 354),
        array($data . "\r\n.", 250),
    );
    foreach ($steps as $i => $step) {
        $err = smtpCmd($fp, $step[0], $step[1]);
        if ($err !== null) {
            fclose($fp);
            return ($i <= 2 ? 'prijava: ' : '') . $err;
        }
    }

    smtpCmd($fp, 'QUIT', 221);
    fclose($fp);

    return null;
}
