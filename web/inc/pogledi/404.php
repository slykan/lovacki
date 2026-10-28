<?php
$stranica['naslov'] = 'Stranica nije pronađena';
require __DIR__ . '/_vrh.php';
?>
<section class="sec">
  <div class="wrap stranica-404">
    <h1>Ova stranica ne postoji.</h1>
    <p>Možda je adresa promijenjena s novom web stranicom. Potražite ono što vas zanima:</p>
    <form class="trazi-velika" action="/pretraga" role="search">
      <label class="sr" for="q404">Pojam</label>
      <input id="q404" name="q" type="search" placeholder="Pretraži novosti i stranice…">
      <button class="btn btn-glavni" type="submit">Traži</button>
    </form>
    <p><a class="btn btn-obrub" href="/">Na naslovnu</a> <a class="btn btn-obrub" href="/novosti">Sve novosti</a> <a class="btn btn-obrub" href="/clanice-saveza">Članice</a></p>
  </div>
</section>
<?php require __DIR__ . '/_dno.php'; ?>
