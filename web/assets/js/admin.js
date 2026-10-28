// Admin Lovački savez OBŽ — uređivač teksta (vizualno ↔ HTML), slike, potvrde, izbornik
(function () {
  // potvrda brisanja
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-potvrdi]');
    if (b && !confirm(b.getAttribute('data-potvrdi'))) e.preventDefault();
  });

  /* ---------- uređivač ---------- */
  var ed = document.querySelector('[data-editor]');
  var forma = document.querySelector('[data-uredi]');
  if (ed && forma) {
    var viz = ed.querySelector('[data-vizualno]');
    var kod = ed.querySelector('[data-kod]');
    var htmlBtn = ed.querySelector('[data-html]');
    var datoteka = ed.querySelector('[data-slika-datoteka]');
    var csrf = forma.querySelector('input[name=csrf]').value;
    var promijenjeno = false;
    var uHtml = false;

    document.execCommand('defaultParagraphSeparator', false, 'p');
    function sinkroniziraj() { if (!uHtml) kod.value = viz.innerHTML; }
    viz.addEventListener('input', function () { promijenjeno = true; });
    forma.addEventListener('input', function () { promijenjeno = true; });

    ed.querySelectorAll('[data-cmd]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () { if (uHtml) return; document.execCommand(b.dataset.cmd, false, null); viz.focus(); promijenjeno = true; });
    });
    ed.querySelectorAll('[data-blok]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () { if (uHtml) return; document.execCommand('formatBlock', false, b.dataset.blok); viz.focus(); promijenjeno = true; });
    });
    var link = ed.querySelector('[data-link]');
    link.addEventListener('mousedown', function (e) { e.preventDefault(); });
    link.addEventListener('click', function () {
      if (uHtml) return;
      var url = prompt('Adresa poveznice (https://…):', 'https://');
      if (!url || url === 'https://') return;
      document.execCommand('createLink', false, url);
      var a = window.getSelection().anchorNode;
      a = a && (a.nodeType === 1 ? a : a.parentNode).closest('a');
      if (a && /^https?:/.test(url) && url.indexOf(location.host) === -1) { a.target = '_blank'; a.rel = 'noopener'; }
      promijenjeno = true;
    });
    var video = ed.querySelector('[data-video]');
    video.addEventListener('mousedown', function (e) { e.preventDefault(); });
    video.addEventListener('click', function () {
      if (uHtml) return;
      var url = prompt('YouTube adresa videa:');
      var m = url && url.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([\w-]{11})/);
      if (!m) { if (url) alert('To ne izgleda kao YouTube adresa.'); return; }
      document.execCommand('insertHTML', false, '<p><iframe src="https://www.youtube-nocookie.com/embed/' + m[1] + '" title="Video" allowfullscreen></iframe></p><p></p>');
      promijenjeno = true;
    });

    // slika u tekstu: upload → umetni
    var spremljeno = null;
    ed.querySelector('[data-slika]').addEventListener('mousedown', function (e) {
      e.preventDefault();
      var s = window.getSelection();
      spremljeno = s.rangeCount ? s.getRangeAt(0).cloneRange() : null;
    });
    ed.querySelector('[data-slika]').addEventListener('click', function () { if (!uHtml) datoteka.click(); });
    datoteka.addEventListener('change', function () {
      var f = datoteka.files[0];
      if (!f) return;
      var fd = new FormData(); fd.append('slika', f); fd.append('csrf', csrf);
      var btn = ed.querySelector('[data-slika]'); btn.textContent = '⏳ Šaljem…';
      fetch('/admin/slika.php', { method: 'POST', body: fd, headers: { 'X-CSRF': csrf } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.url) throw new Error(d.greska || 'Greška');
          viz.focus();
          if (spremljeno && viz.contains(spremljeno.startContainer)) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(spremljeno); }
          document.execCommand('insertHTML', false, '<p><img src="' + d.url + '" alt=""></p><p></p>');
          promijenjeno = true;
        })
        .catch(function (e) { alert('Slika nije spremljena: ' + e.message); })
        .finally(function () { btn.textContent = '🖼 Slika'; datoteka.value = ''; });
    });

    // vizualno ↔ HTML
    htmlBtn.addEventListener('click', function () {
      uHtml = !uHtml;
      if (uHtml) { kod.value = viz.innerHTML.replace(/<\/(p|h2|h3|blockquote|ul|ol|li|figure)>/g, '</$1>\n'); kod.hidden = false; viz.hidden = true; }
      else { viz.innerHTML = kod.value; kod.hidden = true; viz.hidden = false; }
      htmlBtn.classList.toggle('aktivno', uHtml);
      ed.querySelectorAll('.a-traka button:not([data-html])').forEach(function (b) { b.disabled = uHtml; });
    });

    forma.addEventListener('submit', function () { sinkroniziraj(); promijenjeno = false; });
    window.addEventListener('beforeunload', function (e) { if (promijenjeno) { e.preventDefault(); e.returnValue = ''; } });

  }

  /* ---------- galerija: smanji u pregledniku → pošalji jednu po jednu → sličica ---------- */
  var gal = document.querySelector('[data-galerija]');
  if (gal && forma) {
    var popis = gal.querySelector('[data-galerija-popis]');
    var ulaz = gal.querySelector('[data-galerija-datoteke]');
    var stanje = gal.querySelector('[data-galerija-stanje]');
    var gCsrf = forma.querySelector('input[name=csrf]').value;
    var MAX = 1600;

    // najveća strana 1600 px, JPEG; preglednik sam ispravlja orijentaciju s mobitela
    function smanji(f) {
      return new Promise(function (ok) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return ok(f);
        var img = new Image(), url = URL.createObjectURL(f);
        img.onload = function () {
          var k = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
          if (k === 1 && f.size < 1500000) { URL.revokeObjectURL(url); return ok(f); }
          var c = document.createElement('canvas');
          c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
          var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
          x.drawImage(img, 0, 0, c.width, c.height);
          URL.revokeObjectURL(url);
          c.toBlob(function (b) { ok(b || f); }, 'image/jpeg', 0.86);
        };
        img.onerror = function () { URL.revokeObjectURL(url); ok(f); };
        img.src = url;
      });
    }

    function stavka(id, src) {
      var d = document.createElement('div');
      d.className = 'a-gal-slika';
      d.innerHTML = '<img alt=""><input type="hidden" name="galerija[]"><span class="a-gal-gumbi"><button type="button" data-gal-lijevo title="Pomakni ranije">‹</button><button type="button" data-gal-desno title="Pomakni kasnije">›</button><button type="button" data-gal-makni title="Makni iz galerije">×</button></span>';
      d.querySelector('img').src = src;
      d.querySelector('input').value = id;
      popis.appendChild(d);
    }

    ulaz.addEventListener('change', function () {
      var datoteke = Array.prototype.slice.call(ulaz.files), n = 0, greske = [];
      if (!datoteke.length) return;
      ulaz.disabled = true;
      var gumbi = forma.querySelectorAll('button[type=submit]');
      gumbi.forEach(function (b) { b.disabled = true; });
      (function sljedeca() {
        if (n >= datoteke.length) {
          ulaz.disabled = false; ulaz.value = '';
          gumbi.forEach(function (b) { b.disabled = false; });
          stanje.textContent = greske.length ? 'Nije učitano: ' + greske.join('; ') : 'Učitano. Kliknite „Spremi” da galerija ostane uz novost.';
          return;
        }
        var f = datoteke[n++];
        stanje.textContent = 'Smanjujem i šaljem ' + n + ' / ' + datoteke.length + '…';
        smanji(f).then(function (b) {
          var fd = new FormData(); fd.append('slika', b, (f.name || 'slika').replace(/\.\w+$/, '') + '.jpg'); fd.append('csrf', gCsrf);
          return fetch('/admin/galerija.php', { method: 'POST', body: fd, headers: { 'X-CSRF': gCsrf } }).then(function (r) { return r.json(); });
        }).then(function (d) {
          if (!d.id) throw new Error(d.greska || 'greška');
          stavka(d.id, d.mala);
          promijenjeno = true;
        }).catch(function (e) { greske.push(f.name + ' (' + e.message + ')'); }).then(sljedeca);
      })();
    });

    popis.addEventListener('click', function (e) {
      var s = e.target.closest('.a-gal-slika');
      if (!s) return;
      if (e.target.closest('[data-gal-lijevo]') && s.previousElementSibling) popis.insertBefore(s, s.previousElementSibling);
      else if (e.target.closest('[data-gal-desno]') && s.nextElementSibling) popis.insertBefore(s.nextElementSibling, s);
      else if (e.target.closest('[data-gal-makni]')) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'galerija_obrisi[]'; h.value = s.querySelector('input').value;
        gal.appendChild(h);
        s.remove();
        stanje.textContent = 'Slika se briše kad kliknete „Spremi”.';
      } else return;
      promijenjeno = true;
    });
  }

  /* ---------- pregled slike prije spremanja (uređivač, popup) ---------- */
  document.querySelectorAll('[data-glavna-slika]').forEach(function (gl) {
    var pr = gl.form && gl.form.querySelector('[data-pregled]');
    if (pr) gl.addEventListener('change', function () {
      if (gl.files[0]) { pr.src = URL.createObjectURL(gl.files[0]); pr.hidden = false; }
    });
  });

  /* ---------- izbornik: polja ovisno o vrsti ---------- */
  var iz = document.querySelector('[data-izbornik-obrazac]');
  if (iz) {
    var tip = iz.querySelector('[data-tip]');
    function sync() { iz.querySelectorAll('[data-za]').forEach(function (el) { el.hidden = el.dataset.za !== tip.value; }); }
    tip.addEventListener('change', sync);
    sync();
  }
})();
