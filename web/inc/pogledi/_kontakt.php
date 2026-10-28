<?php $O = $GLOBALS['ORG']; $lat = $O['geo'][0]; $lon = $O['geo'][1]; ?>
<div class="kontakt-kartice">
  <div><span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></span><b>Adresa</b><p><?= e($O['adresa']) ?><br><?= e($O['mjesto']) ?></p></div>
  <div><span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span><b>Telefon</b><p><a href="tel:<?= e($O['tel_link']) ?>"><?= e($O['tel']) ?></a><br><a href="tel:<?= e($O['mob_link']) ?>"><?= e($O['mob']) ?></a></p></div>
  <div><span class="ik" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><b>E-mail</b><p><a href="mailto:<?= e($O['email']) ?>"><?= e($O['email']) ?></a></p></div>
</div>

<div class="kontakt-raspored">
  <form class="obrazac" action="/posalji.php" method="post" novalidate data-obrazac>
    <h2>Pošaljite nam poruku</h2>
    <input type="hidden" name="obrazac" value="kontakt">
    <input type="hidden" name="t" value="" data-t>
    <div class="hp" aria-hidden="true"><label>Web <input name="web" tabindex="-1" autocomplete="off"></label></div>
    <div class="red2">
      <label>Ime i prezime *<input name="ime" required maxlength="120" autocomplete="name"></label>
      <label>E-mail *<input name="email" type="email" required maxlength="160" autocomplete="email"></label>
    </div>
    <div class="red2">
      <label>Telefon<input name="mobitel" type="tel" maxlength="30" autocomplete="tel"></label>
      <label>Tema
        <select name="tema">
          <option>Opći upit</option>
          <option>Članstvo i lovačka društva</option>
          <option>Lovački ispit i osposobljavanje</option>
          <option>Lovišta i divljač</option>
          <option>Novosti i događanja</option>
        </select>
      </label>
    </div>
    <label>Poruka *<textarea name="poruka" rows="6" required maxlength="5000"></textarea></label>
    <label class="privola"><input type="checkbox" name="privola" required><span>Pristajem da Lovački savez Osječko-baranjske županije obradi moje podatke radi odgovora na upit (<a href="/politika-privatnosti">politika privatnosti</a>).</span></label>
    <button type="submit" class="btn btn-glavni">Pošalji poruku</button>
    <p class="obrazac-poruka" role="status" aria-live="polite" data-poruka></p>
  </form>
  <div class="karta">
    <iframe title="Karta: <?= e($O['adresa'] . ', ' . $O['mjesto']) ?>" loading="lazy" src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $lon - 0.008 ?>%2C<?= $lat - 0.004 ?>%2C<?= $lon + 0.008 ?>%2C<?= $lat + 0.004 ?>&amp;layer=mapnik&amp;marker=<?= $lat ?>%2C<?= $lon ?>"></iframe>
    <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&amp;mlon=<?= $lon ?>#map=17/<?= $lat ?>/<?= $lon ?>" target="_blank" rel="noopener">Otvori veću kartu ↗</a>
  </div>
</div>
