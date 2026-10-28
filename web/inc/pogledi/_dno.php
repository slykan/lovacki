<?php $O = $GLOBALS['ORG']; ?>
</main>

<footer class="dno">
  <div class="dno-krajolik" aria-hidden="true"></div>
  <div class="wrap dno-in">
    <div class="dno-o">
      <img src="/assets/img/logo-160.png" alt="" width="96" height="91" loading="lazy">
      <p><b><?= e($O['naziv']) ?></b><br>Objedinjuje lovačka društva s područja županije, promiče lovstvo te unapređuje uzgoj, zaštitu, lov i korištenje divljači.</p>
    </div>
    <div>
      <h2>Kontakt</h2>
      <p><?= e($O['adresa']) ?><br><?= e($O['mjesto']) ?><br>
      <a href="tel:<?= e($O['tel_link']) ?>"><?= e($O['tel']) ?></a><br>
      <a href="tel:<?= e($O['mob_link']) ?>"><?= e($O['mob']) ?></a><br>
      <a href="mailto:<?= e($O['email']) ?>"><?= e($O['email']) ?></a></p>
      <p class="dno-sitno">OIB <?= e($O['oib']) ?> · MB <?= e($O['mb']) ?><br>IBAN <?= e($O['iban']) ?></p>
    </div>
    <div>
      <h2>Brzi linkovi</h2>
      <ul>
        <li><a href="/novosti">Novosti</a></li>
        <li><a href="/clanice-saveza">Članice Saveza</a></li>
        <li><a href="/lovidba">Lovidba ovaj mjesec</a></li>
        <li><a href="/dokumenti">Dokumenti</a></li>
        <li><a href="/kontakt">Kontakt</a></li>
      </ul>
    </div>
    <div>
      <h2>Poveznice</h2>
      <ul>
        <li><a href="<?= e($O['hls']) ?>" target="_blank" rel="noopener">Hrvatski lovački savez ↗</a></li>
        <li><a href="<?= e($O['facebook']) ?>" target="_blank" rel="noopener">Facebook ↗</a></li>
        <li><a href="/politika-privatnosti">Politika privatnosti</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap dno-mali">
    <span>© 1995–<?= date('Y') ?> <?= e($O['naziv']) ?></span>
    <span>Izrada: <a href="https://www.on-click.hr/" target="_blank" rel="noopener">on-click.hr</a></span>
  </div>
</footer>

<?php if (postavka('popup_ukljucen') === '1' && postavka('popup_slika') && http_response_code() !== 404): $pl = postavka('popup_link'); ?>
<div class="popup" data-popup data-verzija="<?= e(postavka('popup_verzija')) ?>" data-ucestalost="<?= e(postavka('popup_ucestalost', 'jednom')) ?>" hidden>
  <div class="popup-okvir" role="dialog" aria-modal="true" aria-label="<?= e(postavka('popup_opis') ?: 'Obavijest') ?>">
    <button type="button" class="popup-x" data-popup-zatvori aria-label="Zatvori">×</button>
    <?php if ($pl): ?><a href="<?= e($pl) ?>"<?= preg_match('#^https?://#', $pl) && strpos($pl, SITE_URL) !== 0 ? ' target="_blank" rel="noopener"' : '' ?>><?php endif; ?>
    <img src="/<?= e(postavka('popup_slika')) ?>" alt="<?= e(postavka('popup_opis')) ?>">
    <?php if ($pl): ?></a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script src="/assets/js/main.js?v=<?= filemtime(ROOT . "/assets/js/main.js") ?>" defer></script>
</body>
</html>
