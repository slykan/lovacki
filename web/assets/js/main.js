// Lovački savez OBŽ — izbornik, lightbox, galerija, dijeljenje, kontakt obrazac, popup
(function () {
  var reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var top = document.querySelector('[data-top]');
  function sjena() { if (top) top.classList.toggle('skrolano', window.scrollY > 8); }
  window.addEventListener('scroll', sjena, { passive: true });
  sjena();

  // mobilni izbornik i podizbornici
  var btn = document.querySelector('[data-menu-btn]');
  var menu = document.querySelector('[data-menu]');
  if (btn && menu) {
    btn.addEventListener('click', function () {
      var o = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', o);
      menu.classList.toggle('otvoren', o);
    });
  }
  document.querySelectorAll('[data-pod-btn]').forEach(function (b) {
    b.addEventListener('click', function () {
      var li = b.parentNode, o = !li.classList.contains('otvoren');
      document.querySelectorAll('.ima-pod.otvoren').forEach(function (x) { if (x !== li) { x.classList.remove('otvoren'); x.querySelector('[data-pod-btn]').setAttribute('aria-expanded', 'false'); } });
      li.classList.toggle('otvoren', o);
      b.setAttribute('aria-expanded', o);
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.ima-pod')) document.querySelectorAll('.ima-pod.otvoren').forEach(function (x) { x.classList.remove('otvoren'); });
  });

  // slider istaknutih vijesti
  var sl = document.querySelector('[data-slider]');
  if (sl) {
    var slajdovi = sl.querySelectorAll('.slajd'), brojevi = sl.querySelectorAll('[data-idi]'), i = 0, timer = null;
    var pauza = sl.querySelector('[data-pauza]');
    function idi(n) {
      i = (n + slajdovi.length) % slajdovi.length;
      slajdovi.forEach(function (s, k) { s.hidden = k !== i; s.classList.toggle('aktivan', k === i); });
      brojevi.forEach(function (b, k) { b.toggleAttribute('aria-current', k === i); if (k === i) b.setAttribute('aria-current', 'true'); });
    }
    function kreni() { stani(); if (!reduced) timer = setInterval(function () { idi(i + 1); }, 7000); if (pauza) pauza.lastChild.textContent = 'Stop'; }
    function stani() { if (timer) clearInterval(timer); timer = null; }
    brojevi.forEach(function (b) { b.addEventListener('click', function () { idi(+b.dataset.idi); kreni(); }); });
    sl.querySelector('[data-pret]').addEventListener('click', function () { idi(i - 1); kreni(); });
    sl.querySelector('[data-slj]').addEventListener('click', function () { idi(i + 1); kreni(); });
    if (pauza) pauza.addEventListener('click', function () {
      if (timer) { stani(); pauza.lastChild.textContent = 'Kreni'; } else { kreni(); }
    });
    sl.addEventListener('mouseenter', stani);
    sl.addEventListener('mouseleave', function () { if (pauza && pauza.lastChild.textContent === 'Stop') kreni(); });
    kreni();
  }

  // povećanje slika u članku
  document.querySelectorAll('[data-lightbox], [data-tekst] img').forEach(function (el) {
    el.style.cursor = 'zoom-in';
    el.addEventListener('click', function (e) {
      var src = el.tagName === 'A' ? el.getAttribute('href') : el.getAttribute('src');
      if (el.closest('a') && el.tagName === 'IMG' && !el.closest('[data-lightbox]')) return; // slika je već link negdje
      e.preventDefault();
      var lb = document.createElement('div');
      lb.className = 'lightbox';
      lb.innerHTML = '<img alt="">';
      lb.firstChild.src = src;
      lb.addEventListener('click', function () { lb.remove(); });
      document.addEventListener('keydown', function esc(ev) { if (ev.key === 'Escape') { lb.remove(); document.removeEventListener('keydown', esc); } });
      document.body.appendChild(lb);
    });
  });

  // galerija ispod teksta: traka se sama pomiče, klik otvara pregled sa strelicama
  document.querySelectorAll('[data-galerija]').forEach(function (g) {
    var traka = g.querySelector('[data-gal-traka]');
    var slike = Array.prototype.slice.call(g.querySelectorAll('[data-gal-slika]'));
    var korak = function () { return slike[0].getBoundingClientRect().width + parseFloat(getComputedStyle(traka).columnGap || 0); };
    var naKraju = function () { return traka.scrollLeft + traka.clientWidth >= traka.scrollWidth - 4; };
    function pomakni(smjer) {
      if (smjer > 0 && naKraju()) traka.scrollTo({ left: 0, behavior: reduced ? 'auto' : 'smooth' });
      else if (smjer < 0 && traka.scrollLeft <= 4) traka.scrollTo({ left: traka.scrollWidth, behavior: reduced ? 'auto' : 'smooth' });
      else traka.scrollBy({ left: smjer * korak(), behavior: reduced ? 'auto' : 'smooth' });
    }
    var pret = g.querySelector('[data-gal-pret]'), slj = g.querySelector('[data-gal-slj]');
    if (pret) pret.addEventListener('click', function () { pomakni(-1); });
    if (slj) slj.addEventListener('click', function () { pomakni(1); });

    var gTimer = null, vidljiva = false, drzi = false, otvoren = false;
    function vrti() {
      clearInterval(gTimer); gTimer = null;
      if (!reduced && vidljiva && !drzi && !otvoren && traka.scrollWidth > traka.clientWidth + 4) gTimer = setInterval(function () { pomakni(1); }, 3500);
    }
    g.addEventListener('mouseenter', function () { drzi = true; vrti(); });
    g.addEventListener('mouseleave', function () { drzi = false; vrti(); });
    traka.addEventListener('touchstart', function () { drzi = true; vrti(); }, { passive: true });
    traka.addEventListener('touchend', function () { setTimeout(function () { drzi = false; vrti(); }, 4000); }, { passive: true });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (ul) { vidljiva = ul[0].isIntersecting; vrti(); }, { threshold: 0.3 }).observe(g);
    } else { vidljiva = true; vrti(); }

    // pregled preko cijelog ekrana
    slike.forEach(function (a, k) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var i = k, prije = document.activeElement;
        var lb = document.createElement('div');
        lb.className = 'lightbox lightbox-gal';
        lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Galerija');
        lb.innerHTML = '<img alt=""><button type="button" class="lb-x" aria-label="Zatvori">×</button>' +
          (slike.length > 1 ? '<button type="button" class="lb-pret" aria-label="Prethodna">‹</button><button type="button" class="lb-slj" aria-label="Sljedeća">›</button><span class="lb-broj"></span>' : '');
        var img = lb.querySelector('img'), broj = lb.querySelector('.lb-broj');
        function pokazi(n) {
          i = (n + slike.length) % slike.length;
          img.src = slike[i].getAttribute('href');
          img.alt = slike[i].querySelector('img').alt;
          if (broj) broj.textContent = (i + 1) + ' / ' + slike.length;
        }
        function zatvori() { lb.remove(); document.removeEventListener('keydown', tipke); otvoren = false; vrti(); if (prije) prije.focus(); }
        function tipke(ev) {
          if (ev.key === 'Escape') zatvori();
          else if (ev.key === 'ArrowLeft') pokazi(i - 1);
          else if (ev.key === 'ArrowRight') pokazi(i + 1);
        }
        lb.addEventListener('click', function (ev) {
          if (ev.target.closest('.lb-pret')) pokazi(i - 1);
          else if (ev.target.closest('.lb-slj')) pokazi(i + 1);
          else if (ev.target === lb || ev.target.closest('.lb-x')) zatvori();
        });
        var x0 = null;
        lb.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, { passive: true });
        lb.addEventListener('touchend', function (ev) {
          if (x0 === null) return;
          var dx = ev.changedTouches[0].clientX - x0; x0 = null;
          if (Math.abs(dx) > 50) pokazi(i + (dx < 0 ? 1 : -1));
        }, { passive: true });
        document.addEventListener('keydown', tipke);
        pokazi(i);
        document.body.appendChild(lb);
        otvoren = true; vrti();
        lb.querySelector('.lb-x').focus();
      });
    });
  });

  // kopiraj link
  document.querySelectorAll('[data-kopiraj]').forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard && navigator.clipboard.writeText(b.dataset.kopiraj).then(function () { b.textContent = 'Kopirano ✓'; });
    });
  });

  // obrasci
  document.querySelectorAll('[data-obrazac]').forEach(function (f) {
    var poruka = f.querySelector('[data-poruka]');
    var t = f.querySelector('[data-t]');
    if (t) t.value = Date.now();
    function javi(txt, ok) { poruka.textContent = txt; poruka.className = 'obrazac-poruka ' + (ok ? 'ok' : 'greska'); }
    if (/[?&]poslano=1/.test(location.search)) javi('Hvala! Poslano je, javit ćemo vam se uskoro.', true);

    function valjan(okvir) {
      var prvi = null;
      okvir.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.classList.remove('neispravno');
        if (el.type !== 'radio' && !el.checkValidity()) { el.classList.add('neispravno'); prvi = prvi || el; }
      });
      if (prvi) { prvi.reportValidity(); return false; }
      return true;
    }

    // koraci
    var koraci = f.querySelectorAll('.korak'), oznake = f.querySelectorAll('.koraci-oznake li'), k = 0;
    function korak(n) {
      k = n;
      koraci.forEach(function (x, j) { x.classList.toggle('aktivan', j === k); });
      oznake.forEach(function (x, j) { x.classList.toggle('aktivan', j === k); x.classList.toggle('gotov', j < k); });
      f.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    }
    f.addEventListener('click', function (e) {
      if (e.target.closest('[data-dalje]')) { if (valjan(koraci[k])) korak(k + 1); }
      if (e.target.closest('[data-natrag]')) korak(k - 1);
    });

    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!valjan(koraci.length ? koraci[k] : f)) return;
      var gumb = f.querySelector('button[type=submit]');
      gumb.disabled = true;
      javi('Šaljem…', true);
      fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          javi(d.message, d.ok);
          if (d.ok) { f.reset(); f.classList.add('poslano'); if (t) t.value = Date.now(); }
        })
        .catch(function () { javi('Slanje nije uspjelo. Pišite nam na lovacki.savez.obz@gmail.com.', false); })
        .finally(function () { gumb.disabled = false; });
    });
  });

  /* ---------- popup (iz admina) ---------- */
  var pop = document.querySelector('[data-popup]');
  if (pop) {
    var kljuc = 'lso-popup', ver = pop.dataset.verzija, sprem = pop.dataset.ucestalost === 'posjet' ? window.sessionStorage : window.localStorage;
    var vidio = false;
    try { vidio = sprem.getItem(kljuc) === ver; } catch (e) {}
    if (!vidio) {
      var prije = null;
      var zatvori = function () {
        pop.classList.remove('otvoren');
        setTimeout(function () { pop.hidden = true; }, 250);
        document.removeEventListener('keydown', esc);
        try { sprem.setItem(kljuc, ver); } catch (e) {}
        if (prije) prije.focus();
      };
      var esc = function (e) { if (e.key === 'Escape') zatvori(); };
      setTimeout(function () {
        prije = document.activeElement;
        pop.hidden = false;
        requestAnimationFrame(function () { pop.classList.add('otvoren'); });
        pop.querySelector('[data-popup-zatvori]').focus();
        document.addEventListener('keydown', esc);
      }, 1000);
      pop.addEventListener('click', function (e) { if (e.target === pop || e.target.closest('[data-popup-zatvori]')) zatvori(); });
      var pa = pop.querySelector('a');
      if (pa) pa.addEventListener('click', function () { try { sprem.setItem(kljuc, ver); } catch (e) {} });
    }
  }

  /* ---------- animacija grada: kreće kad je pojas na ekranu ---------- */
  document.querySelectorAll('[data-grad-anim]').forEach(function (ga) {
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (ul) {
        ul.forEach(function (u) { ga.classList.toggle('ziv', u.isIntersecting); });
      }, { threshold: 0.15 }).observe(ga);
    } else {
      ga.classList.add('ziv');
    }
  });
})();
