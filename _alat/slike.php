<?php
/**
 * Iz logo.jpeg (bijela pozadina) napravi prozirne PNG-ove, ikone, favicon i OG sliku.
 *   php _alat/slike.php
 */
$K = dirname(__DIR__);
$izl = $K . '/web/assets/img';
if (!is_dir($izl)) mkdir($izl, 0755, true);

$src = imagecreatefromjpeg($K . '/logo.jpeg');
$w = imagesx($src); $h = imagesy($src);

// bijela pozadina → prozirno: širenje od rubova kroz gotovo bijele piksele (crni obrub pečata zaustavlja)
$img = imagecreatetruecolor($w, $h);
imagealphablending($img, false); imagesavealpha($img, true);
imagecopy($img, $src, 0, 0, 0, 0, $w, $h);
$bijela = function ($x, $y) use ($src) { $c = imagecolorat($src, $x, $y); return (($c >> 16) & 255) > 225 && (($c >> 8) & 255) > 225 && ($c & 255) > 215; };
$vidjen = array_fill(0, $w * $h, false);
$red = new SplQueue();
for ($x = 0; $x < $w; $x++) { $red->enqueue(array($x, 0)); $red->enqueue(array($x, $h - 1)); }
for ($y = 0; $y < $h; $y++) { $red->enqueue(array(0, $y)); $red->enqueue(array($w - 1, $y)); }
$prozirno = imagecolorallocatealpha($img, 255, 255, 255, 127);
while (!$red->isEmpty()) {
    list($x, $y) = $red->dequeue();
    $i = $y * $w + $x;
    if ($vidjen[$i] || !$bijela($x, $y)) continue;
    $vidjen[$i] = true;
    imagesetpixel($img, $x, $y, $prozirno);
    if ($x > 0) $red->enqueue(array($x - 1, $y));
    if ($x < $w - 1) $red->enqueue(array($x + 1, $y));
    if ($y > 0) $red->enqueue(array($x, $y - 1));
    if ($y < $h - 1) $red->enqueue(array($x, $y + 1));
}

// izrezivanje na sadržaj
$minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
for ($y = 0; $y < $h; $y += 2) for ($x = 0; $x < $w; $x += 2) {
    if (!$vidjen[$y * $w + $x]) { $minX = min($minX, $x); $maxX = max($maxX, $x); $minY = min($minY, $y); $maxY = max($maxY, $y); }
}
$cw = $maxX - $minX + 1; $ch = $maxY - $minY + 1;

function spremi_png($img, $sx, $sy, $sw, $sh, $sirina, $put, $kvadrat = false, $pozadina = null)
{
    $nw = $sirina; $nh = (int) round($sh * $sirina / $sw);
    $cw = $kvadrat ? $sirina : $nw; $chh = $kvadrat ? $sirina : $nh;
    if ($kvadrat) { $k = min($sirina * 0.9 / $sw, $sirina * 0.9 / $sh); $nw = (int) round($sw * $k); $nh = (int) round($sh * $k); }
    $o = imagecreatetruecolor($cw, $chh);
    imagealphablending($o, false); imagesavealpha($o, true);
    imagefill($o, 0, 0, $pozadina === null ? imagecolorallocatealpha($o, 0, 0, 0, 127) : $pozadina($o));
    imagealphablending($o, true);
    imagecopyresampled($o, $img, (int) (($cw - $nw) / 2), (int) (($chh - $nh) / 2), $sx, $sy, $nw, $nh, $sw, $sh);
    imagealphablending($o, false);
    imagepng($o, $put, 9);
    return $o;
}
spremi_png($img, $minX, $minY, $cw, $ch, 160, "$izl/logo-160.png");
spremi_png($img, $minX, $minY, $cw, $ch, 640, "$izl/logo-640.png");
spremi_png($img, $minX, $minY, $cw, $ch, 512, "$izl/logo-512.png", true);
$krem = function ($o) { return imagecolorallocate($o, 251, 244, 226); };
spremi_png($img, $minX, $minY, $cw, $ch, 192, "$izl/icon-192.png", true, $krem);
spremi_png($img, $minX, $minY, $cw, $ch, 180, $K . '/web/apple-touch-icon.png', true, $krem);
$f32 = spremi_png($img, $minX, $minY, $cw, $ch, 48, "$izl/favicon-48.png", true);

// favicon.ico s PNG-om (48 px) unutra
$png = file_get_contents("$izl/favicon-48.png");
file_put_contents($K . '/web/favicon.ico', pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 48, 48, 0, 0, 1, 32, strlen($png), 22) . $png);
unlink("$izl/favicon-48.png");

// OG 1200×630: tamno smeđa pozadina sa zlatnim prijelazom, pečat lijevo, naziv desno
$og = imagecreatetruecolor(1200, 630);
for ($y = 0; $y < 630; $y++) {
    $t = $y / 629;
    imageline($og, 0, $y, 1199, $y, imagecolorallocate($og, (int) (43 + 60 * $t), (int) (29 + 34 * $t), (int) (18 + 8 * $t)));
}
imagealphablending($og, true);
$k = 520 / $ch;
imagecopyresampled($og, $img, 60, 55, $minX, $minY, (int) ($cw * $k), 520, $cw, $ch);
$font = null;
foreach (array('C:/Windows/Fonts/georgiab.ttf', 'C:/Windows/Fonts/timesbd.ttf') as $f) if (is_file($f)) { $font = $f; break; }
if ($font) {
    $zlato = imagecolorallocate($og, 240, 200, 110); $bijela = imagecolorallocate($og, 251, 244, 226);
    imagettftext($og, 52, 0, 640, 270, $bijela, $font, 'Lovački savez');
    imagettftext($og, 30, 0, 642, 330, $zlato, $font, 'Osječko-baranjske');
    imagettftext($og, 30, 0, 642, 375, $zlato, $font, 'županije');
    imagettftext($og, 20, 0, 644, 450, $bijela, $font, 'od 1995. · lovacki-savez-osijek.hr');
}
imagejpeg($og, "$izl/og-lso.jpg", 86);
echo "gotovo: logo " . $cw . "x" . $ch . "\n";
