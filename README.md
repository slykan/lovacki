# Lovački savez Osječko-baranjske županije — nova stranica s adminom

Zamjena za staru Joomlu (K2 + SP Page Builder) na lovacki-savez-osijek.hr. Lagani PHP CMS bez frameworka, nastao iz CMS-a za HNS Osijek. Razlika: **sav sadržaj je u kategorijama** (stablo s podkategorijama), a samostalne stranice nemaju kategoriju. Boje i izgled su iz novog grba (1995–2025): krem, zlato, smeđa; fontovi Alegreya i Alegreya Sans.

## Što je gdje
- `web/` — sve što ide na poslužitelj
  - `index.php` — ruter: `/`, `/{kategorija}[/{pod}]`, `/{kategorija}/…/{slug}`, `/{stranica}`, `/pretraga`, `/sitemap.xml`, `/novosti.xml`, stare `/index.php/…` adrese → 301
  - `admin/` — sadržaj, **kategorije**, izbornik, popup, lozinka
  - `inc/app.php` — baza (MySQL ako postoji `inc/db-config.php`, inače SQLite `data/lovacki.sqlite`), kategorije, prijava, sanitizacija HTML-a, slike
  - `inc/pogledi/` — predlošci (naslovna, kategorija, clanak, _lovidba, _kontakt…)
  - `posalji.php` — kontakt obrazac → e-mail Savezu
  - `uploads/` — slike, galerije i privitci (nije u gitu)
  - `images/` — slike iz starih tekstova i fotografije vodstva (nije u gitu)
- `_alat/` — samo lokalno
  - `uvoz.php` — K2 → nova baza (gradi bazu i `uploads/` **iznova**; smije se pokrenuti ponovno kad stignu nove slike)
  - `izvoz-mysql.php` — SQLite → MySQL dump za poslužitelj
  - `slike.php` — iz `logo.jpeg` prozirni PNG-ovi, ikone, favicon, OG slika
  - `korisnik.php` — napravi/resetira admina, pristup u `admin-pristup.txt`
  - `router.php` — lokalni test: `php -S 127.0.0.1:8150 -t web _alat/router.php`

## Kategorije (iz K2)
| Kategorija | Prikaz | Napomena |
|---|---|---|
| Novosti | novosti (slika, datum) | 397 novosti 2011.–2025. |
| Članice Saveza › 6 lovnih ureda | popis | 107 članica; podaci iz tablica pretvoreni u kartice (lovište, kontakt, adresa, broj lovaca…); opis LU = tekst lovnog ureda |
| Divljač › Krupna, Sitna | popis | |
| Lov › Lovište kroz godinu | popis, redom | Način lova, Lovački običaji; 9 mjesečnih tekstova |
| Dokumenti | dokumenti | 20 zakona/pravilnika s PDF-om |
| Arhiva (skrivena) | popis | oglasi 2011. i salon 2012. — klijent odlučuje |

Stranice bez kategorije: O nama (vodstvo s fotografijama, podaci o Savezu), Kontakt (obrazac + karta), **Lovidba** (automatski prikazuje tekst „Lovište u …” za tekući mjesec), Politika privatnosti.

**Duplikati:** stara baza je imala 981 K2 zapis, većinu dvaput (2011. i kopije iz 2017.). Spojeni su po kategoriji, naslovu i tekstu: ostaje 550 zapisa, a stari ID-jevi duplikata preusmjeravaju na zadržani zapis.

## Admin
- `/admin/`, pristup u `_alat/admin-pristup.txt` (poslati klijentu sigurnim putem)
- **Sadržaj:** kartice po glavnim kategorijama (+ podkategorije) i „Stranice”. Polja: naslov, kategorija (stablo), datum, redni broj, tekst (vizualno ↔ HTML), sažetak, glavna slika, galerija, privitci, objavljeno
- **Kategorije:** naziv, nadređena, prikaz (novosti / popis / dokumenti), redoslijed zapisa (datum / abecedno / redni broj), opis iznad popisa, vidljivost. Brisanje samo prazne kategorije. Promjena adrese → 301 sa stare
- **Dokumenti:** novi dokument = novi zapis u „Dokumenti” s privitkom (PDF/Word/Excel)

## Slike
Slike se skidaju sa starog servera u `lovacki/images/` (K2 galerije su u `images/{ID članka}/`). Ako stignu i `media/k2/items/src` (glavne slike K2) i `media/k2/attachments` (privitci), staviti ih u `lovacki/media/k2/…`. Zatim pokrenuti `php _alat/uvoz.php` i `php _alat/korisnik.php savez`. Popis onoga što nedostaje je u `_alat/uvoz.log`.

## Poslužitelj
- baza **lovacki_new** (MySQL): `inc/db-config.php.posluzitelj` → na serveru `inc/db-config.php`; uvesti `_alat/lovacki-baza.sql` (iz `izvoz-mysql.php`)
- `.htaccess`: DCV blok ostaje; PHP-FPM pool provjeriti (`proxy:fcgi://localhost:8074` je s HNS-a)
- `data/` i `uploads/` moraju biti zapisivi za PHP
- stara Joomla: backup cijelog `public_html` + baze prije zamjene; provjeriti backdoore

## Mail
- šalje `web@lovacki-savez-osijek.hr` preko `vps.on-click.hr:465`, prima `lovacki.savez.obz@gmail.com` (adresa sa stare stranice), Reply-To je pošiljatelj
- 2026-10-28: prijava (235) i primatelj (250) provjereni, bez slanja stvarne poruke

## Otvoreno
- [ ] stvarni test slanja obrasca (ide klijentu)
- [ ] slike: najnovije galerije (10xx) i glavne slike K2, privitci `media/k2/attachments` (31 dokument)
- [ ] dokumenti su iz 2006.–2009. (Zakon o lovstvu NN 140/05 — danas vrijedi NN 99/18 i izmjene): pitati klijenta za aktualne
- [ ] brojke na naslovnici (109 članica, 2.812 lovaca, 274.554 ha) su sa stare stranice — provjeriti
- [ ] fotografija vodstva i sastav (2023.) — još vrijedi?
- [ ] arhiva (oglasi 2011., salon 2012.) — obrisati ili objaviti?
